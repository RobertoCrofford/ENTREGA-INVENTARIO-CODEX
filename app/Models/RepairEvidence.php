<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reparacion_id', 'ruta', 'nombre_original', 'mime', 'tamano', 'subido_por'])]
class RepairEvidence extends Model
{
    protected $table = 'evidencias_reparacion';

    public const CREATED_AT = 'creado_at';

    public const UPDATED_AT = null;

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class, 'reparacion_id');
    }
}
