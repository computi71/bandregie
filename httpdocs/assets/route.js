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

  // Die einmal gewählte App merken (pro Gerät), dann beim nächsten Mal direkt
  // öffnen. Lange auf das 🧭 drücken öffnet die Auswahl wieder zum Wechseln.
  const KEY = 'bandregie-maps-app';
  const getPref = () => { try { return localStorage.getItem(KEY); } catch (e) { return null; } };
  const setPref = (n) => { try { localStorage.setItem(KEY, n); } catch (e) { /* egal */ } };
  const appByName = (n) => APPS.find((a) => a[0] === n);

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
      b.addEventListener('click', () => {
        setPref(name); // Wahl merken → nächstes Mal ohne Nachfrage
        pop.hidden = true;
        window.location.href = make(enc(dest));
      });
      card.appendChild(b);
    });
    const hint = document.createElement('p');
    hint.className = 'maps-pick-hint';
    hint.textContent = document.body.dataset.naviPickHint || '';
    card.appendChild(hint);
    pop.appendChild(card);
    pop.addEventListener('click', (e) => { if (e.target === pop) pop.hidden = true; });
    document.body.appendChild(pop);
  }
  function showChooser(d) { if (!pop) build(); dest = d; pop.hidden = false; }

  links.forEach((a) => {
    // Lange drücken → Auswahl erneut, zum Wechseln. iOS Safari feuert bei
    // Touch-Langdruck kein contextmenu — deshalb ein eigener Timer; das
    // contextmenu bleibt für Trackpad/Maus (iPad, Desktop-Safari) bestehen.
    let holdTimer = 0;
    let held = false;
    a.addEventListener('touchstart', () => {
      held = false;
      holdTimer = window.setTimeout(() => { held = true; showChooser(a.dataset.navi); }, 500);
    }, { passive: true });
    a.addEventListener('touchmove', () => clearTimeout(holdTimer), { passive: true });
    ['touchend', 'touchcancel'].forEach((ev) => a.addEventListener(ev, (e) => {
      clearTimeout(holdTimer);
      // Nach einem Langdruck darf der nachlaufende Klick nicht auch noch
      // navigieren — die Auswahl ist ja schon offen.
      if (held && e.cancelable) e.preventDefault();
    }));
    a.addEventListener('click', (e) => {
      if (!a.dataset.navi) return;
      e.preventDefault();
      if (held) { held = false; return; }
      dest = a.dataset.navi;
      const app = appByName(getPref());
      if (app) window.location.href = app[1](enc(dest)); // gemerkte App direkt
      else showChooser(dest);                             // erste Wahl treffen
    });
    a.addEventListener('contextmenu', (e) => {
      if (!a.dataset.navi) return;
      e.preventDefault();
      showChooser(a.dataset.navi);
    });
  });

  // Escape schließt die Auswahl — für Tastatur und Bluetooth-Pedale.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && pop && !pop.hidden) pop.hidden = true;
  });
})();
