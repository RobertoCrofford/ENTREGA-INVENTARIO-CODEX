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
            if ($asset->status->codigo === 'dado_baja') {
                throw ValidationException::withMessages(['activo' => 'Un activo dado de baja no puede modificarse.']);
            }
            if ($statusCode === 'no_localizado') {
                $locationId = null;
            }
            if (in_array($statusCode, ['operativo', 'en_reparacion', 'no_operativo'], true) && ! $locationId && ! $asset->responsable_nombre) {
                throw ValidationException::withMessages(['ubicacion' => 'El activo debe tener ubicación o responsable.']);
            }
            $oldStatus = $asset->estado_activo_id;
            $oldLocation = $asset->ubicacion_actual_id;
            $asset->update(['estado_activo_id' => $target->id, 'ubicacion_actual_id' => $locationId ?? $asset->ubicacion_actual_id]);
            DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'cambio_estado', 'estado_origen_id' => $oldStatus, 'estado_destino_id' => $target->id, 'ubicacion_origen_id' => $oldLocation, 'ubicacion_destino_id' => $asset->ubicacion_actual_id, 'motivo' => $reason, 'ejecutado_por' => $actor->id, 'ocurrido_at' => now()]);

            return $asset->fresh(['type', 'status']);
        });
    }
}
