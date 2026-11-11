@extends('layouts.mobile')
@section('title', 'Revisar sala · Inventario móvil')
@section('content')
<section class="mobile-hero">
    <span class="mobile-eyebrow">Inventario físico</span>
    <h1>Revisar sala</h1>
    <p>Selecciona una revisión abierta o inicia el inventario físico de una sala.</p>
</section>

@if($inventories->isNotEmpty())
<section class="mobile-section" aria-labelledby="open-inventories-title">
    <div class="mobile-section-heading"><div><span class="mobile-eyebrow">Continuar</span><h2 id="open-inventories-title">Inventarios abiertos</h2></div><span class="mobile-count-badge">{{ $inventories->count() }}</span></div>
    <div class="mobile-inventory-list">
        @foreach($inventories as $inventory)
            @php($label = str_ends_with(mb_strtoupper($inventory->ubicacion_codigo), '-'.mb_strtoupper($inventory->ubicacion_nombre)) ? ucfirst($inventory->ubicacion_tipo).' '.$inventory->ubicacion_nombre : $inventory->ubicacion_codigo.' — '.$inventory->ubicacion_nombre)
            @php($pending = max(0, (int) $inventory->esperados - (int) $inventory->escaneados))
            <a class="mobile-inventory-card" href="{{ route('mobile.inventory.show', $inventory->id) }}">
                <div><span class="mobile-state {{ $inventory->estado === 'pendiente' ? 'is-pending' : '' }}">{{ $inventory->estado === 'pendiente' ? 'Con pendientes' : 'En curso' }}</span><h3>{{ $label }}</h3><small>{{ collect([$inventory->ubicacion_edificio, $inventory->ubicacion_piso])->filter()->join(' · ') ?: 'Ubicación institucional' }}</small></div>
                <div class="mobile-inventory-progress"><strong>{{ $inventory->escaneados }}/{{ $inventory->esperados }}</strong><span>{{ $pending }} pendiente(s)</span><i class="bi bi-chevron-right"></i></div>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="mobile-section" aria-labelledby="new-inventory-title">
    <div class="mobile-section-heading"><div><span class="mobile-eyebrow">Nueva revisión</span><h2 id="new-inventory-title">Seleccionar sala</h2></div></div>
    <form method="POST" action="{{ route('physical-inventories.store') }}" class="mobile-start-form">
        @csrf
        <input type="hidden" name="modo" value="mobile">
        <label for="ubicacion_id">Sala o ubicación</label>
        <select id="ubicacion_id" name="ubicacion_id" required>
            <option value="">Selecciona una ubicación</option>
            @foreach($locations as $location)
                @php($label = str_ends_with(mb_strtoupper($location->codigo), '-'.mb_strtoupper($location->nombre)) ? ucfirst($location->tipo).' '.$location->nombre : $location->codigo.' — '.$location->nombre)
                <option value="{{ $location->id }}" @selected(old('ubicacion_id') == $location->id)>{{ $label }}{{ $location->edificio || $location->piso ? ' · '.collect([$location->edificio, $location->piso])->filter()->join(', ') : '' }}</option>
            @endforeach
        </select>
        @error('ubicacion_id')<div class="mobile-field-error">{{ $message }}</div>@enderror
        <button class="mobile-primary-button" type="submit"><i class="bi bi-camera"></i>Iniciar escaneo</button>
    </form>
</section>
@endsection
