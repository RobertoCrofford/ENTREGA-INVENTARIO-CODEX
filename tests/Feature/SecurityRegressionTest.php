<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_suite_uses_an_isolated_in_memory_database(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_first_superadministrator_can_be_configured_once_from_the_browser(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Configurar primera cuenta');
        $this->get(route('setup.create'))->assertOk();

        $this->post(route('setup.store'), [
            'name' => 'Roberto Crofford',
            'email' => 'roberto@example.test',
            'username' => 'roberto',
            'password' => 'Roberto1988.',
            'password_confirmation' => 'Roberto1988.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'name' => 'Roberto Crofford',
            'username' => 'roberto',
            'activo' => true,
            'debe_cambiar_password' => false,
            'rol_id' => Role::query()->where('codigo', Role::SUPERADMIN)->value('id'),
        ]);
        $this->get(route('setup.create'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Configurar primera cuenta');
    }

    public function test_director_cannot_administer_users(): void
    {
        $this->actingAs($this->user(Role::DIRECTOR_TECNICO))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_only_a_superadministrator_can_access_the_audit_log(): void
    {
        $this->actingAs($this->user(Role::TECNICO))
            ->get(route('audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($this->user(Role::SUPERADMIN))
            ->get(route('audit-logs.index'))
            ->assertOk();
    }

    public function test_an_invited_user_can_consult_but_cannot_manage_inventory(): void
    {
        $guest = $this->user(Role::INVITADO);

        $this->actingAs($guest)->get(route('products.index'))->assertOk();
        $this->actingAs($guest)->get(route('products.create'))->assertForbidden();
        $this->actingAs($guest)->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_notification_summary_refreshes_the_user_notifications(): void
    {
        $technician = $this->user(Role::TECNICO);
        DB::table('notificaciones')->insert([
            'usuario_id' => $technician->id,
            'titulo' => 'Código sin registrar',
            'mensaje' => 'Se detectó un código nuevo.',
            'creado_at' => now(),
        ]);

        $this->actingAs($technician)->get(route('notifications.summary'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('notifications.0.titulo', 'Código sin registrar');
    }

    public function test_technician_cannot_publish_a_direct_stock_disposal(): void
    {
        $this->actingAs($this->user(Role::TECNICO))
            ->post(route('movements.store'), [
                'item_type' => 'producto',
                'tipo' => 'baja',
                'cantidad' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('tipo');

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_editing_a_product_cannot_bypass_the_deactivation_flow(): void
    {
        $technician = $this->user(Role::TECNICO);
        $categoryId = DB::table('categorias')->value('id');
        $product = Product::query()->create([
            'categoria_id' => $categoryId,
            'codigo_interno' => 'PRD-TEST-1',
            'numero_parte' => 'NP-1',
            'nombre' => 'Producto de prueba',
            'costo_neto_actual' => 100,
            'activo' => true,
            'creado_por' => $technician->id,
        ]);

        $this->actingAs($technician)->put(route('products.update', $product), [
            'categoria_id' => $categoryId,
            'numero_parte' => 'NP-1',
            'nombre' => 'Producto de prueba',
            'costo_neto_actual' => 100,
            'activo' => false,
        ])->assertRedirect(route('products.index'));

        $this->assertTrue($product->fresh()->activo);
        $this->actingAs($technician)->delete(route('products.destroy', $product))->assertForbidden();
    }

    public function test_creating_a_product_with_initial_stock_creates_an_inventory_entry(): void
    {
        $technician = $this->user(Role::TECNICO);
        $categoryId = DB::table('categorias')->value('id');
        $warehouseId = DB::table('ubicaciones')->where('tipo', 'bodega')->value('id');

        $this->actingAs($technician)->post(route('products.store'), [
            'categoria_id' => $categoryId,
            'numero_parte' => 'MOUSE-USB-001',
            'nombre' => 'Mouse USB',
            'costo_neto_actual' => 5990,
            'codigo_escaneado' => 'MOUSE-USB-001',
            'cantidad_inicial' => 10,
            'bodega_inicial_id' => $warehouseId,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('numero_parte', 'MOUSE-USB-001')->firstOrFail();
        $this->assertDatabaseHas('existencias', ['producto_id' => $product->id, 'bodega_id' => $warehouseId, 'cantidad' => 10]);
        $this->assertDatabaseHas('movimientos_detalle', ['producto_id' => $product->id, 'cantidad' => 10]);
        $this->assertDatabaseHas('codigos_escaneo', ['id' => $product->codigo_escaneo_id, 'codigo' => 'MOUSE-USB-001']);
    }

    public function test_an_unregistered_scan_is_carried_to_the_new_product_form(): void
    {
        $technician = $this->user(Role::TECNICO);
        $code = '6922000100999';

        $this->actingAs($technician)->post(route('scan.search'), ['codigo' => $code])
            ->assertOk()
            ->assertSee(route('products.create', ['codigo' => $code]));

        $this->actingAs($technician)->get(route('products.create', ['codigo' => $code]))
            ->assertOk()
            ->assertSee('Código escaneado:')
            ->assertSee('value="'.$code.'"', false);
    }

    public function test_technician_cannot_mark_an_asset_as_disposed_directly(): void
    {
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->post(route('assets.status', $asset), [
            'estado' => 'dado_baja',
            'motivo' => 'Intento de baja directa',
        ])->assertSessionHasErrors('estado');

        $this->assertSame('operativo', $asset->fresh()->status->codigo);
    }

    public function test_asset_disposal_requires_and_records_director_approval(): void
    {
        $technician = $this->user(Role::TECNICO);
        $director = $this->user(Role::DIRECTOR_TECNICO);
        $asset = $this->asset($technician, 100);
        $requestId = DB::table('solicitudes_baja_activo')->insertGetId([
            'activo_id' => $asset->id,
            'estado' => 'pendiente',
            'motivo' => 'Activo sin reparación posible',
            'diagnostico' => 'Daño irreversible confirmado',
            'solicitado_por' => $technician->id,
            'solicitado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($technician)->post(route('asset-disposals.approve', $requestId))->assertForbidden();
        $this->actingAs($director)->post(route('asset-disposals.approve', $requestId))->assertRedirect();

        $this->assertSame('dado_baja', $asset->fresh()->status->codigo);
        $this->assertDatabaseHas('solicitudes_baja_activo', ['id' => $requestId, 'estado' => 'aprobada', 'resuelto_por' => $director->id]);
        $this->assertDatabaseHas('eventos_activo', [
            'activo_id' => $asset->id,
            'tipo' => 'baja',
            'estado_origen_id' => DB::table('estados_activo')->where('codigo', 'operativo')->value('id'),
            'estado_destino_id' => DB::table('estados_activo')->where('codigo', 'dado_baja')->value('id'),
        ]);
    }

    public function test_repair_updates_asset_status_and_requires_a_documented_outcome(): void
    {
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->post(route('repairs.store'), [
            'activo_id' => $asset->id,
            'tecnico_id' => $technician->id,
            'prioridad' => 'media',
            'falla_reportada' => 'El equipo no inicia correctamente.',
        ])->assertRedirect(route('repairs.index'));

        $repair = DB::table('reparaciones')->where('activo_id', $asset->id)->first();
        $this->assertSame('en_reparacion', $asset->fresh()->status->codigo);
        $this->actingAs($technician)->post(route('repairs.complete', $repair->id), [
            'estado_final' => 'no_operativo',
            'resultado' => 'Se diagnosticó una falla irreversible en la placa madre.',
        ])->assertRedirect(route('repairs.index'));

        $this->assertSame('no_operativo', $asset->fresh()->status->codigo);
        $this->assertDatabaseHas('reparaciones', ['id' => $repair->id, 'estado' => 'resuelta']);
    }

    private function user(string $roleCode): User
    {
        $role = Role::query()->where('codigo', $roleCode)->firstOrFail();

        return User::query()->create([
            'rol_id' => $role->id,
            'name' => 'Usuario '.$roleCode,
            'email' => $roleCode.'@example.test',
            'username' => $roleCode,
            'password' => 'Password1234',
            'activo' => true,
            'debe_cambiar_password' => false,
        ]);
    }

    private function asset(User $creator, int $cost = 300000): Asset
    {
        return Asset::query()->create([
            'sede_id' => DB::table('sedes')->value('id'),
            'tipo_activo_id' => DB::table('tipos_activo')->where('codigo', 'notebook')->value('id'),
            'estado_activo_id' => DB::table('estados_activo')->where('codigo', 'operativo')->value('id'),
            'uso' => 'administrativo',
            'ubicacion_actual_id' => DB::table('ubicaciones')->where('tipo', 'bodega')->value('id'),
            'activo_fijo' => 'AF-'.Str::upper(Str::random(10)),
            'costo_neto_actual' => $cost,
            'creado_por' => $creator->id,
        ]);
    }
}
