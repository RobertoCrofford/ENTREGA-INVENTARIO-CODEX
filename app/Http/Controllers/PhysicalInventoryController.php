<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditService;
use App\Support\AssetCodeMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
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

    public function mobileIndex(): View
    {
        Gate::authorize('realizar-inventario-fisico');

        $locations = DB::table('ubicaciones')
            ->where('activo', true)
            ->where('disponible_inventario_fisico', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo', 'edificio', 'piso']);
        $inventories = DB::table('inventarios_fisicos as inventarios')
            ->join('ubicaciones', 'ubicaciones.id', '=', 'inventarios.ubicacion_id')
            ->whereIn('inventarios.estado', ['en_curso', 'pendiente'])
            ->select([
                'inventarios.id', 'inventarios.estado', 'inventarios.iniciado_at',
                'ubicaciones.codigo as ubicacion_codigo', 'ubicaciones.nombre as ubicacion_nombre',
                'ubicaciones.tipo as ubicacion_tipo', 'ubicaciones.edificio as ubicacion_edificio',
                'ubicaciones.piso as ubicacion_piso',
            ])
            ->selectSub(function ($query) {
                $query->from('inventarios_fisicos_esperados')
                    ->selectRaw('count(*)')
                    ->whereColumn('inventario_fisico_id', 'inventarios.id');
            }, 'esperados')
            ->selectSub(function ($query) {
                $query->from('inventarios_fisicos_detalle')
                    ->selectRaw('count(*)')
                    ->whereColumn('inventario_fisico_id', 'inventarios.id');
            }, 'escaneados')
            ->orderByDesc('inventarios.iniciado_at')
            ->get();

        return view('mobile.inventory.index', compact('locations', 'inventories'));
    }

    public function mobileShow(int $physicalInventory): View
    {
        Gate::authorize('realizar-inventario-fisico');

        $inventory = $this->inventory($physicalInventory);
        $summary = $this->summary($inventory->id);
        $scans = $this->recentScans($inventory->id);

        return view('mobile.inventory.show', compact('inventory', 'summary', 'scans'));
    }

    public function mobileLookup(Request $request, int $physicalInventory): JsonResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        $inventory = $this->inventory($physicalInventory);

        if (! in_array($inventory->estado, ['en_curso', 'pendiente'], true)) {
            return response()->json(['message' => 'La revisión ya está finalizada.'], 409);
        }

        $assets = $this->matchingAssets(trim($data['codigo']));
        if ($assets->isEmpty()) {
            return response()->json(['message' => 'No existe un activo asociado al código leído.', 'status' => 'not_found'], 404);
        }
        if ($assets->count() > 1) {
            return response()->json(['message' => 'El código coincide con más de un activo. Debe corregirse antes de continuar.', 'status' => 'ambiguous'], 409);
        }

        $asset = $assets->first();
        $duplicate = DB::table('inventarios_fisicos_detalle')
            ->where('inventario_fisico_id', $inventory->id)
            ->where('activo_id', $asset->id)
            ->exists();

        return response()->json([
            'status' => $duplicate ? 'duplicate' : 'ready',
            'message' => $duplicate ? 'Este equipo ya fue escaneado en la revisión.' : 'Equipo encontrado. Confirma antes de registrarlo.',
            'asset' => $this->assetPayload($asset),
        ], $duplicate ? 409 : 200);
    }

    public function mobileState(int $physicalInventory): JsonResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $inventory = $this->inventory($physicalInventory);

        return response()->json([
            'inventory' => ['id' => $inventory->id, 'estado' => $inventory->estado],
            'summary' => $this->summary($inventory->id),
            'scans' => $this->recentScans($inventory->id)->map(fn (object $scan): array => $this->scanPayload($scan))->values(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $data = $request->validate([
            'ubicacion_id' => ['required', 'integer'],
            'modo' => ['nullable', 'in:mobile'],
        ]);

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

        $route = ($data['modo'] ?? null) === 'mobile' ? 'mobile.inventory.show' : 'physical-inventories.show';

        return redirect()->route($route, $result['id'])
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
            ->leftJoin('users as escaneadores', 'escaneadores.id', '=', 'detalle.escaneado_por')
            ->where('detalle.inventario_fisico_id', $inventory->id)
            ->orderByDesc('detalle.escaneado_at')
            ->get([
                'detalle.*', 'activos.activo_fijo', 'activos.marca', 'activos.modelo',
                'activos.responsable_nombre', 'ubicaciones.codigo as ubicacion_actual_codigo',
                'ubicaciones.nombre as ubicacion_actual_nombre', 'escaneadores.name as escaneado_por_nombre',
            ]);
        $pending = $expected->whereNotIn('id', $scans->pluck('activo_id'))->values();

        return view('physical-inventories.show', compact('inventory', 'expected', 'scans', 'pending'));
    }

    public function print(int $physicalInventory): View
    {
        Gate::authorize('realizar-inventario-fisico');

        $inventory = $this->inventory($physicalInventory);
        $expected = $this->expectedAssets($inventory->id);
        $scans = DB::table('inventarios_fisicos_detalle as detalle')
            ->join('activos', 'activos.id', '=', 'detalle.activo_id')
            ->leftJoin('ubicaciones', 'ubicaciones.id', '=', 'activos.ubicacion_actual_id')
            ->leftJoin('users as escaneadores', 'escaneadores.id', '=', 'detalle.escaneado_por')
            ->where('detalle.inventario_fisico_id', $inventory->id)
            ->orderBy('activos.activo_fijo')
            ->get([
                'detalle.*', 'activos.activo_fijo', 'activos.numero_serie', 'activos.marca', 'activos.modelo',
                'activos.responsable_nombre', 'ubicaciones.codigo as ubicacion_actual_codigo',
                'ubicaciones.nombre as ubicacion_actual_nombre', 'escaneadores.name as escaneado_por_nombre',
            ]);
        $pending = $expected->whereNotIn('id', $scans->pluck('activo_id'))->values();

        return view('physical-inventories.print', compact('inventory', 'expected', 'scans', 'pending'));
    }

    public function scan(Request $request, int $physicalInventory, AuditService $audit): RedirectResponse|JsonResponse
    {
        Gate::authorize('realizar-inventario-fisico');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        $code = trim($data['codigo']);

        $result = DB::transaction(function () use ($physicalInventory, $code, $request): array {
            $inventory = DB::table('inventarios_fisicos')->where('id', $physicalInventory)->lockForUpdate()->first();
            abort_unless($inventory, 404);
            abort_unless(in_array($inventory->estado, ['en_curso', 'pendiente'], true), 409, 'La revisión ya está finalizada.');

            $assets = $this->matchingAssets($code);

            if ($assets->isEmpty()) {
                return ['error' => 'No existe un activo asociado al código leído.', 'status' => 404];
            }
            if ($assets->count() > 1) {
                return ['error' => 'El código coincide con más de un activo y no puede registrarse automáticamente.', 'status' => 409];
            }

            $asset = $assets->first();
            $existing = DB::table('inventarios_fisicos_detalle')
                ->where('inventario_fisico_id', $inventory->id)
                ->where('activo_id', $asset->id)
                ->first();
            if ($existing) {
                return [
                    'error' => 'Este equipo ya fue escaneado en la revisión.',
                    'status' => 409,
                    'duplicate' => true,
                    'asset' => $this->assetPayload($asset),
                ];
            }

            $scanResult = $asset->ubicacion_actual_id === null
                ? 'sin_ubicacion'
                : ((int) $asset->ubicacion_actual_id === (int) $inventory->ubicacion_id ? 'encontrado' : 'ubicacion_distinta');
            DB::table('inventarios_fisicos_detalle')->insert([
                'inventario_fisico_id' => $inventory->id,
                'activo_id' => $asset->id,
                'codigo_escaneado' => $code,
                'resultado' => $scanResult,
                'escaneado_por' => $request->user()->id,
                'escaneado_at' => now(),
            ]);
            DB::table('inventarios_fisicos')->where('id', $inventory->id)->update(['updated_at' => now()]);

            return [
                'asset_id' => $asset->id,
                'resultado' => $scanResult,
                'asset' => $this->assetPayload($asset),
            ];
        });

        if (isset($result['error'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $result['error'],
                    'status' => ($result['duplicate'] ?? false) ? 'duplicate' : 'error',
                    'asset' => $result['asset'] ?? null,
                ], $result['status'] ?? 422);
            }

            return back()->withErrors(['codigo' => $result['error']])->withInput();
        }
        $audit->record($request->user(), 'escanear', 'inventario_fisico', $physicalInventory, after: $result, notify: false);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Lectura registrada.',
                'status' => 'registered',
                'asset' => $result['asset'],
                'resultado' => $result['resultado'],
                'summary' => $this->summary($physicalInventory),
                'scans' => $this->recentScans($physicalInventory)->map(fn (object $scan): array => $this->scanPayload($scan))->values(),
            ], 201);
        }

        return redirect()->route('physical-inventories.show', $physicalInventory)->with('success', 'Lectura registrada.');
    }

    public function removeScan(Request $request, int $physicalInventory, int $asset, AuditService $audit): RedirectResponse|JsonResponse
    {
        Gate::authorize('realizar-inventario-fisico');

        $removed = DB::transaction(function () use ($physicalInventory, $asset): array {
            $inventory = DB::table('inventarios_fisicos')->where('id', $physicalInventory)->lockForUpdate()->first();
            abort_unless($inventory, 404);
            abort_unless(in_array($inventory->estado, ['en_curso', 'pendiente'], true), 409, 'Una revisión finalizada no admite correcciones.');

            $scan = DB::table('inventarios_fisicos_detalle')
                ->where('inventario_fisico_id', $inventory->id)
                ->where('activo_id', $asset)
                ->lockForUpdate()
                ->first();
            abort_unless($scan, 404);

            DB::table('inventarios_fisicos_detalle')->where('id', $scan->id)->delete();
            DB::table('inventarios_fisicos')->where('id', $inventory->id)->update(['updated_at' => now()]);

            return [
                'activo_id' => $scan->activo_id,
                'codigo_escaneado' => $scan->codigo_escaneado,
                'resultado' => $scan->resultado,
                'escaneado_at' => $scan->escaneado_at,
            ];
        });

        $audit->record(
            $request->user(),
            'remover_escaneo',
            'inventario_fisico',
            $physicalInventory,
            before: $removed,
            reason: 'Corrección de una lectura registrada por error.',
            notify: false,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Lectura removida. El activo puede escanearse nuevamente.',
                'summary' => $this->summary($physicalInventory),
                'scans' => $this->recentScans($physicalInventory)->map(fn (object $scan): array => $this->scanPayload($scan))->values(),
            ]);
        }

        return redirect()->route('physical-inventories.show', $physicalInventory)->with('success', 'Lectura removida. El activo puede escanearse nuevamente.');
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

    private function matchingAssets(string $code): Collection
    {
        $codeId = DB::table('codigos_escaneo')->where('codigo', $code)->value('id');
        $matchingIds = AssetCodeMatch::assetIds($code);

        return Asset::query()
            ->with(['location', 'type'])
            ->where(function ($query) use ($codeId, $code, $matchingIds) {
                if ($codeId) {
                    $query->where('codigo_escaneo_id', $codeId);
                }
                $query->orWhere('activo_fijo', $code)
                    ->orWhere('numero_serie', $code)
                    ->orWhereIn('id', $matchingIds);
            })
            ->orderBy('id')
            ->get();
    }

    private function summary(int $inventoryId): array
    {
        $expected = DB::table('inventarios_fisicos_esperados')->where('inventario_fisico_id', $inventoryId)->count();
        $scanned = DB::table('inventarios_fisicos_detalle')->where('inventario_fisico_id', $inventoryId)->count();

        return [
            'esperados' => $expected,
            'encontrados' => $scanned,
            'pendientes' => max(0, $expected - $scanned),
            'progreso' => $expected > 0 ? min(100, (int) round(($scanned / $expected) * 100)) : 0,
        ];
    }

    private function recentScans(int $inventoryId, int $limit = 10): Collection
    {
        return DB::table('inventarios_fisicos_detalle as detalle')
            ->join('activos', 'activos.id', '=', 'detalle.activo_id')
            ->leftJoin('users', 'users.id', '=', 'detalle.escaneado_por')
            ->leftJoin('ubicaciones', 'ubicaciones.id', '=', 'activos.ubicacion_actual_id')
            ->where('detalle.inventario_fisico_id', $inventoryId)
            ->orderByDesc('detalle.escaneado_at')
            ->limit($limit)
            ->get([
                'detalle.activo_id', 'detalle.codigo_escaneado', 'detalle.resultado', 'detalle.escaneado_at',
                'activos.activo_fijo', 'activos.numero_serie', 'activos.marca', 'activos.modelo',
                'users.name as escaneado_por_nombre', 'ubicaciones.codigo as ubicacion_codigo',
                'ubicaciones.nombre as ubicacion_nombre',
            ]);
    }

    private function assetPayload(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'activo_fijo' => $asset->activo_fijo,
            'numero_serie' => $asset->numero_serie,
            'marca_modelo' => trim(($asset->marca ?: '').' '.($asset->modelo ?: '')) ?: 'Sin marca o modelo',
            'tipo' => $asset->type?->nombre,
            'ubicacion' => $asset->location ? trim($asset->location->codigo.' — '.$asset->location->nombre) : 'Sin ubicación asignada',
        ];
    }

    private function scanPayload(object $scan): array
    {
        return [
            'asset_id' => $scan->activo_id,
            'activo_fijo' => $scan->activo_fijo,
            'numero_serie' => $scan->numero_serie,
            'marca_modelo' => trim(($scan->marca ?: '').' '.($scan->modelo ?: '')) ?: 'Sin marca o modelo',
            'codigo_escaneado' => $scan->codigo_escaneado,
            'resultado' => $scan->resultado,
            'resultado_label' => match ($scan->resultado) {
                'encontrado' => 'Encontrado',
                'ubicacion_distinta' => 'Ubicación distinta',
                default => 'Sin ubicación',
            },
            'escaneado_por' => $scan->escaneado_por_nombre ?: 'Usuario anterior',
            'escaneado_at' => Carbon::parse($scan->escaneado_at)->timezone(config('app.timezone'))->toIso8601String(),
            'escaneado_at_label' => Carbon::parse($scan->escaneado_at)->timezone(config('app.timezone'))->format('d-m-Y H:i'),
            'ubicacion' => $scan->ubicacion_nombre ? trim($scan->ubicacion_codigo.' — '.$scan->ubicacion_nombre) : 'Sin ubicación',
        ];
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
