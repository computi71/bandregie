// Wer absagt, wird nach einer Alternative gefragt (#401).
//
// Wer absagt, ist der Einzige, der weiß warum — und damit der, der am ehesten
// einen anderen Termin vorschlagen kann. Die Band macht das längst von Hand;
// in den echten Daten steht ein Termin namens „Donnerstag als Alternative?".
// Nur sagt die Anwendung nichts dazu.
//
// Ein Fenster und keine Mitteilung: Die Person hat gerade getippt und schaut
// hin. Eine Mitteilung an jemanden, der eine Sekunde zuvor selbst gehandelt
// hat, ist derselbe Fehler, den #382 beim Eintrager vermeidet. Die Erinnerung
// per Mitteilung, falls danach nichts passiert, ist ein zweiter Schritt und
// braucht die Warteschlange aus #383.
//
// Zwei Wege führen her, weil es zwei Wege zum Absagen gibt:
//
//   Übersicht      zusage.js schickt im Hintergrund (#377) und ruft hier an
//   Terminkarte    gewöhnliches Formular, die Seite lädt neu — dann trägt das
//                  <dialog> die Terminnummer im data-absage-auto
//
// Ohne <dialog> oder ohne Skript erscheint gar nichts, und das Absagen
// funktioniert wie vorher.
(function () {
  const WEGGEKLICKT = 'absage-weg:';   // je Termin gemerkt

  const halten = (fn) => { try { return fn(); } catch (e) { return null; } };

  /** Hat dieses Konto den Hinweis zu diesem Termin schon weggeklickt? */
  const schonWeg = (nr) => halten(() => localStorage.getItem(WEGGEKLICKT + nr)) !== null;
  const merken = (nr) => halten(() => localStorage.setItem(WEGGEKLICKT + nr, '1'));

  /**
   * Das Fenster zu einem Termin öffnen.
   *
   * Öffentlich an window, damit zusage.js es rufen kann, ohne dass die beiden
   * Dateien sich gegenseitig laden müssen.
   */
  window.absageHinweis = function (nr) {
    const dlg = document.getElementById('absage-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') return;
    if (!nr || schonWeg(nr)) return;
    const link = dlg.querySelector('[data-absage-link]');
    if (link) link.setAttribute('href', '/intern/termine?vorlage=' + encodeURIComponent(nr) + '#ev-neu');
    dlg.dataset.absageNr = String(nr);
    dlg.showModal();
  };

  document.addEventListener('DOMContentLoaded', () => {
    const dlg = document.getElementById('absage-dialog');
    if (!dlg) return;

    // Weggeklickt heißt: zu diesem Termin nicht noch einmal. Wer eine Antwort
    // hin und her ändert, soll nicht jedes Mal dasselbe Fenster sehen.
    dlg.querySelectorAll('[data-absage-close]').forEach(knopf => {
      knopf.addEventListener('click', () => {
        merken(dlg.dataset.absageNr || '0');
        dlg.close();
      });
    });
    // Auch das Wegtippen neben dem Fenster und die Esc-Taste zählen als
    // weggeklickt — sonst käme es beim nächsten Mal wieder.
    dlg.addEventListener('close', () => merken(dlg.dataset.absageNr || '0'));

    // Der Weg über die Terminkarte: Die Seite hat neu geladen und bringt die
    // Nummer mit.
    const auto = Number(dlg.dataset.absageAuto || 0);
    if (auto) window.absageHinweis(auto);
  });
})();
