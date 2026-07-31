// Selbstheilung für festhängende Installationen.
//
// Eine als App installierte Seite (iPhone Home-Screen) kann auf einem alten
// Service Worker festsitzen, der neue Dateien aus seinem Zwischenspeicher
// liefert statt aus dem Netz — dann kommen Updates nicht an. Der alte Worker
// selbst lässt sich von hier aus nicht austauschen. Aber: diese Datei trägt
// einen Namen, den der alte Worker nie zwischengespeichert hat, also holt er
// sie zwangsläufig frisch aus dem Netz und führt sie aus.
//
// Von hier stoßen wir das Update an: den Worker neu prüfen (das umgeht den
// HTTP-Cache der sw.js) und, sobald der neue übernimmt, EINMAL neu laden, damit
// die frischen Dateien greifen. Danach hängt nichts mehr — künftige Updates
// erledigt die reguläre Anmeldung in app.js.
//
// Nur wenn schon ein Worker die Kontrolle hat (also eine bestehende Installation,
// kein Erstbesuch) — sonst gäbe es ein überflüssiges Neuladen beim ersten Start.
if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
  let reloaded = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloaded) return;
    reloaded = true;
    window.location.reload();
  });
  navigator.serviceWorker.getRegistration()
    .then((reg) => { if (reg) reg.update(); })
    .catch(() => { /* kein Worker, nichts zu tun */ });
}
