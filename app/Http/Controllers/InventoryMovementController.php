<?php

namespace App\Http\Controllers;

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
            ->join('productos as producto', 'producto.id', '=', 'detalle.producto_id')
            ->join('users as creador', 'creador.id', '=', 'movimiento.creado_por')
            ->leftJoin('users as publicador', 'publicador.id', '=', 'movimiento.publicado_por')
            ->when($request->q, fn ($query, $term) => $query->whereAny(['movimiento.folio', 'producto.codigo_interno', 'producto.nombre', 'movimiento.motivo', 'creador.name'], 'like', "%{$term}%"))
            ->select(['movimiento.folio', 'movimiento.tipo', 'movimiento.estado', 'movimiento.motivo', 'movimiento.publicado_at', 'movimiento.created_at', 'producto.codigo_interno', 'producto.nombre as producto_nombre', 'detalle.cantidad', 'creador.name as creado_por_nombre', 'publicador.name as publicado_por_nombre'])
            ->orderByDesc('movimiento.id')->paginate(20)->withQueryString();

        return view('movements.index', compact('movements'));
    }

    public function create(): View
    {
        Gate::authorize('gestionar-inventario');

        return view('movements.form', [
            'products' => Product::query()->with('categoria')->where('activo', true)->orderBy('nombre')->get(),
            'locations' => DB::table('ubicaciones')->where('activo', true)->orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, InventoryMovementService $service): RedirectResponse
    {
        Gate::authorize('gestionar-inventario');
        $data = $request->validate(['tipo' => ['required', Rule::in(['entrada', 'salida', 'devolucion', 'traslado', 'ajuste', 'baja'])], 'producto_id' => ['required', 'exists:productos,id'], 'cantidad' => ['required', 'integer', 'min:1', 'max:100000'], 'origen_id' => ['nullable', 'exists:ubicaciones,id'], 'destino_id' => ['nullable', 'exists:ubicaciones,id'], 'observacion' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid']]);
        $id = $service->createAndPublish($data, $request->user());

        return redirect()->route('movements.index')->with('success', "Movimiento #{$id} publicado.");
    }
}
