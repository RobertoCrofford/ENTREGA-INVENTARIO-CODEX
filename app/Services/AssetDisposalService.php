<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AssetDisposalService
{
    public function approve(int $requestId, User $approver, ?string $selfJustification = null): void
    {
        DB::transaction(function () use ($requestId, $approver, $selfJustification) {
            $request = DB::table('solicitudes_baja_activo')->lockForUpdate()->find($requestId);
            if (! $request || $request->estado !== 'pendiente') {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud no está pendiente.']);
            } $asset = Asset::query()->lockForUpdate()->findOrFail($request->activo_id);
            $self = $request->solicitado_por === $approver->id;
            if ($self && ! $approver->tieneRol('superadmin')) {
                throw ValidationException::withMessages(['solicitud' => 'Un Director no puede aprobar su propia solicitud.']);
            } if ($self && blank($selfJustification)) {
                throw ValidationException::withMessages(['justificacion' => 'La autoaprobación exige justificación.']);
            } if ($asset->costo_neto_actual === null) {
                throw ValidationException::withMessages(['costo' => 'Debe completar el costo antes de aprobar.']);
            } $low = AssetStatus::where('codigo', 'dado_baja')->firstOrFail();
            $pdf = $asset->costo_neto_actual > 200000;
            DB::table('solicitudes_baja_activo')->where('id', $requestId)->update(['estado' => 'aprobada', 'resuelto_por' => $approver->id, 'resuelto_at' => now(), 'autoaprobacion_excepcional' => $self, 'justificacion_autoaprobacion' => $selfJustification, 'costo_neto_snapshot' => $asset->costo_neto_actual, 'genera_acta_pdf' => $pdf, 'estado_pdf' => $pdf ? 'pendiente' : 'no_requerido', 'updated_at' => now()]);
            $asset->update(['estado_activo_id' => $low->id, 'ubicacion_actual_id' => null, 'responsable_nombre' => null, 'responsable_email' => null, 'responsable_departamento' => null]);
            DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'baja', 'estado_origen_id' => $asset->estado_activo_id, 'estado_destino_id' => $low->id, 'motivo' => $request->motivo, 'ejecutado_por' => $approver->id, 'ocurrido_at' => now()]);
            app(AuditService::class)->record($approver, 'aprobar', 'solicitud_baja_activo', $requestId, reason: $request->motivo);
            if ($pdf) {
                DB::afterCommit(fn () => $this->generatePdf($requestId));
            }
        });
    }

    public function generatePdf(int $requestId): void
    {
        $request = DB::table('solicitudes_baja_activo')->find($requestId);
        $asset = Asset::findOrFail($request->activo_id);
        try {
            $path = "actas/baja-activo-{$requestId}.pdf";
            Storage::disk('local')->put($path, Pdf::loadView('pdf.asset-disposal', compact('request', 'asset'))->output());
            DB::table('solicitudes_baja_activo')->where('id', $requestId)->update(['pdf_path' => $path, 'pdf_sha256' => hash('sha256', Storage::disk('local')->get($path)), 'estado_pdf' => 'generado', 'updated_at' => now()]);
        } catch (\Throwable) {
            DB::table('solicitudes_baja_activo')->where('id', $requestId)->update(['estado_pdf' => 'fallido', 'updated_at' => now()]);
        }
    }
}
