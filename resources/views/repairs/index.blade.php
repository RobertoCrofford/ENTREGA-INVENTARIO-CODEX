@extends('layouts.app')

@section('title', 'Reparaciones')

@section('content')
@php($canManageRepairs = auth()->user()->can('gestionar-inventario'))

<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div>
        <span class="eyebrow">Mantenimiento</span>
        <h1 class="h3 mb-1">Reparaciones</h1>
        <p class="text-body-secondary mb-0">Casos registrados y técnicos asignados.</p>
    </div>
    @if($canManageRepairs)
        <a class="btn btn-primary" href="{{ route('repairs.create') }}"><i class="bi bi-plus-lg me-1"></i>Registrar reparación</a>
    @endif
</div>

<form class="mb-3" method="GET">
    <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por activo, técnico o falla reportada">
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr><th>Activo</th><th>Técnico</th><th>Prioridad</th><th>Falla reportada</th><th>Evidencia</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
                @if($repairs->isEmpty())
                    <tr>
                        <td class="p-5 text-center text-body-secondary" colspan="7"><i class="bi bi-tools d-block fs-3 mb-2"></i>No hay reparaciones registradas.</td>
                    </tr>
                @else
                    @foreach($repairs as $repair)
                        @php($canCloseRepair = auth()->id() === $repair->tecnico_id || auth()->user()->tieneRol('director_tecnico', 'superadmin'))
                        <tr>
                            <td>{{ $repair->asset?->activo_fijo ?? 'Activo no disponible' }}<div class="small text-body-secondary">{{ $repair->asset?->marca }} {{ $repair->asset?->modelo }}</div></td>
                            <td>{{ $repair->technician?->name ?? 'Técnico no disponible' }}</td>
                            <td><span class="repair-priority priority-{{ $repair->prioridad }}">{{ ucfirst($repair->prioridad) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($repair->falla_reportada, 90) }}</td>
                            <td>@forelse($repair->evidences as $evidence)<a class="d-inline-block me-1 mb-1" href="{{ route('repairs.evidence', [$repair, $evidence]) }}" target="_blank" title="Ver {{ $evidence->nombre_original }}"><img src="{{ route('repairs.evidence', [$repair, $evidence]) }}" alt="Evidencia de daño" class="rounded border" style="width:42px;height:42px;object-fit:cover"></a>@empty<span class="text-body-secondary small">Sin fotos</span>@endforelse</td>
                            <td><span class="repair-status">{{ str_replace('_', ' ', ucfirst($repair->estado)) }}</span></td>
                            <td>
                                @if($canManageRepairs && $repair->estado !== 'resuelta' && $canCloseRepair)
                                    <form method="POST" action="{{ route('repairs.complete', $repair) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Cerrar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $repairs->links() }}</div>
@endsection

