<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inventario Institucional')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary institutional-page">
<nav class="navbar navbar-expand-lg navbar-dark institutional-navbar">
    <div class="container"><a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">Inventario Institucional</a>
        @auth
            <div class="d-flex align-items-center gap-3 text-white small">
                <a class="link-light text-decoration-none" href="{{ route('dashboard') }}">Inicio</a>
                @can('gestionar-inventario')
                    <a class="link-light text-decoration-none" href="{{ route('products.index') }}">Productos</a><a class="link-light text-decoration-none" href="{{ route('movements.index') }}">Movimientos</a><a class="link-light text-decoration-none" href="{{ route('assets.index') }}">Activos</a>
                @else
                    <a class="link-light text-decoration-none" href="{{ route('products.index') }}">Buscar</a>
                @endcan
                <a class="link-light text-decoration-none" href="{{ route('warehouses.index') }}">Bodega</a><a class="link-light text-decoration-none" href="{{ route('scan.index') }}">Escanear</a>
                @can('administrar-usuarios')
                    <a class="link-light text-decoration-none" href="{{ route('users.index') }}">Usuarios</a>
                @endcan
                @can('generar-bitacora')
                    <a class="link-light text-decoration-none" href="{{ route('audit-logs.index') }}">Bitácora</a>
                @endcan
                <span>{{ auth()->user()->name }} · {{ auth()->user()->rol->nombre }}</span>
                @can('ver-notificaciones')
                    <a class="link-light text-decoration-none position-relative" href="{{ route('notifications.index') }}" title="Notificaciones" aria-label="Notificaciones">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 16a2.5 2.5 0 0 0 2.5-2.5h-5A2.5 2.5 0 0 0 8 16m0-14a4.5 4.5 0 0 0-4.5 4.5c0 1.2-.36 2.78-1.24 4.36L1 13h14l-1.26-2.14C12.86 9.28 12.5 7.7 12.5 6.5A4.5 4.5 0 0 0 8 2"/></svg>
                        @if($unreadNotifications)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadNotifications }}<span class="visually-hidden">notificaciones no leídas</span></span>
                        @endif
                    </a>
                @endcan
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="btn btn-sm btn-outline-light">Salir</button></form>
            </div>
        @endauth
    </div>
</nav>
<main class="container py-4 app-content">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
    @yield('content')
</main>
</body>
</html>
