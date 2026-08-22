<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit')->with('warning', 'Debes cambiar tu contraseña antes de continuar.');
        }

        return $next($request);
    }
}
