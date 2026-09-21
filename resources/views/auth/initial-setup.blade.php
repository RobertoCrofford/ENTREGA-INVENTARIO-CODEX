<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar primera cuenta · Inventario Institucional</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100 institutional-page login-page">
<main class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <section class="card shadow-sm login-card">
                <div class="card-body p-4">
                    <div class="institution-mark mb-3"><i class="bi bi-shield-lock-fill"></i></div>
                    <h1 class="h3 mb-1">Configurar primera cuenta</h1>
                    <p class="text-body-secondary mb-4">Crea la cuenta superadministradora para comenzar a usar el sistema. Esta pantalla se desactiva automáticamente al guardar la primera cuenta.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('setup.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="name">Nombre completo</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Correo institucional</label>
                            <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="username">Usuario</label>
                            <input class="form-control" id="username" name="username" value="{{ old('username') }}" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Contraseña</label>
                            <div class="input-group">
                                <input class="form-control" type="password" id="password" name="password" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                            <div class="form-text">Mínimo 12 caracteres, incluyendo una mayúscula y un número.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary w-100">Crear superadministrador</button>
                        <div class="text-center mt-3"><a class="link-light small" href="{{ route('login') }}">Volver al acceso</a></div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
