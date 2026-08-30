<?php

namespace App\Http\Middleware;

use App\Services\SessionLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Tu cuenta está desactivada.']);
        }

        if ($user) {
            app(SessionLimitService::class)->enforce($user->id, $request->session()->getId());
        }

        return $next($request);
    }
}
