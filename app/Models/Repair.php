<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activo_id', 'tecnico_id', 'prioridad', 'falla_reportada', 'estado', 'creado_por'])]
class Repair extends Model
{
    protected $table = 'reparaciones';

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'activo_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }
}

