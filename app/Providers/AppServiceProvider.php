<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        Gate::before(fn (User $user) => $user->tieneRol(Role::SUPERADMIN) ? true : null);

        Gate::define('consultar-inventario', fn (User $user) => $user->activo);
        Gate::define('clasificar-activos', fn (User $user) => $user->activo && ! $user->tieneRol(Role::INVITADO));
        Gate::define('ver-notificaciones', fn (User $user) => $user->tieneRol(Role::INVITADO, Role::TECNICO, Role::DIRECTOR_TECNICO));
        Gate::define('generar-bitacora', fn (User $user) => false);
        Gate::define('administrar-usuarios', fn (User $user) => $user->tieneRol(Role::DIRECTOR_TECNICO));
        Gate::define('gestionar-inventario', fn (User $user) => $user->tieneRol(Role::TECNICO, Role::DIRECTOR_TECNICO));
        Gate::define('autorizar-operaciones', fn (User $user) => $user->tieneRol(Role::DIRECTOR_TECNICO));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.$request->input('username')));

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            $unreadNotifications = $user && Schema::hasTable('notificaciones')
                ? DB::table('notificaciones')->where('usuario_id', $user->id)->whereNull('leido_at')->count()
                : 0;
            $recentNotifications = $user && Schema::hasTable('notificaciones') && $user->can('ver-notificaciones')
                ? DB::table('notificaciones')->where('usuario_id', $user->id)->orderByDesc('id')->limit(5)->get()
                : collect();

            $view->with(compact('unreadNotifications', 'recentNotifications'));
        });
    }
}

