@extends('layouts.app')
@section('title', 'Bodega')
@section('content')
<div class="mb-3"><h1 class="h3 mb-1">Bodega</h1><p class="text-body-secondary mb-0">Consulta las existencias por bodega, sede o producto.</p></div>
<form class="mb-3"><div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar bodega, sede, código o producto"><button class="btn btn-primary">Buscar</button></div></form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Sede</th><th>Bodega</th><th>Producto</th><th>Disponibles</th><th>Stock mínimo</th></tr></thead><tbody>@forelse($stock as $item)<tr><td>{{ $item->sede_nombre }}</td><td>{{ $item->bodega_nombre }}<div class="small text-body-secondary">{{ $item->bodega_codigo }}</div></td><td>{{ $item->producto_nombre }}<div class="small text-body-secondary">{{ $item->codigo_interno }}</div></td><td>{{ $item->cantidad }}</td><td>{{ $item->stock_minimo }}</td></tr>@empty<tr><td colspan="5" class="text-center p-4 text-body-secondary">No hay existencias que coincidan con la búsqueda.</td></tr>@endforelse</tbody></table></div></div><div class="mt-3">{{ $stock->links() }}</div>
@endsection
