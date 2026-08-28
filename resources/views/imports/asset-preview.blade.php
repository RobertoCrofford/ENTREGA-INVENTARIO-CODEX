@extends('layouts.app')
@section('title', 'Validación de importación')
@section('content')
<h1 class="h3">Validación de importación</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card mb-4"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Archivo</dt><dd class="col-sm-9">{{ $import->archivo_nombre }}</dd><dt class="col-sm-3">Filas válidas</dt><dd class="col-sm-9">{{ $import->filas_exitosas }}</dd><dt class="col-sm-3">Filas con error</dt><dd class="col-sm-9">{{ $import->filas_error }}</dd></dl></div><div class="card-footer d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('imports.assets.rejected', $import->id) }}">Descargar rechazados</a>@if($import->estado === 'lista' && $import->filas_exitosas > 0)<form method="POST" action="{{ route('imports.assets.confirm', $import->id) }}">@csrf<button class="btn btn-primary">Confirmar importación</button></form>@endif</div></div>
<div class="card"><div class="card-header">Errores detectados</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fila</th><th>Campo</th><th>Detalle</th></tr></thead><tbody>@forelse($errors as $error)<tr><td>{{ $error->fila }}</td><td>{{ $error->campo }}</td><td>{{ $error->mensaje }}</td></tr>@empty<tr><td colspan="3" class="text-center p-4 text-body-secondary">No se detectaron errores.</td></tr>@endforelse</tbody></table></div></div>
@endsection
