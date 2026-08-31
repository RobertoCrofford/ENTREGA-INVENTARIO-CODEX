<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = User::query()->where('email', $data['email'])->where('activo', true)->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer la contraseña.');
    }
}
