<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recuperar contraseña · Inventario Institucional</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-body-tertiary d-flex align-items-center min-vh-100 institutional-page login-page"><main class="container"><div class="row justify-content-center"><div class="col-md-6 col-lg-4"><section class="card shadow-sm login-card"><div class="card-body p-4"><div class="institution-mark mb-3"><i class="bi bi-key-fill"></i></div><h1 class="h3 mb-1">Recuperar contraseña</h1><p class="text-body-secondary mb-4">Ingresa tu correo institucional y te enviaremos un enlace temporal.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf
<div class="mb-3"><label class="form-label" for="email">Correo institucional</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
<button class="btn btn-primary w-100">Enviar enlace</button><div class="text-center mt-3"><a class="link-light small" href="{{ route('login') }}">Volver al inicio de sesión</a></div></form></div></section></div></div></main></body></html>
