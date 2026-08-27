<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error · Inventario Institucional</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="institutional-page error-page">
    <main class="error-panel">
        <div class="error-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <span class="eyebrow">Error {{ $status }}</span>
        <h1>No se pudo completar la acción</h1>
        <p>{{ $message }}</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a class="btn btn-primary" href="{{ route('dashboard') }}"><i class="bi bi-house-door me-1"></i>Ir al panel</a>
            <a class="btn btn-outline-primary" href="#" onclick="window.history.back(); return false;"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>
    </main>
</body>
</html>

