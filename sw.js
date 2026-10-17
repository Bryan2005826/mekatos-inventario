/* Service worker: guarda en caché solo los archivos estáticos.
   Los registros de asistencia SIEMPRE van al servidor: sin internet no se marca. */
const CACHE = 'asistencia-v1';
const ESTATICOS = ['assets/css/app.css', 'assets/js/kiosco.js', 'assets/js/admin.js', 'assets/icons/icon-192.png', 'offline.html'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.includes('/assets/')) {
    // estáticos: red primero (para tomar cambios), caché si no hay red
    e.respondWith(fetch(req).then(r => { const copia = r.clone(); caches.open(CACHE).then(c => c.put(req, copia)); return r; }).catch(() => caches.match(req, { ignoreSearch: true })));
    return;
  }
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
  }
});
