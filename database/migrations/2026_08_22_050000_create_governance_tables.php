<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_baja_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->string('estado', 20)->default('borrador');
            $table->text('motivo')->nullable();
            $table->text('diagnostico')->nullable();
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('solicitado_at')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->text('comentario_resolucion')->nullable();
            $table->boolean('autoaprobacion_excepcional')->default(false);
            $table->text('justificacion_autoaprobacion')->nullable();
            $table->decimal('costo_neto_snapshot', 14, 2)->nullable();
            $table->boolean('genera_acta_pdf')->default(false);
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->string('estado_pdf', 20)->default('no_requerido');
            $table->timestamps();
            $table->index(['estado', 'solicitado_at']);
        });
        Schema::create('solicitudes_baja_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->string('estado', 20)->default('borrador');
            $table->text('motivo');
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('solicitado_at')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->text('comentario_resolucion')->nullable();
            $table->boolean('autoaprobacion_excepcional')->default(false);
            $table->text('justificacion_autoaprobacion')->nullable();
            $table->foreignId('movimiento_id')->nullable()->unique()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->string('estado_pdf', 20)->default('pendiente');
            $table->timestamps();
        });
        Schema::create('solicitudes_baja_stock_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_baja_stock')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('costo_neto_snapshot', 14, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->unique(['solicitud_id', 'producto_id']);
        });
        Schema::create('archivos_auditoria', function (Blueprint $table) {
            $table->id();
            $table->timestamp('periodo_desde');
            $table->timestamp('periodo_hasta');
            $table->string('ruta');
            $table->string('sha256', 64);
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('creado_at');
        });
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('accion', 80);
            $table->string('entidad_tipo', 80);
            $table->string('entidad_id')->nullable();
            $table->json('antes_json')->nullable();
            $table->json('despues_json')->nullable();
            $table->text('motivo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('correlation_id');
            $table->string('resultado', 20);
            $table->foreignId('archivo_auditoria_id')->nullable()->constrained('archivos_auditoria')->restrictOnDelete();
            $table->timestamp('archivado_at')->nullable();
            $table->timestamp('creado_at');
            $table->index(['creado_at', 'usuario_id']);
            $table->index(['entidad_tipo', 'entidad_id']);
        });
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->string('archivo_nombre');
            $table->string('archivo_sha256', 64);
            $table->string('estado', 20);
            $table->unsignedInteger('filas_total')->default(0);
            $table->unsignedInteger('filas_exitosas')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->foreignId('ejecutado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('iniciado_at');
            $table->timestamp('finalizado_at')->nullable();
        });
        Schema::create('errores_importacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones')->restrictOnDelete();
            $table->unsignedInteger('fila');
            $table->string('campo')->nullable();
            $table->string('codigo_error', 80);
            $table->text('mensaje');
            $table->json('datos_json')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['errores_importacion', 'importaciones', 'bitacora', 'archivos_auditoria', 'solicitudes_baja_stock_detalle', 'solicitudes_baja_stock', 'solicitudes_baja_activo'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
