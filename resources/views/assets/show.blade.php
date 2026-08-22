@extends('layouts.app')
@section('title', 'Ver activo')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Activo {{ $asset->activo_fijo }}</h1><p class="text-body-secondary mb-0">Ficha de consulta del activo.</p></div>@can('gestionar-inventario')<a class="btn btn-primary" href="{{ route('assets.edit', $asset) }}">Editar activo</a>@endcan</div>
<div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Activo fijo</dt><dd class="col-sm-9">{{ $asset->activo_fijo }}</dd><dt class="col-sm-3">Tipo / estado</dt><dd class="col-sm-9">{{ $asset->type->nombre }} / {{ $asset->status->nombre }}</dd><dt class="col-sm-3">Número de serie</dt><dd class="col-sm-9">{{ $asset->numero_serie ?: '—' }}</dd><dt class="col-sm-3">Marca / modelo</dt><dd class="col-sm-9">{{ $asset->marca ?: '—' }} / {{ $asset->modelo ?: '—' }}</dd><dt class="col-sm-3">Responsable</dt><dd class="col-sm-9">{{ $asset->responsable_nombre ?: '—' }}</dd><dt class="col-sm-3">Observación</dt><dd class="col-sm-9">{{ $asset->observacion ?: '—' }}</dd></dl></div></div><a class="btn btn-link mt-2" href="{{ url()->previous() }}">Volver</a>
@endsection
