// Ein Klick auf die Versionsnummer fragt nach, ob es etwas Neueres gibt, und
// zeigt die Antwort. Ohne Klick fragt niemand — die Fußzeile steht auf jeder
// Seite, und ein Abruf je Seitenaufruf wäre unverschämt gegenüber dem Server,
// den man fragt.
document.addEventListener('DOMContentLoaded', function () {
  var trigger = document.querySelector('[data-versioncheck]');
  var dialog = document.getElementById('version-dialog');
  if (!trigger || !dialog || typeof dialog.showModal !== 'function') return;

  var body = dialog.querySelector('[data-versionbody]');
  var busy = false;

  trigger.addEventListener('click', function (ev) {
    ev.preventDefault();
    if (busy) return;
    busy = true;
    body.textContent = trigger.dataset.checking || '…';
    dialog.showModal();

    fetch('/intern/version', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        body.textContent = '';
        // Zeile für Zeile aufbauen statt HTML zusammenzukleben: was vom
        // Server kommt, landet als Text und nicht als Markup.
        [d.installedLabel, d.latestLabel, d.verdict].forEach(function (line) {
          if (!line) return;
          var p = document.createElement('p');
          p.textContent = line;
          if (line === d.verdict && d.available) p.className = 'warn';
          body.appendChild(p);
        });
      })
      .catch(function () { body.textContent = trigger.dataset.failed || ''; })
      .finally(function () { busy = false; });
  });

  dialog.querySelectorAll('[data-versionclose]').forEach(function (b) {
    b.addEventListener('click', function () { dialog.close(); });
  });
});
