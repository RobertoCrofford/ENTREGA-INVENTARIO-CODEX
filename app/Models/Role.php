<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const INVITADO = 'invitado';

    public const TECNICO = 'tecnico';

    public const DIRECTOR_TECNICO = 'director_tecnico';

    public const SUPERADMIN = 'superadmin';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}
