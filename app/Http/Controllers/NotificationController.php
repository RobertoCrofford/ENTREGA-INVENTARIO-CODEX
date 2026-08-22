<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('ver-notificaciones');
        $userId = auth()->id();
        $notifications = DB::table('notificaciones')->where('usuario_id', $userId)->orderByDesc('id')->paginate(30);
        DB::table('notificaciones')->where('usuario_id', $userId)->whereNull('leido_at')->update(['leido_at' => now()]);

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead(): RedirectResponse
    {
        Gate::authorize('ver-notificaciones');
        DB::table('notificaciones')->where('usuario_id', auth()->id())->whereNull('leido_at')->update(['leido_at' => now()]);

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    public function attend(Request $request, int $notificationId): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $notification = DB::transaction(function () use ($notificationId, $request) {
            $notification = DB::table('notificaciones')->where('id', $notificationId)->where('usuario_id', $request->user()->id)->lockForUpdate()->first();
            if (! $notification || $notification->titulo !== 'Código sin registrar' || $notification->atendido_at) {
                return null;
            }
            $originUserId = $notification->origen_usuario_id;
            if (! $originUserId && preg_match('/El usuario invitado (.+) escaneó el código /', $notification->mensaje, $match)) {
                $originUserId = DB::table('users')->join('roles', 'roles.id', '=', 'users.rol_id')->where('roles.codigo', 'invitado')->where('users.name', $match[1])->value('users.id');
            }
            if (! $originUserId) {
                return null;
            }
            preg_match('/código ([A-Za-z0-9-]+)/', $notification->mensaje, $codeMatch);
            $referenceUrl = ! empty($codeMatch[1]) ? route('scan.index', ['codigo' => $codeMatch[1]]) : route('scan.index');
            DB::table('notificaciones')->where('id', $notification->id)->update(['origen_usuario_id' => $originUserId, 'atendido_at' => now(), 'atendido_por' => $request->user()->id, 'leido_at' => now(), 'url' => $referenceUrl]);
            DB::table('notificaciones')->insert([
                'usuario_id' => $originUserId,
                'titulo' => 'Solicitud en atención',
                'mensaje' => "Tu solicitud por el código ".($codeMatch[1] ?? 'sin registrar')." está siendo atendida por {$request->user()->name}.",
                'url' => $referenceUrl, 'creado_at' => now(),
            ]);

            return $notification;
        });

        return back()->with($notification ? 'success' : 'warning', $notification ? 'Se informó al invitado que su solicitud está siendo atendida.' : 'Esta solicitud ya fue atendida o no está disponible.');
    }
}
