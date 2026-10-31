<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->string('receptor_cargo')->nullable()->after('receptor_nombre');
            $table->foreignId('receptor_confirmado_por')->nullable()->after('publicado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('receptor_confirmado_at')->nullable()->after('publicado_at');
            $table->text('confirmacion_observacion')->nullable()->after('receptor_confirmado_at');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receptor_confirmado_por');
            $table->dropColumn(['receptor_cargo', 'receptor_confirmado_at', 'confirmacion_observacion']);
        });
    }
};
