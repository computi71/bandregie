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
//     wie der Bildschirm hoch ist, und das Ziel liegt darunter.
//
// Die Pfeiltasten bleiben: Sie sind der Weg mit der Tastatur und der Notausgang,
// wenn ein Gerät sich quer legt.
document.addEventListener('DOMContentLoaded', () => {
  const list = document.querySelector('ol.sortable');
  if (!list) return;

  const url = list.dataset.reorder;
  const token = list.dataset.token;
  const RAND = 70;      // Pixel bis zum Rand, ab denen mitgescrollt wird
  const SCHRITT = 12;   // Pixel je Bild
  let dragged = null;
  let scrollRichtung = 0;
  let scrollLauf = 0;

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

  const scrollen = () => {
    if (!scrollRichtung || !dragged) { scrollLauf = 0; return; }
    window.scrollBy(0, scrollRichtung * SCHRITT);
    scrollLauf = requestAnimationFrame(scrollen);
  };

  const randPruefen = (y) => {
    const vorher = scrollRichtung;
    scrollRichtung = y < RAND ? -1 : (y > window.innerHeight - RAND ? 1 : 0);
    if (scrollRichtung && !scrollLauf) scrollLauf = requestAnimationFrame(scrollen);
    if (!scrollRichtung && vorher) { cancelAnimationFrame(scrollLauf); scrollLauf = 0; }
  };

  list.addEventListener('pointerdown', (e) => {
    // Nur der Griff zieht. Alles andere bleibt Scrollen, Antippen, Blättern.
    const griff = e.target.closest('.drag-handle');
    if (!griff || e.button > 0) return;
    const li = griff.closest('li');
    if (!li) return;
    dragged = li;
    li.classList.add('dragging');
    // Ab jetzt gehen alle Zeigerereignisse an den Griff — auch wenn der Finger
    // die Zeile verlässt, die unter ihm liegt.
    try { griff.setPointerCapture(e.pointerId); } catch (err) { /* dann eben nicht */ }
    e.preventDefault();
  });

  list.addEventListener('pointermove', (e) => {
    if (!dragged) return;
    e.preventDefault();
    const ziel = zeileUnter(e.clientY);
    if (ziel) {
      const b = ziel.getBoundingClientRect();
      const danach = e.clientY > b.top + b.height / 2;
      list.insertBefore(dragged, danach ? ziel.nextSibling : ziel);
    }
    randPruefen(e.clientY);
  });

  const beenden = () => {
    if (!dragged) return;
    dragged.classList.remove('dragging');
    dragged = null;
    scrollRichtung = 0;
    if (scrollLauf) { cancelAnimationFrame(scrollLauf); scrollLauf = 0; }
    renumber();
    save();
  };

  list.addEventListener('pointerup', beenden);
  list.addEventListener('pointercancel', beenden);
  // Verlässt der Zeiger das Fenster, ohne loszulassen, bliebe die Zeile sonst
  // für immer „in der Hand".
  window.addEventListener('blur', beenden);
});
