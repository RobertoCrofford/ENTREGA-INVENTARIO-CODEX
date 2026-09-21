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

class AdminRecoveryController extends Controller
{
    public function create(): View
    {
        $this->ensureRecoveryIsAvailable();

        return view('auth.admin-recovery');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureRecoveryIsAvailable();

        $data = $request->validate([
            'recovery_code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120', "regex:/^[\\pL .'-]+$/u"],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('users', 'username')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if (! hash_equals((string) config('app.admin_recovery_code'), $data['recovery_code'])) {
            return back()->withInput($request->except(['password', 'password_confirmation', 'recovery_code']))
                ->withErrors(['recovery_code' => 'El código de recuperación no es válido.']);
        }

        DB::transaction(function () use ($data): void {
            abort_if($this->hasSuperadministrator(true), 404);

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

        return redirect()->route('login')->with('status', 'Superadministrador recuperado. Ya puedes iniciar sesión.');
    }

    private function ensureRecoveryIsAvailable(): void
    {
        abort_unless(User::query()->exists() && ! $this->hasSuperadministrator() && filled(config('app.admin_recovery_code')), 404);
    }

    private function hasSuperadministrator(bool $lock = false): bool
    {
        $query = User::query()->whereHas('rol', fn ($role) => $role->where('codigo', Role::SUPERADMIN));

        return ($lock ? $query->lockForUpdate() : $query)->exists();
    }
}
