@extends('layouts.app')

@section('title', 'Clasificar activo')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><span class="eyebrow">Clasificación</span><h1 class="h3 mb-1">Activo {{ $asset->activo_fijo }}</h1><p class="text-body-secondary mb-0">Define el uso institucional de este equipo.</p></div>
    <a href="{{ route('assets.index', ['uso' => 'sin_definir']) }}" class="btn btn-outline-secondary">Volver al listado</a>
</div>

<div class="card"><div class="card-body">
    <dl class="row mb-4 details-list">
        <dt class="col-sm-3">Marca / modelo</dt><dd class="col-sm-9">{{ trim(($asset->marca ?? '').' '.($asset->modelo ?? '')) ?: '—' }}</dd>
        <dt class="col-sm-3">N.º de serie</dt><dd class="col-sm-9">{{ $asset->numero_serie ?: '—' }}</dd>
        <dt class="col-sm-3">Ubicación</dt><dd class="col-sm-9">{{ $asset->location?->nombre ?? '—' }}</dd>
    </dl>
    <form method="POST" action="{{ route('assets.update-usage', $asset) }}">
        @csrf
        @method('PATCH')
        <div class="mb-3"><label for="uso" class="form-label">Uso del equipo</label><select id="uso" name="uso" class="form-select @error('uso') is-invalid @enderror" required><option value="">Selecciona una opción</option><option value="administrativo" @selected(old('uso') === 'administrativo')>Administrativo</option><option value="alumnos" @selected(old('uso') === 'alumnos')>Alumnos</option><option value="docente" @selected(old('uso') === 'docente')>Docente</option><option value="comun" @selected(old('uso') === 'comun')>Uso común</option></select>@error('uso')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Guardar clasificación</button>
    </form>
</div></div>
@endsection
