<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventarios_fisicos_esperados')) {
            Schema::create('inventarios_fisicos_esperados', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventario_fisico_id')->constrained('inventarios_fisicos')->cascadeOnDelete();
                $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
                $table->string('activo_fijo_snapshot', 40);
                $table->string('numero_serie_snapshot', 40)->nullable();
                $table->string('marca_snapshot', 80)->nullable();
                $table->string('modelo_snapshot', 80)->nullable();
                $table->unique(['inventario_fisico_id', 'activo_id'], 'inv_fisico_esperado_activo_unique');
            });
        }

        // Existing reviews did not preserve their original expected set. Capture
        // the best available baseline at migration time so they remain usable.
        DB::table('inventarios_fisicos')->orderBy('id')->each(function (object $inventory): void {
            $rows = DB::table('activos')
                ->join('estados_activo', 'estados_activo.id', '=', 'activos.estado_activo_id')
                ->where('activos.ubicacion_actual_id', $inventory->ubicacion_id)
                ->where('estados_activo.codigo', '!=', 'dado_baja')
                ->get(['activos.id', 'activos.activo_fijo', 'activos.numero_serie', 'activos.marca', 'activos.modelo'])
                ->map(fn (object $asset): array => [
                    'inventario_fisico_id' => $inventory->id,
                    'activo_id' => $asset->id,
                    'activo_fijo_snapshot' => $asset->activo_fijo,
                    'numero_serie_snapshot' => $asset->numero_serie,
                    'marca_snapshot' => $asset->marca,
                    'modelo_snapshot' => $asset->modelo,
                ])->all();

            if ($rows) {
                DB::table('inventarios_fisicos_esperados')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_fisicos_esperados');
    }
};
