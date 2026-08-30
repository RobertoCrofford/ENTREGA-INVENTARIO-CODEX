<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Role;
use App\Services\AssetLifecycleService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $term = trim((string) $request->query('q', ''));
        $usage = trim((string) $request->query('uso', ''));
        $assets = Asset::query()->with(['type', 'status', 'location'])
            ->when($term !== '', fn ($query) => $query->whereAny(['activo_fijo', 'numero_serie', 'marca', 'modelo'], 'like', "%{$term}%"))
            ->when($usage !== '', fn ($query) => $query->where('uso', $usage))
            ->when($term === '' && $usage === '', fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('assets.index', compact('assets', 'term', 'usage'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar-inventario');

        $scanCode = $this->scanCode($request);
        $scanCode ? $request->session()->put('pending_scan.asset', $scanCode) : $request->session()->forget('pending_scan.asset');

        return view('assets.form', $this->formData(new Asset) + ['scanCode' => $scanCode]);
    }

    public function show(Asset $asset): View
    {
        Gate::authorize('consultar-inventario');

        return view('assets.show', compact('asset'));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $asset = DB::transaction(fn () => Asset::create($this->data($request) + ['codigo_escaneo_id' => $this->scanCodeId($request->session()->pull('pending_scan.asset')), 'creado_por' => $request->user()->id]));
        DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'alta', 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => 'Alta de activo', 'ejecutado_por' => $request->user()->id, 'ocurrido_at' => now()]);
        $audit->record($request->user(), 'crear', 'activo', $asset->id, after: $asset->toArray());

        return redirect()->route('assets.index')->with('success', 'Activo registrado.');
    }

    public function edit(Asset $asset): View
    {
        Gate::authorize('gestionar-inventario');

        return view('assets.form', $this->formData($asset));
    }

    public function update(Request $request, Asset $asset, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        if ($asset->status()->where('codigo', 'dado_baja')->exists()) {
            return back()->withErrors(['activo' => 'Un activo dado de baja no puede modificarse.']);
        } $before = $asset->toArray();
        $asset->update($this->data($request));
        $audit->record($request->user(), 'actualizar', 'activo', $asset->id, $before, $asset->fresh()->toArray());

        return redirect()->route('assets.index')->with('success', 'Activo actualizado.');
    }

    public function changeStatus(Request $request, Asset $asset, AssetLifecycleService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['estado' => ['required', Rule::exists('estados_activo', 'codigo')], 'motivo' => ['required', 'string'], 'ubicacion_actual_id' => ['nullable', 'exists:ubicaciones,id']]);
        $service->changeStatus($asset, $data['estado'], $request->user(), $data['motivo'], $data['ubicacion_actual_id'] ?? null);

        return back()->with('success', 'Estado actualizado y registrado en historial.');
    }

    public function reincorporate(Request $request, Asset $asset, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->tieneRol(Role::SUPERADMIN), 403);
        $data = $request->validate([
            'ubicacion_actual_id' => ['required', Rule::exists('ubicaciones', 'id')->where('activo', true)],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $asset = DB::transaction(function () use ($asset, $data, $request) {
            $asset = Asset::query()->with('status')->lockForUpdate()->findOrFail($asset->id);
            abort_unless($asset->status->codigo === 'dado_baja', 422, 'Solo se pueden reincorporar activos dados de baja.');
            $operational = AssetStatus::query()->where('codigo', 'operativo')->where('activo', true)->firstOrFail();
            $before = $asset->toArray();
            $asset->update([
                'estado_activo_id' => $operational->id,
                'ubicacion_actual_id' => $data['ubicacion_actual_id'],
            ]);
            DB::table('eventos_activo')->insert([
                'activo_id' => $asset->id,
                'tipo' => 'reincorporacion',
                'estado_origen_id' => $before['estado_activo_id'],
                'estado_destino_id' => $operational->id,
                'ubicacion_destino_id' => $data['ubicacion_actual_id'],
                'motivo' => $data['motivo'],
                'ejecutado_por' => $request->user()->id,
                'ocurrido_at' => now(),
            ]);

            return [$asset->fresh(), $before];
        });
        [$updatedAsset, $before] = $asset;
        $audit->record($request->user(), 'reincorporar', 'activo', $updatedAsset->id, $before, $updatedAsset->toArray(), $data['motivo']);

        return redirect()->route('assets.edit', $updatedAsset)->with('success', 'Activo reincorporado como operativo y registrado en la bitácora.');
    }

    private function formData(Asset $asset): array
    {
        return ['asset' => $asset, 'sites' => DB::table('sedes')->where('activo', true)->orderBy('nombre')->get(), 'types' => AssetType::where('activo', true)->orderBy('nombre')->get(), 'statuses' => AssetStatus::where('activo', true)->orderBy('nombre')->get(), 'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('nombre')->get()];
    }

    private function data(Request $request): array
    {
        $data = $request->validate(['sede_id' => ['required', 'exists:sedes,id'], 'tipo_activo_id' => ['required', 'exists:tipos_activo,id'], 'estado_activo_id' => ['required', 'exists:estados_activo,id'], 'uso' => ['required', Rule::in(['administrativo', 'alumnos', 'docente', 'comun'])], 'ubicacion_actual_id' => ['nullable', 'exists:ubicaciones,id'], 'activo_fijo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('activos', 'activo_fijo')->ignore($request->route('asset'))], 'numero_serie' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'], 'marca' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'modelo' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'costo_neto_actual' => ['nullable', 'numeric', 'min:0'], 'responsable_nombre' => ['nullable', 'string', 'max:120', 'regex:/^[\pL .\'-]+$/u'], 'responsable_email' => ['nullable', 'email', 'max:255'], 'responsable_departamento' => ['nullable', 'string', 'max:120', 'regex:/^[\pL\pN .\-]+$/u'], 'asignacion_vence_at' => ['nullable', 'date'], 'observacion' => ['nullable', 'string', 'max:2000']]);
        if (($data['responsable_nombre'] ?? null) xor ($data['responsable_email'] ?? null)) {
            abort(422, 'El responsable exige nombre y correo.');
        } if (($data['ubicacion_actual_id'] ?? null) && ($data['responsable_nombre'] ?? null)) {
            abort(422, 'Ubicación y responsable no pueden coexistir.');
        }

        return $data;
    }

    private function scanCode(Request $request): ?string
    {
        $code = $request->query('codigo');
        abort_unless($code === null || preg_match('/^[A-Za-z0-9-]{1,40}$/', $code), 422, 'Código de escaneo inválido.');

        return $code;
    }

    private function scanCodeId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        $record = DB::table('codigos_escaneo')->where('codigo', $code)->lockForUpdate()->first();
        if ($record && (DB::table('productos')->where('codigo_escaneo_id', $record->id)->exists() || Asset::query()->where('codigo_escaneo_id', $record->id)->exists())) {
            abort(422, 'El código escaneado ya está asociado a otro registro.');
        }

        return $record?->id ?? DB::table('codigos_escaneo')->insertGetId(['codigo' => $code, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
