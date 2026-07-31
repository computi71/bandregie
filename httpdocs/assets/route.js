// Navi-Links auf die passende Karten-App schicken statt fest auf Google.
//
//   Android → geo:  — das System öffnet die als Standard eingestellte App
//                     (Waze, Google Maps … — genau wie gewünscht).
//   iPhone  → kleine Auswahl, weil iOS die System-Standard-Navi-App für
//             Web-Links NICHT preisgibt: maps:// erzwingt immer Apple Karten.
//             Also einmal fragen, womit navigiert werden soll.
//   Desktop → bleibt beim Web-Link (OpenStreetMap) aus dem href.
//
// Das Ziel steht als data-navi (Koordinaten "lat,lng" oder Adresse). Die
// Anwendung ruft dabei nichts ab — es wird nur eine App geöffnet.
(function () {
  const ua = navigator.userAgent || '';
  const ios = /iPhone|iPad|iPod/.test(ua) || (/Mac/.test(ua) && 'ontouchend' in document);
  const android = /Android/.test(ua);
  if (!ios && !android) return; // Desktop: der Web-Link im href genügt

  const enc = (s) => encodeURIComponent(s);
  const links = document.querySelectorAll('a.navi-link[data-navi]');

  if (android) {
    links.forEach((a) => a.addEventListener('click', (e) => {
      if (!a.dataset.navi) return;
      e.preventDefault();
      window.location.href = 'geo:0,0?q=' + enc(a.dataset.navi);
    }));
    return;
  }

  // iOS: eine schlichte Auswahl. daddr/q/destination nehmen Koordinaten wie Adresse.
  const APPS = [
    ['Apple Karten', (d) => 'maps://?daddr=' + d + '&dirflg=d'],
    ['Google Maps', (d) => 'https://www.google.com/maps/dir/?api=1&destination=' + d],
    ['Waze', (d) => 'https://waze.com/ul?q=' + d + '&navigate=yes'],
    ['OpenStreetMap', (d) => 'https://www.openstreetmap.org/search?query=' + d],
  ];

  let pop = null, dest = '';
  function build() {
    pop = document.createElement('div');
    pop.className = 'maps-pick';
    pop.hidden = true;
    const card = document.createElement('div');
    card.className = 'maps-pick-card';
    const title = document.createElement('p');
    title.className = 'maps-pick-title';
    title.textContent = document.body.dataset.naviPick || 'Womit navigieren?';
    card.appendChild(title);
    APPS.forEach(([name, make]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn maps-pick-btn';
      b.textContent = name;
      b.addEventListener('click', () => { pop.hidden = true; window.location.href = make(enc(dest)); });
      card.appendChild(b);
    });
    pop.appendChild(card);
    // Hintergrund antippen schließt.
    pop.addEventListener('click', (e) => { if (e.target === pop) pop.hidden = true; });
    document.body.appendChild(pop);
  }

  links.forEach((a) => a.addEventListener('click', (e) => {
    if (!a.dataset.navi) return;
    e.preventDefault();
    if (!pop) build();
    dest = a.dataset.navi;
    pop.hidden = false;
  }));
})();
