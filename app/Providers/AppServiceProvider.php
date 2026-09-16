<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::before(fn (User $user, string $ability) => $ability !== 'solicitar-baja-activo' && $user->tieneRol(Role::SUPERADMIN) ? true : null);

        Gate::define('consultar-inventario', fn (User $user) => $user->activo);
        Gate::define('ver-eventos', fn (User $user) => $user->activo);
        Gate::define('gestionar-eventos', fn (User $user) => $user->activo && $user->tieneRol(Role::TECNICO, Role::DIRECTOR_TECNICO));
        Gate::define('clasificar-activos', fn (User $user) => $user->activo && ! $user->tieneRol(Role::INVITADO));
        Gate::define('ver-notificaciones', fn (User $user) => $user->tieneRol(Role::INVITADO, Role::TECNICO, Role::DIRECTOR_TECNICO));
        Gate::define('generar-bitacora', fn (User $user) => $user->activo && $user->tieneRol(Role::SUPERADMIN));
        Gate::define('administrar-usuarios', fn (User $user) => $user->tieneRol(Role::SUPERADMIN));
        Gate::define('gestionar-inventario', fn (User $user) => $user->tieneRol(Role::TECNICO, Role::DIRECTOR_TECNICO));
        Gate::define('solicitar-baja-activo', fn (User $user) => $user->activo && $user->tieneRol(Role::TECNICO, Role::DIRECTOR_TECNICO, Role::SUPERADMIN));
        Gate::define('autorizar-operaciones', fn (User $user) => $user->tieneRol(Role::DIRECTOR_TECNICO));
        Gate::define('desactivar-productos', fn (User $user) => $user->tieneRol(Role::DIRECTOR_TECNICO));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.$request->input('username')));

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            $unreadNotifications = $user && Schema::hasTable('notificaciones')
                ? DB::table('notificaciones')->where('usuario_id', $user->id)->whereNull('leido_at')->count()
                : 0;
            $recentNotifications = $user && Schema::hasTable('notificaciones') && $user->can('ver-notificaciones')
                ? DB::table('notificaciones')->where('usuario_id', $user->id)->orderByDesc('id')->limit(5)->get()
                    ->map(function (object $notification) {
                        $hasLegacyTarget = preg_match('/sobre (activo|producto) #\d+/i', $notification->mensaje) === 1;
                        $notification->open_url = ($notification->url || $hasLegacyTarget)
                            ? route('notifications.open', $notification->id)
                            : null;

                        return $notification;
                    })
                : collect();

            $view->with(compact('unreadNotifications', 'recentNotifications'));
        });
    }
}
