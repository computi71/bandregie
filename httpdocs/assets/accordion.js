// Aufklappbare Karten: immer nur eine offen. Neuere Browser können das selbst
// über das name-Attribut von <details>; nur für die anderen springt dieses
// Skript ein. Fällt es aus, lassen sich eben mehrere Karten gleichzeitig
// öffnen — unbequem, aber nichts geht verloren.
/**
 * Ein Verweis auf eine zugeklappte Karte muss sie aufklappen.
 *
 * Sonst springt der Browser brav zu ihr hin und zeigt eine geschlossene
 * Überschrift — genau das passiert beim Hinweis auf der Startseite, der zu den
 * Mitteilungen führt: Man landet richtig und sieht trotzdem nichts.
 *
 * Auch beim späteren Wechsel des Ankers, damit ein zweiter Klick auf denselben
 * Verweis nicht ins Leere läuft.
 */
function accOeffneAnker() {
  const id = decodeURIComponent(location.hash.replace('#', ''));
  if (!id) return;
  const ziel = document.getElementById(id);
  if (!ziel) return;
  const karte = ziel.tagName === 'DETAILS' ? ziel : ziel.closest('details');
  if (karte) {
    karte.open = true;
    // Nach dem Aufklappen noch einmal hinspringen: Die Karte ist jetzt höher,
    // und der Browser hat vorher auf die geschlossene Fassung gezielt.
    karte.scrollIntoView({ block: 'start', behavior: 'smooth' });
    return;
  }
  // Keine Faltkarte, sondern eine Zeile — so sieht die Übersicht aus (#421).
  // Hinspringen reicht da nicht: Wer aus einer Mitteilung kommt, landet in
  // einer Liste von neun und weiß nicht, welche gemeint war. Die Zeile sagt
  // es deshalb kurz selbst und hört dann wieder auf — eine Hervorhebung, die
  // bleibt, ist beim zweiten Hinsehen nur noch Farbe.
  ziel.scrollIntoView({ block: 'center', behavior: 'smooth' });
  ziel.classList.remove('angesprungen');
  // Erzwingt den Neuanfang der Animation, wenn derselbe Anker zweimal kommt.
  void ziel.offsetWidth;
  ziel.classList.add('angesprungen');
}
document.addEventListener('DOMContentLoaded', accOeffneAnker);
window.addEventListener('hashchange', accOeffneAnker);

document.addEventListener('DOMContentLoaded', () => {
  if ('name' in document.createElement('details')) return;

  document.querySelectorAll('details.acc[name]').forEach(item => {
    item.addEventListener('toggle', () => {
      if (!item.open) return;
      document.querySelectorAll(`details.acc[name="${item.getAttribute('name')}"]`)
        .forEach(other => { if (other !== item) other.open = false; });
    });
  });
});
