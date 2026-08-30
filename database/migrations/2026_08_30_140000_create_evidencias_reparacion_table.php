<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias_reparacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparacion_id')->constrained('reparaciones')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre_original', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('tamano');
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('creado_at')->useCurrent();
            $table->index('reparacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_reparacion');
    }
};
