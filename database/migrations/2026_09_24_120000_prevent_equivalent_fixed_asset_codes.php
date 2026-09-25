<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE activos ADD activo_fijo_normalizado VARCHAR(40) GENERATED ALWAYS AS (CASE WHEN activo_fijo REGEXP '^[0-9]{6,}$' THEN COALESCE(NULLIF(TRIM(TRAILING '0' FROM activo_fijo), ''), '0') ELSE NULL END) STORED");
        } else {
            DB::statement("ALTER TABLE activos ADD COLUMN activo_fijo_normalizado VARCHAR(40) GENERATED ALWAYS AS (CASE WHEN activo_fijo GLOB '[0-9]*' AND activo_fijo NOT GLOB '*[^0-9]*' AND length(activo_fijo) >= 6 THEN CASE WHEN rtrim(activo_fijo, '0') = '' THEN '0' ELSE rtrim(activo_fijo, '0') END ELSE NULL END) VIRTUAL");
        }

        DB::statement('CREATE UNIQUE INDEX activos_activo_fijo_normalizado_unique ON activos (activo_fijo_normalizado)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX activos_activo_fijo_normalizado_unique');

            return;
        }

        DB::statement('DROP INDEX activos_activo_fijo_normalizado_unique ON activos');
        DB::statement('ALTER TABLE activos DROP COLUMN activo_fijo_normalizado');
    }
};
