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

const VERSION = 'bandregie-v13';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const FILE_CACHE = VERSION + '-files';

const STATIC_FILES = [
  '/assets/style.css',
  '/assets/nav.js',
  '/assets/accordion.js',
  '/assets/buehne.js',
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
  /^\/intern\/songs\/\d+\/(buehne|noten)$/,
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
      // cache: 'reload' erzwingt frische Fassungen: die query-losen Adressen
      // liegen wegen der Ein-Jahr-Regel im HTTP-Cache, und ohne dies legte eine
      // neue Fassung des Service Workers jahrealte Dateien in ihren Vorrat.
      .then(cache => cache.addAll(STATIC_FILES.map(u => new Request(u, { cache: 'reload' }))))
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

/**
 * Eine Antwort mit dem Zeitpunkt versehen, zu dem sie aufbewahrt wurde. Ohne
 * ihn wüsste später niemand, wie alt das ist, was er ansieht — und eine
 * Setlist von gestern sieht genauso aus wie die von heute.
 */
async function mitZeitstempel(response) {
  const kopf = new Headers(response.headers);
  kopf.set('X-Cached-At', new Date().toISOString());
  return new Response(await response.blob(), {
    status: response.status, statusText: response.statusText, headers: kopf,
  });
}

/**
 * Seite: erst das Netz, dann der Zwischenspeicher.
 *
 * Das Ablegen hängt an event.waitUntil und nicht an einem losgelösten Promise:
 * Sobald der Handler zurückkehrt, darf der Browser den Service Worker beenden,
 * und was dann noch unterwegs ist, wird nicht zwingend fertig. Ohne das käme
 * die Seite an, landete aber mitunter nie im Vorrat — und auf der Bühne fehlte
 * genau sie.
 */
async function seite(event) {
  const request = event.request;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const kopie = await mitZeitstempel(response.clone());
      event.waitUntil(caches.open(PAGE_CACHE).then(cache => cache.put(request, kopie)));
    }
    return response;
  } catch (e) {
    // Von wann der Stand ist, steht im Kopf der aufbewahrten Antwort. Die
    // Seite liest ihn selbst aus dem Zwischenspeicher — eine Antwort hier neu
    // zu verpacken hieße, sich mit Content-Encoding anzulegen, und dafür ist
    // ein Hinweistext kein Grund.
    // Query-String tolerant abgleichen: die Bühne wird aus einer Setlist als
    // …/buehne?sl=7 geöffnet, vorgehalten wird oft nur …/buehne. Sonst landete
    // man offline auf der Termin-Liste statt beim Liedtext.
    const hit = await caches.match(request)
      || await caches.match(request, { ignoreSearch: true });
    return hit || (await caches.match('/intern/termine')) || Response.error();
  }
}

/** Anhang: erst der Zwischenspeicher, dann das Netz. */
async function anhang(event) {
  const request = event.request;
  const hit = await caches.match(request);
  if (hit) return hit;
  const response = await fetch(request);
  // Nur vollständige Antworten aufbewahren: ein Teilstück (206) ergäbe beim
  // nächsten Mal eine kaputte Datei.
  if (response.status === 200 && await hatPlatz()) {
    const copy = response.clone();
    // Wie bei den Seiten am Ereignis festgemacht — sonst bricht das Ablegen bei
    // einer großen Datei mitten im Schreiben ab, und beim nächsten Mal lädt sie
    // wieder komplett neu.
    event.waitUntil(caches.open(FILE_CACHE).then(cache => cache.put(request, copy)));
  }
  return response;
}

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') {
    // Beim Abmelden alles vergessen, was jemandem gehört — ein fremdes Gerät
    // geht uns nichts an.
    //
    // Zwingend an event.waitUntil: Kehrt der Handler zurück, darf der Browser
    // den Service Worker sofort beenden, und ein losgelöstes caches.delete()
    // muss er nicht mehr zu Ende bringen. Ohne das überlebten Setlists, Noten
    // und Verträge des Vorgängers genau auf dem geteilten Gerät, um das es hier
    // geht. Der Zählstand am App-Symbol gehört mit dazu — sonst startet die
    // nächste Person mit den Zahlen der vorherigen.
    if (new URL(request.url).pathname === '/logout') {
      event.waitUntil(Promise.all([
        caches.delete(PAGE_CACHE),
        caches.delete(FILE_CACHE),
        caches.delete(VERSION + '-state'),
      ]));
    }
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (STATIC_FILES.includes(url.pathname) || url.pathname.startsWith('/assets/')) {
    // Assets tragen ?v=<Version> und sind damit unveränderlich: exakt zuerst aus
    // dem Zwischenspeicher, sonst frisch aus dem Netz — so kommt eine neue Fassung
    // sofort an. NUR wenn das Netz fehlt, per ignoreSearch auf die vorgehaltene
    // (query-lose) Fassung zurückfallen, damit die Bühne offline ihr Skript findet.
    // (Ohne diese Trennung liefe ignoreSearch auch online und hielte alte Assets
    // fest — der Grund, warum neue Stylesheets nicht ankamen.)
    event.respondWith(
      caches.match(request).then(hit => hit || fetch(request).then(response => {
        // Nur gelungene Antworten aufbewahren — ein flüchtiger Fehler (404/500)
        // würde sonst kleben, bis die nächste SW-Version den Cache erneuert.
        if (response.ok) {
          const copy = response.clone();
          // Erst ablegen, dann die anderen Fassungen desselben Pfads räumen —
          // niemals umgekehrt: zwischen Löschen und Ablegen darf der Browser den
          // Service Worker beenden, und dann stünde für dieses Asset gar nichts
          // mehr im Zwischenspeicher. Die Bühne wäre offline ohne ihr Skript.
          // waitUntil hält den Worker so lange am Leben, bis beides durch ist.
          event.waitUntil(caches.open(STATIC_CACHE).then(async cache => {
            await cache.put(request, copy);
            for (const alt of await cache.keys(request, { ignoreSearch: true })) {
              if (alt.url !== request.url) await cache.delete(alt);
            }
          }));
        }
        return response;
      }).catch(() => caches.match(request, { ignoreSearch: true })))
    );
    return;
  }

  if (FILE_PATTERN.test(url.pathname)) {
    event.respondWith(anhang(event));
    return;
  }

  const istSeite = OFFLINE_PAGES.includes(url.pathname)
    || OFFLINE_PATTERNS.some(p => p.test(url.pathname));
  if (istSeite) event.respondWith(seite(event));
});

