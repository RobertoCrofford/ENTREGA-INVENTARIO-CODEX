@extends('layouts.app')
@section('title', 'Ver producto')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">{{ $product->nombre }}</h1><p class="text-body-secondary mb-0">Ficha de consulta del producto.</p></div>@can('gestionar-inventario')<a class="btn btn-primary" href="{{ route('products.edit', $product) }}">Editar producto</a>@endcan</div>
<div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Código institucional</dt><dd class="col-sm-9">{{ $product->codigo_interno }}</dd><dt class="col-sm-3">Categoría</dt><dd class="col-sm-9">{{ $product->categoria->nombre }}</dd><dt class="col-sm-3">Número de serie</dt><dd class="col-sm-9">{{ $product->numero_parte }}</dd><dt class="col-sm-3">Marca / modelo</dt><dd class="col-sm-9">{{ $product->marca ?: '—' }} / {{ $product->modelo ?: '—' }}</dd><dt class="col-sm-3">Costo neto</dt><dd class="col-sm-9">${{ number_format($product->costo_neto_actual, 0, ',', '.') }}</dd><dt class="col-sm-3">Descripción</dt><dd class="col-sm-9">{{ $product->descripcion ?: '—' }}</dd></dl></div></div><a class="btn btn-link mt-2" href="{{ url()->previous() }}">Volver</a>
@endsection


