<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->foreignId('origen_usuario_id')->nullable()->after('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('atendido_por')->nullable()->after('leido_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('atendido_at')->nullable()->after('atendido_por');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_usuario_id');
            $table->dropConstrainedForeignId('atendido_por');
            $table->dropColumn('atendido_at');
        });
    }
};
