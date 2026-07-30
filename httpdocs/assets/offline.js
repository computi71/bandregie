// „Diesen Auftritt mitnehmen": holt Setlist, Noten, Rider und Patchliste in
// den Zwischenspeicher, damit auf der Bühne nichts fehlt.
//
// Der Knopf ist im Quelltext versteckt und wird hier eingeschaltet — ohne
// Service Worker hätte er keine Wirkung, und ein Knopf, der nichts tut, ist
// schlimmer als keiner.
document.addEventListener('DOMContentLoaded', () => {
  if (!('serviceWorker' in navigator)) return;

  document.querySelectorAll('[data-offlinegig]').forEach(box => {
    const knopf = box.querySelector('[data-offlinestart]');
    const stand = box.querySelector('[data-offlinestate]');
    if (!knopf || !stand) return;
    box.hidden = false;

    knopf.addEventListener('click', async () => {
      knopf.disabled = true;
      stand.textContent = ' ' + (box.dataset.offlinebusy || '…');
      try {
        const antwort = await fetch(box.dataset.offlinegig, { credentials: 'same-origin' });
        if (!antwort.ok) throw new Error(String(antwort.status));
        const daten = await antwort.json();
        const sw = await navigator.serviceWorker.ready;
        if (!sw.active) throw new Error('kein Service Worker');
        sw.active.postMessage({ type: 'mitnehmen', urls: daten.urls || [] });
      } catch (e) {
        stand.textContent = ' ' + (box.dataset.offlinefailed || '');
        knopf.disabled = false;
      }
    });
  });

  // Der Service Worker meldet, was er geholt hat.
  navigator.serviceWorker.addEventListener('message', ev => {
    const daten = ev.data || {};
    if (daten.type !== 'mitgenommen') return;
    document.querySelectorAll('[data-offlinegig]').forEach(box => {
      const knopf = box.querySelector('[data-offlinestart]');
      const stand = box.querySelector('[data-offlinestate]');
      if (!knopf || !stand || !knopf.disabled) return;
      const vorlage = daten.uebersprungen > 0
        ? (box.dataset.offlinesome || '')
        : (box.dataset.offlinedone || '');
      stand.textContent = ' ' + vorlage.replace('%1', daten.geholt).replace('%2', daten.uebersprungen);
      knopf.disabled = false;
    });
  });
});

// Aus dem Zwischenspeicher geliefert? Dann sagt die Seite, von wann sie ist.
// Eine Setlist von gestern sieht sonst genauso aus wie die von heute — und auf
// der Bühne ist das der Unterschied, der zählt.
//
// Gefragt wird der Zwischenspeicher direkt: dort steht am Eintrag, wann er
// hineingelegt wurde. Der Service Worker muss dafür nichts in die Seite
// schreiben.
document.addEventListener('DOMContentLoaded', () => {
  const vorlage = document.body.dataset.staletpl || '';
  if (vorlage === '' || !('caches' in window)) return;

  // navigator.onLine taugt nicht: es meldet „online", sobald ein WLAN
  // verbunden ist — auch das der Halle, hinter dem eine Anmeldeseite steht und
  // sonst nichts. Also wird nachgesehen, statt gefragt: eine winzige Anfrage,
  // die ohne Netz scheitert.
  const erreichbar = async () => {
    const abbruch = new AbortController();
    const zeit = setTimeout(() => abbruch.abort(), 2500);
    try {
      await fetch('/assets/app/icon-192.png?probe=' + Date.now(),
        { method: 'HEAD', cache: 'no-store', signal: abbruch.signal });
      return true;
    } catch (e) {
      return false;
    } finally {
      clearTimeout(zeit);
    }
  };

  const zeigen = async () => {
    if (document.querySelector('.stale-banner')) return;
    if (await erreichbar()) return;
    let wann = null;
    try {
      const treffer = await caches.match(location.href);
      if (!treffer) return;
      const kopf = treffer.headers.get('X-Cached-At');
      if (!kopf) return;
      wann = new Date(kopf);
    } catch (e) {
      return;
    }
    if (!wann || isNaN(wann.getTime())) return;

    const zeit = wann.toLocaleString(document.documentElement.lang || undefined,
      { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    const banner = document.createElement('p');
    banner.className = 'warn stale-banner';
    banner.textContent = vorlage.replace('%1', zeit);
    const ziel = document.querySelector('main') || document.body;
    ziel.insertBefore(banner, ziel.firstChild);
  };

  zeigen();
  // Wer unterwegs den Empfang verliert, sieht ihn ab dann auch.
  window.addEventListener('offline', zeigen);
  window.addEventListener('online', () => {
    const alt = document.querySelector('.stale-banner');
    if (alt) alt.remove();
  });
});

// Im Hintergrund frisch halten: bei jedem Seitenaufruf nachsehen, was sich an
// der gewählten Auswahl geändert hat — aber nur mit Verbindung, und nicht bei
// jedem Klick. Wer zehnmal hintereinander eine Seite öffnet, soll nicht
// zehnmal alles neu laden.
document.addEventListener('DOMContentLoaded', () => {
  if (!('serviceWorker' in navigator)) return;

  const ABSTAND = 10 * 60 * 1000;   // höchstens alle zehn Minuten
  const SCHLUESSEL = 'bandregie-offline-sync';

  const faellig = () => {
    try {
      const zuletzt = Number(localStorage.getItem(SCHLUESSEL) || 0);
      return !zuletzt || Date.now() - zuletzt > ABSTAND;
    } catch (e) {
      return false;   // ohne localStorage lieber gar nicht als dauernd
    }
  };

  const abgleichen = async () => {
    if (!faellig()) return;
    try {
      const antwort = await fetch('/intern/offline/liste', { credentials: 'same-origin' });
      if (!antwort.ok) return;
      const daten = await antwort.json();
      if (!Array.isArray(daten.urls) || daten.urls.length === 0) return;
      const sw = await navigator.serviceWorker.ready;
      if (!sw.active) return;
      try { localStorage.setItem(SCHLUESSEL, String(Date.now())); } catch (e) { /* egal */ }
      sw.active.postMessage({ type: 'mitnehmen', urls: daten.urls, still: true });
    } catch (e) {
      // Kein Netz, kein Problem: es bleibt, was da ist.
    }
  };

  // Nicht sofort: erst soll die Seite fertig sein, die jemand sehen wollte.
  if ('requestIdleCallback' in window) {
    requestIdleCallback(() => abgleichen(), { timeout: 4000 });
  } else {
    setTimeout(abgleichen, 2000);
  }
});

// Wie viel Platz belegt ist — im Profil neben der Auswahl.
document.addEventListener('DOMContentLoaded', async () => {
  const feld = document.querySelector('[data-offlineuse]');
  if (!feld || !navigator.storage || !navigator.storage.estimate) return;
  const vorlage = document.body.dataset.offlineusetpl || '';
  if (vorlage === '') return;
  try {
    const { usage = 0, quota = 0 } = await navigator.storage.estimate();
    const mb = z => (z / 1048576).toFixed(z > 10485760 ? 0 : 1) + ' MB';
    feld.textContent = vorlage.replace('%1', mb(usage)).replace('%2', quota ? mb(quota) : '?');
  } catch (e) {
    // Ohne Auskunft schweigen statt schätzen
  }
});
