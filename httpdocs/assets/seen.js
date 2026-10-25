// Neu und geändert: Was aufgeklappt wurde, ist gesehen (#321).
//
// Der Server kann das nicht wissen — eine zugeklappte Karte steht mit im
// Seitentext, und wer sie nie öffnet, hat sie nicht gelesen. Deshalb meldet
// erst das Aufklappen. Fällt das Skript aus, bleibt die Marke stehen: ärgerlich,
// aber nichts geht verloren.
document.addEventListener('DOMContentLoaded', () => {
  let token = '';

  // Eine Karte vormerken: Gibt ihren Wert zurueck, oder nichts, wenn sie schon
  // dran war oder nichts zu melden hat.
  const vormerken = (karte) => {
    // Nur einmal je Seitenaufruf: Auf- und Zuklappen ist keine neue Nachricht.
    if (karte.dataset.seenDone) return null;
    const wert = karte.dataset.seen || '';
    const [art, nr] = wert.split(':');
    if (!art || !nr) return null;
    karte.dataset.seenDone = '1';
    token = token || karte.dataset.token || '';
    return wert;
  };

  const schickeAlle = (posten) => {
    const fd = new FormData();
    fd.append('_token', token);
    posten.forEach(w => fd.append('posten[]', w));
    fetch('/intern/gesehen', { method: 'POST', body: fd, credentials: 'same-origin' })
      .catch(() => { /* Ohne Netz bleibt die Marke stehen — das ist in Ordnung. */ });
  };

  const melde = (karte) => {
    const wert = vormerken(karte);
    if (wert) schickeAlle([wert]);
  };

  // Die beim Laden offenen Karten gehen GEMEINSAM raus (#400). Einzeln waren
  // das auf einer Terminliste sechsundzwanzig Anfragen in derselben Sekunde,
  // und der Pool dahinter haelt fuenf Arbeiter.
  //
  // Spaeteres Aufklappen bleibt einzeln: Das passiert mit der Hand, eine
  // Anfrage nach der anderen, und da ist nichts zu buendeln.
  const offen = [];
  document.querySelectorAll('details[data-seen]').forEach(karte => {
    // Die erste Karte kommt offen an und löst deshalb nie ein toggle aus. Ohne
    // diese Zeile behielte ausgerechnet der Termin seine Marke, den man bei
    // jedem Besuch der Übersicht vor Augen hat.
    if (karte.open) {
      const wert = vormerken(karte);
      if (wert) offen.push(wert);
    }
    karte.addEventListener('toggle', () => { if (karte.open) melde(karte); });
  });
  if (offen.length) schickeAlle(offen);
});
