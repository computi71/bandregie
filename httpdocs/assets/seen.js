// Neu und geändert: Was aufgeklappt wurde, ist gesehen (#321).
//
// Der Server kann das nicht wissen — eine zugeklappte Karte steht mit im
// Seitentext, und wer sie nie öffnet, hat sie nicht gelesen. Deshalb meldet
// erst das Aufklappen. Fällt das Skript aus, bleibt die Marke stehen: ärgerlich,
// aber nichts geht verloren, und die offene Karte meldet der Server selbst.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-seen]').forEach(karte => {
    if (karte.tagName !== 'DETAILS') return;
    karte.addEventListener('toggle', () => {
      if (!karte.open || karte.dataset.seenDone) return;
      // Nur einmal je Seitenaufruf: Auf- und Zuklappen ist keine neue Nachricht.
      karte.dataset.seenDone = '1';
      const [art, nr] = (karte.dataset.seen || '').split(':');
      if (!art || !nr) return;
      const fd = new FormData();
      fd.append('_token', karte.dataset.token || '');
      fd.append('art', art);
      fd.append('nr', nr);
      fetch('/intern/gesehen', { method: 'POST', body: fd, credentials: 'same-origin' })
        .catch(() => { /* Ohne Netz bleibt die Marke stehen — das ist in Ordnung. */ });
    });
  });
});
