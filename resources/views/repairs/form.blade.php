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
                <div class="col-md-8">
                    <label class="form-label" for="buscar_activo">Buscar activo</label>
                    <input class="form-control mb-2" id="buscar_activo" type="search" placeholder="Código, serie, marca o modelo" autocomplete="off" @disabled($assets->isEmpty())>
                    <input id="activo_id" name="activo_id" type="hidden" value="{{ old('activo_id') }}" required>
                    <div id="activo_seleccionado" class="asset-picker-selected d-none" aria-live="polite">
                        <div><span class="eyebrow">Activo seleccionado</span><strong></strong></div>
                        <button class="btn btn-sm btn-outline-light" id="cambiar_activo" type="button">Cambiar</button>
                    </div>
                    <div id="resultados_activos" class="asset-picker-results" role="listbox" aria-label="Resultados de activos">
                        @foreach($assets as $asset)
                            <button class="asset-picker-option" type="button" role="option" data-id="{{ $asset->id }}" data-search="{{ $asset->activo_fijo }} {{ $asset->numero_serie }} {{ $asset->marca }} {{ $asset->modelo }}" data-label="{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }}{{ $asset->numero_serie ? ' · Serie: '.$asset->numero_serie : '' }}">
                                <strong>{{ $asset->activo_fijo }}</strong>
                                <span>{{ $asset->marca }} {{ $asset->modelo }}@if($asset->numero_serie) · Serie {{ $asset->numero_serie }}@endif</span>
                            </button>
                        @endforeach
                    </div>
                    <div id="resultado_busqueda_activo" class="form-text" aria-live="polite">{{ $assets->count() }} activo(s) disponible(s). Escribe para filtrar.</div>
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
                    <label class="form-label" for="evidencias">Ficha técnica Word</label>
                    <input class="form-control" id="evidencias" name="evidencias[]" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                    <div class="form-text">Obligatoria. Adjunta una ficha técnica en formato Word (.docx), de máximo 10 MB.</div>
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
        const assetId = document.getElementById('activo_id');
        const results = document.getElementById('resultados_activos');
        const selected = document.getElementById('activo_seleccionado');
        const selectedLabel = selected?.querySelector('strong');
        const change = document.getElementById('cambiar_activo');
        const feedback = document.getElementById('resultado_busqueda_activo');
        const options = Array.from(results?.querySelectorAll('.asset-picker-option') ?? []);
        if (!search || !assetId || !results || !selected || !selectedLabel || !change || !feedback) return;
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

        const filter = () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));
            feedback.textContent = term === '' ? `${options.length} activo(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s). Selecciona uno.`;
        };
        const select = (option) => {
            assetId.value = option.dataset.id;
            selectedLabel.textContent = option.dataset.label;
            selected.classList.remove('d-none');
            results.classList.add('d-none');
            search.classList.add('d-none');
            feedback.textContent = 'Activo listo para registrar la reparación.';
        };
        results.addEventListener('click', (event) => {
            const option = event.target.closest('.asset-picker-option');
            if (option) select(option);
        });
        change.addEventListener('click', () => {
            assetId.value = '';
            search.value = '';
            selected.classList.add('d-none');
            results.classList.remove('d-none');
            search.classList.remove('d-none');
            filter();
            search.focus();
        });
        search.addEventListener('input', filter);
        const previous = options.find((option) => option.dataset.id === assetId.value);
        if (previous) select(previous);
        else filter();
    });
</script>
@endsection
