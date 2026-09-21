<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InitialSetupController extends Controller
{
    public function create(): View
    {
        $this->ensureSetupIsAvailable();

        return view('auth.initial-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', "regex:/^[\\pL .'-]+$/u"],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('users', 'username')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        DB::transaction(function () use ($data): void {
            abort_if(User::query()->lockForUpdate()->exists(), 404);

            $role = Role::query()
                ->where('codigo', Role::SUPERADMIN)
                ->where('activo', true)
                ->firstOrFail();

            User::query()->create([
                'rol_id' => $role->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'activo' => true,
                'debe_cambiar_password' => false,
            ]);
        });

        return redirect()->route('login')->with('status', 'Cuenta de superadministrador creada. Ya puedes iniciar sesión.');
    }

    private function ensureSetupIsAvailable(): void
    {
        abort_if(User::query()->exists(), 404);
    }
}
