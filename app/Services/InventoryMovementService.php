<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    /** @param array<string, mixed> $data */
    public function createAndPublish(array $data, User $actor): int
    {
        return DB::transaction(function () use ($data, $actor) {
            $key = $data['idempotency_key'] ?? (string) Str::uuid();
            if ($existing = DB::table('movimientos_inventario')->where('idempotency_key', $key)->lockForUpdate()->first()) {
                if ((int) $existing->creado_por !== (int) $actor->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave de operación ya pertenece a otro usuario.']);
                }

                return $existing->id;
            }
            $this->validateShape($data);
            if (($data['item_type'] ?? 'producto') === 'activo') {
                return $this->transferAsset($data, $actor, $key);
            }
            $product = Product::query()->where('activo', true)->findOrFail($data['producto_id']);
            $origin = $data['origen_id'] ?? null;
            $destination = $data['destino_id'] ?? null;
            foreach (collect([$origin, $destination])->filter()->unique() as $locationId) {
                if (! DB::table('ubicaciones')->where('id', $locationId)->where('tipo', 'bodega')->where('activo', true)->exists()) {
                    throw ValidationException::withMessages(['ubicacion' => 'El origen y destino de productos deben ser bodegas activas.']);
                }
            }
            $reason = ucfirst($data['tipo']).' registrada desde el sistema.';
            $id = DB::table('movimientos_inventario')->insertGetId(['folio' => 'MOV-'.$key, 'tipo' => $data['tipo'], 'estado' => 'borrador', 'origen_id' => $origin, 'destino_id' => $destination, 'motivo' => $reason, 'observacion' => $data['observacion'] ?? null, 'receptor_tipo' => null, 'receptor_nombre' => null, 'receptor_email' => null, 'receptor_departamento' => null, 'idempotency_key' => $key, 'creado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('movimientos_detalle')->insert(['movimiento_id' => $id, 'producto_id' => $product->id, 'cantidad' => $data['cantidad'], 'costo_neto_snapshot' => $product->costo_neto_actual]);
            $this->apply($data['tipo'], $product->id, $data['cantidad'], $origin, $destination);
            DB::table('movimientos_inventario')->where('id', $id)->update(['estado' => 'publicado', 'publicado_por' => $actor->id, 'publicado_at' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record($actor, 'publicar', 'movimiento_inventario', $id, reason: $reason);

            return $id;
        });
    }

    private function apply(string $type, int $productId, int $quantity, ?int $origin, ?int $destination): void
    {
        $stock = [];
        foreach (collect([$origin, $destination])->filter()->unique()->sort() as $warehouse) {
            $stock[$warehouse] = Stock::query()->where('producto_id', $productId)->where('bodega_id', $warehouse)->lockForUpdate()->first();
        }
        if (in_array($type, ['salida', 'traslado'], true) && (! $origin || ! ($stock[$origin] ?? null) || $stock[$origin]->cantidad < $quantity)) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
        }
        if (in_array($type, ['salida', 'traslado'], true)) {
            $this->change($stock[$origin], -$quantity);
        }
        if (in_array($type, ['entrada', 'devolucion', 'traslado'], true)) {
            $this->change($stock[$destination] ?? null, $quantity, $productId, $destination);
        }
    }

    /** @param array<string, mixed> $data */
    private function transferAsset(array $data, User $actor, string $key): int
    {
        if ($data['tipo'] !== 'traslado') {
            throw ValidationException::withMessages(['tipo' => 'Un activo fijo solo se puede registrar como traslado.']);
        }
        if (empty($data['activo_id'])) {
            throw ValidationException::withMessages(['activo_id' => 'Selecciona un activo fijo.']);
        }
        if (empty($data['destino_id'])) {
            throw ValidationException::withMessages(['destino_id' => 'Selecciona la ubicación de destino del activo.']);
        }

        $asset = Asset::query()->with('status')->lockForUpdate()->findOrFail($data['activo_id']);
        if ($asset->status?->codigo === 'dado_baja') {
            throw ValidationException::withMessages(['activo_id' => 'Un activo dado de baja no se puede trasladar.']);
        }
        $origin = $asset->ubicacion_actual_id;
        if (! $origin) {
            throw ValidationException::withMessages(['activo_id' => 'El activo no tiene una ubicación actual registrada.']);
        }
        if ((int) $origin === (int) $data['destino_id']) {
            throw ValidationException::withMessages(['destino_id' => 'La ubicación de destino debe ser diferente de la actual.']);
        }
        if (! DB::table('ubicaciones')->where('id', $data['destino_id'])->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['destino_id' => 'La ubicación de destino debe estar activa.']);
        }

        $reason = 'Traslado de activo fijo registrado desde el sistema.';
        $id = DB::table('movimientos_inventario')->insertGetId(['folio' => 'MOV-'.$key, 'tipo' => 'traslado', 'estado' => 'borrador', 'origen_id' => $origin, 'destino_id' => $data['destino_id'], 'motivo' => $reason, 'observacion' => $data['observacion'] ?? null, 'idempotency_key' => $key, 'creado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('movimientos_detalle')->insert(['movimiento_id' => $id, 'activo_id' => $asset->id, 'cantidad' => 1, 'costo_neto_snapshot' => $asset->costo_neto_actual ?? 0]);
        $asset->update(['ubicacion_actual_id' => $data['destino_id']]);
        DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'traslado', 'estado_origen_id' => $asset->estado_activo_id, 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_origen_id' => $origin, 'ubicacion_destino_id' => $data['destino_id'], 'motivo' => $reason, 'ejecutado_por' => $actor->id, 'ocurrido_at' => now()]);
        DB::table('movimientos_inventario')->where('id', $id)->update(['estado' => 'publicado', 'publicado_por' => $actor->id, 'publicado_at' => now(), 'updated_at' => now()]);
        app(AuditService::class)->record($actor, 'trasladar', 'activo', $asset->id, reason: $reason);

        return $id;
    }

    private function change(?Stock $stock, int $delta, ?int $product = null, ?int $warehouse = null): void
    {
        if (! $stock) {
            Stock::query()->create(['producto_id' => $product, 'bodega_id' => $warehouse, 'cantidad' => $delta, 'stock_minimo' => 0, 'version' => 1, 'activo' => true]);

            return;
        } $stock->update(['cantidad' => $stock->cantidad + $delta, 'version' => $stock->version + 1]);
    }

    /** @param array<string, mixed> $data */
    private function validateShape(array $data): void
    {
        if (($data['item_type'] ?? 'producto') === 'activo') {
            return;
        }
        if (empty($data['producto_id'])) {
            throw ValidationException::withMessages(['producto_id' => 'Selecciona un producto.']);
        }
        if (($data['cantidad'] ?? 0) < 1) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor que cero.']);
        }
        if (in_array($data['tipo'], ['entrada', 'devolucion'], true) && empty($data['destino_id'])) {
            throw ValidationException::withMessages(['destino_id' => 'Se requiere una ubicación de destino.']);
        }
        if (in_array($data['tipo'], ['salida', 'traslado', 'baja'], true) && empty($data['origen_id'])) {
            throw ValidationException::withMessages(['origen_id' => 'Se requiere una ubicación de origen.']);
        }
        if ($data['tipo'] === 'traslado' && $data['origen_id'] === $data['destino_id']) {
            throw ValidationException::withMessages(['destino_id' => 'El destino debe ser diferente del origen.']);
        }
    }
}
