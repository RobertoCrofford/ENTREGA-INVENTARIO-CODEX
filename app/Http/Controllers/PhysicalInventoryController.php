<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditService;
use App\Support\AssetCodeMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PhysicalInventoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('realizar-inventario-fisico');

        $locations = DB::table('ubicaciones')
            ->where('activo', true)
            ->where('disponible_inventario_fisico', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo', 'edificio', 'piso']);
        $inventories = DB::table('inventarios_fisicos as inventarios')
            ->join('ubicaciones as ubicaciones', 'ubicaciones.id', '=', 'inventarios.ubicacion_id')
            ->join('users as usuarios', 'usuarios.id', '=', 'inventarios.iniciado_por')
            ->select([
                'inventarios.*',
                'ubicaciones.codigo as ubicacion_codigo',
                'ubicaciones.nombre as ubicacion_nombre',
                'ubicaciones.tipo as ubicacion_tipo',
                'ubicaciones.edificio as ubicacion_edificio',
                'ubicaciones.piso as ubicacion_piso',
                'usuarios.name as iniciado_por_nombre',
            ])
            ->selectSub(function ($query) {
                $query->from('inventarios_fisicos_detalle')
                    ->selectRaw('count(*)')
                    ->whereColumn('inventario_fisico_id', 'inventarios.id');
            }, 'escaneados')
            ->orderByDesc('inventarios.iniciado_at')
            ->paginate(15);

        return view('physical-inventories.index', compact('locations', 'inventories'));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $data = $request->validate(['ubicacion_id' => ['required', 'integer']]);

        $result = DB::transaction(function () use ($data, $request): array {
            $location = DB::table('ubicaciones')
                ->where('id', $data['ubicacion_id'])
                ->where('activo', true)
                ->where('disponible_inventario_fisico', true)
                ->lockForUpdate()
                ->first();

            if (! $location) {
                abort(422, 'La ubicación no está disponible para inventario físico.');
            }

            $existing = DB::table('inventarios_fisicos')
                ->where('ubicacion_id', $location->id)
                ->whereIn('estado', ['en_curso', 'pendiente'])
                ->orderByDesc('id')
                ->first();
            if ($existing) {
                return ['id' => $existing->id, 'created' => false];
            }

            $now = now();
            $id = DB::table('inventarios_fisicos')->insertGetId([
                'ubicacion_id' => $location->id,
                'estado' => 'en_curso',
                'iniciado_por' => $request->user()->id,
                'iniciado_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->snapshotExpectedAssets($id, $location->id);

            return ['id' => $id, 'created' => true];
        });

        if ($result['created']) {
            $audit->record($request->user(), 'iniciar', 'inventario_fisico', $result['id'], after: ['ubicacion_id' => (int) $data['ubicacion_id']], notify: false);
        }

        return redirect()->route('physical-inventories.show', $result['id'])
            ->with($result['created'] ? 'success' : 'warning', $result['created'] ? 'Revisión física iniciada.' : 'Ya existe una revisión abierta para esta ubicación.');
    }

    public function show(int $physicalInventory): View
    {
        Gate::authorize('realizar-inventario-fisico');

        $inventory = $this->inventory($physicalInventory);
        $expected = $this->expectedAssets($inventory->id);
        $scans = DB::table('inventarios_fisicos_detalle as detalle')
            ->join('activos', 'activos.id', '=', 'detalle.activo_id')
            ->leftJoin('ubicaciones', 'ubicaciones.id', '=', 'activos.ubicacion_actual_id')
            ->where('detalle.inventario_fisico_id', $inventory->id)
            ->orderByDesc('detalle.escaneado_at')
            ->get([
                'detalle.*', 'activos.activo_fijo', 'activos.marca', 'activos.modelo',
                'activos.responsable_nombre', 'ubicaciones.codigo as ubicacion_actual_codigo',
                'ubicaciones.nombre as ubicacion_actual_nombre',
            ]);
        $pending = $expected->whereNotIn('id', $scans->pluck('activo_id'))->values();

        return view('physical-inventories.show', compact('inventory', 'expected', 'scans', 'pending'));
    }

    public function scan(Request $request, int $physicalInventory, AuditService $audit): RedirectResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        $code = trim($data['codigo']);

        $result = DB::transaction(function () use ($physicalInventory, $code): array {
            $inventory = DB::table('inventarios_fisicos')->where('id', $physicalInventory)->lockForUpdate()->first();
            abort_unless($inventory, 404);
            abort_unless(in_array($inventory->estado, ['en_curso', 'pendiente'], true), 409, 'La revisión ya está finalizada.');

            $codeId = DB::table('codigos_escaneo')->where('codigo', $code)->value('id');
            $matchingIds = AssetCodeMatch::assetIds($code);
            $assets = Asset::query()->where(function ($query) use ($codeId, $code, $matchingIds) {
                if ($codeId) {
                    $query->where('codigo_escaneo_id', $codeId);
                }
                $query->orWhere('activo_fijo', $code)
                    ->orWhere('numero_serie', $code)
                    ->orWhereIn('id', $matchingIds);
            })->get();

            if ($assets->isEmpty()) {
                return ['error' => 'No existe un activo asociado al código leído.'];
            }
            if ($assets->count() > 1) {
                return ['error' => 'El código coincide con más de un activo y no puede registrarse automáticamente.'];
            }

            $asset = $assets->first();
            $scanResult = $asset->ubicacion_actual_id === null
                ? 'sin_ubicacion'
                : ((int) $asset->ubicacion_actual_id === (int) $inventory->ubicacion_id ? 'encontrado' : 'ubicacion_distinta');
            DB::table('inventarios_fisicos_detalle')->updateOrInsert(
                ['inventario_fisico_id' => $inventory->id, 'activo_id' => $asset->id],
                ['codigo_escaneado' => $code, 'resultado' => $scanResult, 'escaneado_at' => now()]
            );

            if ($inventory->estado === 'pendiente' && $this->pendingCount($inventory->id) === 0) {
                DB::table('inventarios_fisicos')->where('id', $inventory->id)->update([
                    'estado' => 'finalizado', 'finalizado_at' => now(), 'updated_at' => now(),
                ]);
            }

            return ['asset_id' => $asset->id, 'resultado' => $scanResult];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['codigo' => $result['error']])->withInput();
        }
        $audit->record($request->user(), 'escanear', 'inventario_fisico', $physicalInventory, after: $result, notify: false);

        return redirect()->route('physical-inventories.show', $physicalInventory)->with('success', 'Lectura registrada.');
    }

    public function complete(Request $request, int $physicalInventory, AuditService $audit): RedirectResponse
    {
        Gate::authorize('realizar-inventario-fisico');

        $state = DB::transaction(function () use ($physicalInventory): string {
            $inventory = DB::table('inventarios_fisicos')->where('id', $physicalInventory)->lockForUpdate()->first();
            abort_unless($inventory, 404);
            abort_unless(in_array($inventory->estado, ['en_curso', 'pendiente'], true), 409, 'La revisión ya está finalizada.');

            $state = $this->pendingCount($inventory->id) > 0 ? 'pendiente' : 'finalizado';
            DB::table('inventarios_fisicos')->where('id', $inventory->id)->update([
                'estado' => $state,
                'finalizado_at' => $state === 'finalizado' ? now() : null,
                'updated_at' => now(),
            ]);

            return $state;
        });
        $audit->record($request->user(), 'cerrar', 'inventario_fisico', $physicalInventory, after: ['estado' => $state], notify: false);

        return redirect()->route('physical-inventories.show', $physicalInventory)
            ->with($state === 'finalizado' ? 'success' : 'warning', $state === 'finalizado' ? 'Revisión finalizada sin pendientes.' : 'La revisión quedó abierta con activos pendientes.');
    }

    private function inventory(int $id): object
    {
        $inventory = DB::table('inventarios_fisicos as inventarios')
            ->join('ubicaciones', 'ubicaciones.id', '=', 'inventarios.ubicacion_id')
            ->join('users', 'users.id', '=', 'inventarios.iniciado_por')
            ->where('inventarios.id', $id)
            ->first([
                'inventarios.*', 'ubicaciones.codigo as ubicacion_codigo',
                'ubicaciones.nombre as ubicacion_nombre', 'ubicaciones.tipo as ubicacion_tipo',
                'ubicaciones.edificio as ubicacion_edificio', 'ubicaciones.piso as ubicacion_piso',
                'users.name as iniciado_por_nombre',
            ]);
        abort_unless($inventory, 404);

        return $inventory;
    }

    private function snapshotExpectedAssets(int $inventoryId, int $locationId): void
    {
        $rows = DB::table('activos')
            ->join('estados_activo', 'estados_activo.id', '=', 'activos.estado_activo_id')
            ->where('activos.ubicacion_actual_id', $locationId)
            ->where('estados_activo.codigo', '!=', 'dado_baja')
            ->orderBy('activos.activo_fijo')
            ->get(['activos.id', 'activos.activo_fijo', 'activos.numero_serie', 'activos.marca', 'activos.modelo'])
            ->map(fn (object $asset): array => [
                'inventario_fisico_id' => $inventoryId,
                'activo_id' => $asset->id,
                'activo_fijo_snapshot' => $asset->activo_fijo,
                'numero_serie_snapshot' => $asset->numero_serie,
                'marca_snapshot' => $asset->marca,
                'modelo_snapshot' => $asset->modelo,
            ])->all();

        if ($rows) {
            DB::table('inventarios_fisicos_esperados')->insert($rows);
        }
    }

    private function expectedAssets(int $inventoryId): Collection
    {
        return DB::table('inventarios_fisicos_esperados')
            ->where('inventario_fisico_id', $inventoryId)
            ->orderBy('activo_fijo_snapshot')
            ->get([
                'activo_id as id', 'activo_fijo_snapshot as activo_fijo',
                'numero_serie_snapshot as numero_serie', 'marca_snapshot as marca',
                'modelo_snapshot as modelo',
            ]);
    }

    private function pendingCount(int $inventoryId): int
    {
        return DB::table('inventarios_fisicos_esperados as esperados')
            ->where('esperados.inventario_fisico_id', $inventoryId)
            ->whereNotExists(function ($query) use ($inventoryId) {
                $query->selectRaw('1')->from('inventarios_fisicos_detalle')
                    ->where('inventario_fisico_id', $inventoryId)
                    ->whereColumn('activo_id', 'esperados.activo_id');
            })->count();
    }
}
