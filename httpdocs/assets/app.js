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
    //
    // „Gesehen" heißt aber: etwas gesehen. Wer die App nach zwei Wochen öffnet
    // und auf einem Anmeldeformular landet, hat die Mitteilungen nicht gesehen —
    // und trotzdem fiel der Zähler auf null, weil die Anmeldeseite mit HTTP 200
    // antwortete und das hier als Auskunft durchging (#231). Gemeldet wird nur
    // nach einer Antwort, die sagt, dass jemand angemeldet ist.
    const gesehen = async () => {
      if (document.visibilityState !== 'visible') return;
      // Was noch zu tun ist, weiß nur der Server — die Mitteilungen dagegen sind
      // mit dem Öffnen gesehen. Deshalb hier die frische Zahl holen und dem
      // Service Worker mitgeben, statt die Marke blind zu löschen: eine offene
      // Aufgabe verschwindet nicht dadurch, dass man die App aufmacht.
      let offen = null;        // null heißt: nicht erfahren, also nichts überschreiben
      let angemeldet = false;  // und ohne Anmeldung wird gar nichts gemeldet
      try {
        const r = await fetch('/intern/badge', { credentials: 'same-origin' });
        // Nur eine echte Auskunft zählt: Statuscode, Inhaltstyp und der Inhalt
        // selbst müssen zusammenpassen. Eine ausgelieferte Seite tut das nicht.
        if (r.ok && (r.headers.get('content-type') || '').includes('json')) {
          const daten = await r.json();
          if (daten && daten.angemeldet === true) {
            angemeldet = true;
            offen = Number(daten.offen) || 0;
          }
        }
      } catch (e) { /* kein Netz — der letzte bekannte Stand bleibt stehen */ }
      if (!angemeldet) return;   // Zahl und Mitteilungen bleiben, wie sie waren
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
