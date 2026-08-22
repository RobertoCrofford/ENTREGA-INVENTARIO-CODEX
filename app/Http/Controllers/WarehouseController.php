<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('consultar-inventario');

        $stock = DB::table('existencias')
            ->join('productos', 'existencias.producto_id', '=', 'productos.id')
            ->join('ubicaciones', 'existencias.bodega_id', '=', 'ubicaciones.id')
            ->join('sedes', 'ubicaciones.sede_id', '=', 'sedes.id')
            ->where('existencias.activo', true)
            ->where('ubicaciones.tipo', 'bodega')
            ->when($request->q, fn ($query, $term) => $query->whereAny([
                'productos.codigo_interno', 'productos.nombre', 'productos.numero_parte',
                'ubicaciones.codigo', 'ubicaciones.nombre', 'sedes.nombre',
            ], 'like', "%{$term}%"))
            ->select('productos.codigo_interno', 'productos.nombre as producto_nombre', 'ubicaciones.codigo as bodega_codigo', 'ubicaciones.nombre as bodega_nombre', 'sedes.nombre as sede_nombre', 'existencias.cantidad', 'existencias.stock_minimo')
            ->orderBy('sedes.nombre')->orderBy('ubicaciones.nombre')->orderBy('productos.nombre')
            ->paginate(20)->withQueryString();

        return view('warehouses.index', compact('stock'));
    }
}
