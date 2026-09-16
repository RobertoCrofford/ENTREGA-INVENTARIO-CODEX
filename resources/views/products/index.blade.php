@extends('layouts.app')

@section('title', 'Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div><h1 class="h3 mb-1">Productos</h1><p class="text-body-secondary mb-0">Busca un producto para consultar su detalle.</p></div>
    @can('gestionar-inventario')<a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</a>@endcan
</div>

<form class="mb-3" method="GET">
    <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="{{ $term }}" placeholder="Buscar por código, número de parte, nombre, marca o modelo" autofocus><button class="btn btn-outline-primary" type="submit">Buscar</button></div>
</form>

@if($assetMatch)
    <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
        <div><i class="bi bi-pc-display-horizontal me-1"></i><strong>Este código corresponde a un activo, no a un producto.</strong><br><span class="small">Activo fijo: {{ $assetMatch->activo_fijo }} · {{ $assetMatch->type?->nombre ?? 'Activo' }} · Estado: {{ $assetMatch->status?->nombre ?? '—' }}</span></div>
        <a class="btn btn-sm btn-outline-light flex-shrink-0" href="{{ route('assets.index', ['q' => $assetMatch->activo_fijo]) }}">Ver activo</a>
    </div>
@endif

@if($term === '')
    <div class="card"><div class="card-body text-center py-5 text-body-secondary"><i class="bi bi-search fs-2 d-block mb-2"></i>Ingresa un código, número de parte o nombre para ver el detalle de un producto.</div></div>
@elseif($products->isEmpty())
    <div class="card"><div class="card-body text-center py-5 text-body-secondary">No se encontraron productos para “{{ $term }}”.</div></div>
@else
    <p class="text-body-secondary small">{{ $products->total() }} resultado(s) para “{{ $term }}”.</p>
    <div class="row g-3">
        @foreach($products as $product)
            <div class="col-12">
                <article class="card"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><span class="eyebrow">{{ $product->codigo_interno }}</span><h2 class="h5 mb-0">{{ $product->nombre }}</h2></div>@can('gestionar-inventario')<div class="d-flex gap-2 flex-wrap justify-content-end"><a class="btn btn-sm btn-outline-danger" href="{{ route('movements.create', ['producto' => $product->id, 'tipo' => 'salida']) }}"><i class="bi bi-dash-circle me-1"></i>Descontar stock</a><a class="btn btn-sm btn-outline-primary" href="{{ route('products.edit', $product) }}"><i class="bi bi-pencil-square me-1"></i>Editar</a></div>@endcan</div>
                    <dl class="row mb-0 details-list">
                        <dt class="col-md-3">Categoría</dt><dd class="col-md-3">{{ $product->categoria?->nombre ?? '—' }}</dd>
                        <dt class="col-md-3">Número de parte</dt><dd class="col-md-3">{{ $product->numero_parte }}</dd>
                        <dt class="col-md-3">Marca / modelo</dt><dd class="col-md-3">{{ trim(($product->marca ?? '').' '.($product->modelo ?? '')) ?: '—' }}</dd>
                        <dt class="col-md-3">Costo neto</dt><dd class="col-md-3">${{ number_format($product->costo_neto_actual, 0, ',', '.') }}</dd>
                        <dt class="col-md-3">Stock disponible</dt><dd class="col-md-3"><span class="badge text-bg-{{ ($product->stock_total ?? 0) > 0 ? 'success' : 'danger' }}">{{ $product->stock_total ?? 0 }} unidad(es)</span></dd>
                        <dt class="col-md-3">Descripción</dt><dd class="col-md-9">{{ $product->descripcion ?: '—' }}</dd>
                    </dl>
                </div></article>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $products->links() }}</div>
@endif
@endsection

