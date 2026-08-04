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
