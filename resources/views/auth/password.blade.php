@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('content')
<div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body p-4"><h1 class="h4">Cambiar contraseña</h1><p class="text-body-secondary">Usa al menos 12 caracteres, mayúsculas y números.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Contraseña actual</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div>
<div class="mb-3"><label class="form-label">Nueva contraseña</label><input class="form-control" type="password" name="password" required autocomplete="new-password"></div>
<div class="mb-3"><label class="form-label">Confirmar contraseña</label><input class="form-control" type="password" name="password_confirmation" required autocomplete="new-password"></div>
<button class="btn btn-primary">Guardar contraseña</button></form></div></div></div></div>
@endsection
