<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_detalle', function (Blueprint $table) {
            $table->foreignId('activo_id')->nullable()->after('producto_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('producto_id')->nullable()->change();
            $table->unique(['movimiento_id', 'activo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_detalle', function (Blueprint $table) {
            $table->dropUnique(['movimiento_id', 'activo_id']);
            $table->dropConstrainedForeignId('activo_id');
            $table->foreignId('producto_id')->nullable(false)->change();
        });
    }
};
