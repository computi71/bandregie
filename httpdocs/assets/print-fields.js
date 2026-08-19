// Was auf dem gedruckten Blatt mitkommt (#255).
//
// Die Felder stehen alle im HTML; hier werden nur Klassen am body gesetzt. Das
// hat zwei Vorteile: ein Haken wirkt ohne Neuladen, und gedruckt wird genau das,
// was auf dem Bildschirm steht.
//
// Die Auswahl bleibt im Gerät gespeichert — eine Band druckt ihre Blätter jedes
// Mal gleich, und niemand will das vor jedem Gig neu anklicken.
(function () {
  var SCHLUESSEL = 'bandregie.setlist.felder';
  var HAKEN = ['interpret', 'jahr', 'bpm', 'zeit'];
  var koerper = document.body;
  var kaesten = {};
  HAKEN.forEach(function (name) {
    kaesten[name] = document.querySelector('[data-feld="' + name + '"]');
  });
  var auswahlNotiz = document.querySelector('[data-notiz]');
  if (!auswahlNotiz) return;

  function gespeichert() {
    try {
      var roh = localStorage.getItem(SCHLUESSEL);
      return roh ? JSON.parse(roh) : null;
    } catch (e) {
      return null; // Kein Speicher (privates Fenster): dann eben die Vorgabe.
    }
  }

  function merken(stand) {
    try { localStorage.setItem(SCHLUESSEL, JSON.stringify(stand)); } catch (e) { /* siehe oben */ }
  }

  function anwenden(stand, merkenAuch) {
    HAKEN.forEach(function (name) {
      var an = !!stand[name];
      if (kaesten[name]) kaesten[name].checked = an;
      // Präfix „mit-", damit die Klasse am body nicht so heißt wie die am Feld —
      // gleiche Namen an beiden Stellen machen jede Suche zweideutig.
      koerper.classList.toggle('mit-' + name, an);
    });
    auswahlNotiz.value = stand.notiz || '';
    koerper.classList.toggle('mit-notiz-kurz', stand.notiz === 'kurz');
    koerper.classList.toggle('mit-notiz-lang', stand.notiz === 'lang');
    if (merkenAuch) merken(stand);
    // Andere Zeilenhöhe heißt andere Schriftgröße — der Messer rechnet neu.
    document.dispatchEvent(new CustomEvent('setlist:refit'));
  }

  function lesen() {
    var stand = { notiz: auswahlNotiz.value };
    HAKEN.forEach(function (name) { stand[name] = !!(kaesten[name] && kaesten[name].checked); });
    return stand;
  }

  // Vorgabe ist der Zustand von vorher: nur die erste Notizzeile, sonst nichts.
  anwenden(gespeichert() || { notiz: 'kurz' }, false);

  HAKEN.forEach(function (name) {
    if (kaesten[name]) kaesten[name].addEventListener('change', function () { anwenden(lesen(), true); });
  });
  auswahlNotiz.addEventListener('change', function () { anwenden(lesen(), true); });
})();
