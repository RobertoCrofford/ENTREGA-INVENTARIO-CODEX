<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyTodayEvents extends Command
{
    protected $signature = 'eventos:notificar-hoy {--fecha= : Fecha a revisar en formato AAAA-MM-DD} {--dry-run : Solo informa los avisos que se enviarían}';

    protected $description = 'Notifica a los usuarios activos los eventos programados para el día';

    public function handle(): int
    {
        try {
            $date = $this->option('fecha') ? Carbon::createFromFormat('Y-m-d', $this->option('fecha'))->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            $this->components->error('La fecha debe tener el formato AAAA-MM-DD.');

            return self::FAILURE;
        }
        $events = DB::table('eventos_calendario')->where('estado', 'programado')->whereDate('inicio_at', $date)->orderBy('inicio_at')->get();
        $users = DB::table('users')->where('activo', true)->pluck('id');
        $sent = 0;

        foreach ($events as $event) {
            $message = "Evento del día #{$event->id}: {$event->titulo}".($event->lugar ? " · {$event->lugar}" : '');
            $url = route('events.index', ['mes' => $date->format('Y-m'), 'dia' => $date->toDateString()]);
            foreach ($users as $userId) {
                $exists = DB::table('notificaciones')->where('usuario_id', $userId)
                    ->where('mensaje', 'like', "Evento del día #{$event->id}:%")
                    ->where('creado_at', '>=', $date)->exists();
                if ($exists) {
                    continue;
                }
                if (! $this->option('dry-run')) {
                    DB::table('notificaciones')->insert([
                        'usuario_id' => $userId, 'titulo' => 'Evento de hoy', 'mensaje' => $message,
                        'url' => $url, 'creado_at' => now(),
                    ]);
                }
                $sent++;
            }
        }
        $this->components->info($events->isEmpty() ? 'No hay eventos para esta fecha.' : "{$sent} notificación(es) ".($this->option('dry-run') ? 'se enviarían.' : 'enviada(s).'));

        return self::SUCCESS;
    }
}
