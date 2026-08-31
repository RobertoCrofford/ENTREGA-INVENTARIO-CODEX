<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('administrar-usuarios');

        return view('users.index', ['users' => User::query()->with('rol')->orderBy('name')->paginate(15)]);
    }

    public function create(): View
    {
        Gate::authorize('administrar-usuarios');

        return view('users.form', ['user' => new User, 'roles' => Role::query()->where('activo', true)->orderBy('nombre')->get()]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('administrar-usuarios');
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        $data['debe_cambiar_password'] = true;
        $user = User::query()->create($data);
        $audit->record($request->user(), 'crear', 'usuario', $user->id, after: $user->toArray(), reason: 'Creación de cuenta local.', notify: false);

        return redirect()->route('users.index')->with('success', 'Usuario creado; deberá cambiar su contraseña al ingresar.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('administrar-usuarios');

        return view('users.form', ['user' => $user, 'roles' => Role::query()->where('activo', true)->orderBy('nombre')->get()]);
    }

    public function update(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        Gate::authorize('administrar-usuarios');
        $data = $this->validated($request, $user);
        $before = $user->toArray();
        $passwordChanged = ! blank($data['password']);
        if (! $passwordChanged) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
            $data['debe_cambiar_password'] = true;
        }
        if ($user->tieneRol(Role::SUPERADMIN) && ($data['rol_id'] != $user->rol_id || ! $data['activo']) && User::query()->where('rol_id', $user->rol_id)->where('activo', true)->count() === 1) {
            return back()->withErrors(['rol_id' => 'Debe existir al menos un superadministrador activo.'])->withInput();
        }
        $user->update($data);
        if (! $user->activo || $passwordChanged) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $audit->record($request->user(), 'actualizar', 'usuario', $user->id, $before, $user->fresh()->toArray(), 'Actualización administrativa de usuario.', notify: false);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function resetPassword(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->tieneRol(Role::SUPERADMIN), 403);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'debe_cambiar_password' => true,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'password_cambiado_at' => null,
            'remember_token' => Str::random(60),
        ])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $audit->record($request->user(), 'restablecer_password', 'usuario', $user->id, reason: 'Restablecimiento administrativo de contraseña.', notify: false);

        return back()->with('success', 'Contraseña temporal restablecida; el usuario deberá cambiarla al ingresar.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[\pL .\'-]+$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('users', 'username')->ignore($user)],
            'rol_id' => ['required', Rule::exists('roles', 'id')->where('activo', true)],
            'activo' => ['required', 'boolean'],
            'password' => $user ? ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()] : ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
    }
}
