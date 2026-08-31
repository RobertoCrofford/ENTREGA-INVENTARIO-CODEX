<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'debe_cambiar_password' => false,
            'password_cambiado_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $audit->record($request->user(), 'cambiar_password', 'usuario', $request->user()->id, reason: 'Cambio de contraseña por el propio usuario.', notify: false);

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada.');
    }
}
