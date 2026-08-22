<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_activo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->boolean('es_pc')->default(false);
            $table->boolean('activo')->default(true);
        });
        Schema::create('estados_activo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('tipo_activo_id')->constrained('tipos_activo')->restrictOnDelete();
            $table->foreignId('estado_activo_id')->constrained('estados_activo')->restrictOnDelete();
            $table->foreignId('codigo_escaneo_id')->nullable()->unique()->constrained('codigos_escaneo')->restrictOnDelete();
            $table->foreignId('ubicacion_actual_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->string('activo_fijo')->unique();
            $table->string('numero_serie')->nullable()->index();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->decimal('costo_neto_actual', 14, 2)->nullable();
            $table->string('responsable_nombre')->nullable();
            $table->string('responsable_email')->nullable();
            $table->string('responsable_departamento')->nullable();
            $table->timestamp('asignacion_vence_at')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['tipo_activo_id', 'estado_activo_id']);
        });
        Schema::create('eventos_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->foreignId('estado_origen_id')->nullable()->constrained('estados_activo')->restrictOnDelete();
            $table->foreignId('estado_destino_id')->nullable()->constrained('estados_activo')->restrictOnDelete();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->json('responsable_origen_json')->nullable();
            $table->json('responsable_destino_json')->nullable();
            $table->string('responsable_reparacion')->nullable();
            $table->text('resultado_reparacion')->nullable();
            $table->text('motivo')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('ejecutado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('ocurrido_at');
            $table->index(['activo_id', 'ocurrido_at']);
        });
        Schema::create('especificaciones_pc', function (Blueprint $table) {
            $table->foreignId('activo_id')->primary()->constrained('activos')->restrictOnDelete();
            $table->string('procesador');
            $table->unsignedInteger('ram_total_gb')->nullable();
            $table->string('tipo_ram')->nullable();
            $table->unsignedInteger('almacenamiento_total_gb')->nullable();
            $table->string('tipo_almacenamiento')->nullable();
            $table->string('sistema_operativo')->nullable();
            $table->string('arquitectura')->nullable();
            $table->string('placa_madre')->nullable();
            $table->string('gpu')->nullable();
            $table->string('direccion_mac')->nullable();
            $table->string('nombre_red')->nullable();
            $table->string('tpm')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
        Schema::create('componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pc_actual_id')->nullable()->constrained('activos')->restrictOnDelete();
            $table->foreignId('ubicacion_actual_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->string('estado', 30);
            $table->string('activo_fijo')->nullable()->unique();
            $table->string('numero_serie')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('capacidad')->nullable();
            $table->string('unidad')->nullable();
            $table->json('detalle_json')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
        Schema::create('eventos_componente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->foreignId('pc_origen_id')->nullable()->constrained('activos')->restrictOnDelete();
            $table->foreignId('pc_destino_id')->nullable()->constrained('activos')->restrictOnDelete();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->text('motivo');
            $table->text('observacion')->nullable();
            $table->foreignId('ejecutado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('ocurrido_at');
            $table->index(['componente_id', 'ocurrido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_componente');
        Schema::dropIfExists('componentes');
        Schema::dropIfExists('especificaciones_pc');
        Schema::dropIfExists('eventos_activo');
        Schema::dropIfExists('activos');
        Schema::dropIfExists('estados_activo');
        Schema::dropIfExists('tipos_activo');
    }
};
