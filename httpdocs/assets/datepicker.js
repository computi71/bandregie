// Ein Klick ins Datumsfeld soll den Kalender öffnen. Von sich aus tun das die
// Browser am Rechner nicht — dort führt nur das kleine Symbol am rechten Rand
// zum Kalender, und der sieht dann aus, als gäbe es ihn nicht.
//
// showPicker() gibt es erst seit Chrome 99 und Safari 16; wo es fehlt, bleibt
// es beim Symbol. Und wo das Feld gesperrt ist, hat niemand etwas zu wählen.
document.addEventListener('click', function (ev) {
  var el = ev.target;
  if (!el || el.tagName !== 'INPUT') return;
  if (el.type !== 'date' && el.type !== 'month' && el.type !== 'time') return;
  if (el.disabled || el.readOnly || typeof el.showPicker !== 'function') return;
  try {
    el.showPicker();
  } catch (e) {
    // Manche Browser lassen showPicker nur direkt aus einer Nutzeraktion zu
    // und werfen sonst. Dann bleibt es beim gewohnten Verhalten.
  }
});
