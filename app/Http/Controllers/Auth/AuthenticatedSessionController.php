<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login', [
            'canSetupInitialUser' => User::query()->doesntExist(),
        ]);
    }

    public function store(Request $request, SessionLimitService $sessionLimit): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $user = User::query()->with('rol')->where('username', $credentials['username'])->first();

        if (! $user || ! $user->activo) {
            throw ValidationException::withMessages(['username' => 'Las credenciales no son válidas.']);
        }
        if ($user->estaBloqueado()) {
            throw ValidationException::withMessages(['username' => 'Las credenciales no son válidas.']);
        }
        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], false)) {
            $attempts = $user->intentos_fallidos + 1;
            $user->forceFill([
                'intentos_fallidos' => $attempts,
                'bloqueado_hasta' => $attempts >= 5 ? now()->addMinutes(15) : null,
            ])->save();
            throw ValidationException::withMessages(['username' => 'Las credenciales no son válidas.']);
        }

        $request->session()->regenerate();
        $sessionLimit->enforce($user->id, $request->session()->getId());
        $user->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso_at' => now(),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
