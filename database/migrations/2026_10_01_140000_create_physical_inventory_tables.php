<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventarios_fisicos')) {
            Schema::create('inventarios_fisicos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
                $table->string('estado', 20)->default('en_curso');
                $table->foreignId('iniciado_por')->constrained('users')->restrictOnDelete();
                $table->timestamp('iniciado_at');
                $table->timestamp('finalizado_at')->nullable();
                $table->timestamps();
                $table->index(['ubicacion_id', 'estado']);
            });
        }

        if (! Schema::hasTable('inventarios_fisicos_detalle')) {
            Schema::create('inventarios_fisicos_detalle', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventario_fisico_id')->constrained('inventarios_fisicos')->cascadeOnDelete();
                $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
                $table->string('codigo_escaneado', 40);
                $table->string('resultado', 30);
                $table->timestamp('escaneado_at');
                $table->unique(['inventario_fisico_id', 'activo_id'], 'inv_fisico_detalle_activo_unique');
            });
        } elseif (! collect(Schema::getIndexes('inventarios_fisicos_detalle'))->contains('name', 'inv_fisico_detalle_activo_unique')) {
            Schema::table('inventarios_fisicos_detalle', function (Blueprint $table): void {
                $table->unique(['inventario_fisico_id', 'activo_id'], 'inv_fisico_detalle_activo_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_fisicos_detalle');
        Schema::dropIfExists('inventarios_fisicos');
    }
};
