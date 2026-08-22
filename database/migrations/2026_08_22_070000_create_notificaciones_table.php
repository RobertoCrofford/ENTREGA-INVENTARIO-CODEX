<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('titulo', 120);
            $table->text('mensaje');
            $table->string('url')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamp('creado_at');
            $table->index(['usuario_id', 'leido_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
