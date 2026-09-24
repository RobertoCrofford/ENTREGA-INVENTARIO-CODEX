<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Product;
use App\Models\Role;
use App\Services\AssetLifecycleService;
use App\Services\AuditService;
use App\Support\AssetCodeMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $term = trim((string) $request->query('q', ''));
        $usage = trim((string) $request->query('uso', ''));
        $status = trim((string) $request->query('estado', ''));
        $searched = $request->boolean('buscar') || $term !== '' || $usage !== '' || $status !== '';
        $matchingCodeIds = $term === '' ? [] : AssetCodeMatch::assetIds($term);
        $assets = Asset::query()->with(['type', 'status', 'location'])
            ->when($term !== '', fn ($query) => $query->where(fn ($matches) => $matches->whereAny(['activo_fijo', 'numero_serie', 'marca', 'modelo'], 'like', "%{$term}%")
                ->orWhereIn('id', $matchingCodeIds)))
            ->when($usage !== '', fn ($query) => $query->where('uso', $usage))
            ->when($status !== '', fn ($query) => $query->whereHas('status', fn ($statuses) => $statuses->where('codigo', $status)))
            ->when(! $searched, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('id')->paginate(20)->withQueryString();
        $productMatch = $term === '' ? null : Product::query()
            ->with('categoria')
            ->where(fn ($query) => $query->where('codigo_interno', $term)
                ->orWhere('numero_parte', $term)
                ->orWhereIn('codigo_escaneo_id', DB::table('codigos_escaneo')->where('codigo', $term)->select('id')))
            ->first();

        return view('assets.index', compact('assets', 'term', 'usage', 'searched', 'productMatch'));
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
        $asset = DB::transaction(function () use ($request, $audit) {
            $data = $this->data($request);
            $processor = $data['procesador'] ?? null;
            unset($data['procesador']);
            $asset = Asset::create($data + ['codigo_escaneo_id' => $this->scanCodeId($request->session()->pull('pending_scan.asset')), 'creado_por' => $request->user()->id]);
            $this->syncPcSpecification($asset, $processor);
            DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'alta', 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => 'Alta de activo', 'ejecutado_por' => $request->user()->id, 'ocurrido_at' => now()]);
            $audit->record($request->user(), 'crear', 'activo', $asset->id, after: $asset->toArray());

            return $asset;
        });

        return redirect()->route('assets.index')->with('success', 'Activo registrado.');
    }

    public function edit(Asset $asset): View
    {
        Gate::authorize('gestionar-inventario');

        return view('assets.form', $this->formData($asset));
    }

    public function classify(Asset $asset): View
    {
        Gate::authorize('clasificar-activos');

        return view('assets.classify', compact('asset'));
    }

    public function updateUsage(Request $request, Asset $asset, AuditService $audit): RedirectResponse
    {
        Gate::authorize('clasificar-activos');
        abort_if($asset->uso !== 'sin_definir', 422, 'Este activo ya fue clasificado.');

        $data = $request->validate(['uso' => ['required', Rule::in(['administrativo', 'alumnos', 'docente', 'comun'])]]);
        $before = $asset->toArray();
        $asset->update($data);
        $audit->record($request->user(), 'clasificar', 'activo', $asset->id, $before, $asset->fresh()->toArray(), 'Clasificación de uso del activo.');

        return redirect()->route('assets.index', ['uso' => 'sin_definir'])->with('success', 'Activo clasificado correctamente.');
    }

    public function update(Request $request, Asset $asset, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        if ($asset->status()->where('codigo', 'dado_baja')->exists()) {
            return back()->withErrors(['activo' => 'Un activo dado de baja no puede modificarse.']);
        } $before = $asset->toArray();
        $data = $this->data($request, false);
        $processor = $data['procesador'] ?? null;
        unset($data['procesador']);
        DB::transaction(function () use ($asset, $data, $processor): void {
            $asset->update($data);
            $this->syncPcSpecification($asset, $processor);
        });
        $audit->record($request->user(), 'actualizar', 'activo', $asset->id, $before, $asset->fresh()->toArray());

        return redirect()->route('assets.index')->with('success', 'Activo actualizado.');
    }

    public function changeStatus(Request $request, Asset $asset, AssetLifecycleService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['estado' => ['required', Rule::exists('estados_activo', 'codigo')->where('activo', true), Rule::notIn(['dado_baja'])], 'motivo' => ['required', 'string', 'min:5', 'max:1000'], 'ubicacion_actual_id' => ['nullable', Rule::exists('ubicaciones', 'id')->where('activo', true)]]);
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
        return ['asset' => $asset, 'processor' => DB::table('especificaciones_pc')->where('activo_id', $asset->id)->value('procesador'), 'sites' => DB::table('sedes')->where('activo', true)->orderBy('nombre')->get(), 'types' => AssetType::where('activo', true)->orderBy('nombre')->get(), 'statuses' => AssetStatus::where('activo', true)->where('codigo', '!=', 'dado_baja')->orderBy('nombre')->get(), 'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('nombre')->get()];
    }

    private function data(Request $request, bool $includeStatus = true): array
    {
        $rules = ['sede_id' => ['required', Rule::exists('sedes', 'id')->where('activo', true)], 'tipo_activo_id' => ['required', Rule::exists('tipos_activo', 'id')->where('activo', true)], 'uso' => ['required', Rule::in(['administrativo', 'alumnos', 'docente', 'comun'])], 'ubicacion_actual_id' => ['nullable', Rule::exists('ubicaciones', 'id')->where('activo', true)], 'activo_fijo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('activos', 'activo_fijo')->ignore($request->route('asset'))], 'numero_serie' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'], 'marca' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'modelo' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN .,_()\/-]+$/u'], 'costo_neto_actual' => ['required', 'numeric', 'min:0'], 'responsable_nombre' => ['nullable', 'string', 'max:120', 'regex:/^[\pL .\'-]+$/u'], 'responsable_email' => ['nullable', 'email', 'max:255'], 'responsable_departamento' => ['nullable', 'string', 'max:120', 'regex:/^[\pL\pN .\-]+$/u'], 'asignacion_vence_at' => ['nullable', 'date'], 'observacion' => ['nullable', 'string', 'max:2000'], 'procesador' => ['nullable', 'string', 'max:255']];
        if ($includeStatus) {
            $rules['estado_activo_id'] = ['required', Rule::exists('estados_activo', 'id')->where(fn ($query) => $query->where('activo', true)->where('codigo', '!=', 'dado_baja'))];
        }
        $data = $request->validate($rules);
        if (AssetType::query()->whereKey($data['tipo_activo_id'])->where('es_pc', true)->exists() && blank($data['procesador'] ?? null)) {
            throw ValidationException::withMessages(['procesador' => 'El procesador es obligatorio para activos de tipo PC.']);
        }
        if (($data['responsable_nombre'] ?? null) xor ($data['responsable_email'] ?? null)) {
            throw ValidationException::withMessages([
                'responsable_nombre' => 'Debes ingresar el nombre y el correo del responsable.',
                'responsable_email' => 'Debes ingresar el nombre y el correo del responsable.',
            ]);
        }
        if (($data['ubicacion_actual_id'] ?? null) && ($data['responsable_nombre'] ?? null)) {
            throw ValidationException::withMessages([
                'ubicacion_actual_id' => 'El activo debe tener una ubicación o un responsable, no ambos.',
                'responsable_nombre' => 'El activo debe tener una ubicación o un responsable, no ambos.',
            ]);
        }
        if (! empty($data['ubicacion_actual_id']) && ! DB::table('ubicaciones')->where('id', $data['ubicacion_actual_id'])->where('sede_id', $data['sede_id'])->exists()) {
            throw ValidationException::withMessages(['ubicacion_actual_id' => 'La ubicación debe pertenecer a la sede seleccionada.']);
        }
        $statusId = $includeStatus ? $data['estado_activo_id'] : $request->route('asset')?->estado_activo_id;
        $statusCode = AssetStatus::query()->whereKey($statusId)->value('codigo');
        if ($statusCode !== 'no_localizado' && empty($data['ubicacion_actual_id']) && empty($data['responsable_nombre'])) {
            throw ValidationException::withMessages(['ubicacion_actual_id' => 'El activo debe tener una ubicación o un responsable.']);
        }

        return $data;
    }

    private function syncPcSpecification(Asset $asset, ?string $processor): void
    {
        if ($asset->type()->where('es_pc', true)->exists()) {
            if (DB::table('especificaciones_pc')->where('activo_id', $asset->id)->exists()) {
                DB::table('especificaciones_pc')->where('activo_id', $asset->id)->update(['procesador' => $processor, 'updated_at' => now()]);
            } else {
                DB::table('especificaciones_pc')->insert(['activo_id' => $asset->id, 'procesador' => $processor, 'created_at' => now(), 'updated_at' => now()]);
            }

            return;
        }

        DB::table('especificaciones_pc')->where('activo_id', $asset->id)->delete();
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
