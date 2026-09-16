@extends('layouts.app')

@section('title', 'Calendario de eventos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 page-heading">
    <div><div class="eyebrow">Planificación institucional</div><h1>Calendario de eventos</h1><p>Consulta los eventos institucionales y actividades programadas.</p></div>
    @can('gestionar-eventos')
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#import-eventos"><i class="bi bi-file-earmark-excel me-1"></i> Importar Excel</button>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nuevo-evento"><i class="bi bi-calendar-plus me-1"></i> Nuevo evento</button>
        </div>
    @endcan
</div>

@can('gestionar-eventos')
<div class="collapse mb-4" id="import-eventos">
    <div class="card"><div class="card-body">
        <h2 class="h5 mb-2">Importar calendario desde Excel</h2>
        <p class="text-body-secondary small">Selecciona un archivo Excel o CSV de hasta 5 MB. Reconoce el formato de planificación semanal con las columnas Fecha, Nombre de la Actividad / Evento, Horario, Lugar / Espacio, Responsable y Requerimientos. El nombre del archivo no importa.</p>
        <form method="POST" action="{{ route('events.import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">@csrf
            <div class="col-md-6"><label class="form-label" for="archivo_eventos">Archivo de eventos</label><input class="form-control @error('archivo') is-invalid @enderror" id="archivo_eventos" name="archivo" type="file" accept=".xlsx,.csv" required>@error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-2"><label class="form-label" for="anio_eventos">Año</label><input class="form-control @error('anio') is-invalid @enderror" id="anio_eventos" name="anio" type="number" min="2020" max="2100" value="{{ old('anio', now()->year) }}" required>@error('anio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-upload me-1"></i> Registrar eventos</button></div>
        </form>
    </div></div>
</div>
<div class="collapse mb-4 @if($errors->any() && old('titulo')) show @endif" id="nuevo-evento">
    <div class="card"><div class="card-body">
        <h2 class="h5 mb-3">Registrar evento manualmente</h2>
        <form method="POST" action="{{ route('events.store') }}" class="row g-3">@csrf
            <div class="col-md-6"><label class="form-label" for="titulo">Nombre del evento</label><input class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo" value="{{ old('titulo') }}" required>@error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="fecha_inicio">Fecha de inicio</label><input class="form-control @error('fecha_inicio') is-invalid @enderror" id="fecha_inicio" name="fecha_inicio" type="date" value="{{ old('fecha_inicio', now()->toDateString()) }}" required>@error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="fecha_termino">Fecha de término</label><input class="form-control @error('fecha_termino') is-invalid @enderror" id="fecha_termino" name="fecha_termino" type="date" value="{{ old('fecha_termino') }}">@error('fecha_termino')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="hora_inicio">Hora de inicio <span class="text-body-secondary">(opcional)</span></label><input class="form-control @error('hora_inicio') is-invalid @enderror" id="hora_inicio" name="hora_inicio" type="time" value="{{ old('hora_inicio') }}">@error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="hora_termino">Hora de término</label><input class="form-control @error('hora_termino') is-invalid @enderror" id="hora_termino" name="hora_termino" type="time" value="{{ old('hora_termino') }}">@error('hora_termino')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="lugar">Lugar</label><input class="form-control @error('lugar') is-invalid @enderror" id="lugar" name="lugar" value="{{ old('lugar') }}" placeholder="Ej.: Auditorio, sede Maipú">@error('lugar')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" rows="2">{{ old('descripcion') }}</textarea>@error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Guardar evento</button></div>
        </form>
    </div></div>
</div>
@endcan

<div class="card calendar-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2"><a class="btn btn-sm btn-outline-light" href="{{ route('events.index', ['mes' => $previousMonth]) }}" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a><h2 class="h5 mb-0 text-capitalize">{{ $month->translatedFormat('F Y') }}</h2><a class="btn btn-sm btn-outline-light" href="{{ route('events.index', ['mes' => $nextMonth]) }}" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a></div>
    <div class="calendar-grid">
        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dayName)<div class="calendar-weekday">{{ $dayName }}</div>@endforeach
        @foreach($weeks as $week)
            @foreach($week as $day)
                @php
                    $dailyEvents = $eventsByDay->get($day->toDateString(), collect());
                @endphp
                <a class="calendar-day {{ $day->month !== $month->month ? 'is-outside' : '' }} {{ $day->isToday() ? 'is-today' : '' }} {{ $selectedDate === $day->toDateString() ? 'is-selected' : '' }}" href="{{ route('events.index', ['mes' => $day->format('Y-m'), 'dia' => $day->toDateString()]) }}" aria-label="Ver detalles del {{ $day->translatedFormat('d F') }}"><span class="calendar-date">{{ $day->day }}</span>
                    @foreach($dailyEvents->take(3) as $event)<div class="calendar-event" title="{{ $event->titulo }}{{ $event->lugar ? ' · '.$event->lugar : '' }}"><strong>{{ $event->titulo }}</strong>@if(!$event->todo_el_dia)<small>{{ \Illuminate\Support\Carbon::parse($event->inicio_at)->format('H:i') }}</small>@endif</div>@endforeach
                    @if($dailyEvents->count() > 3)<small class="calendar-more">+{{ $dailyEvents->count() - 3 }} más</small>@endif
                </a>
            @endforeach
        @endforeach
    </div>
