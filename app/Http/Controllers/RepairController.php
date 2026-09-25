<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Repair;
use App\Models\RepairEvidence;
use App\Models\Role;
use App\Models\User;
use App\Services\AssetLifecycleService;
use App\Services\AuditService;
use App\Support\AssetCodeMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $matchingCodeIds = $request->filled('q') ? AssetCodeMatch::assetIds((string) $request->q) : [];
        $repairs = Repair::query()->with(['asset', 'technician', 'evidences'])
            ->when($request->q, fn ($query, $term) => $query->where('falla_reportada', 'like', "%{$term}%")
                ->orWhereHas('asset', fn ($assets) => $assets->where('activo_fijo', 'like', "%{$term}%")
                    ->orWhere('numero_serie', 'like', "%{$term}%")
                    ->orWhereIn('id', $matchingCodeIds))
                ->orWhereHas('technician', fn ($users) => $users->where('name', 'like', "%{$term}%")))
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->latest()->paginate(20)->withQueryString();

        return view('repairs.index', compact('repairs'));
    }

    public function create(): View
    {
        Gate::authorize('gestionar-inventario');

        return view('repairs.form', ['assets' => Asset::query()->whereHas('status', fn ($query) => $query->where('codigo', 'operativo'))->whereDoesntHave('repairs', fn ($query) => $query->where('estado', '!=', 'resuelta'))->orderBy('activo_fijo')->get(), 'technicians' => $this->technicians()]);
    }

    public function store(Request $request, AuditService $audit, AssetLifecycleService $lifecycle): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $technicianIds = $this->technicians()->pluck('id')->all();
        $data = $request->validate([
            'activo_id' => ['required', 'exists:activos,id'],
            'tecnico_id' => ['required', Rule::in($technicianIds)],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])],
            'falla_reportada' => ['required', 'string', 'max:2000'],
            'evidencias' => ['required', 'array', 'size:1'],
            'evidencias.*' => ['file', 'mimes:docx', 'max:10240'],
        ]);
        $repair = DB::transaction(function () use ($data, $request, $audit, $lifecycle) {
            $asset = Asset::query()->with('status')->lockForUpdate()->findOrFail($data['activo_id']);
            abort_unless($asset->status?->codigo === 'operativo', 422, 'Solo un activo operativo puede enviarse a reparación.');
            abort_if(Repair::query()->where('activo_id', $asset->id)->where('estado', '!=', 'resuelta')->exists(), 422, 'El activo ya tiene una reparación abierta.');
            $repair = Repair::query()->create(collect($data)->except('evidencias')->all() + ['estado' => 'abierta', 'creado_por' => $request->user()->id]);
            $lifecycle->changeStatus($asset, 'en_reparacion', $request->user(), 'Ingreso a reparación #'.$repair->id);
            foreach ($request->file('evidencias', []) as $file) {
                $path = $file->store("reparaciones/{$repair->id}", 'local');
                $repair->evidences()->create([
                    'ruta' => $path,
                    'nombre_original' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'subido_por' => $request->user()->id,
                ]);
            }
            $repair->load('asset');
            $audit->record($request->user(), 'crear', 'reparacion', $repair->id, after: $repair->toArray(), reason: 'Ficha técnica Word adjunta.', notify: false);

            $recipientIds = User::query()
                ->where('activo', true)
                ->where(function ($query) use ($repair, $request) {
                    $query->where('id', $repair->tecnico_id)
                        ->orWhere('id', $request->user()->id)
                        ->orWhereHas('rol', fn ($roles) => $roles->whereIn('codigo', [Role::DIRECTOR_TECNICO, Role::SUPERADMIN]));
                })
                ->pluck('id')
                ->unique();

            foreach ($recipientIds as $recipientId) {
                $message = $recipientId === $repair->tecnico_id
                    ? "Se te asignó la reparación del activo {$repair->asset->activo_fijo}."
                    : "{$request->user()->name} registró una reparación para el activo {$repair->asset->activo_fijo}.";
                DB::table('notificaciones')->insert([
                    'usuario_id' => $recipientId,
                    'titulo' => 'Reparación registrada',
                    'mensaje' => $message,
                    'url' => route('repairs.index'),
                    'creado_at' => now(),
                ]);
            }

            return $repair;
        });

        return redirect()->route('repairs.index')->with('success', 'Reparación registrada y notificada.');
    }

    public function complete(Request $request, Repair $repair, AuditService $audit, AssetLifecycleService $lifecycle): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $user = $request->user();
        abort_unless($repair->tecnico_id === $user->id || $user->tieneRol(Role::DIRECTOR_TECNICO, Role::SUPERADMIN), 403);
        $data = $request->validate([
            'estado_final' => ['required', Rule::in(['operativo', 'no_operativo'])],
            'resultado' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $repair = DB::transaction(function () use ($repair, $data, $user, $audit, $lifecycle) {
            $repair = Repair::query()->with('asset.status')->lockForUpdate()->findOrFail($repair->id);
            abort_if($repair->estado === 'resuelta', 422, 'La reparación ya estaba cerrada.');
            abort_unless($repair->asset->status?->codigo === 'en_reparacion', 422, 'El activo ya no está marcado en reparación.');
            $before = $repair->toArray();
            $lifecycle->changeStatus($repair->asset, $data['estado_final'], $user, $data['resultado']);
            $finalStatusId = DB::table('estados_activo')->where('codigo', $data['estado_final'])->value('id');
            $repair->update(['estado' => 'resuelta', 'estado_final_id' => $finalStatusId, 'resultado' => $data['resultado'], 'finalizado_at' => now()]);
            $audit->record($user, 'cerrar', 'reparacion', $repair->id, $before, $repair->fresh()->toArray(), $data['resultado'], notify: false);

            return $repair->fresh(['asset']);
        });

        $recipients = User::query()->where('activo', true)
            ->whereHas('rol', fn ($roles) => $roles->whereIn('codigo', [Role::DIRECTOR_TECNICO, Role::SUPERADMIN]))
            ->pluck('id');
        foreach ($recipients as $recipientId) {
            DB::table('notificaciones')->insert([
                'usuario_id' => $recipientId,
                'titulo' => 'Reparación resuelta',
                'mensaje' => "{$user->name} cerró la reparación del activo {$repair->asset->activo_fijo}. Falla reportada: {$repair->falla_reportada}",
                'url' => route('repairs.index'),
                'creado_at' => now(),
            ]);
        }

        return redirect()->route('repairs.index')->with('success', 'Reparación cerrada; se notificó al Director técnico y al Superadministrador.');
    }

    public function cancel(Request $request, Repair $repair, AuditService $audit, AssetLifecycleService $lifecycle): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $user = $request->user();
        abort_unless($repair->tecnico_id === $user->id || $user->tieneRol(Role::DIRECTOR_TECNICO, Role::SUPERADMIN), 403);
        $data = $request->validate([
            'motivo_cancelacion' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $repair = DB::transaction(function () use ($repair, $data, $user, $audit, $lifecycle) {
            $repair = Repair::query()->with('asset.status')->lockForUpdate()->findOrFail($repair->id);
            abort_if(in_array($repair->estado, ['resuelta', 'cancelada'], true), 422, 'La reparación ya fue finalizada.');
            abort_unless($repair->asset->status?->codigo === 'en_reparacion', 422, 'El activo ya no está marcado en reparación.');
            $before = $repair->toArray();
            $reason = 'Reparación cancelada: '.$data['motivo_cancelacion'];
            $lifecycle->changeStatus($repair->asset, 'operativo', $user, $reason);
            $repair->update(['estado' => 'cancelada', 'resultado' => $data['motivo_cancelacion'], 'finalizado_at' => now()]);
            $audit->record($user, 'cancelar', 'reparacion', $repair->id, $before, $repair->fresh()->toArray(), $data['motivo_cancelacion'], notify: false);

            return $repair->fresh(['asset']);
        });

        $recipientIds = User::query()->where('activo', true)
            ->where(function ($query) use ($repair) {
                $query->where('id', $repair->tecnico_id)
                    ->orWhereHas('rol', fn ($roles) => $roles->whereIn('codigo', [Role::DIRECTOR_TECNICO, Role::SUPERADMIN]));
            })->pluck('id')->unique();
        foreach ($recipientIds as $recipientId) {
            DB::table('notificaciones')->insert([
                'usuario_id' => $recipientId,
                'titulo' => 'Reparación cancelada',
                'mensaje' => "{$user->name} canceló la reparación del activo {$repair->asset->activo_fijo}. Motivo: {$repair->resultado}",
                'url' => route('repairs.index'),
                'creado_at' => now(),
            ]);
        }

        return redirect()->route('repairs.index')->with('success', 'Reparación cancelada; el activo volvió a estado operativo.');
    }

    public function evidence(Repair $repair, RepairEvidence $evidence)
    {
        Gate::authorize('gestionar-inventario');
        abort_unless($evidence->reparacion_id === $repair->id && Storage::disk('local')->exists($evidence->ruta), 404);

        return Storage::disk('local')->response($evidence->ruta, $evidence->nombre_original, ['Content-Type' => $evidence->mime]);
    }

    public function replaceEvidence(Request $request, Repair $repair, RepairEvidence $evidence, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $user = $request->user();
        abort_unless($evidence->reparacion_id === $repair->id, 404);
        abort_unless($repair->tecnico_id === $user->id || $user->tieneRol(Role::DIRECTOR_TECNICO, Role::SUPERADMIN), 403);
        abort_if($repair->estado !== 'abierta', 422, 'La ficha técnica solo puede reemplazarse mientras la reparación está abierta.');
        $data = $request->validate([
            'evidencia' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);

        $file = $data['evidencia'];
        $newPath = $file->store("reparaciones/{$repair->id}", 'local');

        try {
            $oldPath = DB::transaction(function () use ($repair, $evidence, $file, $newPath, $user, $audit) {
                $repair = Repair::query()->lockForUpdate()->findOrFail($repair->id);
                abort_if($repair->estado !== 'abierta', 422, 'La ficha técnica solo puede reemplazarse mientras la reparación está abierta.');
                $evidence = RepairEvidence::query()->lockForUpdate()->where('reparacion_id', $repair->id)->findOrFail($evidence->id);
                $before = $evidence->toArray();
                $oldPath = $evidence->ruta;
                $evidence->update([
                    'ruta' => $newPath,
                    'nombre_original' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'subido_por' => $user->id,
                ]);
                $audit->record($user, 'actualizar', 'reparacion', $repair->id, $before, $evidence->fresh()->toArray(), 'Ficha técnica reemplazada.', notify: false);

                return $oldPath;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPath);

            throw $exception;
        }

        Storage::disk('local')->delete($oldPath);

        return redirect()->route('repairs.index')->with('success', 'Ficha técnica reemplazada correctamente.');
    }

    private function technicians()
    {
        return User::query()->where('activo', true)->whereHas('rol', fn ($roles) => $roles->whereIn('codigo', [Role::TECNICO, Role::DIRECTOR_TECNICO, Role::SUPERADMIN]))->orderBy('name')->get();
    }
}
