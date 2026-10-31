@extends('layouts.app')
@section('title','Movimientos')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">Historial de movimientos</h1>
    @can('gestionar-inventario')
        <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('movements.group.create') }}"><i class="bi bi-collection me-1"></i>Movimiento grupal</a><a class="btn btn-primary" href="{{ route('movements.create') }}">Registrar movimiento</a></div>
    @endcan
</div>
<form class="mb-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar por folio, producto, activo fijo, razón o responsable"></form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Folio</th><th>Producto / Activo</th><th>Movimiento</th><th>Receptor</th><th>Responsable</th><th>Razón</th><th>Fecha</th></tr></thead>
    <tbody>
    @forelse($movements as $movement)
        <tr>
            <td>{{ $movement->folio }}</td>
            <td>{{ $movement->item_nombre ?: 'Activo fijo' }}<div class="small text-body-secondary">{{ $movement->item_codigo }} · Cantidad: {{ $movement->cantidad }}</div></td>
            <td>{{ ucfirst($movement->tipo) }}<div class="small text-body-secondary">{{ ucfirst($movement->estado) }}</div></td>
            <td>@if($movement->receptor_nombre)<strong>{{ $movement->receptor_nombre }}</strong><div class="small text-body-secondary">{{ collect([$movement->receptor_cargo, $movement->receptor_departamento])->filter()->join(' · ') ?: 'Sin cargo registrado' }}</div>@if($movement->receptor_confirmado_at)<span class="badge text-bg-success mt-1">Recepción confirmada</span>@elseif(auth()->user()->can('gestionar-inventario'))<form method="POST" action="{{ route('movements.confirm-receipt', $movement->id) }}" class="mt-1">@csrf<button class="btn btn-sm btn-outline-primary">Confirmar recepción</button></form>@endif @else<span class="text-body-secondary">Sin receptor registrado</span>@endif</td>
            <td>{{ $movement->publicado_por_nombre ?? $movement->creado_por_nombre }}</td>
            <td>{{ $movement->motivo }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($movement->publicado_at ?? $movement->created_at)->format('d-m-Y H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center p-4 text-body-secondary">Aún no hay movimientos registrados.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
