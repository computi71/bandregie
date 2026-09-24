// Ein sichtbarer Kalenderknopf neben jedem Datumsfeld (#343).
//
// Vorher öffnete ein Klick irgendwo ins Feld den Kalender (#64) — das native
// Symbol wurde übersehen, und der Kalender sah aus, als gäbe es ihn nicht.
// Das nahm aber das Tippen weg: Steht der Kalender offen, hält er die
// Tastatur, und die Ziffern erreichen die Stellen des Feldes nie. Gemessen an
// der Demo: bei offenem Kalender kommt von „03082028" nichts an, bei
// geschlossenem steht danach 2028-08-03 darin.
//
// Wer ein Datum zwei Jahre voraus braucht, klickt sich sonst durch 23 Monate.
//
// Der Knopf löst beides: Er ist auffälliger als das native Symbol je war —
// was #64 eigentlich wollte —, und das Feld bleibt ein Feld, in das man
// tippen kann.
(function () {
  'use strict';

  // showPicker() gibt es erst seit Chrome 99 und Safari 16. Fehlt es, bleibt
  // es beim nativen Symbol; ein Knopf, der nichts tut, wäre schlimmer als
  // keiner.
  function moeglich(feld) {
    return !feld.disabled && !feld.readOnly && typeof feld.showPicker === 'function';
  }

  function versorge(feld) {
    if (feld.dataset.kalender || !moeglich(feld)) return;
    feld.dataset.kalender = '1';

    // Eine Zeile um Feld und Knopf: label ist eine Spalte, der Knopf stünde
    // sonst unter dem Feld und über die ganze Breite.
    var reihe = document.createElement('span');
    reihe.className = 'date-row';
    feld.parentNode.insertBefore(reihe, feld);
    reihe.appendChild(feld);

    var knopf = document.createElement('button');
    // type="button": ohne das verschickt ein Klick das Formular.
    knopf.type = 'button';
    knopf.className = 'btn btn-small date-btn';
    knopf.textContent = '📅';
    knopf.tabIndex = -1;   // wer tippt, soll nicht erst daran vorbei
    var text = document.body.dataset.datePick || '';
    if (text) { knopf.title = text; knopf.setAttribute('aria-label', text); }
    knopf.addEventListener('click', function () {
      try {
        feld.showPicker();
      } catch (e) {
        // Manche Browser lassen showPicker nur direkt aus einer Nutzeraktion
        // zu. Dann wenigstens den Sprung ins Feld.
        feld.focus();
      }
    });
    reihe.appendChild(knopf);
  }

  function alle(wurzel) {
    var felder = wurzel.querySelectorAll('input[type=date],input[type=month],input[type=time]');
    for (var i = 0; i < felder.length; i++) versorge(felder[i]);
  }

  function start() {
    alle(document);
    // Termine, Rechnungen und Equipment legen Felder erst später an. Ohne den
    // Beobachter bekämen genau die keinen Knopf — und niemand sucht den
    // Fehler dort, wo er nur manchmal auftritt.
    if (typeof MutationObserver !== 'function') return;
    new MutationObserver(function (aenderungen) {
      for (var i = 0; i < aenderungen.length; i++) {
        var neu = aenderungen[i].addedNodes;
        for (var j = 0; j < neu.length; j++) {
          if (neu[j].nodeType !== 1) continue;
          if (neu[j].matches && neu[j].matches('input[type=date],input[type=month],input[type=time]')) versorge(neu[j]);
          else if (neu[j].querySelectorAll) alle(neu[j]);
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
