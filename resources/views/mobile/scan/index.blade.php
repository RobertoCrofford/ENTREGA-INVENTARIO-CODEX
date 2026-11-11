@extends('layouts.mobile')
@section('title', 'Escanear equipo · Inventario móvil')
@section('content')
<div id="mobileAssetLookup" data-lookup-url="{{ route('mobile.lookup') }}">
    <section class="mobile-hero">
        <span class="mobile-eyebrow">Consulta rápida</span>
        <h1>Escanear equipo</h1>
        <p>Lee el código de barras o QR para conocer la sala y toda la información del PC.</p>
    </section>

    <div id="assetLookupAlert" class="mobile-scanner-alert d-none" role="alert" aria-live="assertive"></div>

    <aside id="assetCameraPrivacy" class="mobile-camera-privacy">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <div><strong>Uso seguro de la cámara</strong><span>Se utiliza únicamente para leer la etiqueta. No se guardan fotografías ni videos.</span></div>
    </aside>

    <section class="mobile-camera-card">
        <div class="mobile-camera-stage">
            <video id="assetScannerVideo" playsinline muted aria-label="Vista de la cámara"></video>
            <div class="mobile-camera-placeholder" id="assetCameraPlaceholder"><i class="bi bi-upc-scan"></i><strong>Listo para escanear</strong><span>Apunta al código de barras o QR pegado en el equipo.</span></div>
            <div class="mobile-scan-frame" aria-hidden="true"><span></span></div>
        </div>
        <div class="mobile-camera-actions">
            <button id="startAssetCamera" class="mobile-primary-button" type="button"><i class="bi bi-camera-fill"></i><span>Activar cámara y escanear</span></button>
            <button id="stopAssetCamera" class="mobile-secondary-button d-none" type="button"><i class="bi bi-stop-circle"></i>Detener</button>
        </div>
        <p class="mobile-camera-help" id="assetCameraHelp">Admite QR y códigos de barras. No registra un inventario físico.</p>
    </section>

    <section class="mobile-manual-card">
        <button class="mobile-collapsible-button" type="button" data-bs-toggle="collapse" data-bs-target="#assetManualEntry" aria-expanded="false"><span><i class="bi bi-keyboard"></i>Ingresar código manualmente</span><i class="bi bi-chevron-down"></i></button>
        <div class="collapse" id="assetManualEntry">
            <form id="assetManualForm" class="mobile-manual-form">
                <label for="assetManualCode">Activo fijo, número de serie o código</label>
                <div><input id="assetManualCode" name="codigo" maxlength="40" pattern="[A-Za-z0-9-]+" autocomplete="off" required><button type="submit">Buscar</button></div>
            </form>
        </div>
    </section>

    <section id="assetLookupResult" class="mobile-asset-result d-none" aria-live="polite">
        <div class="mobile-result-heading"><span class="mobile-result-icon"><i class="bi bi-check2-circle"></i></span><div><span class="mobile-eyebrow">Equipo encontrado</span><h2 id="assetResultCode">—</h2></div></div>
        <div id="assetLocationBanner" class="mobile-location-banner"><i class="bi bi-geo-alt-fill"></i><div><span>Ubicación actual</span><strong id="assetResultLocation">—</strong><small id="assetResultLocationDetail"></small></div></div>
        <dl class="mobile-asset-details">
            <div><dt>Número de serie</dt><dd id="assetResultSerial">—</dd></div>
            <div><dt>Tipo</dt><dd id="assetResultType">—</dd></div>
            <div><dt>Estado</dt><dd id="assetResultStatus">—</dd></div>
            <div><dt>Marca / modelo</dt><dd id="assetResultModel">—</dd></div>
            <div><dt>Uso</dt><dd id="assetResultUsage">—</dd></div>
            <div><dt>Responsable</dt><dd id="assetResultResponsible">—</dd></div>
            <div><dt>Departamento</dt><dd id="assetResultDepartment">—</dd></div>
            <div><dt>Observación</dt><dd id="assetResultObservation">—</dd></div>
        </dl>
        <div class="mobile-result-actions"><button id="scanAnotherAsset" class="mobile-primary-button" type="button"><i class="bi bi-upc-scan"></i>Escanear otro</button><a id="assetResultLink" class="mobile-secondary-button" href="#"><i class="bi bi-card-list"></i>Ver ficha</a></div>
    </section>

    <a class="mobile-room-option" href="{{ route('mobile.rooms') }}"><span><i class="bi bi-door-open"></i></span><div><strong>Revisar una sala</strong><small>Iniciar o continuar un inventario físico por ubicación.</small></div><i class="bi bi-chevron-right"></i></a>
</div>
@endsection
