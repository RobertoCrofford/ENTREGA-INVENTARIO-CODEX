<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table): void {
            $table->string('uso', 30)->default('sin_definir')->after('estado_activo_id');
            $table->index('uso');
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table): void {
            $table->dropIndex(['uso']);
            $table->dropColumn('uso');
        });
    }
};
