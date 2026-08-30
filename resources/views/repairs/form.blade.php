@extends('layouts.app')

@section('title', 'Registrar reparación')

@section('content')
<div class="mb-4">
    <span class="eyebrow">Mantenimiento</span>
    <h1 class="h3 mb-1">Registrar reparación</h1>
    <p class="text-body-secondary mb-0">Busca el activo, asigna un técnico e informa la falla.</p>
</div>

<div class="card">
    <div class="card-body p-4">
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="buscar_activo">Buscar activo</label>
                    <input class="form-control" id="buscar_activo" type="search" placeholder="Activo fijo, serie, marca o modelo" autocomplete="off">
                    <div class="form-text">Escribe para reducir el listado y luego selecciona el activo.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="activo_id">Activo seleccionado</label>
                    <select class="form-select" id="activo_id" name="activo_id" size="5" required>
                        <option value="">Selecciona un activo</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" data-search="{{ $asset->activo_fijo }} {{ $asset->numero_serie }} {{ $asset->marca }} {{ $asset->modelo }}" @selected(old('activo_id') == $asset->id)>
                                {{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }}{{ $asset->numero_serie ? ' · Serie: '.$asset->numero_serie : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('activo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="tecnico_id">Técnico asignado</label>
                    <select class="form-select" id="tecnico_id" name="tecnico_id" required>
                        <option value="">Selecciona un técnico</option>
                        @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected(old('tecnico_id') == $technician->id)>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="prioridad">Prioridad</label>
                    <select class="form-select" id="prioridad" name="prioridad" required>
                        @foreach(['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('prioridad', 'media') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="falla_reportada">Falla reportada</label>
                    <textarea class="form-control" id="falla_reportada" name="falla_reportada" rows="5" required>{{ old('falla_reportada') }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label" for="evidencias">Evidencia fotográfica <span class="text-body-secondary fw-normal">(opcional)</span></label>
                    <input class="form-control" id="evidencias" name="evidencias[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="form-text">Puedes adjuntar hasta 5 fotos JPG, PNG o WEBP, de máximo 5 MB cada una.</div>
                    @error('evidencias')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @error('evidencias.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary"><i class="bi bi-tools me-1"></i>Registrar reparación</button>
                <a class="btn btn-outline-primary" href="{{ route('repairs.index') }}">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const search = document.getElementById('buscar_activo');
        const assets = document.getElementById('activo_id');
        const options = Array.from(assets.options).filter((option) => option.value);
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

        search.addEventListener('input', () => {
            const term = normalize(search.value.trim());

            options.forEach((option) => {
                option.hidden = term !== '' && !normalize(option.dataset.search).includes(term);
            });
        });
    });
</script>
@endsection
