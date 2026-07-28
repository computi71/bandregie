// Service Worker: macht die Seite installierbar und hält das Nötigste für
// unterwegs vor. Auf einer Bühne ist der Empfang oft schlecht — Termine und
// Setlists sollen trotzdem dastehen.
//
// Zwei Regeln, mehr nicht:
//   * Statisches (CSS, Skripte, Symbole) kommt aus dem Zwischenspeicher.
//   * Seiten kommen aus dem Netz; klappt das nicht, aus dem Zwischenspeicher.
//
// Beim Abmelden wird der Zwischenspeicher der Seiten geleert, damit auf einem
// geteilten Gerät niemand die Termine des Vorgängers findet.

const VERSION = 'bandroadie-v1';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';

const STATIC_FILES = [
  '/assets/style.css',
  '/assets/nav.js',
  '/assets/accordion.js',
  '/assets/app/icon-192.png',
  '/assets/app/icon-512.png',
];

// Diese Seiten sind offline etwas wert; alles andere braucht ohnehin den Server
const OFFLINE_PAGES = ['/intern', '/intern/termine', '/intern/setlists', '/intern/songs'];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(STATIC_FILES))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(names => Promise.all(names.filter(n => !n.startsWith(VERSION)).map(n => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') {
    // Beim Abmelden die Seiten vergessen — ein fremdes Gerät geht uns nichts an
    if (new URL(request.url).pathname === '/logout') caches.delete(PAGE_CACHE);
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (STATIC_FILES.includes(url.pathname) || url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(request).then(hit => hit || fetch(request).then(response => {
        const copy = response.clone();
        caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
        return response;
      }))
    );
    return;
  }

  if (!OFFLINE_PAGES.some(p => url.pathname === p)) return;

  event.respondWith(
    fetch(request)
      .then(response => {
        const copy = response.clone();
        caches.open(PAGE_CACHE).then(cache => cache.put(request, copy));
        return response;
      })
      .catch(() => caches.match(request).then(hit => hit || caches.match('/intern/termine')))
  );
});
