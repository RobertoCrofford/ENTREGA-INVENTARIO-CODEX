@extends('layouts.print')
@section('title', 'Ficha de activo '.$asset->activo_fijo)
@section('content')
<header class="header"><h1>Ficha de activo</h1><p class="muted">Inventario Institucional · Emitida el {{ now()->format('d-m-Y H:i') }}</p></header>
<dl class="meta">
    <div><dt>Activo fijo</dt><dd><strong>{{ $asset->activo_fijo }}</strong></dd></div>
    <div><dt>Estado</dt><dd>{{ $asset->status->nombre }}</dd></div>
    <div><dt>Tipo</dt><dd>{{ $asset->type->nombre }}</dd></div>
    <div><dt>Uso</dt><dd>{{ ['administrativo' => 'Administrativo', 'alumnos' => 'Alumnos', 'docente' => 'Docente', 'comun' => 'Uso común', 'sin_definir' => 'Sin definir'][$asset->uso] ?? 'Sin definir' }}</dd></div>
    <div><dt>Número de serie</dt><dd>{{ $asset->numero_serie ?: '—' }}</dd></div>
    <div><dt>Marca / modelo</dt><dd>{{ trim(($asset->marca ?: '').' '.($asset->modelo ?: '')) ?: '—' }}</dd></div>
    <div><dt>Ubicación actual</dt><dd>{{ $asset->location ? $asset->location->codigo.' — '.$asset->location->nombre : '—' }}</dd></div>
    <div><dt>Responsable</dt><dd>{{ $asset->responsable_nombre ?: 'Sin responsable asignado' }}</dd></div>
    <div><dt>Departamento</dt><dd>{{ $asset->responsable_departamento ?: '—' }}</dd></div>
    <div><dt>Correo responsable</dt><dd>{{ $asset->responsable_email ?: '—' }}</dd></div>
</dl>
<h2>Observación</h2><p>{{ $asset->observacion ?: 'Sin observaciones registradas.' }}</p>
<footer class="footer">Documento informativo generado por Inventario Institucional. La información corresponde al estado registrado al momento de la impresión.</footer>
@endsection
