<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos_auditoria', function (Blueprint $table) {
            $table->string('nombre', 120)->nullable()->after('ruta');
        });
    }

    public function down(): void
    {
        Schema::table('archivos_auditoria', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
};
