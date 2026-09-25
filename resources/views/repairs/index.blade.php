@extends('layouts.app')

@section('title', 'Reparaciones')

@section('content')
@php($canManageRepairs = auth()->user()->can('gestionar-inventario'))

<div class="repair-page-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <span class="eyebrow">Mantenimiento</span>
        <h1 class="h3 mb-1"><i class="bi bi-tools me-2 text-primary"></i>Reparaciones</h1>
        <p class="text-body-secondary mb-0">Casos registrados y técnicos asignados.</p>
    </div>
    @if($canManageRepairs)
        <a class="btn btn-primary repair-create-button" href="{{ route('repairs.create') }}"><i class="bi bi-plus-lg me-1"></i>Registrar reparación</a>
    @endif
</div>

<form class="repair-search-card mb-4" method="GET" role="search">
    <label class="visually-hidden" for="repair-search">Buscar reparaciones</label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="repair-search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por activo, serie, técnico o falla reportada">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
        @if(request()->filled('q'))
            <a class="btn btn-outline-secondary" href="{{ route('repairs.index') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="card repairs-panel">
    <div class="card-header repair-list-header">
        <div>
            <span class="eyebrow">Seguimiento técnico</span>
            <strong>Listado de reparaciones</strong>
        </div>
        <span class="repair-total"><i class="bi bi-clipboard2-check me-1"></i>{{ $repairs->total() }} {{ $repairs->total() === 1 ? 'caso' : 'casos' }}</span>
    </div>
    <div class="table-responsive">
        <table class="table repair-table mb-0">
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
                        @php($canReplaceEvidence = $canManageRepairs && $repair->estado === 'abierta' && $canCloseRepair)
                        <tr>
                            <td><strong class="repair-asset-code">{{ $repair->asset?->activo_fijo ?? 'Activo no disponible' }}</strong><div class="small text-body-secondary">{{ $repair->asset?->marca }} {{ $repair->asset?->modelo }}</div></td>
                            <td><span class="repair-technician"><i class="bi bi-person-gear me-1"></i>{{ $repair->technician?->name ?? 'Técnico no disponible' }}</span></td>
                            <td><span class="repair-priority priority-{{ $repair->prioridad }}">{{ ucfirst($repair->prioridad) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($repair->falla_reportada, 90) }}</td>
                            <td>
                                @forelse($repair->evidences as $evidence)
                                    <a class="btn btn-sm btn-outline-primary repair-evidence-button" href="{{ route('repairs.evidence', [$repair, $evidence]) }}" target="_blank" title="Abrir ficha técnica {{ $evidence->nombre_original }}"><i class="bi bi-file-earmark-word me-1"></i>Ficha técnica</a>
                                    @if($canReplaceEvidence)
                                        <form method="POST" action="{{ route('repairs.evidences.replace', [$repair, $evidence]) }}" enctype="multipart/form-data" class="repair-evidence-replace mt-2">
                                            @csrf
                                            @method('PUT')
                                            <label class="visually-hidden" for="evidencia_{{ $evidence->id }}">Nueva ficha técnica</label>
                                            <input class="form-control form-control-sm mb-1" id="evidencia_{{ $evidence->id }}" name="evidencia" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                                            <button class="btn btn-sm btn-outline-warning w-100" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Reemplazar ficha</button>
                                        </form>
                                    @endif
                                @empty
                                    <span class="text-body-secondary small">Sin ficha técnica</span>
                                @endforelse
                            </td>
                            <td><span class="repair-status">{{ str_replace('_', ' ', ucfirst($repair->estado)) }}</span></td>
                            <td class="repair-actions">
                                @if($canManageRepairs && $repair->estado !== 'resuelta' && $canCloseRepair)
                                    <form method="POST" action="{{ route('repairs.complete', $repair) }}" class="repair-action-form d-grid gap-2">
                                        @csrf
                                        <label class="visually-hidden" for="estado_final_{{ $repair->id }}">Estado final</label>
                                        <select class="form-select form-select-sm" id="estado_final_{{ $repair->id }}" name="estado_final" required><option value="operativo">Operativo</option><option value="no_operativo">No operativo</option></select>
                                        <label class="visually-hidden" for="resultado_{{ $repair->id }}">Resultado</label>
                                        <textarea class="form-control form-control-sm" id="resultado_{{ $repair->id }}" name="resultado" rows="2" minlength="5" maxlength="2000" placeholder="Resultado de la reparación" required></textarea>
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Cerrar</button>
                                    </form>
                                    <form method="POST" action="{{ route('repairs.cancel', $repair) }}" class="repair-action-form repair-cancel-form d-grid gap-2 mt-2">
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

