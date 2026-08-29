// Setlist umsortieren — mit Maus, Finger oder Stift.
//
// Vorher lag hier HTML5-Drag-and-drop, und das feuert auf Touchgeräten nicht.
// Der Hinweistext schickte Handynutzer deshalb zu den Pfeiltasten: bei achtund-
// dreißig Titeln bis zu siebenunddreißig Tipp-Vorgänge, um eine Zugabe nach vorn
// zu holen — auf genau der Seite, die kurz vor dem Auftritt am Handy offen ist
// (#237).
//
// Zeigerereignisse kennen alle drei Eingabearten, also gibt es jetzt EINEN
// Mechanismus statt zweier. Zwei Dinge entscheiden, ob es am Handy taugt:
//
//   * Gezogen wird nur am Griff (⠿), und nur der bekommt touch-action: none.
//     Wäre die ganze Zeile ziehbar, könnte der Finger die Liste nicht mehr
//     scrollen — und Scrollen braucht man bei achtunddreißig Zeilen zuerst.
//   * Am Bildschirmrand wird mitgescrollt. Ohne das reicht ein Zug nur so weit,
//     wie der Bildschirm hoch ist, und das Ziel liegt darunter. Wie schnell,
//     hängt davon ab, wie tief der Finger im Randstreifen steht — mit festem
//     Tempo raste die Liste am Ziel vorbei, sobald man den Rand nur streifte,
//     und wer eine Zeile am unteren Bildschirmrand anfasste, sah sie sofort
//     davonlaufen (#265).
//
// Die Pfeiltasten bleiben: Sie sind der Weg mit der Tastatur und der Notausgang,
// wenn ein Gerät sich quer legt.
document.addEventListener('DOMContentLoaded', () => {
  const list = document.querySelector('ol.sortable');
  if (!list) return;

  const url = list.dataset.reorder;
  const token = list.dataset.token;
  const RAND = 60;        // Randstreifen in Pixeln, in dem mitgescrollt wird
  const TEMPO = 700;      // Pixel je Sekunde, ganz außen am Rand
  const SCHWELLE = 6;     // so weit muss der Finger, bevor gescrollt wird
  let dragged = null;
  let startY = 0;
  let letztesY = 0;
  let bewegt = false;
  let scrollTempo = 0;    // Pixel je Sekunde, Vorzeichen ist die Richtung
  let scrollLauf = 0;
  let letzterTakt = 0;

  const flash = (text, ok = true) => {
    let note = document.getElementById('sort-note');
    if (!note) {
      note = document.createElement('div');
      note.id = 'sort-note';
      note.className = 'sort-note';
      list.parentNode.insertBefore(note, list.nextSibling);
    }
    note.textContent = text;
    note.classList.toggle('sort-note-error', !ok);
    note.style.opacity = '1';
    clearTimeout(note.timer);
    note.timer = setTimeout(() => { note.style.opacity = '0'; }, 2500);
  };

  // Nach dem Ziehen neu zählen — und zwar nur die Lieder. Pausen, Sprechpausen
  // und der Zugabe-Strich tragen keine Nummer, sonst wäre der zwölfte Song die
  // Vierzehn (#247). Erkannt werden sie an der Klasse, die die Ansicht schon
  // setzt: break-row.
  const renumber = () => {
    let n = 0;
    [...list.children].forEach((li) => {
      const pos = li.querySelector('.pos');
      if (!pos) return;
      pos.textContent = li.classList.contains('break-row') ? '' : ++n;
    });
  };

  const save = () => {
    const body = new FormData();
    body.append('_token', token);
    [...list.children].forEach(li => body.append('order[]', li.dataset.item));
    fetch(url, { method: 'POST', body, credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(() => flash(list.dataset.savedText || '✔'))
      .catch(() => flash('✕', false));
  };

  // Die Zeile unter dem Zeiger — über die Geometrie und nicht über
  // elementFromPoint: Unter dem Finger liegt die gezogene Zeile selbst.
  const zeileUnter = (y) => [...list.children].find((li) => {
    if (li === dragged) return false;
    const b = li.getBoundingClientRect();
    return y >= b.top && y <= b.bottom;
  });

  // Die Zeile dorthin einsortieren, wo der Zeiger gerade steht.
  const einsortieren = (y) => {
    const ziel = zeileUnter(y);
    if (!ziel) return;
    const b = ziel.getBoundingClientRect();
    const danach = y > b.top + b.height / 2;
    list.insertBefore(dragged, danach ? ziel.nextSibling : ziel);
  };

  // In Pixeln je Sekunde gerechnet, nicht je Bild: Ein Telefon mit 120 Hz
  // scrollte sonst doppelt so schnell wie eines mit 60.
  const scrollen = (zeit) => {
    if (!scrollTempo || !dragged) { scrollLauf = 0; letzterTakt = 0; return; }
    // Der erste Takt hat keine Vorgeschichte, und ein Bild, das zu lange her
    // ist (Tab war im Hintergrund), darf keinen Sprung auslösen.
    const dauer = letzterTakt ? Math.min((zeit - letzterTakt) / 1000, 0.05) : 0;
    letzterTakt = zeit;
    if (dauer) {
      const vorher = window.scrollY;
      window.scrollBy(0, scrollTempo * dauer);
      // Die Zeile bleibt unter dem Finger, während die Seite darunter wandert.
      if (window.scrollY !== vorher) einsortieren(letztesY);
    }
    scrollLauf = requestAnimationFrame(scrollen);
  };

  const randPruefen = (y) => {
    // Anteil im Randstreifen: an der Innenkante 0, ganz außen 1.
    const anteil = (tiefe) => Math.min(1, Math.max(0, tiefe) / RAND);
    const oben = RAND - y;
    const unten = y - (window.innerHeight - RAND);
    scrollTempo = 0;
    // Erst wenn der Finger sich wirklich bewegt hat: Wer eine Zeile am unteren
    // Rand nur anfasst, will sie greifen und nicht die Liste durchlaufen sehen.
    if (bewegt) {
      if (oben > 0) scrollTempo = -TEMPO * anteil(oben);
      else if (unten > 0) scrollTempo = TEMPO * anteil(unten);
    }
    if (scrollTempo && !scrollLauf) { letzterTakt = 0; scrollLauf = requestAnimationFrame(scrollen); }
  };

  list.addEventListener('pointerdown', (e) => {
    // Nur der Griff zieht. Alles andere bleibt Scrollen, Antippen, Blättern.
    const griff = e.target.closest('.drag-handle');
    if (!griff || e.button > 0) return;
    const li = griff.closest('li');
    if (!li) return;
    dragged = li;
    startY = e.clientY;
    letztesY = e.clientY;
    bewegt = false;
    li.classList.add('dragging');
    // Ab jetzt gehen alle Zeigerereignisse an den Griff — auch wenn der Finger
    // die Zeile verlässt, die unter ihm liegt.
    try { griff.setPointerCapture(e.pointerId); } catch (err) { /* dann eben nicht */ }
    e.preventDefault();
  });

  list.addEventListener('pointermove', (e) => {
    if (!dragged) return;
    e.preventDefault();
    letztesY = e.clientY;
    if (Math.abs(e.clientY - startY) > SCHWELLE) bewegt = true;
    einsortieren(e.clientY);
    randPruefen(e.clientY);
  });

  const beenden = () => {
    if (!dragged) return;
    dragged.classList.remove('dragging');
    dragged = null;
    bewegt = false;
    scrollTempo = 0;
    letzterTakt = 0;
    if (scrollLauf) { cancelAnimationFrame(scrollLauf); scrollLauf = 0; }
    renumber();
    save();
  };

  // Die Pfeiltasten verschieben die Zeile hier und speichern wie das Ziehen.
  // Vorher schickte jeder Tipp ein Formular ab: Die Seite lud neu, stand wieder
  // oben, und der Song, den man gerade bewegt hatte, lag irgendwo darunter — bei
  // vierzig Zeilen sucht man ihn nach jedem Schritt erneut (#265).
  list.addEventListener('click', (e) => {
    const knopf = e.target.closest('button[name="dir"]');
    if (!knopf) return;
    const li = knopf.closest('li');
    const hoch = knopf.value === 'up';
    const nachbar = li && (hoch ? li.previousElementSibling : li.nextElementSibling);
    // Am Anfang oder Ende gibt es nichts zu tauschen — der Server täte auch
    // nichts, aber ohne Neuladen bleibt wenigstens die Ansicht ruhig.
    if (!nachbar) { e.preventDefault(); return; }
    // Klammern zeichnet der Server. Wandert eine Zeile in eine hinein oder aus
    // ihr heraus, muss die Seite neu kommen, sonst steht hier eine Klammer, die
    // es so nicht mehr gibt — dann bleibt es beim Formular.
    if (li.dataset.bracket || nachbar.dataset.bracket) return;
    e.preventDefault();
    list.insertBefore(hoch ? li : nachbar, hoch ? nachbar : li);
    renumber();
    save();
    // Der Finger bleibt, wo er ist: derselbe Knopf, derselbe Song.
    knopf.focus();
    knopf.scrollIntoView({ block: 'nearest' });
  });

  list.addEventListener('pointerup', beenden);
  list.addEventListener('pointercancel', beenden);
  // Verlässt der Zeiger das Fenster, ohne loszulassen, bliebe die Zeile sonst
  // für immer „in der Hand".
  window.addEventListener('blur', beenden);
});
