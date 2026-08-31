<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso · Inventario Institucional</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100 institutional-page login-page"><main class="container"><div class="row justify-content-center"><div class="col-md-6 col-lg-4"><section class="card shadow-sm login-card"><div class="card-body p-4"><div class="institution-mark mb-3"><i class="bi bi-building-fill"></i></div><h1 class="h3 mb-1">Inventario Institucional</h1><p class="text-body-secondary mb-4">Ingresa con tu cuenta institucional.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}">@csrf
<div class="mb-3"><label class="form-label" for="username">Usuario</label><input class="form-control" id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"></div>
<div class="mb-3"><label class="form-label" for="password">Contraseña</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Mantener sesión</label></div>
<button class="btn btn-primary w-100">Ingresar</button><div class="text-center mt-3"><a class="link-light small" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div></form></div></section></div></div></main></body></html>
