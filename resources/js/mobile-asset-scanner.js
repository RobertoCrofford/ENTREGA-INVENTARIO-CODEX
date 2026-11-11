import { BrowserMultiFormatReader } from '@zxing/browser';

const root = document.getElementById('mobileAssetLookup');

if (root) {
    const video = document.getElementById('assetScannerVideo');
    const placeholder = document.getElementById('assetCameraPlaceholder');
    const startButton = document.getElementById('startAssetCamera');
    const stopButton = document.getElementById('stopAssetCamera');
    const help = document.getElementById('assetCameraHelp');
    const alertBox = document.getElementById('assetLookupAlert');
    const resultCard = document.getElementById('assetLookupResult');
    const manualForm = document.getElementById('assetManualForm');
    const manualCode = document.getElementById('assetManualCode');
    const privacyNote = document.getElementById('assetCameraPrivacy');
    let controls = null;
    let busy = false;
    let lastCode = '';
    let lastCodeAt = 0;

    const announce = (message, type = 'info') => {
        alertBox.textContent = message;
        alertBox.className = `mobile-scanner-alert is-${type}`;
    };

    const feedback = (success) => {
        if (navigator.vibrate) navigator.vibrate(success ? [120] : [80, 70, 80]);
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.frequency.value = success ? 880 : 240;
            gain.gain.setValueAtTime(0.08, context.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.18);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.18);
        } catch (_) {}
    };

    const stopCamera = () => {
        controls?.stop();
        controls = null;
        if (video?.srcObject) video.srcObject.getTracks().forEach((track) => track.stop());
        if (video) video.srcObject = null;
        placeholder?.classList.remove('d-none');
        startButton?.classList.remove('d-none');
        stopButton?.classList.add('d-none');
        if (help) help.textContent = 'Admite QR y códigos de barras. No registra un inventario físico.';
    };

    const syncCameraPermission = async () => {
        try {
            const permission = await navigator.permissions?.query({ name: 'camera' });
            if (!permission) return;
            const render = () => {
                const label = startButton?.querySelector('span');
                if (label) label.textContent = permission.state === 'granted' ? 'Escanear código' : 'Activar cámara y escanear';
                privacyNote?.classList.toggle('is-granted', permission.state === 'granted');
            };
            render();
            permission.addEventListener?.('change', render);
        } catch (_) {}
    };

    const cameraErrorMessage = (error) => {
        const messages = {
            NotAllowedError: 'El permiso de cámara está bloqueado. Habilítalo desde el candado de la barra de direcciones y vuelve a intentar.',
            NotFoundError: 'No se encontró una cámara disponible en este dispositivo.',
            NotReadableError: 'La cámara está siendo utilizada por otra aplicación. Ciérrala y vuelve a intentar.',
            OverconstrainedError: 'No se pudo seleccionar la cámara trasera. Se intentó utilizar otra cámara disponible.',
            SecurityError: 'El navegador bloqueó la cámara. Utiliza HTTPS o abre la aplicación mediante localhost.',
            TypeError: 'La cámara requiere HTTPS o un navegador compatible como Chrome, Edge o Safari.',
        };

        return messages[error?.name] || 'Este navegador no pudo abrir la cámara. Prueba en Chrome o Edge y revisa el permiso de cámara.';
    };

    const openCamera = async (reader) => {
        try {
            return await reader.decodeFromConstraints(
                { audio: false, video: { facingMode: { ideal: 'environment' } } },
                video,
                (result) => { if (result && !busy) lookup(result.getText()); },
            );
        } catch (error) {
            if (error?.name !== 'OverconstrainedError') throw error;

            return reader.decodeFromConstraints(
                { audio: false, video: true },
                video,
                (result) => { if (result && !busy) lookup(result.getText()); },
            );
        }
    };

    const showAsset = (asset) => {
        const location = asset.ubicacion;
        document.getElementById('assetResultCode').textContent = asset.activo_fijo;
        document.getElementById('assetResultLocation').textContent = location ? `${location.codigo} — ${location.nombre}` : 'Sin sala asignada';
        document.getElementById('assetResultLocationDetail').textContent = location ? [location.tipo, location.edificio, location.piso].filter(Boolean).join(' · ') : 'Este equipo requiere asignación de ubicación.';
        document.getElementById('assetLocationBanner').classList.toggle('is-unassigned', !location);
        document.getElementById('assetResultSerial').textContent = asset.numero_serie;
        document.getElementById('assetResultType').textContent = asset.tipo;
        document.getElementById('assetResultStatus').textContent = asset.estado;
        document.getElementById('assetResultModel').textContent = asset.marca_modelo;
        document.getElementById('assetResultUsage').textContent = asset.uso;
        document.getElementById('assetResultResponsible').textContent = asset.responsable;
        document.getElementById('assetResultDepartment').textContent = asset.departamento;
        document.getElementById('assetResultObservation').textContent = asset.observacion;
        document.getElementById('assetResultLink').href = asset.url;
        resultCard.classList.remove('d-none');
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const lookup = async (rawCode) => {
        const code = String(rawCode || '').trim();
        if (!code || busy) return;
        const now = Date.now();
        if (code === lastCode && now - lastCodeAt < 2500) return;
        lastCode = code;
        lastCodeAt = now;
        busy = true;
        resultCard.classList.add('d-none');
        announce(`Buscando ${code}…`, 'info');
        try {
            const response = await fetch(`${root.dataset.lookupUrl}?codigo=${encodeURIComponent(code)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) {
                feedback(false);
                announce(data.message || 'No se encontró el equipo.', data.status === 'duplicate' ? 'warning' : 'danger');
                return;
            }
            stopCamera();
            feedback(true);
            announce(data.message, 'success');
            showAsset(data.asset);
            if (manualCode) manualCode.value = '';
        } catch (_) {
            announce('No hay conexión con el servidor. Revisa la red e inténtalo nuevamente.', 'danger');
        } finally {
            window.setTimeout(() => { busy = false; }, 700);
        }
    };

    const startCamera = async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            announce('La cámara requiere HTTPS. Puedes continuar mediante ingreso manual.', 'danger');
            return;
        }
        resultCard.classList.add('d-none');
        alertBox.classList.add('d-none');
        startButton.disabled = true;
        try {
            const reader = new BrowserMultiFormatReader();
            controls = await openCamera(reader);
            privacyNote?.classList.add('is-granted');
            const label = startButton?.querySelector('span');
            if (label) label.textContent = 'Escanear código';
            placeholder.classList.add('d-none');
            startButton.classList.add('d-none');
            stopButton.classList.remove('d-none');
            help.textContent = 'Cámara activa. Centra el QR o código de barras dentro del recuadro.';
        } catch (error) {
            announce(cameraErrorMessage(error), 'danger');
        } finally {
            startButton.disabled = false;
        }
    };

    startButton?.addEventListener('click', startCamera);
    stopButton?.addEventListener('click', stopCamera);
    manualForm?.addEventListener('submit', (event) => { event.preventDefault(); lookup(manualCode.value); });
    document.getElementById('scanAnotherAsset')?.addEventListener('click', () => {
        resultCard.classList.add('d-none');
        alertBox.classList.add('d-none');
        startCamera();
    });
    window.addEventListener('pagehide', stopCamera);
    syncCameraPermission();
}
