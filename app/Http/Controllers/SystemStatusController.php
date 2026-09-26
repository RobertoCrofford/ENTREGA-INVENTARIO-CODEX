<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SystemStatusController extends Controller
{
    public function index(SystemHealthService $health): View
    {
        Gate::authorize('administrar-usuarios');

        $checks = $health->checks();
        $operational = collect($checks)->every(fn (array $check) => $check['ok']);

        return view('system-status.index', compact('checks', 'operational'));
    }

    public function requestBackup(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('administrar-usuarios');

        $requestFile = '/backup-requests/manual-backup-request';
        if (! is_dir(dirname($requestFile))) {
            mkdir(dirname($requestFile), 0770, true);
        }
        file_put_contents($requestFile, now()->utc()->toIso8601String());
        $audit->record($request->user(), 'solicitar', 'respaldo', null, reason: 'Respaldo manual solicitado desde Estado del sistema.', notify: false);

        return back()->with('success', 'Respaldo manual solicitado. El servicio lo iniciará en menos de un minuto; actualiza esta pantalla para ver la nueva fecha de copia.');
    }
}
