<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    public function record(?User $user, string $action, string $entityType, int|string|null $entityId, array $before = [], array $after = [], ?string $reason = null, string $result = 'exitoso', bool $notify = true): void
    {
        DB::table('bitacora')->insert([
            'usuario_id' => $user?->id, 'accion' => $action, 'entidad_tipo' => $entityType, 'entidad_id' => $entityId,
            'antes_json' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'despues_json' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'motivo' => $reason,
            'ip' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'correlation_id' => (string) Str::uuid(),
            'resultado' => $result, 'creado_at' => now(),
        ]);

        if (! $notify) {
            return;
        }

        $entity = match ($entityType) {
            'movimiento_inventario' => 'movimiento',
            'producto' => 'producto',
            'activo' => 'activo',
            'reparacion' => 'reparación',
            default => str_replace('_', ' ', $entityType),
        };
        $actor = $user?->name ?? 'Sistema';
        $message = "{$actor} realizó la acción '{$action}' sobre {$entity}".($entityId ? " #{$entityId}" : '.');
        if ($reason) {
            $message .= " Razón: {$reason}";
        }
        $recipients = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.rol_id')
            ->where('users.activo', true)
            ->whereIn('roles.codigo', [Role::SUPERADMIN, Role::DIRECTOR_TECNICO, Role::TECNICO])
            ->pluck('users.id');
        foreach ($recipients as $recipientId) {
            DB::table('notificaciones')->insert([
                'usuario_id' => $recipientId,
                'titulo' => ucfirst($entity).' '.ucfirst($action),
                'mensaje' => $message,
                'url' => match ($entityType) {
                    'movimiento_inventario' => route('movements.index'),
                    'producto' => $entityId ? route('products.show', $entityId) : route('products.index'),
                    'activo' => $entityId ? route('assets.show', $entityId) : route('assets.index'),
                    'reparacion' => route('repairs.index'),
                    default => null,
                },
                'creado_at' => now(),
            ]);
        }
    }
}
