<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#082a57">
    <meta name="application-name" content="Inventario móvil">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Inventario">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/images/inventory-mobile-icon.svg" type="image/svg+xml">
    <title>@yield('title', 'Escaneo · Inventario móvil')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/mobile-pwa.js', 'resources/js/mobile-asset-scanner.js', 'resources/js/mobile-scanner.js'])
</head>
<body class="mobile-inventory-body">
    <header class="mobile-app-header">
        <a class="mobile-app-brand" href="{{ route('mobile.index') }}" aria-label="Inicio de escaneo"><span class="mobile-app-mark"><i class="bi bi-upc-scan"></i></span><span><strong>Inventario móvil</strong><small>Escaneo</small></span></a>
        <div class="dropdown">
            <button class="mobile-user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle"></i><span>{{ auth()->user()->name }}</span></button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><span class="dropdown-header">{{ auth()->user()->rol?->nombre ?? 'Usuario' }}</span></li>
                <li><button id="pwaInstallButton" class="dropdown-item" type="button" hidden><i class="bi bi-phone me-2"></i>Instalar aplicación</button></li>
                <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-display me-2"></i>Ir a la web completa</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
            </ul>
        </div>
    </header>
    <div id="mobileConnectionStatus" class="mobile-connection-status" role="status" aria-live="polite" hidden></div>
    <main class="mobile-app-main">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
        @yield('content')
    </main>
    <nav class="mobile-bottom-nav" aria-label="Navegación móvil">
        <a class="{{ request()->routeIs('mobile.index', 'mobile.lookup') ? 'active' : '' }}" href="{{ route('mobile.index') }}"><i class="bi bi-upc-scan"></i><span>Escanear</span></a>
        <a class="{{ request()->routeIs('mobile.rooms', 'mobile.inventory.*') ? 'active' : '' }}" href="{{ route('mobile.rooms') }}"><i class="bi bi-door-open"></i><span>Revisar sala</span></a>
        <a href="{{ route('dashboard') }}" target="_blank" rel="noopener"><i class="bi bi-grid-1x2"></i><span>Panel web</span></a>
    </nav>
    @stack('scripts')
</body>
</html>
