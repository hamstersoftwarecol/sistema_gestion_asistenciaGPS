/*
 * Service worker de GeoAsistencia.
 * - Recursos compilados (/build/*): caché primero.
 * - Página "Mi asistencia": red primero con copia en caché, para poder abrirla sin señal
 *   y registrar marcaciones en la cola local (se sincronizan al reconectar).
 */
const CACHE = 'geoasistencia-v1';
const OFFLINE_PAGES = ['/my-attendance'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));
                return response;
            })),
        );
        return;
    }

    if (request.mode === 'navigate' && OFFLINE_PAGES.includes(url.pathname)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && !response.redirected) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(url.pathname, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(url.pathname)),
        );
    }
});
