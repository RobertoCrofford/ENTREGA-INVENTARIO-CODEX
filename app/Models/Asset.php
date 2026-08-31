<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 'tipo_activo_id', 'estado_activo_id', 'uso', 'codigo_escaneo_id', 'ubicacion_actual_id', 'activo_fijo', 'numero_serie', 'marca', 'modelo', 'costo_neto_actual', 'responsable_nombre', 'responsable_email', 'responsable_departamento', 'asignacion_vence_at', 'observacion', 'creado_por'])]
class Asset extends Model
{
    protected $table = 'activos';

    protected function casts(): array
    {
        return ['costo_neto_actual' => 'decimal:2', 'asignacion_vence_at' => 'datetime'];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'estado_activo_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'tipo_activo_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'ubicacion_actual_id');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class, 'activo_id');
    }
}
