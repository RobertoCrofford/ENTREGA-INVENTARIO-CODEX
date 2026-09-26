@extends('layouts.app')

@section('title', 'Estado del sistema')

@section('content')
<div class="mb-4">
    <span class="eyebrow">Supervisión</span>
    <h1 class="h3 mb-1">Estado del sistema</h1>
    <p class="text-body-secondary mb-0">Revisión automática de los servicios críticos. Las alertas solo aparecen cuando requieren atención.</p>
</div>

<div class="card mb-4 manual-backup-card"><div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><h2 class="h6 mb-1"><i class="bi bi-database-add me-2"></i>Respaldo manual</h2><p class="text-body-secondary mb-0">Solicita una copia adicional antes de una actualización o un cambio importante.</p></div><form method="POST" action="{{ route('system-status.backup') }}" data-confirm-message="Se solicitará una copia adicional de la base de datos. ¿Deseas continuar?">@csrf<button class="btn btn-outline-primary" type="submit"><i class="bi bi-shield-plus me-1"></i>Generar respaldo ahora</button></form></div></div>

<div class="alert {{ $operational ? 'alert-success' : 'alert-warning' }} mb-4">
    <i class="bi {{ $operational ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-2"></i>
    {{ $operational ? 'Todo se encuentra operativo.' : 'Hay elementos que requieren revisión.' }}
</div>

<div class="row g-3">
    @foreach(['database' => ['Base de datos', 'bi-database'], 'backup' => ['Respaldo', 'bi-shield-check'], 'queue' => ['Cola de tareas', 'bi-list-task'], 'imports' => ['Importaciones', 'bi-cloud-arrow-up']] as $key => [$label, $icon])
        @php($check = $checks[$key])
        <div class="col-md-6">
            <div class="card system-check-card {{ $check['ok'] ? 'is-ok' : 'is-warning' }} h-100">
                <div class="card-body d-flex gap-3">
                    <i class="bi {{ $icon }} system-check-icon"></i>
                    <div><h2 class="h6 mb-1">{{ $label }}</h2><p class="mb-0 text-body-secondary">{{ $check['message'] }}</p></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
