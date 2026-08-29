// Schriftgröße des Ausdrucks an das Blatt anpassen (#252).
//
// Die Serverseite kann die Höhe nur schätzen: Wie hoch das Logo wirklich ist, ob
// die Infozeile umbricht, wie breit „Du hast den Farbfilm vergessen" in Calibri
// ausfällt — das weiß erst der Browser. Also messen: die größte Schrift nehmen,
// bei der die Liedliste noch auf das Blatt passt.
//
// Warum überhaupt so groß wie möglich: Das Blatt liegt auf einem Notenpult und
// wird aus zwei Metern gelesen. Jeder Punkt zählt, und ungenutzter Platz unten
// ist verschenkte Lesbarkeit.
(function () {
  var MIN = 12, MAX = 40;

  // Der Platz zwischen dem Beginn der Liste und dem unteren Blattrand.
  function platz(blatt, liste) {
    var stil = getComputedStyle(blatt);
    var unten = parseFloat(stil.paddingBottom) || 0;
    var oben = liste.getBoundingClientRect().top - blatt.getBoundingClientRect().top;
    return blatt.clientHeight - unten - oben;
  }

  function anpassen(blatt) {
    var liste = blatt.querySelector('.songs');
    if (!liste) return;
    var frei = platz(blatt, liste);
    if (frei <= 0) return;
    // Halbierungssuche über Viertelpunkte: höchstens ~9 Messungen je Blatt.
    var klein = MIN, gross = MAX;
    for (var i = 0; i < 9; i++) {
      var mitte = (klein + gross) / 2;
      liste.style.fontSize = mitte.toFixed(2) + 'pt';
      // scrollHeight statt offsetHeight: die Liste darf über das Blatt
      // hinauswachsen, ohne dass overflow:hidden die Messung verfälscht.
      if (liste.scrollHeight <= platz(blatt, liste)) klein = mitte; else gross = mitte;
    }
    liste.style.fontSize = klein.toFixed(2) + 'pt';
  }

  function alle() {
    var blaetter = document.querySelectorAll('.sheet');
    for (var i = 0; i < blaetter.length; i++) anpassen(blaetter[i]);
    aufBildschirmbreite(blaetter);
  }

  // A4 ist 210 mm breit, ein Telefon ist es nicht. Seit die Ansicht ein
  // Viewport-Meta hat, rechnet der Browser nicht mehr heimlich auf 980 px um —
  // also verkleinern wir das Blatt selbst, so weit es sein muss. Der Druck
  // bleibt unberührt: transform gilt nur am Bildschirm.
  function aufBildschirmbreite(blaetter) {
    if (!blaetter.length || !window.matchMedia('screen').matches) return;
    // Der Platz ist der INHALT des Elternkastens: clientWidth zählt sein
    // Innenabstand mit, und die Ansicht hat am Bildschirm einen Rand. Ohne den
    // Abzug ragt das Blatt genau um diesen Rand über die Seite hinaus.
    var eltern = blaetter[0].parentElement || document.body;
    var es = getComputedStyle(eltern);
    var platz = eltern.clientWidth - parseFloat(es.paddingLeft) - parseFloat(es.paddingRight);
    var breite = blaetter[0].offsetWidth;
    var faktor = breite > platz ? platz / breite : 1;
    for (var i = 0; i < blaetter.length; i++) {
      var b = blaetter[i];
      b.style.transformOrigin = 'top left';
      b.style.transform = faktor < 1 ? 'scale(' + faktor.toFixed(4) + ')' : '';
      // Verkleinern ändert die Größe im LAYOUT nicht — ohne Ausgleich klafft
      // unten eine Lücke von einem Drittel Blatt, und die Seite ließe sich
      // seitwärts schieben, obwohl nichts mehr dort steht.
      b.style.marginBottom = faktor < 1 ? (-(1 - faktor) * b.offsetHeight) + 'px' : '';
      b.style.marginRight = faktor < 1 ? (-(1 - faktor) * b.offsetWidth) + 'px' : '';
    }
  }

  alle();
  // Das Logo ist ein Bild: vor dem Laden ist der Kopf noch flach und der Platz
  // zu groß gemessen. Nach dem Laden nochmal.
  window.addEventListener('load', alle);
  window.addEventListener('resize', alle);
  // Wer Felder zu- oder abschaltet, ändert die Höhe der Zeilen — dann gilt die
  // gemessene Schrift nicht mehr (#255).
  document.addEventListener('setlist:refit', alle);
})();
