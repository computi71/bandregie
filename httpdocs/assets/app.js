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
  });
}
