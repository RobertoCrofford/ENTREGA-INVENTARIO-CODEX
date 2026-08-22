<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['categoria_id', 'codigo_escaneo_id', 'codigo_interno', 'numero_parte', 'nombre', 'marca', 'modelo', 'descripcion', 'costo_neto_actual', 'activo', 'creado_por'])]
class Product extends Model
{
    /** El DER usa nombres de tabla en español. */
    protected $table = 'productos';

    protected function casts(): array
    {
        return ['costo_neto_actual' => 'decimal:2', 'activo' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(Stock::class, 'producto_id');
    }
}
