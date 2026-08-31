<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reparaciones', function (Blueprint $table) {
            $table->foreignId('estado_final_id')->nullable()->after('estado')->constrained('estados_activo')->restrictOnDelete();
            $table->text('resultado')->nullable()->after('estado_final_id');
            $table->timestamp('finalizado_at')->nullable()->after('resultado');
        });
    }

    public function down(): void
    {
        Schema::table('reparaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_final_id');
            $table->dropColumn(['resultado', 'finalizado_at']);
        });
    }
};
