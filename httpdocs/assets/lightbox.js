// Galerie-Lightbox: Klick auf ein Bild im Foto-Raster öffnet die Großansicht.
// Von dort aus blättern und, wer mag, eine Diashow (#192).
//
// Alles hier ist eine Zugabe: Ohne JavaScript öffnet der Klick das Bild wie
// gewohnt in seiner eigenen Adresse, und nichts geht verloren.
(function () {
  var TAKT = 4000;   // Millisekunden je Bild in der Diashow — lang genug zum Hinsehen

  // Die Beschriftung ist der Text der Beschriftung, nicht die Bezeichnungen ihrer
  // Bedienelemente. Im Bandbereich stehen im figcaption auch Knöpfe und die
  // Terminauswahl, und der Textinhalt einer Auswahlliste ist die Aneinanderreihung
  // aller Optionen: Unter dem großen Bild stand dadurch der ganze Terminkalender.
  function beschriftung(figur) {
    var quelle = figur && figur.querySelector('figcaption');
    if (!quelle) return '';
    // Auf einer Kopie arbeiten, damit die Kachel darunter unangetastet bleibt.
    var klon = quelle.cloneNode(true);
    klon.querySelectorAll('form, button, select, input, textarea, label').forEach(function (el) {
      el.remove();
    });
    // Reste des Trennzeichens wegräumen, das zwischen Text und Bedienelementen
    // stand — sonst endet die Zeile auf einem einsamen Punkt.
    return klon.textContent.replace(/\s+/g, ' ').trim().replace(/(^·\s*)|(\s*·$)/g, '').trim();
  }

  document.addEventListener('click', function (e) {
    var img = e.target.closest('.photo-grid img');
    if (!img) return;
    e.preventDefault();

    // Alle Bilder desselben Rasters sind die Sammlung, durch die geblättert wird.
    var raster = img.closest('.photo-grid');
    var bilder = Array.prototype.slice.call(raster.querySelectorAll('img'));
    var stelle = bilder.indexOf(img);

    var overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';

    var big = document.createElement('img');
    var cap = document.createElement('div');
    cap.className = 'lightbox-caption';

    function zeigen(i) {
      // Umlaufend: Nach dem letzten kommt das erste. Beim Durchsehen einer Galerie
      // ist das erwartbarer als ein Knopf, der plötzlich nichts tut.
      stelle = (i + bilder.length) % bilder.length;
      var b = bilder[stelle];
      big.src = b.dataset.full || b.src;
      big.alt = b.alt || '';
      var text = beschriftung(b.closest('figure'));
      cap.textContent = bilder.length > 1
        ? (text ? text + ' · ' : '') + (stelle + 1) + '/' + bilder.length
        : text;
      cap.hidden = !cap.textContent;
    }

    function bauKnopf(zeichen, klasse, titel, tun) {
      var k = document.createElement('button');
      k.type = 'button';
      k.className = klasse;
      k.textContent = zeichen;
      k.title = titel;
      k.setAttribute('aria-label', titel);
      // Der Klick darf nicht bis zum Overlay durchfallen — das schließt sonst.
      k.addEventListener('click', function (ev) { ev.stopPropagation(); tun(); });
      return k;
    }

    var lauf = null;
    function haltAn() {
      if (!lauf) return;
      clearInterval(lauf);
      lauf = null;
      show.textContent = '▶';
      show.title = show.dataset.start;
      show.setAttribute('aria-label', show.dataset.start);
    }
    function starte() {
      lauf = setInterval(function () { zeigen(stelle + 1); }, TAKT);
      show.textContent = '⏸';
      show.title = show.dataset.stop;
      show.setAttribute('aria-label', show.dataset.stop);
    }

    var texte = raster.dataset;
    var zurueck = bauKnopf('‹', 'lightbox-nav prev', texte.prev || 'Zurück', function () { haltAn(); zeigen(stelle - 1); });
    var vor     = bauKnopf('›', 'lightbox-nav next', texte.next || 'Weiter', function () { haltAn(); zeigen(stelle + 1); });
    var show    = bauKnopf('▶', 'lightbox-show', texte.showStart || 'Diashow', function () { lauf ? haltAn() : starte(); });
    show.dataset.start = texte.showStart || 'Diashow';
    show.dataset.stop = texte.showStop || 'Anhalten';

    overlay.appendChild(big);
    overlay.appendChild(cap);
    if (bilder.length > 1) {
      overlay.appendChild(zurueck);
      overlay.appendChild(vor);
      overlay.appendChild(show);
    }

    // Ein Klick auf das Bild selbst blättert weiter statt zu schließen: Auf dem
    // Handy ist die Fläche der Griff, und die Knöpfe sind klein.
    big.addEventListener('click', function (ev) { ev.stopPropagation(); haltAn(); zeigen(stelle + 1); });

    function close() {
      haltAn();
      overlay.remove();
      document.removeEventListener('keydown', onKey);
    }
    function onKey(ev) {
      if (ev.key === 'Escape') close();
      else if (ev.key === 'ArrowLeft') { haltAn(); zeigen(stelle - 1); }
      else if (ev.key === 'ArrowRight') { haltAn(); zeigen(stelle + 1); }
      else if (ev.key === ' ') { ev.preventDefault(); lauf ? haltAn() : starte(); }
    }
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', onKey);

    zeigen(stelle);
    document.body.appendChild(overlay);
  });
})();
