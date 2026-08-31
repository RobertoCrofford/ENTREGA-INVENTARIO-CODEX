<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reparaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('tecnico_id')->constrained('users')->restrictOnDelete();
            $table->enum('prioridad', ['baja', 'media', 'alta', 'critica'])->default('media');
            $table->text('falla_reportada');
            $table->enum('estado', ['abierta', 'en_proceso', 'resuelta'])->default('abierta');
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['estado', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparaciones');
    }
};
