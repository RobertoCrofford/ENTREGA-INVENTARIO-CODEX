<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ubicaciones', 'disponible_inventario_fisico')) {
            Schema::table('ubicaciones', function (Blueprint $table) {
                $table->boolean('disponible_inventario_fisico')->default(false)->after('activo');
            });
        }

        // Keep only the three base institutional spaces available until the
        // administrator registers or validates the rest of the locations.
        DB::table('ubicaciones')->whereIn('codigo', ['BOD-MAIPU', 'SSDD-MAIPU', 'SALA-MAIPU'])
            ->update(['disponible_inventario_fisico' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('ubicaciones', 'disponible_inventario_fisico')) {
            Schema::table('ubicaciones', function (Blueprint $table) {
                $table->dropColumn('disponible_inventario_fisico');
            });
        }
    }
};
