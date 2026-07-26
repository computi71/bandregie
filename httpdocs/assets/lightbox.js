// Galerie-Lightbox: Klick auf ein Bild im Foto-Raster öffnet die Großansicht
(function () {
  document.addEventListener('click', function (e) {
    var img = e.target.closest('.photo-grid img');
    if (!img) return;
    e.preventDefault();
    var overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    var big = document.createElement('img');
    big.src = img.src;
    big.alt = img.alt || '';
    overlay.appendChild(big);
    var caption = img.closest('figure') && img.closest('figure').querySelector('figcaption');
    if (caption) {
      var cap = document.createElement('div');
      cap.className = 'lightbox-caption';
      cap.textContent = caption.textContent.trim();
      overlay.appendChild(cap);
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
