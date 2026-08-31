<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['activo_id', 'tecnico_id', 'prioridad', 'falla_reportada', 'estado', 'estado_final_id', 'resultado', 'finalizado_at', 'creado_por'])]
class Repair extends Model
{
    protected $table = 'reparaciones';

    protected function casts(): array
    {
        return ['finalizado_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'activo_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(RepairEvidence::class, 'reparacion_id');
    }
}
