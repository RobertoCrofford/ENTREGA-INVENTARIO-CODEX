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

<form class="mb-3" method="GET" role="search">
    <label class="visually-hidden" for="repair-search">Buscar reparaciones</label>
    <div class="input-group">
        <input id="repair-search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por activo, serie, técnico o falla reportada">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
        @if(request()->filled('q'))
            <a class="btn btn-outline-secondary" href="{{ route('repairs.index') }}">Limpiar</a>
        @endif
    </div>
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
                            <td>@forelse($repair->evidences as $evidence)@php($extension = strtolower(pathinfo($evidence->nombre_original, PATHINFO_EXTENSION)))<a class="btn btn-sm btn-outline-primary" href="{{ route('repairs.evidence', [$repair, $evidence]) }}" target="_blank" title="Abrir ficha técnica {{ $evidence->nombre_original }}"><i class="bi bi-file-earmark-word me-1"></i>Ficha técnica</a>@empty<span class="text-body-secondary small">Sin ficha técnica</span>@endforelse</td>
                            <td><span class="repair-status">{{ str_replace('_', ' ', ucfirst($repair->estado)) }}</span></td>
                            <td>
                                @if($canManageRepairs && $repair->estado !== 'resuelta' && $canCloseRepair)
                                    <form method="POST" action="{{ route('repairs.complete', $repair) }}" class="d-grid gap-2" style="min-width: 250px">
                                        @csrf
                                        <label class="visually-hidden" for="estado_final_{{ $repair->id }}">Estado final</label>
                                        <select class="form-select form-select-sm" id="estado_final_{{ $repair->id }}" name="estado_final" required><option value="operativo">Operativo</option><option value="no_operativo">No operativo</option></select>
                                        <label class="visually-hidden" for="resultado_{{ $repair->id }}">Resultado</label>
                                        <textarea class="form-control form-control-sm" id="resultado_{{ $repair->id }}" name="resultado" rows="2" minlength="5" maxlength="2000" placeholder="Resultado de la reparación" required></textarea>
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Cerrar</button>
                                    </form>
                                    <form method="POST" action="{{ route('repairs.cancel', $repair) }}" class="d-grid gap-2 mt-2" style="min-width: 250px">
                                        @csrf
                                        <label class="visually-hidden" for="motivo_cancelacion_{{ $repair->id }}">Motivo de cancelación</label>
                                        <textarea class="form-control form-control-sm" id="motivo_cancelacion_{{ $repair->id }}" name="motivo_cancelacion" rows="2" minlength="5" maxlength="2000" placeholder="Motivo de cancelación" required></textarea>
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>Cancelar reparación</button>
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

