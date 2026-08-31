<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetLifecycleService
{
    public function changeStatus(Asset $asset, string $statusCode, User $actor, string $reason, ?int $locationId = null): Asset
    {
        return DB::transaction(function () use ($asset, $statusCode, $actor, $reason, $locationId) {
            $asset = Asset::query()->with(['type', 'status'])->lockForUpdate()->findOrFail($asset->id);
            $target = AssetStatus::query()->where('codigo', $statusCode)->where('activo', true)->firstOrFail();
            $before = $asset->toArray();
            if ($asset->status->codigo === 'dado_baja') {
                throw ValidationException::withMessages(['activo' => 'Un activo dado de baja no puede modificarse.']);
            }
            $allowedTransitions = [
                'operativo' => ['en_reparacion', 'no_operativo', 'no_localizado'],
                'en_reparacion' => ['operativo', 'no_operativo', 'no_localizado'],
                'no_operativo' => ['operativo', 'no_localizado'],
                'no_localizado' => ['operativo', 'no_operativo'],
            ];
            if (! in_array($statusCode, $allowedTransitions[$asset->status->codigo] ?? [], true)) {
                throw ValidationException::withMessages(['estado' => 'La transición de estado solicitada no está permitida.']);
            }
            $newLocationId = $statusCode === 'no_localizado' ? null : ($locationId ?? $asset->ubicacion_actual_id);
            if ($newLocationId && ! DB::table('ubicaciones')->where('id', $newLocationId)->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['ubicacion' => 'La ubicación debe estar activa.']);
            }
            if (in_array($statusCode, ['operativo', 'en_reparacion', 'no_operativo'], true) && ! $newLocationId && ! $asset->responsable_nombre) {
                throw ValidationException::withMessages(['ubicacion' => 'El activo debe tener ubicación o responsable.']);
            }
            $oldStatus = $asset->estado_activo_id;
            $oldLocation = $asset->ubicacion_actual_id;
            $asset->update(['estado_activo_id' => $target->id, 'ubicacion_actual_id' => $newLocationId]);
            DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'cambio_estado', 'estado_origen_id' => $oldStatus, 'estado_destino_id' => $target->id, 'ubicacion_origen_id' => $oldLocation, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => $reason, 'ejecutado_por' => $actor->id, 'ocurrido_at' => now()]);
            app(AuditService::class)->record($actor, 'cambiar_estado', 'activo', $asset->id, $before, $asset->fresh()->toArray(), $reason);

            return $asset->fresh(['type', 'status']);
        });
    }
}
