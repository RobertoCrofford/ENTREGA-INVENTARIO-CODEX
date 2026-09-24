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
            <div class="col-md-3"><label class="form-label" for="cantidad">Cantidad</label><input class="form-control" id="cantidad" type="number" min="1" name="cantidad" value="{{ old('cantidad') }}" required></div>
            <div id="product_section" class="col-md-7"><label class="form-label" for="buscar_producto">Buscar producto</label><input class="form-control mb-2" id="buscar_producto" type="search" placeholder="Nombre, categoría o código" autocomplete="off" @disabled($products->isEmpty())><input id="producto_id" name="producto_id" type="hidden" value="{{ old('producto_id', $defaultProductId) }}"><div id="producto_seleccionado" class="asset-picker-selected d-none" aria-live="polite"><div><span class="eyebrow">Producto seleccionado</span><strong></strong></div><button class="btn btn-sm btn-outline-light" id="cambiar_producto" type="button">Cambiar</button></div><div id="resultados_productos" class="asset-picker-results" role="listbox" aria-label="Resultados de productos">@foreach($products as $product)<button class="asset-picker-option" type="button" role="option" data-id="{{ $product->id }}" data-search="{{ $product->nombre }} {{ $product->categoria?->nombre }} {{ $product->codigo_interno }}" data-label="{{ $product->nombre }} · {{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}"><strong>{{ $product->nombre }}</strong><span>{{ $product->categoria?->nombre ?? 'Sin categoría' }} · {{ $product->codigo_interno }}</span></button>@endforeach</div><div id="resultado_busqueda_producto" class="form-text" aria-live="polite">{{ $products->count() }} producto(s) disponible(s). Escribe para filtrar.</div>@error('producto_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
            <div id="asset_section" class="col-md-7 d-none"><label class="form-label" for="buscar_activo">Buscar activo fijo</label><input class="form-control mb-2" id="buscar_activo" type="search" placeholder="Código, serie, marca o modelo" autocomplete="off" @disabled($assets->isEmpty())><input id="activo_id" name="activo_id" type="hidden" value="{{ old('activo_id') }}"><div id="activo_seleccionado" class="asset-picker-selected d-none" aria-live="polite"><div><span class="eyebrow">Activo seleccionado</span><strong></strong></div><button class="btn btn-sm btn-outline-light" id="cambiar_activo" type="button">Cambiar</button></div><div id="resultados_activos" class="asset-picker-results" role="listbox" aria-label="Resultados de activos">@foreach($assets as $asset)<button class="asset-picker-option" type="button" role="option" data-id="{{ $asset->id }}" data-fixed-code="{{ $asset->activo_fijo }}" data-search="{{ $asset->activo_fijo }} {{ $asset->numero_serie }} {{ $asset->marca }} {{ $asset->modelo }}" data-location="{{ $asset->ubicacion_actual_id }}" data-label="{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }} · Ubicación actual: {{ $asset->location?->nombre ?? 'Sin ubicación' }}"><strong>{{ $asset->activo_fijo }}</strong><span>{{ $asset->marca }} {{ $asset->modelo }}@if($asset->numero_serie) · Serie {{ $asset->numero_serie }}@endif · {{ $asset->location?->nombre ?? 'Sin ubicación' }}</span></button>@endforeach</div><div id="resultado_busqueda_activo" class="form-text" aria-live="polite">{{ $assets->count() }} activo(s) disponible(s). Escribe para filtrar.</div><div class="form-text">El traslado usará la ubicación actual del activo como origen.</div>@error('activo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
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
        const assetSelectedLabel = assetSelectedFeedback?.querySelector('strong');
        const assetResults = document.getElementById('resultados_activos');
        const assetFeedback = document.getElementById('resultado_busqueda_activo');
        const changeAsset = document.getElementById('cambiar_activo');
        const productSection = document.getElementById('product_section');
        const productResults = document.getElementById('resultados_productos');
        const changeProduct = document.getElementById('cambiar_producto');
        const assetSection = document.getElementById('asset_section');
        const movementType = document.getElementById('tipo');
        const origin = document.getElementById('origen_id');
        const quantity = document.getElementById('cantidad');
        if (!search || !products || !productResults || !changeProduct || !itemType || !assetSearch || !assets) return;
        const feedback = document.getElementById('resultado_busqueda_producto');
        const selectedFeedback = document.getElementById('producto_seleccionado');
        const selectedLabel = selectedFeedback.querySelector('strong');
        const options = Array.from(productResults.querySelectorAll('.asset-picker-option'));
        const assetOptions = Array.from(assetResults?.querySelectorAll('.asset-picker-option') ?? []);
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const sameFixedCode = (option, value) => {
            const fixedCode = option.dataset.fixedCode ?? '';
            if (!/^\d{6,}$/.test(value) || !/^\d{6,}$/.test(fixedCode)) return false;
            return (value.replace(/0+$/, '') || '0') === (fixedCode.replace(/0+$/, '') || '0');
        };
        const toggleItemType = () => {
            const isAsset = itemType.value === 'activo';
            productSection.classList.toggle('d-none', isAsset);
            assetSection.classList.toggle('d-none', !isAsset);
            products.disabled = isAsset;
            products.required = !isAsset;
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
        const filterProducts = () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));
            feedback.textContent = term === '' ? `${options.length} producto(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s). Selecciona uno.`;
        };
        const selectProduct = (option) => {
            products.value = option.dataset.id;
            selectedLabel.textContent = option.dataset.label;
            selectedFeedback.classList.remove('d-none');
            productResults.classList.add('d-none');
            search.classList.add('d-none');
            feedback.textContent = 'Producto listo para registrar el movimiento.';
        };
        productResults.addEventListener('click', (event) => {
            const option = event.target.closest('.asset-picker-option');
            if (option) selectProduct(option);
        });
        changeProduct.addEventListener('click', () => {
            products.value = '';
            search.value = '';
            selectedFeedback.classList.add('d-none');
            productResults.classList.remove('d-none');
            search.classList.remove('d-none');
            filterProducts();
            search.focus();
        });
        search.addEventListener('input', filterProducts);
        const filterAssets = () => {
            const term = normalize(assetSearch.value.trim());
            const matches = assetOptions.filter((option) => term === '' || normalize(option.dataset.search).includes(term) || sameFixedCode(option, term));
            assetOptions.forEach((option) => option.hidden = !matches.includes(option));
            assetFeedback.textContent = term === '' ? `${assetOptions.length} activo(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s). Selecciona uno.`;
        };
        const selectAsset = (option) => {
            assets.value = option.dataset.id;
            origin.value = option.dataset.location;
            assetSelectedLabel.textContent = option.dataset.label;
            assetSelectedFeedback.classList.remove('d-none');
            assetResults.classList.add('d-none');
            assetSearch.classList.add('d-none');
            assetFeedback.textContent = 'Activo listo para registrar el movimiento.';
        };
        assetResults.addEventListener('click', (event) => {
            const option = event.target.closest('.asset-picker-option');
            if (option) selectAsset(option);
        });
        changeAsset.addEventListener('click', () => {
            assets.value = '';
            origin.value = '';
            assetSearch.value = '';
            assetSelectedFeedback.classList.add('d-none');
            assetResults.classList.remove('d-none');
            assetSearch.classList.remove('d-none');
            filterAssets();
            assetSearch.focus();
        });
        assetSearch.addEventListener('input', filterAssets);
        itemType.addEventListener('change', toggleItemType);
        const previousProduct = options.find((option) => option.dataset.id === products.value);
        if (previousProduct) selectProduct(previousProduct);
        else filterProducts();
        const previousAsset = assetOptions.find((option) => option.dataset.id === assets.value);
        if (previousAsset) selectAsset(previousAsset);
        else filterAssets();
        toggleItemType();
    });
</script>
@endsection
