@extends('layouts.app')
@section('title', 'Importar activos')
@section('content')
<h1 class="h3">Importar activos</h1>
<p class="text-body-secondary">Carga un CSV, valida sus filas antes de guardar y confirma solo los activos válidos.</p>
@if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
<div class="card mb-4"><div class="card-body"><a class="btn btn-outline-primary mb-3" href="{{ route('imports.assets.template') }}">Descargar plantilla CSV</a><form method="POST" action="{{ route('imports.assets.preview') }}" enctype="multipart/form-data">@csrf<div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label" for="archivo">Archivo CSV (máximo 5 MB)</label><input class="form-control" id="archivo" name="archivo" type="file" accept=".csv,text/csv" required>@error('archivo')<div class="text-danger small">{{ $message }}</div>@enderror</div><div class="col-md-4"><button class="btn btn-primary w-100">Validar archivo</button></div></div></form></div></div>
<h2 class="h5">Importaciones recientes</h2><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Archivo</th><th>Estado</th><th>Válidas</th><th>Errores</th><th></th></tr></thead><tbody>@forelse($imports as $import)<tr><td>{{ $import->archivo_nombre }}</td><td>{{ ucfirst($import->estado) }}</td><td>{{ $import->filas_exitosas }}</td><td>{{ $import->filas_error }}</td><td><a href="{{ route('imports.assets.show', $import->id) }}">Ver</a></td></tr>@empty<tr><td colspan="5" class="text-center p-4 text-body-secondary">Aún no hay importaciones.</td></tr>@endforelse</tbody></table></div></div>
@endsection
