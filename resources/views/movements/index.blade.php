@extends('layouts.app')
@section('title','Movimientos')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">Historial de movimientos</h1>
    @can('gestionar-inventario')
        <a class="btn btn-primary" href="{{ route('movements.create') }}">Registrar movimiento</a>
    @endcan
</div>
<form class="mb-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar por folio, producto, razón o responsable"></form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Folio</th><th>Producto</th><th>Movimiento</th><th>Responsable</th><th>Razón</th><th>Fecha</th></tr></thead>
    <tbody>
    @forelse($movements as $movement)
        <tr>
            <td>{{ $movement->folio }}</td>
            <td>{{ $movement->item_nombre ?: 'Activo fijo' }}<div class="small text-body-secondary">{{ $movement->item_codigo }} · Cantidad: {{ $movement->cantidad }}</div></td>
            <td>{{ ucfirst($movement->tipo) }}<div class="small text-body-secondary">{{ ucfirst($movement->estado) }}</div></td>
            <td>{{ $movement->publicado_por_nombre ?? $movement->creado_por_nombre }}</td>
            <td>{{ $movement->motivo }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($movement->publicado_at ?? $movement->created_at)->format('d-m-Y H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center p-4 text-body-secondary">Aún no hay movimientos registrados.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