// Push-Mitteilungen (#24): der Server schickt Titel, Text und Ziel-Adresse als
// JSON. Anzeigen ist Pflicht (userVisibleOnly) — eine leere Nachricht wäre ein
// Vertrauensbruch gegenüber dem Browser, also gibt es notfalls den Bandnamen.
// Zahl am Symbol: wie viele Mitteilungen seit dem letzten Öffnen gekommen sind.
//
// Der Zählstand muss einen Neustart des Service Workers überstehen — der wird
// zwischen zwei Mitteilungen regelmäßig beendet —, deshalb liegt er im
// Zwischenspeicher und nicht in einer Variablen. localStorage gibt es hier nicht.
//
// Kann das Gerät keine Zahl am Symbol (Android-Chrome etwa, oder eine Seite im
// Browser statt als App), passiert schlicht nichts: die Mitteilung kommt
// trotzdem an. Nichts hängt davon ab.
// Zwei Zahlen ergeben die Marke: was noch zu tun ist (offene Aufgaben und
// Termine ohne Antwort — die weiß nur der Server) und wie viele Mitteilungen
// seit dem letzten Öffnen kamen. Beide zusammen sind das, was am Symbol steht.
const BADGE_KEY = '/__badge';   // ungelesene Mitteilungen
const OFFEN_KEY = '/__offen';   // offene Punkte, zuletzt vom Server gehört

async function badgeState(neu = {}) {
  const cache = await caches.open(VERSION + '-state');
  const lies = async (key) => {
    const hit = await cache.match(key);
    return hit ? (Number(await hit.text()) || 0) : 0;
  };
  let mitteilungen = await lies(BADGE_KEY);
  let offen = await lies(OFFEN_KEY);
  if (neu.mitteilungen !== undefined) mitteilungen = Math.max(0, neu.mitteilungen);
  if (neu.plus) mitteilungen = Math.max(0, mitteilungen + neu.plus);
  if (neu.offen !== undefined) offen = Math.max(0, neu.offen);
  await cache.put(BADGE_KEY, new Response(String(mitteilungen)));
  await cache.put(OFFEN_KEY, new Response(String(offen)));
  return offen + mitteilungen;
}

async function badgeSetzen(n) {
  try {
    if (n > 0 && self.navigator.setAppBadge) await self.navigator.setAppBadge(n);
    else if (self.navigator.clearAppBadge) await self.navigator.clearAppBadge();
  } catch (e) {
    /* Gerät kann es nicht — die Mitteilung selbst ist davon unberührt. */
  }
}

/**
 * Alles auf „gesehen": Zähler zurück, Zahl weg, und die Mitteilungen aus der
 * Leiste räumen.
 *
 * Das Aufräumen der Leiste ist der Android-Teil: Chrome kennt dort die
 * Badging-API nicht, das System leitet die Marke am Symbol aber aus den noch
 * offenen Mitteilungen ab. Bleiben sie liegen, bleibt auch die Marke — und
 * umgekehrt verschwindet sie, sobald wir sie schließen. Auf dem iPhone macht
 * setAppBadge die Zahl, hier fällt das Aufräumen bloß zusätzlich sauber aus.
 */
