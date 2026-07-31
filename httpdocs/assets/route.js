// Navi-Links auf die native Karten-App des Geräts schicken statt fest auf Google.
//
//   iPhone  → Apple Karten (die Standard-Navigation von iOS)
//   Android → geo:  — das System öffnet die als Standard eingestellte Karten-App
//   Desktop → bleibt beim Web-Link (OpenStreetMap) aus dem href
//
// Der Link trägt das Ziel als data-navi (Koordinaten "lat,lng" oder Adresse).
// Die Anwendung selbst ruft dabei nichts ab — es wird nur eine App geöffnet.
(function () {
  const ua = navigator.userAgent || '';
  const ios = /iPhone|iPad|iPod/.test(ua) || (/Mac/.test(ua) && 'ontouchend' in document);
  const android = /Android/.test(ua);
  if (!ios && !android) return; // Desktop: der Web-Link im href genügt

  document.querySelectorAll('a.navi-link[data-navi]').forEach((a) => {
    const dest = a.dataset.navi;
    if (!dest) return;
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const enc = encodeURIComponent(dest);
      // daddr/​q nehmen sowohl Koordinaten als auch eine Adresse.
      window.location.href = ios
        ? 'maps://?daddr=' + enc + '&dirflg=d'
        : 'geo:0,0?q=' + enc;
    });
  });
})();
