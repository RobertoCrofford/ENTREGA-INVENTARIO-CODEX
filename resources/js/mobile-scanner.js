import { BrowserMultiFormatReader } from '@zxing/browser';
import { Modal } from 'bootstrap';

const root = document.getElementById('mobileScanner');

if (root) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const video = document.getElementById('scannerVideo');
    const placeholder = document.getElementById('cameraPlaceholder');
    const startButton = document.getElementById('startCamera');
    const stopButton = document.getElementById('stopCamera');
    const help = document.getElementById('cameraHelp');
    const alertBox = document.getElementById('scannerAlert');
    const manualForm = document.getElementById('manualScanForm');
    const manualCode = document.getElementById('manualCode');
    const confirmButton = document.getElementById('confirmScan');
    const modalElement = document.getElementById('confirmAssetModal');
    const modal = modalElement ? new Modal(modalElement) : null;
    let cameraControls = null;
    let pendingCode = null;
    let busy = false;
    let lastCode = '';
    let lastCodeAt = 0;

    const announce = (message, type = 'info') => {
        alertBox.textContent = message;
        alertBox.className = `mobile-scanner-alert is-${type}`;
        window.clearTimeout(announce.timer);
        announce.timer = window.setTimeout(() => alertBox.classList.add('d-none'), 6000);
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

    const updateSummary = (summary) => {
        if (!summary) return;
        document.getElementById('summaryExpected').textContent = summary.esperados;
        document.getElementById('summaryFound').textContent = summary.encontrados;
        document.getElementById('summaryPending').textContent = summary.pendientes;
        document.getElementById('summaryProgress').style.width = `${summary.progreso}%`;
    };

    const renderScans = (scans) => {
        const list = document.getElementById('recentScans');
        list.replaceChildren();
        if (!scans?.length) {
            const empty = document.createElement('div');
            empty.className = 'mobile-empty-state';
            empty.innerHTML = '<i class="bi bi-upc-scan" aria-hidden="true"></i><p>Aún no hay equipos escaneados.</p>';
            list.append(empty);
            return;
        }

        scans.forEach((scan) => {
            const item = document.createElement('article');
            item.className = 'mobile-scan-item';
            item.dataset.assetId = scan.asset_id;
            const icon = document.createElement('div');
            icon.className = `mobile-scan-icon ${scan.resultado === 'encontrado' ? 'is-ok' : 'is-warning'}`;
            icon.innerHTML = `<i class="bi ${scan.resultado === 'encontrado' ? 'bi-check2' : 'bi-exclamation'}" aria-hidden="true"></i>`;
            const detail = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = scan.activo_fijo;
            const model = document.createElement('span');
            model.textContent = scan.marca_modelo;
            const audit = document.createElement('small');
            audit.textContent = `${scan.escaneado_por} · ${scan.escaneado_at_label}`;
            detail.append(title, model, audit);
            item.append(icon, detail);
            if (root.dataset.inventoryOpen === '1') {
                const remove = document.createElement('button');
                remove.className = 'mobile-remove-scan';
                remove.type = 'button';
                remove.dataset.removeScan = scan.asset_id;
                remove.setAttribute('aria-label', `Quitar lectura ${scan.activo_fijo}`);
                remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
                item.append(remove);
            }
            list.append(item);
        });
    };

    const fillConfirmation = (asset) => {
        document.getElementById('confirmFixedCode').textContent = asset.activo_fijo || '—';
        document.getElementById('confirmSerial').textContent = asset.numero_serie || 'No registrado';
        document.getElementById('confirmModel').textContent = asset.marca_modelo || 'Sin marca o modelo';
        document.getElementById('confirmLocation').textContent = asset.ubicacion || 'Sin ubicación';
    };

    const readJson = async (response) => {
        try { return await response.json(); } catch (_) { return { message: 'No se pudo interpretar la respuesta del servidor.' }; }
    };

    const lookup = async (rawCode) => {
        const code = String(rawCode || '').trim();
        if (!code || busy) return;
        const now = Date.now();
        if (code === lastCode && now - lastCodeAt < 2500) return;
        lastCode = code;
        lastCodeAt = now;
        busy = true;
        announce(`Buscando ${code}…`, 'info');

        try {
            const response = await fetch(`${root.dataset.lookupUrl}?codigo=${encodeURIComponent(code)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await readJson(response);
            if (!response.ok) {
                feedback(false);
                announce(data.message || 'No fue posible reconocer el código.', data.status === 'duplicate' ? 'warning' : 'danger');
                busy = false;
                return;
            }

            pendingCode = code;
            fillConfirmation(data.asset);
            alertBox.classList.add('d-none');
            modal?.show();
        } catch (_) {
            announce('No hay conexión con el servidor. Revisa la red e inténtalo nuevamente.', 'danger');
            busy = false;
        }
    };

    const registerPendingScan = async () => {
        if (!pendingCode) return;
        confirmButton.disabled = true;
        try {
            const response = await fetch(root.dataset.scanUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ codigo: pendingCode }),
            });
            const data = await readJson(response);
            modal?.hide();
            if (!response.ok) {
                feedback(false);
                announce(data.message || 'No fue posible registrar la lectura.', data.status === 'duplicate' ? 'warning' : 'danger');
                return;
            }
            updateSummary(data.summary);
            renderScans(data.scans);
            feedback(true);
            announce(`Equipo ${data.asset.activo_fijo} registrado correctamente.`, 'success');
            if (manualCode) manualCode.value = '';
        } catch (_) {
            announce('No hay conexión con el servidor. La lectura no fue registrada.', 'danger');
        } finally {
            pendingCode = null;
            confirmButton.disabled = false;
            window.setTimeout(() => { busy = false; }, 700);
        }
    };

    const stopCamera = () => {
        cameraControls?.stop();
        cameraControls = null;
        if (video?.srcObject) video.srcObject.getTracks().forEach((track) => track.stop());
        if (video) video.srcObject = null;
        placeholder?.classList.remove('d-none');
        startButton?.classList.remove('d-none');
        stopButton?.classList.add('d-none');
        if (help) help.textContent = 'La cámara está detenida.';
    };

    const cameraErrorMessage = (error) => {
        const messages = {
            NotAllowedError: 'El permiso de cámara está bloqueado. Habilítalo desde el candado de la barra de direcciones y vuelve a intentar.',
            NotFoundError: 'No se encontró una cámara disponible en este dispositivo.',
            NotReadableError: 'La cámara está siendo utilizada por otra aplicación. Ciérrala y vuelve a intentar.',
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

    const startCamera = async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            announce('La cámara requiere HTTPS. Puedes continuar mediante ingreso manual.', 'danger');
            return;
        }
        startButton.disabled = true;
        try {
            const reader = new BrowserMultiFormatReader();
            cameraControls = await openCamera(reader);
            placeholder?.classList.add('d-none');
            startButton.classList.add('d-none');
            stopButton.classList.remove('d-none');
            help.textContent = 'Cámara activa. Centra una etiqueta dentro del recuadro.';
        } catch (error) {
            announce(cameraErrorMessage(error), 'danger');
        } finally {
            startButton.disabled = false;
        }
    };

    const refreshState = async (quiet = false) => {
        try {
            const response = await fetch(root.dataset.stateUrl, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const data = await response.json();
            updateSummary(data.summary);
            renderScans(data.scans);
            if (!quiet) announce('Lecturas actualizadas.', 'info');
        } catch (_) {
            if (!quiet) announce('No fue posible actualizar las lecturas.', 'danger');
        }
    };

    startButton?.addEventListener('click', startCamera);
    stopButton?.addEventListener('click', stopCamera);
    confirmButton?.addEventListener('click', registerPendingScan);
    modalElement?.addEventListener('hidden.bs.modal', () => { if (pendingCode) { pendingCode = null; busy = false; } });
    manualForm?.addEventListener('submit', (event) => { event.preventDefault(); lookup(manualCode.value); });
    document.getElementById('refreshScans')?.addEventListener('click', () => refreshState());
    document.getElementById('recentScans')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-scan]');
        if (!button || !window.confirm('¿Quitar esta lectura? El equipo podrá escanearse nuevamente.')) return;
        button.disabled = true;
        try {
            const url = root.dataset.removeUrlTemplate.replace('__ASSET__', button.dataset.removeScan);
            const response = await fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await readJson(response);
            if (!response.ok) throw new Error(data.message);
            updateSummary(data.summary);
            renderScans(data.scans);
            announce(data.message, 'success');
        } catch (_) {
            button.disabled = false;
            announce('No fue posible quitar la lectura.', 'danger');
        }
    });
    window.addEventListener('pagehide', stopCamera);
    window.setInterval(() => refreshState(true), 15000);
}
