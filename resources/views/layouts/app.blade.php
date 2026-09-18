<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario Institucional')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="institutional-page">
@auth
    <header class="app-topbar">
        <a class="brand-link" href="{{ route('dashboard') }}"><i class="bi bi-boxes"></i><span>Inventario Institucional</span></a>
        <span class="system-status"><span></span>Operativo</span>
        <div class="topbar-actions">
            @can('ver-notificaciones')
                <div class="dropdown notification-menu" data-notification-refresh-url="{{ route('notifications.summary') }}">
                    <button class="topbar-icon position-relative" type="button" data-bs-toggle="dropdown" data-notification-read-url="{{ route('notifications.read-all') }}" aria-expanded="false" aria-label="Notificaciones"><i class="bi bi-bell"></i>@if($unreadNotifications)<span class="notification-dot">{{ $unreadNotifications }}</span>@endif</button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="notification-dropdown-header"><strong>Notificaciones</strong><span>Últimas {{ $recentNotifications->count() }}</span></div>
                        <div data-notification-items>@forelse($recentNotifications as $notification)
                            @if($notification->open_url)<a class="notification-dropdown-item notification-dropdown-link {{ $notification->leido_at ? '' : 'is-unread' }}" href="{{ $notification->open_url }}">
                            @else<div class="notification-dropdown-item {{ $notification->leido_at ? '' : 'is-unread' }}">
                            @endif
                                <div class="d-flex justify-content-between gap-2"><strong>{{ $notification->titulo }}</strong><time>{{ \Illuminate\Support\Carbon::parse($notification->creado_at)->format('d-m H:i') }}</time></div>
                                <p>{{ $notification->mensaje }}</p>
                                <span class="notification-open-label">Abrir notificación <i class="bi bi-arrow-right"></i></span>
                            @if($notification->open_url)</a>@else</div>@endif
                        @empty
                            <div class="notification-dropdown-empty">No tienes notificaciones.</div>
                        @endforelse</div>
                        <a class="notification-history-link" href="{{ route('notifications.index') }}">Ver historial completo <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            @endcan
            <div class="dropdown">
                <button class="user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle"></i><span>{{ auth()->user()->name }}</span></button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-header">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="sidebar-section">Operación</div>
            <nav class="sidebar-nav">
                <a class="sidebar-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i>Inicio</a>
                <a class="sidebar-link @if(request()->routeIs('scan.*')) active @endif" href="{{ route('scan.index') }}"><i class="bi bi-upc-scan"></i>Escanear</a>
                <a class="sidebar-link @if(request()->routeIs('events.*')) active @endif" href="{{ route('events.index') }}"><i class="bi bi-calendar-event"></i>Eventos</a>
                <a class="sidebar-link @if(request()->routeIs('products.*')) active @endif" href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i>Productos</a>
                <a class="sidebar-link @if(request()->routeIs('assets.*')) active @endif" href="{{ route('assets.index') }}"><i class="bi bi-pc-display"></i>Activos</a>
                <a class="sidebar-link @if(request()->routeIs('asset-disposals.*')) active @endif" href="{{ route('asset-disposals.index') }}"><i class="bi bi-clipboard-x"></i>Bajas de activos</a>
                @can('gestionar-inventario')
                    <a class="sidebar-link @if(request()->routeIs('movements.*')) active @endif" href="{{ route('movements.index') }}"><i class="bi bi-arrow-left-right"></i>Movimientos</a>
                    <a class="sidebar-link @if(request()->routeIs('imports.*')) active @endif" href="{{ route('imports.assets.index') }}"><i class="bi bi-cloud-arrow-up"></i>Importaciones</a>
                @endcan
                <a class="sidebar-link @if(request()->routeIs('repairs.*')) active @endif" href="{{ route('repairs.index') }}"><i class="bi bi-tools"></i>Reparaciones</a>
                <a class="sidebar-link @if(request()->routeIs('warehouses.*')) active @endif" href="{{ route('warehouses.index') }}"><i class="bi bi-building"></i>Bodega</a>
                <a class="sidebar-link @if(request()->routeIs('help.*')) active @endif" href="{{ route('help.index') }}"><i class="bi bi-question-circle"></i>Ayuda</a>
            </nav>
            @canany(['administrar-usuarios', 'generar-bitacora'])
                <div class="sidebar-section mt-4">Administración</div>
                <nav class="sidebar-nav">
                    @can('administrar-usuarios')<a class="sidebar-link @if(request()->routeIs('users.*')) active @endif" href="{{ route('users.index') }}"><i class="bi bi-people"></i>Usuarios</a>@endcan
                    @can('generar-bitacora')<a class="sidebar-link @if(request()->routeIs('audit-logs.*')) active @endif" href="{{ route('audit-logs.index') }}"><i class="bi bi-journal-text"></i>Bitácora</a>@endcan
                </nav>
            @endcanany
            <div class="sidebar-footer"><i class="bi bi-shield-check"></i>Los cambios publicados conservan trazabilidad.</div>
        </aside>
        <main class="app-content">
            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
            @yield('content')
        </main>
    </div>
@else
    <main class="container py-4">@yield('content')</main>
@endauth
</body>
</html>

