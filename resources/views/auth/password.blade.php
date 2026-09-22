@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('content')
<div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body p-4"><h1 class="h4">Cambiar contraseña</h1><p class="text-body-secondary">Usa al menos 12 caracteres, mayúsculas y números.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">@csrf @method('PUT')
<div class="mb-3"><label class="form-label" for="current_password">Contraseña actual</label><div class="input-group"><input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle aria-controls="current_password" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
<div class="mb-3"><label class="form-label" for="password">Nueva contraseña</label><div class="input-group"><input class="form-control" type="password" id="password" name="password" required autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
<div class="mb-3"><label class="form-label" for="password_confirmation">Confirmar contraseña</label><div class="input-group"><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle aria-controls="password_confirmation" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
<button class="btn btn-primary">Guardar contraseña</button></form></div></div></div></div>
@endsection
