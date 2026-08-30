<?php

namespace App\Services;

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
                return $existing->id;
            }
            $this->validateShape($data);
            $product = Product::query()->where('activo', true)->findOrFail($data['producto_id']);
            $origin = $data['origen_id'] ?? null;
            $destination = $data['destino_id'] ?? null;
            foreach (collect([$origin, $destination])->filter()->unique() as $locationId) {
                if (! DB::table('ubicaciones')->where('id', $locationId)->where('activo', true)->exists()) {
                    throw ValidationException::withMessages(['ubicacion' => 'El origen y destino deben ser ubicaciones activas.']);
                }
            }
            $reason = ucfirst($data['tipo']).' registrada desde el sistema.';
            $id = DB::table('movimientos_inventario')->insertGetId(['folio' => 'MOV-'.now()->format('YmdHis').'-'.str_pad((string) (DB::table('movimientos_inventario')->max('id') + 1), 6, '0', STR_PAD_LEFT), 'tipo' => $data['tipo'], 'estado' => 'borrador', 'origen_id' => $origin, 'destino_id' => $destination, 'motivo' => $reason, 'observacion' => $data['observacion'] ?? null, 'receptor_tipo' => null, 'receptor_nombre' => null, 'receptor_email' => null, 'receptor_departamento' => null, 'idempotency_key' => $key, 'creado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
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
        if (in_array($type, ['salida', 'traslado', 'baja'], true) && (! $origin || ! ($stock[$origin] ?? null) || $stock[$origin]->cantidad < $quantity)) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente.']);
        }
        if (in_array($type, ['salida', 'traslado', 'baja'], true)) {
            $this->change($stock[$origin], -$quantity);
        }
        if (in_array($type, ['entrada', 'devolucion', 'traslado'], true)) {
            $this->change($stock[$destination] ?? null, $quantity, $productId, $destination);
        }
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
