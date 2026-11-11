@extends('layouts.mobile')
@section('title', 'Escanear · Inventario móvil')
@section('content')
@php($locationLabel = str_ends_with(mb_strtoupper($inventory->ubicacion_codigo), '-'.mb_strtoupper($inventory->ubicacion_nombre)) ? ucfirst($inventory->ubicacion_tipo).' '.$inventory->ubicacion_nombre : $inventory->ubicacion_codigo.' — '.$inventory->ubicacion_nombre)
<div id="mobileScanner"
    data-lookup-url="{{ route('mobile.inventory.lookup', $inventory->id) }}"
    data-scan-url="{{ route('physical-inventories.scan', $inventory->id) }}"
    data-state-url="{{ route('mobile.inventory.state', $inventory->id) }}"
    data-remove-url-template="{{ route('physical-inventories.scans.destroy', [$inventory->id, '__ASSET__']) }}"
    data-inventory-open="{{ in_array($inventory->estado, ['en_curso', 'pendiente'], true) ? '1' : '0' }}">
    <section class="mobile-scanner-heading">
        <a href="{{ route('mobile.rooms') }}"><i class="bi bi-arrow-left"></i>Cambiar sala</a>
        <span class="mobile-eyebrow">Inventario físico #{{ $inventory->id }}</span>
        <h1>{{ $locationLabel }}</h1>
        <p>{{ collect([$inventory->ubicacion_edificio, $inventory->ubicacion_piso])->filter()->join(' · ') ?: 'Escaneo en sala' }}</p>
    </section>

    <section class="mobile-summary" aria-label="Progreso del inventario">
        <div><span>Esperados</span><strong id="summaryExpected">{{ $summary['esperados'] }}</strong></div>
        <div><span>Encontrados</span><strong id="summaryFound">{{ $summary['encontrados'] }}</strong></div>
        <div><span>Pendientes</span><strong id="summaryPending">{{ $summary['pendientes'] }}</strong></div>
        <div class="mobile-progress"><span id="summaryProgress" style="width: {{ $summary['progreso'] }}%"></span></div>
    </section>

    <div id="scannerAlert" class="mobile-scanner-alert d-none" role="alert" aria-live="assertive"></div>

    @if(in_array($inventory->estado, ['en_curso', 'pendiente'], true))
    <section class="mobile-camera-card">
        <div class="mobile-camera-stage">
            <video id="scannerVideo" playsinline muted aria-label="Vista de la cámara"></video>
            <div class="mobile-camera-placeholder" id="cameraPlaceholder"><i class="bi bi-camera"></i><strong>Cámara lista para escanear</strong><span>Apunta al código de barras o QR del equipo.</span></div>
            <div class="mobile-scan-frame" aria-hidden="true"><span></span></div>
        </div>
        <div class="mobile-camera-actions">
            <button id="startCamera" class="mobile-primary-button" type="button"><i class="bi bi-camera-fill"></i>Abrir cámara</button>
            <button id="stopCamera" class="mobile-secondary-button d-none" type="button"><i class="bi bi-stop-circle"></i>Detener</button>
        </div>
        <p class="mobile-camera-help" id="cameraHelp">El navegador solicitará permiso para utilizar la cámara trasera.</p>
    </section>

    <section class="mobile-manual-card">
        <button class="mobile-collapsible-button" type="button" data-bs-toggle="collapse" data-bs-target="#manualEntry" aria-expanded="false"><span><i class="bi bi-keyboard"></i>Ingresar código manualmente</span><i class="bi bi-chevron-down"></i></button>
        <div class="collapse" id="manualEntry">
            <form id="manualScanForm" class="mobile-manual-form">
                <label for="manualCode">Activo fijo, serie o código</label>
                <div><input id="manualCode" name="codigo" maxlength="40" pattern="[A-Za-z0-9-]+" autocomplete="off" required><button type="submit">Buscar</button></div>
            </form>
        </div>
    </section>
    @else
        <div class="alert alert-info">Esta revisión está finalizada y solo puede consultarse.</div>
    @endif

    <section class="mobile-section mobile-recent-section" aria-labelledby="recent-scans-title">
        <div class="mobile-section-heading"><div><span class="mobile-eyebrow">Sincronización inmediata</span><h2 id="recent-scans-title">Últimos escaneos</h2></div><button id="refreshScans" class="mobile-icon-button" type="button" aria-label="Actualizar lecturas"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div id="recentScans" class="mobile-scan-list">
            @forelse($scans as $scan)
                <article class="mobile-scan-item" data-asset-id="{{ $scan->activo_id }}"><div class="mobile-scan-icon {{ $scan->resultado === 'encontrado' ? 'is-ok' : 'is-warning' }}"><i class="bi {{ $scan->resultado === 'encontrado' ? 'bi-check2' : 'bi-exclamation' }}"></i></div><div><strong>{{ $scan->activo_fijo }}</strong><span>{{ trim(($scan->marca ?: '').' '.($scan->modelo ?: '')) ?: 'Sin marca o modelo' }}</span><small>{{ $scan->escaneado_por_nombre ?: 'Usuario anterior' }} · {{ \Illuminate\Support\Carbon::parse($scan->escaneado_at)->format('d-m-Y H:i') }}</small></div>@if(in_array($inventory->estado, ['en_curso', 'pendiente'], true))<button class="mobile-remove-scan" type="button" data-remove-scan="{{ $scan->activo_id }}" aria-label="Quitar lectura {{ $scan->activo_fijo }}"><i class="bi bi-x-lg"></i></button>@endif</article>
            @empty
                <div class="mobile-empty-state"><i class="bi bi-upc-scan"></i><p>Aún no hay equipos escaneados.</p></div>
            @endforelse
        </div>
    </section>

    <div class="modal fade" id="confirmAssetModal" tabindex="-1" aria-labelledby="confirmAssetTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content mobile-confirm-modal"><div class="modal-header"><div><span class="mobile-eyebrow">Confirmación</span><h2 class="modal-title fs-5" id="confirmAssetTitle">¿Registrar este equipo?</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><dl class="mobile-asset-details"><div><dt>Activo fijo</dt><dd id="confirmFixedCode">—</dd></div><div><dt>Número de serie</dt><dd id="confirmSerial">—</dd></div><div><dt>Marca / modelo</dt><dd id="confirmModel">—</dd></div><div><dt>Ubicación actual</dt><dd id="confirmLocation">—</dd></div></dl></div><div class="modal-footer"><button type="button" class="mobile-secondary-button" data-bs-dismiss="modal">Cancelar</button><button id="confirmScan" type="button" class="mobile-primary-button"><i class="bi bi-check2-circle"></i>Confirmar</button></div></div></div>
    </div>
</div>
@endsection
