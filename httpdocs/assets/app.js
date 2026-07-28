// Meldet den Service Worker an. Ohne ihn funktioniert alles wie bisher —
// die Seite ist dann nur nicht installierbar und hat nichts für unterwegs.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      // Kein Grund zur Aufregung: ohne HTTPS oder in alten Browsern geht das
      // nicht, und die Anwendung braucht es auch nicht.
    });
  });
}
