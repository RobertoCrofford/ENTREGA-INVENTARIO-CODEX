<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nueva contraseña · Inventario Institucional</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100 institutional-page login-page"><main class="container"><div class="row justify-content-center"><div class="col-md-6 col-lg-4"><section class="card shadow-sm login-card"><div class="card-body p-4"><div class="institution-mark mb-3"><i class="bi bi-shield-lock-fill"></i></div><h1 class="h3 mb-1">Crear nueva contraseña</h1><p class="text-body-secondary mb-4">Usa al menos 12 caracteres, mayúsculas y números.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.store') }}">@csrf
<input type="hidden" name="token" value="{{ $request->route('token') }}">
<div class="mb-3"><label class="form-label" for="email">Correo institucional</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="email"></div>
<div class="mb-3"><label class="form-label" for="password">Nueva contraseña</label><input class="form-control" type="password" id="password" name="password" required autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
<button class="btn btn-primary w-100">Actualizar contraseña</button></form></div></section></div></div></main></body></html>
