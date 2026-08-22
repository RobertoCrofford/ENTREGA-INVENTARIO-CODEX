<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rol_id')->after('id')->constrained('roles')->restrictOnDelete();
            $table->string('username', 80)->unique()->after('email');
            $table->boolean('activo')->default(true)->after('password');
            $table->boolean('debe_cambiar_password')->default(true)->after('activo');
            $table->unsignedSmallInteger('intentos_fallidos')->default(0)->after('debe_cambiar_password');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('bloqueado_hasta');
            $table->timestamp('password_cambiado_at')->nullable()->after('ultimo_acceso_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropUnique(['username']);
            $table->dropColumn(['rol_id', 'username', 'activo', 'debe_cambiar_password', 'intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso_at', 'password_cambiado_at']);
        });
    }
};
