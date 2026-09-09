@extends('layouts.app')

@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')

@section('content')
@if($scanCode ?? null)
    <div class="alert alert-info"><i class="bi bi-upc-scan me-1"></i>Código escaneado: <strong>{{ $scanCode }}</strong>. Se asociará al producto y queda precargado en el número de parte o SKU.</div>
@endif
<h1 class="h3">{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
<form class="card" method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    <div class="card-body">
        @csrf
        @if($scanCode ?? null)<input type="hidden" name="codigo_escaneado" value="{{ $scanCode }}">@endif
        @if($product->exists) @method('PUT') @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Categoría</label><select name="categoria_id" class="form-select">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('categoria_id', $product->categoria_id) == $category->id)>{{ $category->nombre }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Número de parte o SKU</label><input class="form-control" name="numero_parte" value="{{ old('numero_parte', $product->numero_parte ?: ($scanCode ?? '')) }}" required></div>
            <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="{{ old('nombre', $product->nombre) }}" required></div>
            <div class="col-md-6"><label class="form-label">Marca</label><input class="form-control" name="marca" value="{{ old('marca', $product->marca) }}"></div>
            <div class="col-md-6"><label class="form-label">Modelo</label><input class="form-control" name="modelo" value="{{ old('modelo', $product->modelo) }}"></div>
            <div class="col-md-6"><label class="form-label">Costo neto</label><input class="form-control" type="number" min="0" step=".01" name="costo_neto_actual" value="{{ old('costo_neto_actual', $product->costo_neto_actual) }}" required></div>
            <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion">{{ old('descripcion', $product->descripcion) }}</textarea></div>
            @if(! $product->exists)
                <div class="col-12"><hr><h2 class="h5 mb-1">¿Dónde quedará guardado?</h2><p class="text-body-secondary small mb-0">Completa estos datos para registrar el producto y mostrarlo inmediatamente en Bodega.</p></div>
                <div class="col-md-6"><label class="form-label">Cantidad ingresada</label><input class="form-control" type="number" min="1" max="100000" name="cantidad_inicial" value="{{ old('cantidad_inicial') }}" placeholder="Ej.: 10" required><div class="form-text">Ejemplo: compraste 10 mouse.</div></div>
                <div class="col-md-6"><label class="form-label">Bodega donde quedará guardado</label><select class="form-select" name="bodega_inicial_id" required><option value="">Selecciona una bodega</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('bodega_inicial_id') == $warehouse->id)>{{ $warehouse->nombre }} ({{ $warehouse->codigo }})</option>@endforeach</select></div>
            @endif
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Guardar</button><a class="btn btn-link" href="{{ route('products.index') }}">Cancelar</a></div>
</form>
@if($product->exists && $stockTotal <= 0)
    <div class="alert alert-warning mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2"><span><i class="bi bi-box-seam me-1"></i>Este producto aún no tiene existencias en una bodega.</span><a class="btn btn-warning" href="{{ route('movements.create', ['producto' => $product->id, 'tipo' => 'entrada']) }}">Registrar entrada de stock</a></div>
@endif
@if($product->exists && $product->activo && auth()->user()->can('desactivar-productos'))
<form class="card mt-3" method="POST" action="{{ route('products.destroy', $product) }}">
    @csrf @method('DELETE')
    <div class="card-body"><h2 class="h5">Desactivar producto</h2><p class="text-body-secondary mb-0">Solo es posible cuando no mantiene existencias disponibles.</p></div>
    <div class="card-footer"><button class="btn btn-outline-danger" type="submit">Desactivar producto</button></div>
</form>
@endif
@endsection

