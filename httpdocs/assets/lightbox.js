// Galerie-Lightbox: Klick auf ein Bild im Foto-Raster öffnet die Großansicht
(function () {
  document.addEventListener('click', function (e) {
    var img = e.target.closest('.photo-grid img');
    if (!img) return;
    e.preventDefault();
    var overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    var big = document.createElement('img');
    // Die Kachel zeigt eine verkleinerte Fassung; groß wird das Original geholt
    big.src = img.dataset.full || img.src;
    big.alt = img.alt || '';
    overlay.appendChild(big);
    var caption = img.closest('figure') && img.closest('figure').querySelector('figcaption');
    if (caption) {
      // Nur der Text der Beschriftung, nicht die Bedienelemente darin. Im
      // Bandbereich stehen im figcaption auch Knöpfe und die Terminauswahl, und
      // textContent einer Auswahlliste ist die Aneinanderreihung aller Optionen:
      // Unter dem großen Bild stand dadurch der komplette Terminkalender.
      // Kopie statt Original, damit die Kachel darunter unangetastet bleibt.
      var klon = caption.cloneNode(true);
      klon.querySelectorAll('form, button, select, input, textarea, label').forEach(function (el) {
        el.remove();
      });
      // Reste des Trennzeichens wegräumen, das vorher zwischen Text und
      // Bedienelementen stand — sonst endet die Zeile auf einem einsamen Punkt.
      var text = klon.textContent.replace(/\s+/g, ' ').trim().replace(/(^·\s*)|(\s*·$)/g, '').trim();
      if (text) {
        var cap = document.createElement('div');
        cap.className = 'lightbox-caption';
        cap.textContent = text;
        overlay.appendChild(cap);
      }
    }
    function close() {
      overlay.remove();
      document.removeEventListener('keydown', onKey);
    }
    function onKey(ev) { if (ev.key === 'Escape') close(); }
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.appendChild(overlay);
  });
})();
