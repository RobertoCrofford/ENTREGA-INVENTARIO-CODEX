<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('codigo', 40);
            $table->string('nombre');
            $table->string('edificio')->nullable();
            $table->string('piso', 30)->nullable();
            $table->unsignedInteger('capacidad')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['sede_id', 'codigo']);
            $table->index(['sede_id', 'tipo', 'activo']);
        });
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('codigos_escaneo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 13)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('codigo_escaneo_id')->nullable()->unique()->constrained('codigos_escaneo')->restrictOnDelete();
            $table->string('codigo_interno', 40)->unique();
            $table->string('numero_parte');
            $table->string('nombre');
            $table->string('marca')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('costo_neto_actual', 14, 2);
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['categoria_id', 'activo']);
        });
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(0);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['producto_id', 'bodega_id']);
            $table->index(['bodega_id', 'cantidad']);
        });
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->string('tipo', 20);
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('origen_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('destino_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('movimiento_original_id')->nullable()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->foreignId('movimiento_referencia_id')->nullable()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->string('receptor_tipo', 30)->nullable();
            $table->string('receptor_nombre')->nullable();
            $table->string('receptor_email')->nullable();
            $table->string('receptor_departamento')->nullable();
            $table->text('motivo')->nullable();
            $table->text('observacion')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
            $table->index(['tipo', 'estado', 'publicado_at']);
        });
        Schema::create('movimientos_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos_inventario')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('costo_neto_snapshot', 14, 2);
            $table->text('observacion')->nullable();
            $table->unique(['movimiento_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_detalle');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('existencias');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('codigos_escaneo');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('ubicaciones');
        Schema::dropIfExists('sedes');
    }
};
