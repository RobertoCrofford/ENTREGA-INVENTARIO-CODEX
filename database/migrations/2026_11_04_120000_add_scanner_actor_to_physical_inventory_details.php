<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventarios_fisicos_detalle', 'escaneado_por')) {
            Schema::table('inventarios_fisicos_detalle', function (Blueprint $table): void {
                $table->foreignId('escaneado_por')->nullable()->after('resultado')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventarios_fisicos_detalle', 'escaneado_por')) {
            Schema::table('inventarios_fisicos_detalle', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('escaneado_por');
            });
        }
    }
};