</div>

@if($selectedDate)
<div class="card mb-4"><div class="card-header"><i class="bi bi-calendar-day me-2"></i>Detalle del {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d \d\e F \d\e Y') }}</div><div class="card-body">
    @forelse($selectedEvents as $event)
        @php
            $details = (string) $event->descripcion;
            preg_match('/Responsable:\s*(.*?)(?:\n\n|$)/us', $details, $responsibleMatch);
            preg_match('/Requerimientos:\s*(.*)$/us', $details, $requirementsMatch);
            $responsible = trim($responsibleMatch[1] ?? '');
            $requirements = trim($requirementsMatch[1] ?? '');
        @endphp
        <article class="event-detail border-bottom pb-4 mb-4 last-child-no-border"><h3 class="h5 text-white mb-3">{{ $event->titulo }}</h3><dl class="row details-list mb-0"><dt class="col-md-3">Horario</dt><dd class="col-md-9">{{ $event->todo_el_dia ? 'Todo el día / por confirmar' : \Illuminate\Support\Carbon::parse($event->inicio_at)->format('H:i').' - '.\Illuminate\Support\Carbon::parse($event->termino_at)->format('H:i') }}</dd><dt class="col-md-3">Lugar / espacio</dt><dd class="col-md-9">{{ $event->lugar ?: 'Sin lugar definido' }}</dd><dt class="col-md-3">Responsable</dt><dd class="col-md-9">{{ $responsible ?: 'Sin responsable informado' }}</dd><dt class="col-md-3">Requerimientos técnicos y logísticos</dt><dd class="col-md-9 event-requirements">{{ $requirements ?: ($details ?: 'Sin requerimientos informados') }}</dd></dl></article>
    @empty
        <p class="text-body-secondary mb-0">No hay eventos registrados para esta fecha.</p>
    @endforelse
</div></div>
@endif

<div class="card"><div class="card-header"><i class="bi bi-clock-history me-2"></i>Próximos eventos</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha</th><th>Evento</th><th>Lugar</th><th>Origen</th></tr></thead><tbody>@forelse($upcomingEvents as $event)<tr><td>{{ \Illuminate\Support\Carbon::parse($event->inicio_at)->format($event->todo_el_dia ? 'd-m-Y' : 'd-m-Y H:i') }}</td><td><strong>{{ $event->titulo }}</strong>@if($event->descripcion)<div class="small text-body-secondary">{{ $event->descripcion }}</div>@endif</td><td>{{ $event->lugar ?: 'Sin lugar definido' }}</td><td><span class="badge text-bg-secondary">{{ $event->origen === 'excel' ? 'Excel' : 'Manual' }}</span></td></tr>@empty<tr class="empty-row"><td colspan="4"><i class="bi bi-calendar-x"></i>Aún no hay eventos próximos.</td></tr>@endforelse</tbody></table></div></div>
@endsection
