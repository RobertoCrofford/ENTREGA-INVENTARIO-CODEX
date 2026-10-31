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
    /** @param array<string,mixed> $data @param array<int,array<string,mixed>> $items */
    public function createGroupTransfer(array $data, array $items, User $actor): int
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $origin = (int) $data['origen_id']; $destination = (int) $data['destino_id'];
            if (! DB::table('ubicaciones')->where('id', $origin)->where('activo', true)->exists() || ! DB::table('ubicaciones')->where('id', $destination)->where('activo', true)->exists()) throw ValidationException::withMessages(['ubicacion' => 'Las ubicaciones deben estar activas.']);
            $seen = []; $key = (string) Str::uuid();
            $id = DB::table('movimientos_inventario')->insertGetId(['folio' => 'TMP-'.$key, 'tipo' => 'traslado', 'estado' => 'borrador', 'origen_id' => $origin, 'destino_id' => $destination, 'motivo' => 'Traslado grupal registrado desde el sistema.', 'observacion' => $data['observacion'] ?? null, 'receptor_tipo' => $data['receptor_nombre'] ? 'persona' : null, 'receptor_nombre' => $data['receptor_nombre'] ?? null, 'receptor_email' => $data['receptor_email'] ?? null, 'receptor_departamento' => $data['receptor_departamento'] ?? null, 'idempotency_key' => $key, 'creado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as $item) {
                $type = $item['type'] ?? ''; $itemId = (int) ($item['id'] ?? 0); $quantity = max(1, (int) ($item['quantity'] ?? 1)); $identity = "$type:$itemId";
                if (isset($seen[$identity])) throw ValidationException::withMessages(['items_json' => 'Un elemento aparece más de una vez en el carrito.']); $seen[$identity] = true;
                if ($type === 'product') {
                    if (! DB::table('ubicaciones')->where('id', $origin)->where('tipo', 'bodega')->exists() || ! DB::table('ubicaciones')->where('id', $destination)->where('tipo', 'bodega')->exists()) throw ValidationException::withMessages(['ubicacion' => 'Los productos solo se trasladan entre bodegas.']);
                    $product = Product::query()->where('activo', true)->lockForUpdate()->findOrFail($itemId);
                    $this->apply('traslado', $product->id, $quantity, $origin, $destination);
                    DB::table('movimientos_detalle')->insert(['movimiento_id' => $id, 'producto_id' => $product->id, 'cantidad' => $quantity, 'costo_neto_snapshot' => $product->costo_neto_actual]);
                } elseif ($type === 'asset') {
                    $asset = Asset::query()->with('status')->lockForUpdate()->findOrFail($itemId);
                    if ($asset->status?->codigo === 'dado_baja' || (int) $asset->ubicacion_actual_id !== $origin) throw ValidationException::withMessages(['items_json' => "El activo {$asset->activo_fijo} no está disponible en el origen seleccionado."]);
                    $asset->update(['ubicacion_actual_id' => $destination]);
                    DB::table('movimientos_detalle')->insert(['movimiento_id' => $id, 'activo_id' => $asset->id, 'cantidad' => 1, 'costo_neto_snapshot' => $asset->costo_neto_actual ?? 0]);
                    DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'traslado', 'estado_origen_id' => $asset->estado_activo_id, 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_origen_id' => $origin, 'ubicacion_destino_id' => $destination, 'motivo' => 'Traslado grupal.', 'ejecutado_por' => $actor->id, 'ocurrido_at' => now()]);
                } else throw ValidationException::withMessages(['items_json' => 'El carrito contiene un tipo de elemento inválido.']);
            }
            DB::table('movimientos_inventario')->where('id', $id)->update(['folio' => 'MOV-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT), 'estado' => 'publicado', 'publicado_por' => $actor->id, 'publicado_at' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record($actor, 'publicar_grupal', 'movimiento_inventario', $id, after: ['elementos' => count($items)], reason: 'Traslado grupal.');
            return $id;
        });
    }
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
            $id = DB::table('movimientos_inventario')->insertGetId($this->movementRecord($data, $actor, $key, $reason, $origin, $destination));
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
        $id = DB::table('movimientos_inventario')->insertGetId($this->movementRecord($data, $actor, $key, $reason, $origin, $data['destino_id']));
        DB::table('movimientos_detalle')->insert(['movimiento_id' => $id, 'activo_id' => $asset->id, 'cantidad' => 1, 'costo_neto_snapshot' => $asset->costo_neto_actual ?? 0]);
        $asset->update(['ubicacion_actual_id' => $data['destino_id']]);
        DB::table('eventos_activo')->insert(['activo_id' => $asset->id, 'tipo' => 'traslado', 'estado_origen_id' => $asset->estado_activo_id, 'estado_destino_id' => $asset->estado_activo_id, 'ubicacion_origen_id' => $origin, 'ubicacion_destino_id' => $data['destino_id'], 'motivo' => $reason, 'ejecutado_por' => $actor->id, 'ocurrido_at' => now()]);
        DB::table('movimientos_inventario')->where('id', $id)->update(['estado' => 'publicado', 'publicado_por' => $actor->id, 'publicado_at' => now(), 'updated_at' => now()]);
        app(AuditService::class)->record($actor, 'trasladar', 'activo', $asset->id, reason: $reason);

        return $id;
    }

    public function confirmReceipt(int $movementId, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($movementId, $actor, $note): void {
            $movement = DB::table('movimientos_inventario')->lockForUpdate()->find($movementId);
            abort_unless($movement, 404);
            if (! $movement->receptor_nombre) {
                throw ValidationException::withMessages(['receptor' => 'Registra primero el nombre de la persona o área que recibió el movimiento.']);
            }
            if ($movement->receptor_confirmado_at) {
                return;
            }
            DB::table('movimientos_inventario')->where('id', $movementId)->update([
                'receptor_confirmado_por' => $actor->id,
                'receptor_confirmado_at' => now(),
                'confirmacion_observacion' => $note,
                'updated_at' => now(),
            ]);
            app(AuditService::class)->record($actor, 'confirmar_recepcion', 'movimiento_inventario', $movementId, after: ['receptor_nombre' => $movement->receptor_nombre], reason: $note);
        });
    }

    /** @param array<string, mixed> $data */
    private function movementRecord(array $data, User $actor, string $key, string $reason, ?int $origin, ?int $destination): array
    {
        $confirmed = (bool) ($data['confirmar_recepcion'] ?? false) && ! empty($data['receptor_nombre']);

        return [
            'folio' => 'MOV-'.$key, 'tipo' => $data['tipo'], 'estado' => 'borrador',
            'origen_id' => $origin, 'destino_id' => $destination, 'motivo' => $reason,
            'observacion' => $data['observacion'] ?? null, 'receptor_tipo' => $data['receptor_tipo'] ?? null,
            'receptor_nombre' => $data['receptor_nombre'] ?? null, 'receptor_cargo' => $data['receptor_cargo'] ?? null,
            'receptor_email' => $data['receptor_email'] ?? null, 'receptor_departamento' => $data['receptor_departamento'] ?? null,
            'receptor_confirmado_por' => $confirmed ? $actor->id : null,
            'receptor_confirmado_at' => $confirmed ? now() : null,
            'confirmacion_observacion' => $confirmed ? ($data['confirmacion_observacion'] ?? null) : null,
            'idempotency_key' => $key, 'creado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ];
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
