@extends('layouts.app')

@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')

@section('content')
@if($scanCode ?? null)
    <div class="alert alert-info"><i class="bi bi-upc-scan me-1"></i>Código escaneado: <strong>{{ $scanCode }}</strong>. Se asociará al producto y queda precargado en el número de serie.</div>
@endif
<h1 class="h3">{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
<form class="card" method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    <div class="card-body">
        @csrf
        @if($product->exists) @method('PUT') @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Categoría</label><select name="categoria_id" class="form-select">@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('categoria_id', $product->categoria_id) == $category->id)>{{ $category->nombre }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Número de serie</label><input class="form-control" name="numero_parte" value="{{ old('numero_parte', $product->numero_parte ?: ($scanCode ?? '')) }}" required></div>
            <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="{{ old('nombre', $product->nombre) }}" required></div>
            <div class="col-md-6"><label class="form-label">Marca</label><input class="form-control" name="marca" value="{{ old('marca', $product->marca) }}"></div>
            <div class="col-md-6"><label class="form-label">Modelo</label><input class="form-control" name="modelo" value="{{ old('modelo', $product->modelo) }}"></div>
            <div class="col-md-6"><label class="form-label">Costo neto</label><input class="form-control" type="number" min="0" step=".01" name="costo_neto_actual" value="{{ old('costo_neto_actual', $product->costo_neto_actual) }}" required></div>
            <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="activo"><option value="1">Activo</option><option value="0" @selected(old('activo', $product->activo) == false)>Inactivo</option></select></div>
            <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion">{{ old('descripcion', $product->descripcion) }}</textarea></div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Guardar</button><a class="btn btn-link" href="{{ route('products.index') }}">Cancelar</a></div>
</form>
@endsection

