<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codigos_escaneo', function (Blueprint $table) {
            $table->string('codigo', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('codigos_escaneo', function (Blueprint $table) {
            $table->string('codigo', 13)->change();
        });
    }
};
