<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            [Role::INVITADO, 'Invitado', 'Consulta de información autorizada.'],
            [Role::TECNICO, 'Técnico', 'Operación técnica e inventario.'],
            [Role::DIRECTOR_TECNICO, 'Director técnico', 'Supervisión y autorizaciones técnicas.'],
            [Role::SUPERADMIN, 'Superadministrador', 'Administración integral del sistema.'],
        ] as [$codigo, $nombre, $descripcion]) {
            Role::query()->updateOrCreate(['codigo' => $codigo], compact('nombre', 'descripcion'));
        }
        DB::table('sedes')->updateOrInsert(
            ['codigo' => 'MAIPU'],
            ['nombre' => 'Maipú', 'direccion' => null, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        $maipuId = DB::table('sedes')->where('codigo', 'MAIPU')->value('id');
        foreach ([
            ['BOD-MAIPU', 'Bodega', 'bodega'],
            ['SSDD-MAIPU', 'SSDD', 'ssdd'],
            ['SALA-MAIPU', 'Sala', 'sala'],
        ] as [$codigo, $nombre, $tipo]) {
            DB::table('ubicaciones')->updateOrInsert(
                ['sede_id' => $maipuId, 'codigo' => $codigo],
                ['tipo' => $tipo, 'nombre' => $nombre, 'edificio' => null, 'piso' => null, 'capacidad' => null, 'activo' => true, 'observacion' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        foreach ([['operativo', 'Operativo'], ['en_reparacion', 'En reparación'], ['no_operativo', 'No operativo'], ['no_localizado', 'No localizado'], ['dado_baja', 'Dado de baja']] as [$codigo, $nombre]) {
            DB::table('estados_activo')->updateOrInsert(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
        foreach ([['pc', 'PC', true], ['notebook', 'Notebook', false], ['monitor', 'Monitor', false], ['otro', 'Otro', false]] as [$codigo, $nombre, $esPc]) {
            DB::table('tipos_activo')->updateOrInsert(['codigo' => $codigo], ['nombre' => $nombre, 'es_pc' => $esPc, 'activo' => true]);
        }
        foreach ([
            ['Equipos computacionales', 'Equipos de escritorio, notebooks y servidores.'],
            ['Periféricos', 'Teclados, mouse, monitores y accesorios.'],
            ['Redes y conectividad', 'Switches, routers, puntos de acceso y cableado.'],
            ['Audio y video', 'Proyectores, cámaras, parlantes y dispositivos audiovisuales.'],
            ['Suministros', 'Insumos y consumibles para soporte técnico.'],
            ['Otros', 'Artículos que no pertenecen a las categorías anteriores.'],
        ] as [$nombre, $descripcion]) {
            DB::table('categorias')->updateOrInsert(['nombre' => $nombre], ['descripcion' => $descripcion, 'activo' => true, 'updated_at' => now()]);
        }
    }
}

