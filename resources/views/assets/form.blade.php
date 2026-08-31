@extends('layouts.app')

@section('title', $asset->exists ? 'Editar activo' : 'Registrar activo')

@section('content')
@if($scanCode ?? null)
    <div class="alert alert-info"><i class="bi bi-upc-scan me-1"></i>Código escaneado: <strong>{{ $scanCode }}</strong>. Se asociará al activo y queda precargado como activo fijo.</div>
@endif
<h1 class="h3">{{ $asset->exists ? 'Editar activo' : 'Registrar activo' }}</h1>
<form method="POST" class="card" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
    <div class="card-body">
        @csrf
        @if($asset->exists) @method('PUT') @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        @if($asset->exists && $asset->status?->codigo === 'dado_baja')
            <div class="alert alert-warning"><i class="bi bi-lock-fill me-1"></i>Este activo está dado de baja. Sus datos y estado no se pueden modificar directamente.</div>
        @endif
        <fieldset @disabled($asset->exists && $asset->status?->codigo === 'dado_baja')>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Sede</label><select name="sede_id" class="form-select" required>@foreach($sites as $site)<option value="{{ $site->id }}" @selected(old('sede_id', $asset->sede_id ?? $sites->firstWhere('codigo', 'MAIPU')?->id) == $site->id)>{{ $site->nombre }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Tipo</label><select name="tipo_activo_id" class="form-select">@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('tipo_activo_id', $asset->tipo_activo_id) == $type->id)>{{ $type->nombre }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Estado</label><select name="estado_activo_id" class="form-select" @disabled($asset->exists)>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(old('estado_activo_id', $asset->estado_activo_id ?? $statuses->firstWhere('codigo', 'operativo')?->id) == $status->id)>{{ $status->nombre }}</option>@endforeach</select>@if($asset->exists)<div class="form-text">Usa el bloque “Cambio de estado” para mantener el historial.</div>@endif</div>
            <div class="col-md-4"><label class="form-label">Uso</label><select name="uso" class="form-select" required><option value="administrativo" @selected(old('uso', $asset->uso ?? 'administrativo') === 'administrativo')>Administrativo</option><option value="alumnos" @selected(old('uso', $asset->uso) === 'alumnos')>Alumnos</option><option value="docente" @selected(old('uso', $asset->uso) === 'docente')>Docente</option><option value="comun" @selected(old('uso', $asset->uso) === 'comun')>Uso común</option></select></div>
            <div class="col-md-6"><label class="form-label">Activo fijo</label><input name="activo_fijo" class="form-control" value="{{ old('activo_fijo', $asset->activo_fijo ?: ($scanCode ?? '')) }}" required></div>
            <div class="col-md-6"><label class="form-label">Número de serie</label><input name="numero_serie" class="form-control" value="{{ old('numero_serie', $asset->numero_serie) }}"></div>
            <div class="col-md-6"><label class="form-label">Marca</label><input name="marca" class="form-control" value="{{ old('marca', $asset->marca) }}"></div>
            <div class="col-md-6"><label class="form-label">Modelo</label><input name="modelo" class="form-control" value="{{ old('modelo', $asset->modelo) }}"></div>
            <div class="col-md-6"><label class="form-label">Procesador <span class="text-body-secondary fw-normal">(obligatorio para PC)</span></label><input name="procesador" class="form-control" value="{{ old('procesador', $processor) }}">@error('procesador')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Costo neto</label><input type="number" step=".01" min="0" name="costo_neto_actual" class="form-control" value="{{ old('costo_neto_actual', $asset->costo_neto_actual) }}" required></div>
            <div class="col-md-6"><label class="form-label">Ubicación</label><select name="ubicacion_actual_id" class="form-select"><option value="">Sin ubicación</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('ubicacion_actual_id', $asset->ubicacion_actual_id) == $location->id)>{{ $location->nombre }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Responsable</label><input name="responsable_nombre" class="form-control" value="{{ old('responsable_nombre', $asset->responsable_nombre) }}"></div>
            <div class="col-md-4"><label class="form-label">Correo responsable</label><input type="email" name="responsable_email" class="form-control" value="{{ old('responsable_email', $asset->responsable_email) }}"></div>
            <div class="col-md-4"><label class="form-label">Departamento</label><input name="responsable_departamento" class="form-control" value="{{ old('responsable_departamento', $asset->responsable_departamento) }}"></div>
            <div class="col-12"><label class="form-label">Observación</label><textarea name="observacion" class="form-control">{{ old('observacion', $asset->observacion) }}</textarea></div>
        </div>
        </fieldset>
    </div>
    <div class="card-footer">
        @if(! $asset->exists || $asset->status?->codigo !== 'dado_baja')
            <button class="btn btn-primary">Guardar</button>
        @endif
        <a href="{{ route('assets.index') }}" class="btn btn-link">Cancelar</a>
    </div>
</form>
@if($asset->exists && $asset->status?->codigo !== 'dado_baja')
    <form class="card mt-3" method="POST" action="{{ route('assets.status', $asset) }}">
        <div class="card-body">
            @csrf
            <h2 class="h5">Cambio de estado</h2>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label visually-hidden" for="estado">Nuevo estado</label>
                    <select id="estado" name="estado" class="form-select" required @disabled($statuses->isEmpty())>
                        @forelse($statuses as $status)
                            <option value="{{ $status->codigo }}" @selected($asset->status?->codigo === $status->codigo)>{{ $status->nombre }}</option>
                        @empty
                            <option value="">No hay estados disponibles</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-md-5"><input name="motivo" class="form-control" placeholder="Motivo del cambio" required></div>
                <div class="col-md-4"><label class="form-label visually-hidden" for="ubicacion_estado">Ubicación</label><select id="ubicacion_estado" name="ubicacion_actual_id" class="form-select"><option value="">Conservar ubicación actual</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach</select></div>
                <div class="col-md-3"><button class="btn btn-outline-primary w-100" @disabled($statuses->isEmpty())>Registrar</button></div>
            </div>
            @if($statuses->isEmpty())
                <p class="text-danger small mt-2 mb-0">No existen estados activos. Ejecuta el seeder de la base de datos.</p>
            @endif
        </div>
    </form>
@endif

@if($asset->exists && $asset->status?->codigo === 'dado_baja' && auth()->user()->tieneRol('superadmin'))
    <form class="card mt-3" method="POST" action="{{ route('assets.reincorporate', $asset) }}">
        <div class="card-body">
            @csrf
            <span class="eyebrow">Acción exclusiva</span>
            <h2 class="h5 mt-1">Reincorporar activo</h2>
            <p class="text-body-secondary">El activo volverá a estado operativo. Debes indicar una ubicación y el motivo de esta excepción.</p>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Ubicación de reincorporación</label>
                    <select name="ubicacion_actual_id" class="form-select" required @disabled($locations->isEmpty())>
                        <option value="">{{ $locations->isEmpty() ? 'No hay ubicaciones disponibles' : 'Selecciona una ubicación' }}</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->nombre }}</option>@endforeach
                    </select>
                    @if($locations->isEmpty())<div class="text-danger small mt-1">Ejecuta el seeder para cargar Bodega, SSDD y Sala.</div>@endif
                </div>
                <div class="col-md-7"><label class="form-label">Motivo de reincorporación</label><textarea name="motivo" class="form-control" rows="3" minlength="10" required placeholder="Explica por qué se reincorpora el activo dado de baja."></textarea></div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit" @disabled($locations->isEmpty())><i class="bi bi-arrow-counterclockwise me-1"></i>Reincorporar activo</button></div>
    </form>
@endif
@endsection

