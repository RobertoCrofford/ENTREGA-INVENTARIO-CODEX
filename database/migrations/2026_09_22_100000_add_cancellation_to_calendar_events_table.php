<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->string('estado', 20)->default('programado')->after('archivo_origen');
            $table->string('motivo_cancelacion', 60)->nullable()->after('estado');
            $table->text('observacion_cancelacion')->nullable()->after('motivo_cancelacion');
            $table->timestamp('cancelado_at')->nullable()->after('observacion_cancelacion');
            $table->foreignId('cancelado_por')->nullable()->after('cancelado_at')->constrained('users')->restrictOnDelete();
            $table->index(['estado', 'inicio_at']);
        });
    }

    public function down(): void
    {
        Schema::table('eventos_calendario', function (Blueprint $table): void {
            $table->dropForeign(['cancelado_por']);
            $table->dropIndex(['estado', 'inicio_at']);
            $table->dropColumn(['estado', 'motivo_cancelacion', 'observacion_cancelacion', 'cancelado_at', 'cancelado_por']);
        });
    }
};
