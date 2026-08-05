// Mehrere Fotos auf einen Termin (#191): „Alle anhaken", „Keins", und die Zahl
// der angehakten Bilder. Das ist eine Zugabe — ohne JavaScript hakt man von Hand
// an und ordnet zu, verloren geht nichts.
(function () {
  var formular = document.getElementById('fotos-termin');
  if (!formular) return;

  // Die Häkchen liegen nicht IM Formular, sondern hängen über form="fotos-termin"
  // daran. Deshalb über das Dokument suchen und nicht über das Formular.
  function haken() {
    return Array.prototype.slice.call(
      document.querySelectorAll('input[type=checkbox][form="fotos-termin"]'));
  }

  var zaehler = formular.querySelector('[data-masscount]');
  function zaehlen() {
    if (!zaehler) return;
    var n = haken().filter(function (h) { return h.checked; }).length;
    // Bei null nichts schreiben: Eine Null neben dem Knopf sagt weniger als Stille.
    zaehler.textContent = n ? (zaehler.dataset.template || '%1').replace('%1', String(n)) : '';
  }

  function setzen(wert) {
    haken().forEach(function (h) { h.checked = wert; });
    zaehlen();
  }

  var alle = formular.querySelector('[data-massall]');
  var keins = formular.querySelector('[data-massnone]');
  if (alle) alle.addEventListener('click', function () { setzen(true); });
  if (keins) keins.addEventListener('click', function () { setzen(false); });
  document.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('input[type=checkbox][form="fotos-termin"]')) zaehlen();
  });

  // Ohne angehaktes Bild nicht abschicken: Der Server sagt es sonst erst nach
  // einem Seitenwechsel, und das ist eine unnötige Runde.
  formular.addEventListener('submit', function (e) {
    if (!haken().some(function (h) { return h.checked; })) {
      e.preventDefault();
      if (keins) keins.blur();
      var hinweis = formular.querySelector('[data-massempty]');
      if (hinweis) hinweis.hidden = false;
    }
  });

  zaehlen();
})();

// Herkunftspfad mitschicken (#197). Der Browser nennt im Formular nur den
// Dateinamen; wählt jemand einen ganzen Ordner, kennt er zusätzlich den relativen
// Pfad darin. Den reichen wir in verborgenen Feldern nach — in derselben
// Reihenfolge wie die Dateien, sonst gehörte der Pfad zum falschen Bild.
(function () {
  var feld = document.querySelector('input[type=file][data-paths]');
  if (!feld || !feld.form) return;
  feld.addEventListener('change', function () {
    feld.form.querySelectorAll('input[data-pathfor]').forEach(function (el) { el.remove(); });
    Array.prototype.forEach.call(feld.files || [], function (datei, i) {
      // Ohne Ordnerwahl ist webkitRelativePath leer — dann bleibt es beim
      // Dateinamen, den der Server ohnehin hat.
      if (!datei.webkitRelativePath) return;
      var h = document.createElement('input');
      h.type = 'hidden';
      h.name = 'paths[' + i + ']';
      h.value = datei.webkitRelativePath;
      h.setAttribute('data-pathfor', String(i));
      feld.form.appendChild(h);
    });
  });
})();

// Ordnerwahl schlägt den Termin vor (#208): Der häufigste Aufnahmetag des
// Ordners steht am Eintrag, die Termine tragen ihr Datum — gewählt wird der
// nächstgelegene. Nur eine Vorwahl: Abgeschickt wird erst auf Klick, und ohne
// JavaScript trifft man beide Wahlen von Hand.
(function () {
  var ordner = document.querySelector('select[data-folderpick]');
  var ziel = document.querySelector('select[data-foldertarget]');
  if (!ordner || !ziel) return;
  ordner.addEventListener('change', function () {
    var datum = ordner.selectedOptions[0] && ordner.selectedOptions[0].dataset.datum;
    if (!datum) return;
    var anker = Date.parse(datum);
    if (isNaN(anker)) return;
    var beste = null, besterAbstand = Infinity;
    Array.prototype.forEach.call(ziel.options, function (o) {
      if (!o.dataset.date) return;
      var t = Date.parse(o.dataset.date);
      if (isNaN(t)) return;
      var abstand = Math.abs(t - anker);
      // Bei Gleichstand der jüngere Termin — wie in der Nähe-Ordnung (#207).
      if (abstand < besterAbstand || (abstand === besterAbstand && beste && t > Date.parse(beste.dataset.date))) {
        beste = o; besterAbstand = abstand;
      }
    });
    if (beste) ziel.value = beste.value;
  });
})();
