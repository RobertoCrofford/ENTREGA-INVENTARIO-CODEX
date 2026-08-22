<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateFirstSuperadmin extends Command
{
    protected $signature = 'inventario:crear-superadmin {nombre} {email} {usuario}';

    protected $description = 'Crea el primer superadministrador del sistema';

    public function handle(): int
    {
        $password = $this->secret('Contraseña temporal (mínimo 12 caracteres, mayúscula y número)');
        $data = [
            'name' => $this->argument('nombre'), 'email' => $this->argument('email'),
            'username' => $this->argument('usuario'), 'password' => $password,
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', 'unique:users,username'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }
        $role = Role::query()->firstWhere('codigo', Role::SUPERADMIN);
        if (! $role) {
            $this->components->error('Primero ejecute las migraciones y los seeders de roles.');

            return self::FAILURE;
        }
        User::query()->create([
            'rol_id' => $role->id, 'name' => $data['name'], 'email' => $data['email'], 'username' => $data['username'],
            'password' => Hash::make($password), 'activo' => true, 'debe_cambiar_password' => true,
        ]);
        $this->components->info('Superadministrador creado. Deberá cambiar la contraseña al iniciar sesión.');

        return self::SUCCESS;
    }
}
