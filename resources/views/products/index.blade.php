@extends('layouts.app')
@section('title', 'Productos')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Productos</h1>
    @can('gestionar-inventario')
        <a href="{{ route('products.create') }}" class="btn btn-primary">Nuevo producto</a>
    @endcan
</div>
<form class="mb-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar por código, número de parte o nombre"></form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Costo neto</th><th></th></tr></thead>
    <tbody>
    @forelse($products as $product)
        <tr>
            <td>{{ $product->codigo_interno }}</td>
            <td>{{ $product->nombre }}<div class="small text-body-secondary">Serie: {{ $product->numero_parte }}@if($product->modelo) · Modelo: {{ $product->modelo }}@endif</div></td>
            <td>{{ $product->categoria->nombre }}</td>
            <td>${{ number_format($product->costo_neto_actual, 0, ',', '.') }}</td>
            <td class="text-end">
                @can('gestionar-inventario')
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('products.edit', $product) }}">Editar</a>
                    @if($product->activo)
                        <form class="d-inline" method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('¿Desactivar este producto? Se conservará el historial.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Desactivar</button>
                        </form>
                    @endif
                @endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center p-4 text-body-secondary">No hay productos.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
