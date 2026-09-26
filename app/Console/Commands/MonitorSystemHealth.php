<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitorSystemHealth extends Command
{
    protected $signature = 'system:monitor';

    protected $description = 'Revisa el respaldo, cola e importaciones y avisa una vez por incidente.';

    public function handle(SystemHealthService $health): int
    {
        foreach ($health->checks() as $name => $check) {
            $cacheKey = "system-health-alert:{$name}";
            if ($check['ok']) {
                Cache::forget($cacheKey);

                continue;
            }
            if (Cache::has($cacheKey)) {
                continue;
            }

            $recipients = DB::table('users')->join('roles', 'roles.id', '=', 'users.rol_id')
                ->where('users.activo', true)->where('roles.codigo', Role::SUPERADMIN)->pluck('users.id');
            foreach ($recipients as $userId) {
                DB::table('notificaciones')->insert([
                    'usuario_id' => $userId,
                    'titulo' => 'Alerta de supervisión: '.ucfirst($name),
                    'mensaje' => $check['message'],
                    'url' => route('system-status.index'),
                    'creado_at' => now(),
                ]);
            }
            Cache::put($cacheKey, true, now()->addHours(12));
            $this->warn($check['message']);
        }

        return self::SUCCESS;
    }
}
