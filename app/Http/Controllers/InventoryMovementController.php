<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Product;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $movements = DB::table('movimientos_inventario as movimiento')
            ->join('movimientos_detalle as detalle', 'detalle.movimiento_id', '=', 'movimiento.id')
            ->leftJoin('productos as producto', 'producto.id', '=', 'detalle.producto_id')
            ->leftJoin('activos as activo', 'activo.id', '=', 'detalle.activo_id')
            ->join('users as creador', 'creador.id', '=', 'movimiento.creado_por')
            ->leftJoin('users as publicador', 'publicador.id', '=', 'movimiento.publicado_por')
            ->when($request->q, fn ($query, $term) => $query->whereAny(['movimiento.folio', 'producto.codigo_interno', 'producto.nombre', 'activo.activo_fijo', 'activo.numero_serie', 'activo.marca', 'activo.modelo', 'movimiento.motivo', 'creador.name'], 'like', "%{$term}%"))
            ->selectRaw("movimiento.folio, movimiento.tipo, movimiento.estado, movimiento.motivo, movimiento.publicado_at, movimiento.created_at, COALESCE(producto.codigo_interno, activo.activo_fijo) as item_codigo, COALESCE(producto.nombre, CONCAT_WS(' ', activo.marca, activo.modelo)) as item_nombre, detalle.cantidad, creador.name as creado_por_nombre, publicador.name as publicado_por_nombre")
            ->orderByDesc('movimiento.id')->paginate(20)->withQueryString();

        return view('movements.index', compact('movements'));
    }

    public function create(): View
    {
        Gate::authorize('gestionar-inventario');

        $products = Product::query()->with('categoria')->where('activo', true)->orderBy('nombre')->get();
        $assets = Asset::query()->with(['type', 'location'])->whereHas('status', fn ($query) => $query->where('codigo', '!=', 'dado_baja'))->orderBy('activo_fijo')->get();

        return view('movements.form', [
            'products' => $products,
            'assets' => $assets,
            'defaultItemType' => $products->isEmpty() && $assets->isNotEmpty() ? 'activo' : 'producto',
            'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, InventoryMovementService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['item_type' => ['required', Rule::in(['producto', 'activo'])], 'tipo' => ['required', Rule::in(['entrada', 'salida', 'devolucion', 'traslado'])], 'producto_id' => ['nullable', 'exists:productos,id'], 'activo_id' => ['nullable', 'exists:activos,id'], 'cantidad' => ['required', 'integer', 'min:1', 'max:100000'], 'origen_id' => ['nullable', 'exists:ubicaciones,id'], 'destino_id' => ['nullable', 'exists:ubicaciones,id'], 'observacion' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']]);
        $id = $service->createAndPublish($data, $request->user());

        return redirect()->route('movements.index')->with('success', "Movimiento #{$id} publicado.");
    }
}
