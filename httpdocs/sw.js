// Service Worker: macht die Seite installierbar und hält vor, was auf einer
// Bühne gebraucht wird. Dort ist der Empfang schlecht oder gar nicht da — und
// genau dann werden Setlist, Rider und Noten gebraucht.
//
// Drei Regeln:
//   * Statisches (CSS, Skripte, Symbole) kommt aus dem Zwischenspeicher.
//   * Seiten kommen aus dem Netz; klappt das nicht, aus dem Zwischenspeicher.
//     Eine Setlist, die eine Stunde vor dem Auftritt geändert wurde, darf
//     nicht gegen die Fassung von gestern verlieren, solange Empfang da ist.
//   * Anhänge kommen aus dem Zwischenspeicher, wenn sie darin liegen. Ihr
//     Dateiname trägt einen Zufallsanteil: eine neue Datei ist eine neue
//     Adresse, und unter einer Adresse ändert sich der Inhalt nie. Fünf
//     Megabyte Noten über das WLAN der Halle neu zu laden, um dieselben Bytes
//     zu bekommen, wäre der falsche Handel.
//
// Beim Abmelden werden Seiten und Anhänge vergessen, damit auf einem geteilten
// Gerät niemand die Termine und Noten des Vorgängers findet.

const VERSION = 'bandregie-v2';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const FILE_CACHE = VERSION + '-files';

const STATIC_FILES = [
  '/assets/style.css',
  '/assets/nav.js',
  '/assets/accordion.js',
  '/assets/app/icon-192.png',
  '/assets/app/icon-512.png',
];

// Seiten, die offline etwas wert sind. Die Muster decken auch die Unterseiten
// ab — jede Setlist einzeln, mit ihrer Druckfassung, denn die wird auf der
// Bühne hochgehalten.
const OFFLINE_PAGES = [
  '/intern',
  '/intern/termine',
  '/intern/setlists',
  '/intern/songs',
  '/intern/stagerider',
  '/intern/stagerider/print',
  '/intern/kanaele',
];
const OFFLINE_PATTERNS = [
  /^\/intern\/setlists\/\d+(\/print)?$/,
  /^\/intern\/songs\/\d+$/,
];

// Anhänge: Noten, Verträge, Aufnahmen. Die Anwendung nimmt höchstens 20 MB je
// Datei an, mehr kann hier also nicht ankommen.
const FILE_PATTERN = /^\/intern\/datei\/\d+$/;

// Wie viel vom verfügbaren Platz wir höchstens belegen. Ein Telefon, dessen
// Speicher wir vollschreiben, hat ein größeres Problem als fehlende Noten.
const STORAGE_SHARE = 0.5;

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

/** Ist noch Platz, um etwas aufzubewahren? Ohne Auskunft: im Zweifel ja. */
async function hatPlatz() {
  if (!navigator.storage || !navigator.storage.estimate) return true;
  try {
    const { usage = 0, quota = 0 } = await navigator.storage.estimate();
    return quota === 0 || usage < quota * STORAGE_SHARE;
  } catch (e) {
    return true;
  }
}

/** Seite: erst das Netz, dann der Zwischenspeicher. */
async function seite(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const copy = response.clone();
      caches.open(PAGE_CACHE).then(cache => cache.put(request, copy));
    }
    return response;
  } catch (e) {
    const hit = await caches.match(request);
    return hit || (await caches.match('/intern/termine')) || Response.error();
  }
}

/** Anhang: erst der Zwischenspeicher, dann das Netz. */
async function anhang(request) {
  const hit = await caches.match(request);
  if (hit) return hit;
  const response = await fetch(request);
  // Nur vollständige Antworten aufbewahren: ein Teilstück (206) ergäbe beim
  // nächsten Mal eine kaputte Datei.
  if (response.status === 200 && await hatPlatz()) {
    const copy = response.clone();
    caches.open(FILE_CACHE).then(cache => cache.put(request, copy));
  }
  return response;
}

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') {
    // Beim Abmelden alles vergessen, was jemandem gehört — ein fremdes Gerät
    // geht uns nichts an.
    if (new URL(request.url).pathname === '/logout') {
      caches.delete(PAGE_CACHE);
      caches.delete(FILE_CACHE);
    }
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

  if (FILE_PATTERN.test(url.pathname)) {
    event.respondWith(anhang(request));
    return;
  }

  const istSeite = OFFLINE_PAGES.includes(url.pathname)
    || OFFLINE_PATTERNS.some(p => p.test(url.pathname));
  if (istSeite) event.respondWith(seite(request));
});

// Auf Verlangen mitnehmen: die Seite schickt eine Liste von Adressen, der
// Service Worker holt sie in den Zwischenspeicher. So hängt der Vorrat nicht
// davon ab, was jemand vorher zufällig geöffnet hat.
self.addEventListener('message', event => {
  const daten = event.data || {};
  if (daten.type !== 'mitnehmen' || !Array.isArray(daten.urls)) return;

  event.waitUntil((async () => {
    let geholt = 0;
    let uebersprungen = 0;
    for (const roh of daten.urls.slice(0, 200)) {
      let url;
      try { url = new URL(roh, self.location.origin); } catch (e) { continue; }
      if (url.origin !== self.location.origin) continue;
      const istDatei = FILE_PATTERN.test(url.pathname);
      if (istDatei && !(await hatPlatz())) { uebersprungen++; continue; }
      try {
        const response = await fetch(url.href, { credentials: 'same-origin' });
        if (!response.ok) { uebersprungen++; continue; }
        const cache = await caches.open(istDatei ? FILE_CACHE : PAGE_CACHE);
        await cache.put(url.href, response);
        geholt++;
      } catch (e) {
        uebersprungen++;
      }
    }
    // Antwort an alle offenen Fenster: die Oberfläche sagt, was mitkam.
    for (const client of await self.clients.matchAll()) {
      client.postMessage({ type: 'mitgenommen', geholt, uebersprungen });
    }
  })());
});
