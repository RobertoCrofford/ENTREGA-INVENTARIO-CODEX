<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('codigo', 'invitado')->update([
            'nombre' => 'Apoyo técnico',
            'descripcion' => 'Apoyo operativo al equipo técnico sin permisos de aprobación ni administración.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('codigo', 'invitado')->update([
            'nombre' => 'Invitado',
            'descripcion' => 'Consulta de información autorizada.',
            'updated_at' => now(),
        ]);
    }
};
