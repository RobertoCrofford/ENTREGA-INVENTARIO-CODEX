@extends('layouts.app')

@section('title', 'Registrar movimiento')

@section('content')
<div class="mb-4"><span class="eyebrow">Inventario</span><h1 class="h3 mb-1">Registrar movimiento</h1><p class="text-body-secondary mb-0">Selecciona el producto, la cantidad y las ubicaciones involucradas.</p></div>

@if(($products->isEmpty() && $assets->isEmpty()) || $locations->isEmpty())
    <div class="alert alert-warning">@if($products->isEmpty() && $assets->isEmpty()) Primero registra al menos un producto o activo. @endif @if($locations->isEmpty()) Primero registra una ubicación activa. @endif</div>
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
            <div class="col-md-4"><label class="form-label" for="item_type">Qué se mueve</label><select id="item_type" name="item_type" class="form-select"><option value="producto" @selected(old('item_type', $defaultItemType) === 'producto')>Producto</option><option value="activo" @selected(old('item_type', $defaultItemType) === 'activo')>Activo fijo</option></select></div>
            <div class="col-md-4"><label class="form-label" for="tipo">Tipo</label><select id="tipo" name="tipo" class="form-select">@foreach(['entrada' => 'Entrada', 'salida' => 'Salida', 'devolucion' => 'Devolución', 'traslado' => 'Traslado'] as $value => $label)<option value="{{ $value }}" @selected(old('tipo', $defaultMovementType) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div id="product_search_section" class="col-md-5"><label class="form-label" for="buscar_producto">Buscar producto</label><input class="form-control" id="buscar_producto" type="search" placeholder="Nombre, categoría o código" autocomplete="off" @disabled($products->isEmpty())><div id="resultado_busqueda_producto" class="form-text" aria-live="polite">Escribe para buscar por nombre, categoría o código.</div></div>
            <div class="col-md-3"><label class="form-label" for="cantidad">Cantidad</label><input class="form-control" id="cantidad" type="number" min="1" name="cantidad" value="{{ old('cantidad') }}" required></div>
            <div id="product_section" class="col-md-7"><label class="form-label" for="producto_id">Producto seleccionado</label><select id="producto_id" name="producto_id" class="form-select" size="5" required @disabled($products->isEmpty())><option value="">Selecciona un producto</option>@foreach($products as $product)<option value="{{ $product->id }}" data-search="{{ $product->nombre }} {{ $product->categoria?->nombre }} {{ $product->codigo_interno }}" data-label="{{ $product->nombre }} · {{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}" @selected(old('producto_id', $defaultProductId) == $product->id)>{{ $product->nombre }} · {{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}</option>@endforeach</select><div id="producto_seleccionado" class="alert alert-success py-2 mt-2 mb-0 {{ old('producto_id', $defaultProductId) ? '' : 'd-none' }}"><i class="bi bi-check-circle me-1"></i><span>{{ old('producto_id', $defaultProductId) ? 'Producto preparado para el movimiento.' : '' }}</span></div>@error('producto_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
            <div id="asset_section" class="col-md-7 d-none"><label class="form-label" for="buscar_activo">Buscar activo fijo</label><input class="form-control mb-2" id="buscar_activo" type="search" placeholder="Activo fijo, serie, marca o modelo" autocomplete="off" @disabled($assets->isEmpty())><select id="activo_id" name="activo_id" class="form-select" size="5" disabled @disabled($assets->isEmpty())><option value="">Selecciona un activo fijo</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" data-search="{{ $asset->activo_fijo }} {{ $asset->numero_serie }} {{ $asset->marca }} {{ $asset->modelo }}" data-location="{{ $asset->ubicacion_actual_id }}" data-label="{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }} · Ubicación actual: {{ $asset->location?->nombre ?? 'Sin ubicación' }}">{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }} · {{ $asset->location?->nombre ?? 'Sin ubicación' }}</option>@endforeach</select><div id="activo_seleccionado" class="alert alert-success py-2 mt-2 mb-0 d-none"><i class="bi bi-check-circle me-1"></i><span></span></div><div class="form-text">El traslado usará la ubicación actual del activo como origen.</div>@error('activo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
            <div class="col-md-5"><label class="form-label" for="origen_id">Ubicación de origen</label><select class="form-select" id="origen_id" name="origen_id"><option value="">No aplica</option>@foreach($locationGroups as $group => $items)<optgroup label="{{ $group }}">@foreach($items as $location)<option value="{{ $location->id }}" data-location-type="{{ $location->tipo }}" @selected(old('origen_id') == $location->id)>{{ $location->nombre }}</option>@endforeach</optgroup>@endforeach</select><label class="form-label mt-3" for="destino_id">Ubicación de destino</label><select class="form-select" id="destino_id" name="destino_id"><option value="">No aplica</option>@foreach($locationGroups as $group => $items)<optgroup label="{{ $group }}">@foreach($items as $location)<option value="{{ $location->id }}" data-location-type="{{ $location->tipo }}" @selected(old('destino_id') == $location->id)>{{ $location->nombre }}</option>@endforeach</optgroup>@endforeach</select><div id="warehouse-help" class="form-text">Para productos, se muestran solo bodegas activas.</div></div>
            <div class="col-12"><label class="form-label" for="observacion">Observación <span class="text-body-secondary fw-normal">(opcional)</span></label><textarea class="form-control" id="observacion" name="observacion" rows="3">{{ old('observacion') }}</textarea></div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary" @disabled(($products->isEmpty() && $assets->isEmpty()) || $locations->isEmpty())>Publicar movimiento</button><a class="btn btn-link" href="{{ route('movements.index') }}">Cancelar</a></div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const search = document.getElementById('buscar_producto');
        const products = document.getElementById('producto_id');
        const itemType = document.getElementById('item_type');
        const assetSearch = document.getElementById('buscar_activo');
        const assets = document.getElementById('activo_id');
        const assetSelectedFeedback = document.getElementById('activo_seleccionado');
        const assetSelectedLabel = assetSelectedFeedback.querySelector('span');
        const productSection = document.getElementById('product_section');
        const productSearchSection = document.getElementById('product_search_section');
        const assetSection = document.getElementById('asset_section');
        const movementType = document.getElementById('tipo');
        const origin = document.getElementById('origen_id');
        const quantity = document.getElementById('cantidad');
        if (!search || !products || !itemType || !assetSearch || !assets) return;
        const feedback = document.getElementById('resultado_busqueda_producto');
        const selectedFeedback = document.getElementById('producto_seleccionado');
        const selectedLabel = selectedFeedback.querySelector('span');
        const options = Array.from(products.options).filter((option) => option.value);
        const assetOptions = Array.from(assets.options).filter((option) => option.value);
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const toggleItemType = () => {
            const isAsset = itemType.value === 'activo';
            productSection.classList.toggle('d-none', isAsset);
            productSearchSection.classList.toggle('d-none', isAsset);
            assetSection.classList.toggle('d-none', !isAsset);
            products.disabled = isAsset;
            products.required = !isAsset;
            assets.disabled = !isAsset;
            assets.required = isAsset;
            [origin, document.getElementById('destino_id')].forEach((locationSelect) => {
                Array.from(locationSelect.options).forEach((option) => {
                    if (!option.value) return;
                    const unavailableForProduct = !isAsset && option.dataset.locationType !== 'bodega';
                    option.hidden = unavailableForProduct;
                    option.disabled = unavailableForProduct;
                    if (unavailableForProduct && option.selected) locationSelect.value = '';
                });
            });
            if (isAsset) { movementType.value = 'traslado'; quantity.value = 1; quantity.readOnly = true; } else { quantity.readOnly = false; }
        };
        search.addEventListener('input', () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));
            feedback.textContent = term === '' ? `${options.length} producto(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s).`;
            if (matches.length === 1) { products.value = matches[0].value; selectedFeedback.classList.remove('d-none'); selectedLabel.textContent = `Producto seleccionado: ${matches[0].dataset.label}.`; }
        });
        products.addEventListener('change', () => { const selected = products.options[products.selectedIndex]; if (selected?.value) { selectedFeedback.classList.remove('d-none'); selectedLabel.textContent = `Producto seleccionado: ${selected.dataset.label}.`; } else { selectedFeedback.classList.add('d-none'); } });
        assetSearch.addEventListener('input', () => { const term = normalize(assetSearch.value.trim()); const matches = assetOptions.filter((option) => term === '' || normalize(option.dataset.search).includes(term)); assetOptions.forEach((option) => option.hidden = !matches.includes(option)); if (matches.length === 1) { assets.value = matches[0].value; origin.value = matches[0].dataset.location; assetSelectedFeedback.classList.remove('d-none'); assetSelectedLabel.textContent = `Activo seleccionado: ${matches[0].dataset.label}.`; } });
        assets.addEventListener('change', () => { const selected = assets.options[assets.selectedIndex]; if (selected?.value) { origin.value = selected.dataset.location; assetSelectedFeedback.classList.remove('d-none'); assetSelectedLabel.textContent = `Activo seleccionado: ${selected.dataset.label}.`; } else { assetSelectedFeedback.classList.add('d-none'); } });
        itemType.addEventListener('change', toggleItemType);
        toggleItemType();
    });
</script>
@endsection
