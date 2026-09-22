@extends('layouts.app')
@section('title', 'Panel de inventario')
@section('content')
<section class="page-heading d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><span class="eyebrow">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</span><h1>Panel de inventario</h1><p>Resumen operativo y accesos según tu rol.</p></div>
    <span class="trace-badge"><i class="bi bi-shield-check"></i>Operación trazable</span>
</section>
@if($isInvited ?? false)
<section class="card"><div class="card-body"><h2 class="h5">Acceso de invitado</h2><p class="text-body-secondary mb-3">Puedes escanear equipos, revisar el calendario y enviar solicitudes de baja. La información operativa y las exportaciones están restringidas.</p><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('scan.index') }}"><i class="bi bi-upc-scan me-1"></i>Escanear equipo</a><a class="btn btn-outline-light" href="{{ route('asset-disposals.index') }}"><i class="bi bi-clipboard-x me-1"></i>Mis solicitudes de baja</a></div></div></section>
@else
<section class="row g-3 dashboard-stats mb-4">
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('assets.index', ['buscar' => 1]) }}" aria-label="Ver todos los activos registrados"><i class="bi bi-pc-display"></i><span>Activos registrados · Ver inventario</span><strong>{{ $totalActivos ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('assets.index', ['estado' => 'operativo']) }}" aria-label="Ver activos operativos"><i class="bi bi-check2-circle"></i><span>Activos operativos · Ver equipos</span><strong>{{ $activosOperativos ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('assets.index', ['uso' => 'sin_definir']) }}" aria-label="Ver activos por clasificar"><i class="bi bi-tags"></i><span>Por clasificar · Ver equipos</span><strong>{{ $activosSinClasificar ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('products.index', ['stock_agotado' => 1]) }}" aria-label="Ver productos sin stock"><i class="bi bi-box-seam"></i><span>Stock agotado · Ver productos</span><strong>{{ $stockAgotado ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('repairs.index') }}" aria-label="Ver reparaciones"><i class="bi bi-tools"></i><span>En reparación · Ver casos</span><strong>{{ $activosReparacion ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card text-decoration-none" href="{{ route('asset-disposals.index') }}" aria-label="Ver bajas pendientes"><i class="bi bi-clipboard-x"></i><span>Bajas pendientes · Ver solicitudes</span><strong>{{ $solicitudesPendientes ?? 0 }}</strong></a></div>
    <div class="col-sm-6 col-xl-3"><article class="stat-card"><i class="bi bi-person-badge"></i><span>Tu acceso</span><strong class="stat-role">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</strong></article></div>
</section>
<section class="card activity-card"><div class="card-body p-0"><div class="card-titlebar"><div><span class="eyebrow">Actividad</span><h2>Movimientos recientes</h2></div><a href="{{ route('movements.index') }}" class="btn btn-outline-light btn-sm">Ver movimientos</a></div><div class="table-responsive"><table class="table dashboard-table mb-0"><thead><tr><th>Folio</th><th>Tipo</th><th>Fecha</th></tr></thead><tbody>@forelse(($movimientosRecientes ?? []) as $movement)<tr><td>{{ $movement->folio }}</td><td>{{ ucfirst($movement->tipo) }}</td><td>{{ $movement->publicado_at }}</td></tr>@empty<tr><td colspan="3" class="empty-row"><i class="bi bi-arrow-left-right"></i><span>Aún no hay movimientos. Crea uno desde la sección Movimientos.</span></td></tr>@endforelse</tbody></table></div></div></section>
@endif
@endsection

