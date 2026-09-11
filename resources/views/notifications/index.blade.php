@extends('layouts.app')
@section('title', 'Notificaciones')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Notificaciones</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn btn-outline-primary">Marcar todas como leídas</button>
    </form>
</div>
<div class="card"><div class="list-group list-group-flush">
    @forelse($notifications as $notification)
        <div class="list-group-item {{ $notification->leido_at ? '' : 'list-group-item-warning' }}">
            <div class="d-flex justify-content-between gap-3"><strong>{{ $notification->titulo }}</strong><span class="small text-body-secondary">{{ \Illuminate\Support\Carbon::parse($notification->creado_at)->format('d-m-Y H:i') }}</span></div>
            <div>{{ $notification->mensaje }}</div>
            @if($notification->titulo === 'Código sin registrar' && ! $notification->atendido_at)
                <form method="POST" action="{{ route('notifications.attend', $notification->id) }}" class="mt-2">@csrf<button class="btn btn-sm btn-primary">Atender solicitud</button></form>
            @elseif($notification->atendido_at)
                <div class="small text-success mt-2">Solicitud marcada como atendida.</div>
            @endif
            @if($notification->url)<a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('notifications.open', $notification->id) }}"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir evento</a>@endif
        </div>
    @empty
        <div class="list-group-item text-body-secondary p-4 text-center">No hay notificaciones.</div>
    @endforelse
</div></div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
