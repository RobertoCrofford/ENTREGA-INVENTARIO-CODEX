<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function checks(): array
    {
        return [
            'database' => $this->databaseCheck(),
            'backup' => $this->backupCheck(),
            'queue' => $this->queueCheck(),
            'imports' => $this->importsCheck(),
        ];
    }

    private function databaseCheck(): array
    {
        try {
            DB::select('select 1');

            return $this->ok('Base de datos disponible.');
        } catch (\Throwable) {
            return $this->failed('No se pudo consultar la base de datos.');
        }
    }

    private function backupCheck(): array
    {
        $statusFile = '/backups/backup-status';
        if (! is_readable($statusFile)) {
            return $this->failed('No hay un estado de respaldo disponible todavía.');
        }

        $values = parse_ini_file($statusFile, false, INI_SCANNER_RAW);
        $file = $values['file'] ?? null;
        $finishedAt = $values['finished_at'] ?? null;
        if (($values['status'] ?? null) !== 'ok' || ! $file || ! is_file($file) || ! $finishedAt) {
            return $this->failed('El último respaldo no terminó correctamente.');
        }

        try {
            $finished = now()->parse($finishedAt);
        } catch (\Throwable) {
            return $this->failed('El respaldo no tiene una fecha válida.');
        }

        if ($finished->lt(now()->subHours(26))) {
            return $this->failed('El último respaldo tiene más de 26 horas.');
        }

        return $this->ok('Última copia verificada: '.$finished->timezone(config('app.timezone'))->format('d-m-Y H:i'), ['file' => basename($file)]);
    }

    private function queueCheck(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            if ($failed > 0) {
                return $this->failed("Hay {$failed} tarea(s) en cola que requieren revisión.", compact('pending', 'failed'));
            }

            return $this->ok($pending ? "Hay {$pending} tarea(s) esperando en cola." : 'No hay tareas pendientes en cola.', compact('pending', 'failed'));
        } catch (\Throwable) {
            return $this->failed('No se pudo revisar la cola de tareas.');
        }
    }

    private function importsCheck(): array
    {
        try {
            $stuck = DB::table('importaciones')->where('estado', 'procesando')->where('iniciado_at', '<', now()->subHours(2))->count();

            return $stuck
                ? $this->failed("Hay {$stuck} importación(es) en proceso por más de dos horas.", compact('stuck'))
                : $this->ok('No hay importaciones detenidas.', compact('stuck'));
        } catch (\Throwable) {
            return $this->failed('No se pudo revisar el estado de las importaciones.');
        }
    }

    private function ok(string $message, array $details = []): array
    {
        return ['ok' => true, 'message' => $message, 'details' => $details];
    }

    private function failed(string $message, array $details = []): array
    {
        return ['ok' => false, 'message' => $message, 'details' => $details];
    }
}
