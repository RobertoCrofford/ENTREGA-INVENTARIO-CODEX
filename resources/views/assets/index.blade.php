@extends('layouts.app')

@section('title', 'Activos')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div><h1 class="h3 mb-1">Activos</h1><p class="text-body-secondary mb-0">Busca un activo para consultar sus datos.</p></div>
    @can('gestionar-inventario')<a class="btn btn-primary" href="{{ route('assets.create') }}"><i class="bi bi-plus-lg me-1"></i>Registrar activo</a>@endcan
</div>

<form class="mb-3" method="GET">
    <div class="row g-2"><div class="col-md"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input name="q" class="form-control" value="{{ $term }}" placeholder="Buscar activo fijo, serie, marca o modelo" autofocus></div></div><div class="col-md-3"><select name="uso" class="form-select"><option value="">Todos los usos</option><option value="administrativo" @selected($usage === 'administrativo')>Administrativo</option><option value="alumnos" @selected($usage === 'alumnos')>Alumnos</option><option value="docente" @selected($usage === 'docente')>Docente</option><option value="comun" @selected($usage === 'comun')>Uso común</option><option value="sin_definir" @selected($usage === 'sin_definir')>Sin definir</option></select></div><div class="col-md-auto"><button class="btn btn-outline-primary w-100" type="submit">Buscar</button></div></div>
</form>

@if($term === '' && $usage === '')
    <div class="card"><div class="card-body text-center py-5 text-body-secondary"><i class="bi bi-search fs-2 d-block mb-2"></i>Ingresa un código, serie, marca o modelo para ver el detalle de un activo.</div></div>
@elseif($assets->isEmpty())
    <div class="card"><div class="card-body text-center py-5 text-body-secondary">No se encontraron activos con los criterios seleccionados.</div></div>
@else
    <p class="text-body-secondary small">{{ $assets->total() }} resultado(s).</p>
    <div class="row g-3">
        @foreach($assets as $asset)
            <div class="col-12">
                <article class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div><span class="eyebrow">Activo fijo</span><h2 class="h5 mb-0">{{ $asset->activo_fijo }}</h2></div>
                            @can('gestionar-inventario')<a class="btn btn-sm btn-outline-primary" href="{{ route('assets.edit', $asset) }}"><i class="bi bi-pencil-square me-1"></i>Editar</a>@endcan
                        </div>
                        <dl class="row mb-0 details-list">
                            <dt class="col-md-3">Tipo</dt><dd class="col-md-3">{{ $asset->type?->nombre ?? '—' }}</dd>
                            <dt class="col-md-3">Estado</dt><dd class="col-md-3">{{ $asset->status?->nombre ?? '—' }}</dd>
                            <dt class="col-md-3">Uso</dt><dd class="col-md-3">{{ ['administrativo' => 'Administrativo', 'alumnos' => 'Alumnos', 'docente' => 'Docente', 'comun' => 'Uso común', 'sin_definir' => 'Sin definir'][$asset->uso] ?? 'Sin definir' }}</dd>
                            <dt class="col-md-3">Marca / modelo</dt><dd class="col-md-3">{{ trim(($asset->marca ?? '').' '.($asset->modelo ?? '')) ?: '—' }}</dd>
                            <dt class="col-md-3">N.º de serie</dt><dd class="col-md-3">{{ $asset->numero_serie ?: '—' }}</dd>
                            <dt class="col-md-3">Ubicación</dt><dd class="col-md-3">{{ $asset->location?->nombre ?? '—' }}</dd>
                            <dt class="col-md-3">Responsable</dt><dd class="col-md-3">{{ $asset->responsable_nombre ?: '—' }}</dd>
                            <dt class="col-md-3">Costo neto</dt><dd class="col-md-3">{{ $asset->costo_neto_actual !== null ? '$'.number_format($asset->costo_neto_actual, 0, ',', '.') : '—' }}</dd>
                            <dt class="col-md-3">Observación</dt><dd class="col-md-3">{{ $asset->observacion ?: '—' }}</dd>
                        </dl>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $assets->links() }}</div>
@endif
@endsection

