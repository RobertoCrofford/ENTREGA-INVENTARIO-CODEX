@extends('layouts.app')
@section('title', 'Panel de inventario')
@section('content')
<section class="page-heading d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><span class="eyebrow">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</span><h1>Panel de inventario</h1><p>Resumen operativo y accesos según tu rol.</p></div>
    <span class="trace-badge"><i class="bi bi-shield-check"></i>Operación trazable</span>
</section>
<section class="row g-3 dashboard-stats mb-4">
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-pc-display"></i><span>Activos registrados</span><strong>{{ $totalActivos ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-check2-circle"></i><span>Activos operativos</span><strong>{{ $activosOperativos ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-tags"></i><span>Por clasificar</span><strong>{{ $activosSinClasificar ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-box-seam"></i><span>Stock agotado</span><strong>{{ $stockAgotado ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-tools"></i><span>En reparación</span><strong>{{ $activosReparacion ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-clipboard-x"></i><span>Bajas pendientes</span><strong>{{ $solicitudesPendientes ?? 0 }}</strong></article></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-person-badge"></i><span>Tu acceso</span><strong class="stat-role">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</strong></article></div>
</section>
<section class="card activity-card"><div class="card-body p-0"><div class="card-titlebar"><div><span class="eyebrow">Actividad</span><h2>Movimientos recientes</h2></div><a href="{{ route('movements.index') }}" class="btn btn-outline-light btn-sm">Ver movimientos</a></div><div class="table-responsive"><table class="table dashboard-table mb-0"><thead><tr><th>Folio</th><th>Tipo</th><th>Fecha</th></tr></thead><tbody>@forelse(($movimientosRecientes ?? []) as $movement)<tr><td>{{ $movement->folio }}</td><td>{{ ucfirst($movement->tipo) }}</td><td>{{ $movement->publicado_at }}</td></tr>@empty<tr><td colspan="3" class="empty-row"><i class="bi bi-arrow-left-right"></i><span>Aún no hay movimientos. Crea uno desde la sección Movimientos.</span></td></tr>@endforelse</tbody></table></div></div></section>
@endsection

