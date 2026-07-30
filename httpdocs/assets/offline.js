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
document.addEventListener('DOMContentLoaded', () => {
  const marke = document.querySelector('[data-stale]');
  if (!marke) return;
  const wann = new Date(marke.dataset.stale);
  if (isNaN(wann.getTime())) return;

  const vorlage = document.body.dataset.staletpl || '';
  if (vorlage === '') return;
  const zeit = wann.toLocaleString(document.documentElement.lang || undefined,
    { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

  const banner = document.createElement('p');
  banner.className = 'warn stale-banner';
  banner.textContent = vorlage.replace('%1', zeit);
  const ziel = document.querySelector('main') || document.body;
  ziel.insertBefore(banner, ziel.firstChild);
});
