// Meldet den Service Worker an. Ohne ihn funktioniert alles wie bisher —
// die Seite ist dann nur nicht installierbar und hat nichts für unterwegs.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    // updateViaCache: 'none' — den Service-Worker selbst nie aus dem HTTP-Cache
    // holen, sondern immer frisch prüfen. Sonst bemerkt eine als App installierte
    // Seite (iPhone Home-Screen) neue Fassungen nicht und hängt am alten Stand.
    navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' })
      .then((reg) => reg.update())
      .catch(() => {
        // Kein Grund zur Aufregung: ohne HTTPS oder in alten Browsern geht das
        // nicht, und die Anwendung braucht es auch nicht.
      });
    // Übernimmt ein neuer Worker die Kontrolle, einmal neu laden, damit die
    // frischen Dateien auch greifen. Nur wenn schon einer lief (also ein echtes
    // Update, keine Erstinstallation) und nur einmal, sonst dreht die Seite Kreise.
    if (navigator.serviceWorker.controller) {
      let reloaded = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloaded) return;
        reloaded = true;
        window.location.reload();
      });
    }
  });
}
