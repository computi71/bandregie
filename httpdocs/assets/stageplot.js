// Symbole im Bühnenplan ziehen. Das ist eine Zugabe: gespeichert wird über
// die Zahlenfelder darunter, die dieses Skript nur mitschreibt. Ohne
// JavaScript tippt man die Werte eben ein, verloren geht nichts.
document.addEventListener('DOMContentLoaded', () => {
  const svg = document.querySelector('.stage-plot[data-stageedit]');
  if (!svg) return;

  // Die Maße kommen vom SVG und stehen nicht mehr hier: Das Bühnenmaß ist
  // einstellbar, und zwei Stellen mit denselben Zahlen laufen auseinander,
  // sobald jemand eine davon ändert.
  const ox = +svg.dataset.ox, oy = +svg.dataset.oy;
  const sw = +svg.dataset.sw, sh = +svg.dataset.sh;

  // Umgekehrt zur Zeichnung: Bildpunkt zurück auf Prozent rechnen
  const toPercent = (px, py) => ({
    x: Math.max(0, Math.min(100, Math.round(((px - ox) / sw) * 100))),
    y: Math.max(0, Math.min(100, Math.round(((py - oy) / sh) * 100))),
  });

  const place = (item, x, y) => {
    item.setAttribute('transform', `translate(${ox + (x / 100) * sw},${oy + (y / 100) * sh})`);
  };

  let dragged = null;

  const pointIn = event => {
    const box = svg.getBoundingClientRect();
    return {
      px: ((event.clientX - box.left) / box.width) * (+svg.dataset.vw),
      py: ((event.clientY - box.top) / box.height) * (+svg.dataset.vh),
    };
  };

  svg.querySelectorAll('.stage-item').forEach(item => {
    item.addEventListener('pointerdown', event => {
      dragged = item;
      item.setPointerCapture(event.pointerId);
      event.preventDefault();
    });
  });

  svg.addEventListener('pointermove', event => {
    if (!dragged) return;
    const { px, py } = pointIn(event);
    const pos = toPercent(px, py);
    place(dragged, pos.x, pos.y);
    const row = document.querySelector(`[data-stagerow="${dragged.dataset.id}"]`);
    if (!row) return;
    const [fx, fy] = row.querySelectorAll('.stage-num');
    if (fx) fx.value = pos.x;
    if (fy) fy.value = pos.y;
  });

  const stop = () => { dragged = null; };
  svg.addEventListener('pointerup', stop);
  svg.addEventListener('pointercancel', stop);
});
