<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['rol_id', 'name', 'email', 'username', 'password', 'activo', 'debe_cambiar_password', 'intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso_at', 'password_cambiado_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password_cambiado_at' => 'datetime',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->rol?->codigo, $roles, true);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta?->isFuture() ?? false;
    }
}
