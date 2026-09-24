<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Product;
use App\Models\Repair;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_recovery_code_can_restore_a_superadministrator_when_none_remain(): void
    {
        config(['app.admin_recovery_code' => 'codigo-de-recuperacion-seguro']);
        $this->user(Role::TECNICO);

        $this->get(route('login'))->assertOk()->assertSee('Recuperar administración');
        $this->post(route('admin-recovery.store'), [
            'recovery_code' => 'codigo-de-recuperacion-seguro',
            'name' => 'Administrador Recuperado',
            'email' => 'recuperado@example.test',
            'username' => 'administrador_recuperado',
            'password' => 'Recuperacion2026.',
            'password_confirmation' => 'Recuperacion2026.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'username' => 'administrador_recuperado',
            'rol_id' => Role::query()->where('codigo', Role::SUPERADMIN)->value('id'),
        ]);
        $this->get(route('admin-recovery.create'))->assertNotFound();
    }

    public function test_recovery_requires_the_configured_secret_code(): void
    {
        config(['app.admin_recovery_code' => 'codigo-de-recuperacion-seguro']);
        $this->user(Role::TECNICO);

        $this->post(route('admin-recovery.store'), [
            'recovery_code' => 'incorrecto',
            'name' => 'Administrador Recuperado',
            'email' => 'recuperado@example.test',
            'username' => 'administrador_recuperado',
            'password' => 'Recuperacion2026.',
            'password_confirmation' => 'Recuperacion2026.',
        ])->assertSessionHasErrors('recovery_code');

        $this->assertDatabaseMissing('users', ['username' => 'administrador_recuperado']);
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

    public function test_audit_archive_form_includes_its_csrf_token(): void
    {
        $this->actingAs($this->user(Role::SUPERADMIN))
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_an_invited_user_can_scan_and_request_disposals_but_cannot_consult_or_export_inventory(): void
    {
        $guest = $this->user(Role::INVITADO);

        $this->actingAs($guest)->get(route('scan.index'))->assertOk();
        $this->actingAs($guest)->get(route('products.index'))->assertForbidden();
        $this->actingAs($guest)->get(route('products.create'))->assertForbidden();
        $this->actingAs($guest)->get(route('imports.assets.export'))->assertForbidden();
        $this->actingAs($guest)->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_invited_user_only_sees_their_own_disposal_requests(): void
    {
        $guest = $this->user(Role::INVITADO);
        $technician = $this->user(Role::TECNICO);
        $ownAsset = $this->asset($guest);
        $otherAsset = $this->asset($technician);
        $now = now();
        DB::table('solicitudes_baja_activo')->insert([
            ['activo_id' => $ownAsset->id, 'estado' => 'pendiente', 'motivo' => 'Solicitud del invitado', 'diagnostico' => 'Diagnóstico propio', 'solicitado_por' => $guest->id, 'solicitado_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['activo_id' => $otherAsset->id, 'estado' => 'pendiente', 'motivo' => 'Solicitud de otro usuario', 'diagnostico' => 'Diagnóstico ajeno', 'solicitado_por' => $technician->id, 'solicitado_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->actingAs($guest)->get(route('asset-disposals.index'))
            ->assertOk()
            ->assertSee($ownAsset->activo_fijo)
            ->assertDontSee($otherAsset->activo_fijo);
    }

    public function test_invited_dashboard_does_not_expose_operational_inventory_summary(): void
    {
        $guest = $this->user(Role::INVITADO);
        $this->asset($guest);

        $this->actingAs($guest)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Acceso de invitado')
            ->assertDontSee('Movimientos recientes');
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

    public function test_technician_can_cancel_an_event_with_a_reason_without_deleting_its_history(): void
    {
        $technician = $this->user(Role::TECNICO);
        $eventId = DB::table('eventos_calendario')->insertGetId([
            'titulo' => 'Capacitación de inventario',
            'inicio_at' => now()->addWeek(),
            'termino_at' => now()->addWeek()->addHour(),
            'todo_el_dia' => false,
            'origen' => 'manual',
            'estado' => 'programado',
            'creado_por' => $technician->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($technician)->post(route('events.cancel', $eventId), [
            'motivo_cancelacion' => 'disponibilidad',
            'observacion_cancelacion' => 'La sala quedó reservada para una actividad institucional.',
        ])->assertRedirect();

        $this->assertDatabaseHas('eventos_calendario', [
            'id' => $eventId,
            'estado' => 'cancelado',
            'motivo_cancelacion' => 'disponibilidad',
            'cancelado_por' => $technician->id,
        ]);
    }

    public function test_calendar_renders_the_selected_day_without_an_error(): void
    {
        $technician = $this->user(Role::TECNICO);
        $date = now()->addMonth()->startOfDay();
        DB::table('eventos_calendario')->insert([
            'titulo' => 'Prueba de calendario', 'inicio_at' => $date, 'termino_at' => $date->copy()->endOfDay(),
            'todo_el_dia' => true, 'origen' => 'manual', 'estado' => 'programado', 'creado_por' => $technician->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($technician)->get(route('events.index', ['mes' => $date->format('Y-m'), 'dia' => $date->toDateString()]))
            ->assertOk()
            ->assertSee('Prueba de calendario');
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

    public function test_an_unregistered_scan_does_not_create_notifications(): void
    {
        $guest = $this->user(Role::INVITADO);

        $this->actingAs($guest)->post(route('scan.search'), ['codigo' => 'CODIGO-SIN-REGISTRO'])
            ->assertOk()
            ->assertSee('Código no registrado');

        $this->assertDatabaseMissing('notificaciones', ['titulo' => 'Código sin registrar']);
    }

    public function test_scanning_an_asset_shows_its_operational_details(): void
    {
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->get(route('scan.index', ['codigo' => $asset->activo_fijo]))
            ->assertOk()
            ->assertSee('Activo encontrado')
            ->assertSee($asset->activo_fijo)
            ->assertSee('Número de serie')
            ->assertSee('Ubicación actual');
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

    public function test_invited_user_can_request_a_disposal_and_notifies_the_director_and_superadministrator(): void
    {
        $guest = $this->user(Role::INVITADO);
        $director = $this->user(Role::DIRECTOR_TECNICO);
        $superadministrator = $this->user(Role::SUPERADMIN);
        $asset = $this->asset($guest);

        $this->actingAs($guest)->post(route('asset-disposals.store'), [
            'activo_id' => $asset->id,
            'motivo' => 'El equipo presenta un daño físico que impide su uso seguro.',
            'diagnostico' => 'Se confirmó que la reparación no es viable por el daño de sus componentes.',
        ])->assertRedirect();

        $requestId = DB::table('solicitudes_baja_activo')->where('activo_id', $asset->id)->value('id');
        $this->assertDatabaseHas('solicitudes_baja_activo', ['id' => $requestId, 'solicitado_por' => $guest->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $director->id, 'titulo' => 'Solicitud de baja pendiente']);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $superadministrator->id, 'titulo' => 'Solicitud de baja pendiente']);
        $this->assertDatabaseMissing('notificaciones', ['usuario_id' => $guest->id, 'titulo' => 'Solicitud de baja pendiente']);
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
        Storage::fake('local');
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->post(route('repairs.store'), [
            'activo_id' => $asset->id,
            'tecnico_id' => $technician->id,
            'prioridad' => 'media',
            'falla_reportada' => 'El equipo no inicia correctamente.',
            'evidencias' => [UploadedFile::fake()->create('ficha-inicial.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
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

    public function test_repair_requires_a_word_technical_sheet(): void
    {
        Storage::fake('local');
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->post(route('repairs.store'), [
            'activo_id' => $asset->id,
            'tecnico_id' => $technician->id,
            'prioridad' => 'media',
            'falla_reportada' => 'La pantalla presenta daños luego de una caída.',
        ])->assertSessionHasErrors('evidencias');

        $this->actingAs($technician)->post(route('repairs.store'), [
            'activo_id' => $asset->id,
            'tecnico_id' => $technician->id,
            'prioridad' => 'media',
            'falla_reportada' => 'La pantalla presenta daños luego de una caída.',
            'evidencias' => [UploadedFile::fake()->create('ficha-tecnica.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])->assertRedirect(route('repairs.index'));

        $this->assertDatabaseHas('evidencias_reparacion', ['nombre_original' => 'ficha-tecnica.docx']);
    }

    public function test_asset_import_notifies_operational_roles_only_after_confirmation(): void
    {
        Storage::fake('local');
        $technician = $this->user(Role::TECNICO);
        $superadministrator = $this->user(Role::SUPERADMIN);
        $guest = $this->user(Role::INVITADO);
        $csv = implode("\n", [
            'activo_fijo,sede_codigo,tipo_codigo,estado_codigo,uso,ubicacion_codigo,numero_serie,marca,modelo,costo_neto,responsable_nombre,responsable_email,responsable_departamento,observacion',
            'AF-IMPORT-001,MAIPU,notebook,operativo,administrativo,BOD-MAIPU,SN-IMPORT-001,Lenovo,ThinkPad,450000,,,,Carga de prueba',
        ]);

        $this->actingAs($technician)->post(route('imports.assets.preview'), [
            'archivo' => UploadedFile::fake()->createWithContent('activos.csv', $csv),
        ])->assertRedirect();

        $import = DB::table('importaciones')->where('archivo_nombre', 'activos.csv')->firstOrFail();
        $this->assertDatabaseMissing('notificaciones', ['titulo' => 'Importación de activos completada']);

        $this->actingAs($technician)->post(route('imports.assets.confirm', $import->id))
            ->assertRedirect(route('imports.assets.show', $import->id));

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $technician->id,
            'titulo' => 'Importación de activos completada',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $superadministrator->id,
            'titulo' => 'Importación de activos completada',
        ]);
        $this->assertDatabaseMissing('notificaciones', [
            'usuario_id' => $guest->id,
            'titulo' => 'Importación de activos completada',
        ]);
    }

    public function test_zero_padded_fixed_code_finds_the_same_asset_in_scan_and_search(): void
    {
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);
        $asset->update(['activo_fijo' => '500081145']);

        $this->actingAs($technician)->post(route('scan.search'), ['codigo' => '500081145000'])
            ->assertOk()
            ->assertSee('Activo encontrado')
            ->assertSee('500081145');

        $this->actingAs($technician)->get(route('assets.index', ['buscar' => 1, 'q' => '500081145000']))
            ->assertOk()
            ->assertSee('500081145');
    }

    public function test_assigned_technician_can_cancel_a_repair_and_restore_the_asset(): void
    {
        Storage::fake('local');
        $technician = $this->user(Role::TECNICO);
        $asset = $this->asset($technician);

        $this->actingAs($technician)->post(route('repairs.store'), [
            'activo_id' => $asset->id,
            'tecnico_id' => $technician->id,
            'prioridad' => 'media',
            'falla_reportada' => 'El equipo presenta una falla intermitente.',
            'evidencias' => [UploadedFile::fake()->create('ficha-cancelacion.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])->assertRedirect(route('repairs.index'));

        $repair = Repair::query()->where('activo_id', $asset->id)->firstOrFail();
        $this->actingAs($technician)->post(route('repairs.cancel', $repair), [
            'motivo_cancelacion' => 'El diagnóstico confirmó que no se requiere intervención.',
        ])->assertRedirect(route('repairs.index'));

        $this->assertSame('operativo', $asset->fresh()->status->codigo);
        $this->assertDatabaseHas('reparaciones', ['id' => $repair->id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('eventos_activo', ['activo_id' => $asset->id, 'motivo' => 'Reparación cancelada: El diagnóstico confirmó que no se requiere intervención.']);
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
