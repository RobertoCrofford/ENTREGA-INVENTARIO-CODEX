const installButton = document.getElementById('pwaInstallButton');
const connectionStatus = document.getElementById('mobileConnectionStatus');
let deferredInstallPrompt = null;
let connectionTimer = null;
let wasOffline = !navigator.onLine;

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

const updateConnectionStatus = () => {
    if (!connectionStatus) return;
    window.clearTimeout(connectionTimer);
    if (!navigator.onLine) {
        connectionStatus.hidden = false;
        connectionStatus.className = 'mobile-connection-status is-offline';
        connectionStatus.innerHTML = '<i class="bi bi-wifi-off"></i><span>Sin conexión. Las consultas y registros están pausados.</span>';
        wasOffline = true;
        return;
    }

    if (wasOffline) {
        connectionStatus.hidden = false;
        connectionStatus.className = 'mobile-connection-status is-online';
        connectionStatus.innerHTML = '<i class="bi bi-wifi"></i><span>Conexión restablecida.</span>';
        connectionTimer = window.setTimeout(() => { connectionStatus.hidden = true; }, 3500);
    } else {
        connectionStatus.hidden = true;
    }
    wasOffline = false;
};

window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);
updateConnectionStatus();

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (installButton && !isStandalone()) installButton.hidden = false;
});

installButton?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    installButton.disabled = true;
    await deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installButton.hidden = true;
    installButton.disabled = false;
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    if (installButton) installButton.hidden = true;
});

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').then((registration) => registration.update()).catch(() => {});
    });
}
