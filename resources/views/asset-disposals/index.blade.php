@extends('layouts.app')

@section('title', 'Bajas de activos')

@section('content')
<div class="mb-4"><span class="eyebrow">Gobernanza</span><h1 class="h3 mb-1">Bajas de activos</h1><p class="text-body-secondary mb-0">Toda baja requiere una solicitud y una resolución registrada.</p></div>

@can('solicitar-baja-activo')
<form class="card mb-4" id="formulario_baja_activo" method="POST" action="{{ route('asset-disposals.store') }}">
    @csrf
    <div class="card-header">Nueva solicitud</div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="buscar_activo_baja">Buscar activo</label><input class="form-control mb-2" id="buscar_activo_baja" type="search" placeholder="Código, serie, marca o modelo" autocomplete="off"><input id="activo_id" name="activo_id" type="hidden" value="{{ old('activo_id') }}" required><div id="activo_seleccionado_baja" class="asset-picker-selected d-none" aria-live="polite"><div><span class="eyebrow">Activo seleccionado</span><strong></strong></div><button class="btn btn-sm btn-outline-light" id="cambiar_activo_baja" type="button">Cambiar</button></div><div id="resultados_activos_baja" class="asset-picker-results" role="listbox" aria-label="Resultados de activos">@foreach($assets as $asset)<button class="asset-picker-option" type="button" role="option" data-id="{{ $asset->id }}" data-search="{{ $asset->activo_fijo }} {{ $asset->numero_serie }} {{ $asset->marca }} {{ $asset->modelo }}" data-label="{{ $asset->activo_fijo }} · {{ $asset->marca }} {{ $asset->modelo }}"><strong>{{ $asset->activo_fijo }}</strong><span>{{ $asset->marca }} {{ $asset->modelo }}@if($asset->numero_serie) · Serie {{ $asset->numero_serie }}@endif</span></button>@endforeach</div><div id="resultado_busqueda_baja" class="form-text" aria-live="polite">{{ $assets->count() }} activo(s) disponible(s). Escribe para filtrar.</div>@error('activo_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="motivo">Motivo</label><textarea class="form-control" id="motivo" name="motivo" minlength="10" maxlength="2000" required>{{ old('motivo') }}</textarea></div>
        <div class="col-md-4"><label class="form-label" for="diagnostico">Diagnóstico</label><textarea class="form-control" id="diagnostico" name="diagnostico" minlength="10" maxlength="2000" required>{{ old('diagnostico') }}</textarea></div>
    </div>@if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif</div>
    <div class="card-footer"><button class="btn btn-primary" @disabled($assets->isEmpty())>Enviar solicitud</button></div>
</form>
@endcan

@can('solicitar-baja-activo')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('formulario_baja_activo');
        const search = document.getElementById('buscar_activo_baja');
        const assets = document.getElementById('activo_id');
        const feedback = document.getElementById('resultado_busqueda_baja');
        const results = document.getElementById('resultados_activos_baja');
        const selected = document.getElementById('activo_seleccionado_baja');
        const selectedLabel = selected?.querySelector('strong');
        const change = document.getElementById('cambiar_activo_baja');
        if (!form || !search || !assets || !feedback || !results || !selected || !selectedLabel || !change) return;
        const options = Array.from(results.querySelectorAll('.asset-picker-option'));
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const filter = () => {
            const term = normalize(search.value.trim());
            const matches = options.filter((option) => term === '' || normalize(option.dataset.search).includes(term));
            options.forEach((option) => option.hidden = !matches.includes(option));
            feedback.textContent = term === '' ? `${options.length} activo(s) disponible(s).` : `${matches.length} resultado(s) encontrado(s). Selecciona uno.`;
        };
        const select = (option) => {
            assets.value = option.dataset.id;
            selectedLabel.textContent = option.dataset.label;
            selected.classList.remove('d-none');
            results.classList.add('d-none');
            search.classList.add('d-none');
            feedback.textContent = 'Activo listo para la solicitud de baja.';
        };
        results.addEventListener('click', (event) => {
            const option = event.target.closest('.asset-picker-option');
            if (option) select(option);
        });
        change.addEventListener('click', () => {
            assets.value = '';
            search.value = '';
            selected.classList.add('d-none');
            results.classList.remove('d-none');
            search.classList.remove('d-none');
            filter();
            search.focus();
        });
        search.addEventListener('input', filter);
        form.addEventListener('submit', (event) => {
            if (!assets.value) {
                const visibleOptions = options.filter((option) => !option.hidden);
                if (visibleOptions.length === 1) select(visibleOptions[0]);
            }
            if (!assets.value) {
                event.preventDefault();
                feedback.textContent = 'Debes seleccionar un activo de la lista antes de enviar la solicitud.';
                search.classList.remove('d-none');
                results.classList.remove('d-none');
                search.focus();
            }
        });
        const previous = options.find((option) => option.dataset.id === assets.value);
        if (previous) {
            select(previous);
        } else {
            filter();
        }
    });
</script>
@endcan

<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Activo</th><th>Solicitante</th><th>Motivo</th><th>Estado</th><th>Resolución</th></tr></thead><tbody>
@forelse($requests as $disposal)
<tr><td>{{ $disposal->activo_fijo }}<div class="small text-body-secondary">{{ $disposal->marca }} {{ $disposal->modelo }}</div></td><td>{{ $disposal->solicitante_nombre }}</td><td>{{ $disposal->motivo }}<div class="small text-body-secondary">{{ $disposal->diagnostico }}</div></td><td>{{ ucfirst($disposal->estado) }}</td><td>
    @if($disposal->estado === 'pendiente' && auth()->user()->can('autorizar-operaciones') && ($disposal->solicitado_por !== auth()->id() || auth()->user()->tieneRol('superadmin')))
        <form method="POST" action="{{ route('asset-disposals.approve', $disposal->id) }}" class="mb-2">@csrf
            @if($disposal->solicitado_por === auth()->id() && auth()->user()->tieneRol('superadmin'))<textarea class="form-control form-control-sm mb-2" name="justificacion_autoaprobacion" minlength="20" maxlength="2000" placeholder="Justificación reforzada de autoaprobación" required></textarea>@endif
            <button class="btn btn-sm btn-success">Aprobar</button>
        </form>
        @if($disposal->solicitado_por !== auth()->id())<form method="POST" action="{{ route('asset-disposals.reject', $disposal->id) }}">@csrf<textarea class="form-control form-control-sm mb-2" name="comentario" minlength="10" maxlength="2000" placeholder="Comentario obligatorio" required></textarea><button class="btn btn-sm btn-outline-danger">Rechazar</button></form>@endif
    @elseif($disposal->estado_pdf === 'generado')
        <a class="btn btn-sm btn-outline-primary" href="{{ route('asset-disposals.download', $disposal->id) }}">Descargar acta</a>
    @else
        <span class="text-body-secondary">{{ $disposal->resolutor_nombre ?? 'Pendiente' }}</span>
    @endif
</td></tr>
@empty<tr><td colspan="5" class="text-center text-body-secondary p-4">No existen solicitudes.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $requests->links() }}</div>
@endsection
