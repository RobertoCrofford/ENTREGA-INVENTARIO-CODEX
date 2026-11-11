const VERSION = 'inventory-mobile-v2';
const PUBLIC_ASSETS = ['/offline.html', '/manifest.webmanifest', '/images/inventory-mobile-icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PUBLIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);
    if (event.request.method !== 'GET' || requestUrl.origin !== self.location.origin) return;

    if (event.request.mode === 'navigate') {
        // Nunca almacenamos páginas autenticadas. Solo mostramos una pantalla pública
        // sin datos cuando el dispositivo pierde la conexión.
        event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
        return;
    }

    // Las páginas autenticadas y sus respuestas nunca se guardan en el dispositivo.
    if (!PUBLIC_ASSETS.includes(requestUrl.pathname)) return;
    event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request)));
});
