<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Repair;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $repairs = Repair::query()->with(['asset', 'technician'])
            ->when($request->q, fn ($query, $term) => $query->where('falla_reportada', 'like', "%{$term}%")
                ->orWhereHas('asset', fn ($assets) => $assets->where('activo_fijo', 'like', "%{$term}%"))
                ->orWhereHas('technician', fn ($users) => $users->where('name', 'like', "%{$term}%")))
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->latest()->paginate(20)->withQueryString();

        return view('repairs.index', compact('repairs'));
    }

    public function create(): View
    {
        Gate::authorize('gestionar-inventario');

        return view('repairs.form', ['assets' => Asset::query()->orderBy('activo_fijo')->get(), 'technicians' => $this->technicians()]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $technicianIds = $this->technicians()->pluck('id')->all();
        $data = $request->validate([
            'activo_id' => ['required', 'exists:activos,id'],
            'tecnico_id' => ['required', Rule::in($technicianIds)],
            'prioridad' => ['required', Rule::in(['baja', 'media', 'alta', 'critica'])],
            'falla_reportada' => ['required', 'string', 'max:2000'],
        ]);
        $repair = Repair::query()->create($data + ['estado' => 'abierta', 'creado_por' => $request->user()->id]);
        $audit->record($request->user(), 'crear', 'reparacion', $repair->id, after: $repair->toArray());

        return redirect()->route('repairs.index')->with('success', 'Reparación registrada.');
    }

    public function complete(Request $request, Repair $repair, AuditService $audit): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $user = $request->user();
        abort_unless($repair->tecnico_id === $user->id || $user->tieneRol(Role::DIRECTOR_TECNICO, Role::SUPERADMIN), 403);
        if ($repair->estado === 'resuelta') {
            return back()->with('warning', 'La reparación ya estaba cerrada.');
        }

        $before = $repair->toArray();
        $repair->update(['estado' => 'resuelta']);
        $repair->load('asset');
        $audit->record($user, 'cerrar', 'reparacion', $repair->id, $before, $repair->fresh()->toArray(), notify: false);

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

    private function technicians()
    {
        return User::query()->where('activo', true)->whereHas('rol', fn ($roles) => $roles->whereIn('codigo', [Role::TECNICO, Role::DIRECTOR_TECNICO, Role::SUPERADMIN]))->orderBy('name')->get();
    }
}

