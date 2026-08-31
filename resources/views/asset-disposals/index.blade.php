@extends('layouts.app')

@section('title', 'Bajas de activos')

@section('content')
<div class="mb-4"><span class="eyebrow">Gobernanza</span><h1 class="h3 mb-1">Bajas de activos</h1><p class="text-body-secondary mb-0">Toda baja requiere una solicitud y una resolución registrada.</p></div>

@can('gestionar-inventario')
<form class="card mb-4" method="POST" action="{{ route('asset-disposals.store') }}">
    @csrf
    <div class="card-header">Nueva solicitud</div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="activo_id">Activo</label><select class="form-select" id="activo_id" name="activo_id" required><option value="">Selecciona un activo</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="motivo">Motivo</label><textarea class="form-control" id="motivo" name="motivo" minlength="10" maxlength="2000" required>{{ old('motivo') }}</textarea></div>
        <div class="col-md-4"><label class="form-label" for="diagnostico">Diagnóstico</label><textarea class="form-control" id="diagnostico" name="diagnostico" minlength="10" maxlength="2000" required>{{ old('diagnostico') }}</textarea></div>
    </div>@if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif</div>
    <div class="card-footer"><button class="btn btn-primary" @disabled($assets->isEmpty())>Enviar solicitud</button></div>
</form>
@endcan

<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Activo</th><th>Solicitante</th><th>Motivo</th><th>Estado</th><th>Resolución</th></tr></thead><tbody>
@forelse($requests as $disposal)
<tr><td>{{ $disposal->activo_fijo }}<div class="small text-body-secondary">{{ $disposal->marca }} {{ $disposal->modelo }}</div></td><td>{{ $disposal->solicitante_nombre }}</td><td>{{ $disposal->motivo }}<div class="small text-body-secondary">{{ $disposal->diagnostico }}</div></td><td>{{ ucfirst($disposal->estado) }}</td><td>
    @if($disposal->estado === 'pendiente' && auth()->user()->can('autorizar-operaciones') && ($disposal->solicitado_por !== auth()->id() || auth()->user()->tieneRol('superadmin')))
        <form method="POST" action="{{ route('asset-disposals.approve', $disposal->id) }}" class="mb-2">@csrf
            @if($disposal->solicitado_por === auth()->id() && auth()->user()->tieneRol('superadmin'))<textarea class="form-control form-control-sm mb-2" name="justificacion_autoaprobacion" minlength="20" maxlength="2000" placeholder="Justificación reforzada de autoaprobación" required></textarea>@endif
            <button class="btn btn-sm btn-success">Aprobar</button>
        </form>
        @if($disposal->solicitado_por !== auth()->id())<form method="POST" action="{{ route('asset-disposals.reject', $disposal->id) }}">@csrf<textarea class="form-control form-control-sm mb-2" name="comentario" minlength="10" maxlength="2000" placeholder="Comentario obligatorio" required></textarea><button class="btn btn-sm btn-outline-danger">Rechazar</button></form>@endif
    @elseif($disposal->estado_pdf === 'generado')
        <a class="btn btn-sm btn-outline-primary" href="{{ route('asset-disposals.download', $disposal->id) }}">Descargar acta</a>
    @else
        <span class="text-body-secondary">{{ $disposal->resolutor_nombre ?? 'Pendiente' }}</span>
    @endif
</td></tr>
@empty<tr><td colspan="5" class="text-center text-body-secondary p-4">No existen solicitudes.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $requests->links() }}</div>
@endsection
