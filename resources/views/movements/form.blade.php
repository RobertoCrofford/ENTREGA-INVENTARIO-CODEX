@extends('layouts.app')

@section('title', 'Registrar movimiento')

@section('content')
<div class="mb-4"><span class="eyebrow">Inventario</span><h1 class="h3 mb-1">Registrar movimiento</h1><p class="text-body-secondary mb-0">Selecciona el producto, la cantidad y las ubicaciones involucradas.</p></div>

@if($products->isEmpty() || $locations->isEmpty())
    <div class="alert alert-warning">@if($products->isEmpty()) Primero registra al menos un producto. @endif @if($locations->isEmpty()) Primero registra una ubicación activa. @endif</div>
@endif

@php
    $locationGroups = $locations->groupBy(function ($location) {
        if ($location->tipo === 'bodega') return 'Bodega';
        if ($location->tipo === 'ssdd') return 'SSDD';
        if (str_contains(mb_strtoupper($location->nombre), 'AUDITORIO')) return 'Auditorio';
        return 'Sala de clase';
    });
@endphp

<form method="POST" class="card" action="{{ route('movements.store') }}">
    <div class="card-body">
        @csrf
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="tipo">Tipo</label><select id="tipo" name="tipo" class="form-select">@foreach(['entrada' => 'Entrada', 'salida' => 'Salida', 'devolucion' => 'Devolución', 'traslado' => 'Traslado', 'ajuste' => 'Ajuste', 'baja' => 'Baja'] as $value => $label)<option value="{{ $value }}" @selected(old('tipo') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label" for="buscar_producto">Buscar producto</label><input class="form-control" id="buscar_producto" type="search" placeholder="Nombre, categoría o código" autocomplete="off" @disabled($products->isEmpty())><div id="resultado_busqueda_producto" class="form-text" aria-live="polite">Escribe para buscar por nombre, categoría o código.</div></div>
            <div class="col-md-3"><label class="form-label" for="cantidad">Cantidad</label><input class="form-control" id="cantidad" type="number" min="1" name="cantidad" value="{{ old('cantidad') }}" required></div>
            <div class="col-md-7"><label class="form-label" for="producto_id">Producto seleccionado</label><select id="producto_id" name="producto_id" class="form-select" size="5" required @disabled($products->isEmpty())><option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}" data-search="{{ $product->nombre }} {{ $product->categoria?->nombre }} {{ $product->codigo_interno }}" data-label="{{ $product->nombre }} · {{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}" @selected(old('producto_id') == $product->id)>{{ $product->nombre }} · {{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}</option>@endforeach</select><div id="producto_seleccionado" class="alert alert-success py-2 mt-2 mb-0 {{ old('producto_id') ? '' : 'd-none' }}"><i class="bi bi-check-circle me-1"></i><span>{{ old('producto_id') ? 'Producto preparado para el movimiento.' : '' }}</span></div>@error('producto_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
            <div class="col-md-5"><label class="form-label" for="origen_id">Ubicación de origen</label><select class="form-select" id="origen_id" name="origen_id"><option value="">No aplica</option>@foreach($locationGroups as $group => $items)<optgroup label="{{ $group }}">@foreach($items as $location)<option value="{{ $location->id }}" @selected(old('origen_id') == $location->id)>{{ $location->nombre }}</option>@endforeach</optgroup>@endforeach</select><label class="form-label mt-3" for="destino_id">Ubicación de destino</label><select class="form-select" id="destino_id" name="destino_id"><option value="">No aplica</option>@foreach($locationGroups as $group => $items)<optgroup label="{{ $group }}">@foreach($items as $location)<option value="{{ $location->id }}" @selected(old('destino_id') == $location->id)>{{ $location->nombre }}</option>@endforeach</optgroup>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="observacion">Observación <span class="text-body-secondary fw-normal">(opcional)</span></label><textarea class="form-control" id="observacion" name="observacion" rows="3">{{ old('observacion') }}</textarea></div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary" @disabled($products->isEmpty() || $locations->isEmpty())>Publicar movimiento</button><a class="btn btn-link" href="{{ route('movements.index') }}">Cancelar</a></div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const search = document.getElementById('buscar_producto');
        const products = document.getElementById('producto_id');
        if (!search || !products) return;
        const feedback = document.getElementById('resultado_busqueda_producto');
        const selectedFeedback = document.getElementById('producto_seleccionado');
        const selectedLabel = selectedFeedback.querySelector('span');
        const options = Array.from(products.options).filter((option) => option.value);
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        search.addEventListener('input', () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));
            feedback.textContent = term === '' ? `${options.length} producto(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s).`;
            if (matches.length === 1) { products.value = matches[0].value; selectedFeedback.classList.remove('d-none'); selectedLabel.textContent = `Producto seleccionado: ${matches[0].dataset.label}.`; }
        });
        products.addEventListener('change', () => { const selected = products.options[products.selectedIndex]; if (selected?.value) { selectedFeedback.classList.remove('d-none'); selectedLabel.textContent = `Producto seleccionado: ${selected.dataset.label}.`; } else { selectedFeedback.classList.add('d-none'); } });
    });
</script>
@endsection
