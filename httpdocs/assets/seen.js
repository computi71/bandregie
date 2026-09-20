// Neu und geändert: Was aufgeklappt wurde, ist gesehen (#321).
//
// Der Server kann das nicht wissen — eine zugeklappte Karte steht mit im
// Seitentext, und wer sie nie öffnet, hat sie nicht gelesen. Deshalb meldet
// erst das Aufklappen. Fällt das Skript aus, bleibt die Marke stehen: ärgerlich,
// aber nichts geht verloren.
document.addEventListener('DOMContentLoaded', () => {
  const melde = (karte) => {
    // Nur einmal je Seitenaufruf: Auf- und Zuklappen ist keine neue Nachricht.
    if (karte.dataset.seenDone) return;
    karte.dataset.seenDone = '1';
    const [art, nr] = (karte.dataset.seen || '').split(':');
    if (!art || !nr) return;
    const fd = new FormData();
    fd.append('_token', karte.dataset.token || '');
    fd.append('art', art);
    fd.append('nr', nr);
    fetch('/intern/gesehen', { method: 'POST', body: fd, credentials: 'same-origin' })
      .catch(() => { /* Ohne Netz bleibt die Marke stehen — das ist in Ordnung. */ });
  };

  document.querySelectorAll('details[data-seen]').forEach(karte => {
    // Die erste Karte kommt offen an und löst deshalb nie ein toggle aus. Ohne
    // diese Zeile behielte ausgerechnet der Termin seine Marke, den man bei
    // jedem Besuch der Übersicht vor Augen hat.
    if (karte.open) melde(karte);
    karte.addEventListener('toggle', () => { if (karte.open) melde(karte); });
  });
});
