<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Role;
use App\Services\AssetDisposalService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetDisposalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('consultar-inventario');

        $requests = DB::table('solicitudes_baja_activo as solicitud')
            ->join('activos as activo', 'activo.id', '=', 'solicitud.activo_id')
            ->join('users as solicitante', 'solicitante.id', '=', 'solicitud.solicitado_por')
            ->leftJoin('users as resolutor', 'resolutor.id', '=', 'solicitud.resuelto_por')
            ->select('solicitud.*', 'activo.activo_fijo', 'activo.marca', 'activo.modelo', 'solicitante.name as solicitante_nombre', 'resolutor.name as resolutor_nombre')
            ->orderByDesc('solicitud.id')->paginate(20);

        return view('asset-disposals.index', [
            'requests' => $requests,
            'assets' => Asset::query()->with('status')
                ->whereHas('status', fn ($query) => $query->where('codigo', '!=', 'dado_baja'))
                ->whereNotIn('id', DB::table('solicitudes_baja_activo')->whereIn('estado', ['borrador', 'pendiente'])->select('activo_id'))
                ->orderBy('activo_fijo')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('solicitar-baja-activo');
        $data = $request->validate([
            'activo_id' => ['required', 'exists:activos,id'],
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
            'diagnostico' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $requestId = DB::transaction(function () use ($data, $request): int {
            $asset = Asset::query()->with('status')->lockForUpdate()->findOrFail($data['activo_id']);
            abort_if($asset->status?->codigo === 'dado_baja', 422, 'El activo ya está dado de baja.');
            abort_if(DB::table('solicitudes_baja_activo')->where('activo_id', $asset->id)->whereIn('estado', ['borrador', 'pendiente'])->exists(), 422, 'El activo ya tiene una solicitud de baja activa.');

            return DB::table('solicitudes_baja_activo')->insertGetId([
                'activo_id' => $asset->id,
                'estado' => 'pendiente',
                'motivo' => $data['motivo'],
                'diagnostico' => $data['diagnostico'],
                'solicitado_por' => $request->user()->id,
                'solicitado_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $audit->record($request->user(), 'solicitar', 'solicitud_baja_activo', $requestId, reason: $data['motivo']);

        return back()->with('success', 'Solicitud de baja enviada para aprobación.');
    }

    public function approve(Request $request, int $disposal, AssetDisposalService $service): RedirectResponse
    {
        Gate::authorize('autorizar-operaciones');
        $isSelf = DB::table('solicitudes_baja_activo')->where('id', $disposal)->value('solicitado_por') === $request->user()->id;
        $data = $request->validate([
            'justificacion_autoaprobacion' => [Rule::requiredIf($isSelf && $request->user()->tieneRol(Role::SUPERADMIN)), 'nullable', 'string', 'min:20', 'max:2000'],
        ]);
        $service->approve($disposal, $request->user(), $data['justificacion_autoaprobacion'] ?? null);

        return back()->with('success', 'Baja aprobada y activo bloqueado para futuras operaciones.');
    }

    public function reject(Request $request, int $disposal, AuditService $audit): RedirectResponse
    {
        Gate::authorize('autorizar-operaciones');
        $data = $request->validate(['comentario' => ['required', 'string', 'min:10', 'max:2000']]);
        DB::transaction(function () use ($disposal, $data, $request): void {
            $record = DB::table('solicitudes_baja_activo')->lockForUpdate()->find($disposal);
            abort_unless($record && $record->estado === 'pendiente', 422, 'La solicitud ya no está pendiente.');
            abort_if($record->solicitado_por === $request->user()->id, 422, 'No puedes rechazar tu propia solicitud.');
            DB::table('solicitudes_baja_activo')->where('id', $disposal)->update(['estado' => 'rechazada', 'resuelto_por' => $request->user()->id, 'resuelto_at' => now(), 'comentario_resolucion' => $data['comentario'], 'updated_at' => now()]);
        });
        $audit->record($request->user(), 'rechazar', 'solicitud_baja_activo', $disposal, reason: $data['comentario']);

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function download(int $disposal)
    {
        Gate::authorize('consultar-inventario');
        $record = DB::table('solicitudes_baja_activo')->find($disposal);
        abort_unless($record && $record->estado_pdf === 'generado' && $record->pdf_path && Storage::disk('local')->exists($record->pdf_path), 404);

        return Storage::disk('local')->download($record->pdf_path, 'acta-baja-activo-'.$disposal.'.pdf');
    }
}