async function alleGesehen(offen) {
  // Die ungelesenen Mitteilungen sind gesehen; was zu tun ist, bleibt stehen —
  // eine Aufgabe erledigt sich nicht dadurch, dass man die App öffnet. Die
  // frische Zahl der offenen Punkte bringt die Seite mit.
  await badgeSetzen(await badgeState({ mitteilungen: 0, offen }));
  const liste = await self.registration.getNotifications();
  for (const n of liste) n.close();
}

self.addEventListener('push', event => {
  let daten = {};
  try { daten = event.data ? event.data.json() : {}; } catch (e) { /* kein JSON: Standardtext */ }
  event.waitUntil((async () => {
    // Eine Mitteilung mehr, und die Zahl der offenen Punkte so, wie der Server
    // sie beim Verschicken kannte.
    // Ein neuer Termin steckt schon in „offen" — sonst stünde für einen
    // einzigen offenen Punkt eine 2 am Symbol. Der Server sagt uns, ob diese
    // Mitteilung obendrauf zählt.
    const gesamt = await badgeState({
      plus: daten.zaehlt === false ? 0 : 1,
      offen: daten.offen === undefined ? undefined : Number(daten.offen) || 0,
    });
    await self.registration.showNotification(daten.title || 'Bandregie', {
      body: daten.body || '',
      icon: '/assets/app/icon-192.png',
      badge: '/assets/app/icon-192.png',
      // Eigene Kennung je Mitteilung: sonst ersetzt die neue die vorherige und
      // es steht immer nur eine in der Leiste. Auf Android zählt das System
      // genau diese Einträge für die Marke am Symbol — ohne eigene Kennung
      // bliebe sie dort ewig bei eins stehen.
      tag: 'bandregie-' + Date.now(),
      data: { url: daten.url || '/intern' },
    });
    // Die Zahl am Symbol erst danach: die Mitteilung ist das Wichtige, und
    // scheitert das Setzen, soll sie trotzdem angekommen sein.
    await badgeSetzen(gesamt);
  })());
});

// Tippen auf die Mitteilung: ein offenes Fenster wiederverwenden, sonst eines
// öffnen — niemand will fünf Tabs derselben Terminliste.
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/intern';
  event.waitUntil(alleGesehen());
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const client of list) {
      if (!('focus' in client)) continue;
      // navigate() weist Fenster ab, die dieser Worker nicht kontrolliert (nach
      // einem harten Neuladen etwa) — dann lieber ein neues öffnen, statt nur
      // ein Fenster nach vorn zu holen, das weiter die alte Seite zeigt.
      return client.navigate(url).then(() => client.focus())
        .catch(() => clients.openWindow(url));
    }
    return clients.openWindow(url);
  }));
});

// Auf Verlangen mitnehmen: die Seite schickt eine Liste von Adressen, der
// Service Worker holt sie in den Zwischenspeicher. So hängt der Vorrat nicht
// davon ab, was jemand vorher zufällig geöffnet hat.
self.addEventListener('message', event => {
  const daten = event.data || {};
  // Die Seite meldet sich, wenn sie geöffnet oder wieder sichtbar wird: dann
  // hat man die Mitteilungen gesehen, und die Zahl am Symbol gehört weg.
  if (daten.type === 'gesehen') {
    // Ohne Angabe bleibt die zuletzt bekannte Zahl offener Punkte stehen.
    event.waitUntil(alleGesehen(daten.offen === undefined ? undefined : Number(daten.offen) || 0));
    return;
  }
  if (daten.type !== 'mitnehmen' || !Array.isArray(daten.urls)) return;

  event.waitUntil((async () => {
    let geholt = 0;
    let uebersprungen = 0;
    for (const roh of daten.urls.slice(0, 200)) {
      let url;
      try { url = new URL(roh, self.location.origin); } catch (e) { continue; }
      if (url.origin !== self.location.origin) continue;
      const istDatei = FILE_PATTERN.test(url.pathname);
      // Eine Datei, die schon daliegt, ist dieselbe Datei: ihr Name trägt
      // einen Zufallsanteil. Beim regelmäßigen Abgleich wäre sie erneut zu
      // laden reine Verschwendung.
      if (istDatei && await caches.match(url.href)) { continue; }
      if (istDatei && !(await hatPlatz())) { uebersprungen++; continue; }
      try {
        const response = await fetch(url.href, { credentials: 'same-origin' });
        if (!response.ok) { uebersprungen++; continue; }
        const cache = await caches.open(istDatei ? FILE_CACHE : PAGE_CACHE);
        await cache.put(url.href, istDatei ? response : await mitZeitstempel(response));
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
