<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'activos_activo_fijo_normalizado_unique';

    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        $hasDuplicates = DB::table('activos')->selectRaw('1')
            ->whereNotNull('activo_fijo_normalizado')
            ->groupBy('activo_fijo_normalizado')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get()
            ->isNotEmpty();

        if ($hasDuplicates) {
            throw new RuntimeException('Existen códigos de activo equivalentes duplicados. Corrígelos antes de activar la restricción única.');
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON activos (activo_fijo_normalizado)');
    }

    public function down(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX '.self::INDEX);

            return;
        }

        DB::statement('DROP INDEX '.self::INDEX.' ON activos');
    }

    private function indexExists(): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'activos')
                ->where('index_name', self::INDEX)
                ->exists();
        }

        return collect(DB::select("PRAGMA index_list('activos')"))
            ->contains(fn (object $index) => $index->name === self::INDEX);
    }
};
