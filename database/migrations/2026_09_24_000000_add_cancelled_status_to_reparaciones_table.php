<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reparaciones MODIFY estado ENUM('abierta', 'en_proceso', 'resuelta', 'cancelada') NOT NULL DEFAULT 'abierta'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('reparaciones')->where('estado', 'cancelada')->update(['estado' => 'resuelta']);
            DB::statement("ALTER TABLE reparaciones MODIFY estado ENUM('abierta', 'en_proceso', 'resuelta') NOT NULL DEFAULT 'abierta'");
        }
    }
};
