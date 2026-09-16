<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_calendario', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();
            $table->string('lugar', 160)->nullable();
            $table->dateTime('inicio_at');
            $table->dateTime('termino_at')->nullable();
            $table->boolean('todo_el_dia')->default(true);
            $table->string('origen', 20)->default('manual');
            $table->string('archivo_origen')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['inicio_at', 'termino_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_calendario');
    }
};
