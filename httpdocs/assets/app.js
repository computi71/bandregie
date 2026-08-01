// Meldet den Service Worker an. Ohne ihn funktioniert alles wie bisher —
// die Seite ist dann nur nicht installierbar und hat nichts für unterwegs.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    // updateViaCache: 'none' — den Service-Worker selbst nie aus dem HTTP-Cache
    // holen, sondern immer frisch prüfen. Sonst bemerkt eine als App installierte
    // Seite (iPhone Home-Screen) neue Fassungen nicht und hängt am alten Stand.
    //
    // Das Update-Anstoßen und das Neuladen nach der Übernahme wohnen in
    // swkick.js — bewusst nur dort: die Datei erreicht mit ihrem eigenen,
    // nie zwischengespeicherten Namen auch festhängende Installationen, und
    // eine zweite Fassung derselben Logik hier liefe nur auseinander.
    navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' })
      .catch(() => {
        // Kein Grund zur Aufregung: ohne HTTPS oder in alten Browsern geht das
        // nicht, und die Anwendung braucht es auch nicht.
      });

    // Wer die Anwendung öffnet, hat die Mitteilungen gesehen — dann gehört die
    // Zahl am Symbol weg. Auch beim Zurückkehren aus dem Hintergrund, sonst
    // bliebe sie stehen, bis jemand die App einmal ganz neu startet.
    const gesehen = async () => {
      if (document.visibilityState !== 'visible') return;
      // Was noch zu tun ist, weiß nur der Server — die Mitteilungen dagegen sind
      // mit dem Öffnen gesehen. Deshalb hier die frische Zahl holen und dem
      // Service Worker mitgeben, statt die Marke blind zu löschen: eine offene
      // Aufgabe verschwindet nicht dadurch, dass man die App aufmacht.
      let offen = 0;
      try {
        const r = await fetch('/intern/badge', { credentials: 'same-origin' });
        if (r.ok) offen = Number((await r.json()).offen) || 0;
      } catch (e) { /* kein Netz: dann bleibt es beim letzten bekannten Stand */ }
      try {
        if (offen > 0 && navigator.setAppBadge) await navigator.setAppBadge(offen);
        else if (navigator.clearAppBadge) await navigator.clearAppBadge();
      } catch (e) { /* Gerät kann keine Marke — kein Beinbruch */ }
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'gesehen', offen });
      }
    };
    gesehen();
    document.addEventListener('visibilitychange', gesehen);
  });
}
