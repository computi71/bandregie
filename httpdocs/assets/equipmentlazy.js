// Der Bearbeiten-Block eines Geräts kommt erst, wenn ihn jemand sehen will.
//
// Vorher stand er für jedes Gerät im ausgelieferten Quelltext — allein die
// Auswahl des übergeordneten Geräts führt jedes andere Gerät auf, und bei
// hundert Geräten waren das zwei Drittel der Seite, die niemand aufklappt.
//
// Ohne dieses Skript bleibt der Knopf, der auf dieselbe Adresse führt: dort
// steht derselbe Block als eigene Seite. Es geht also nichts verloren, es
// braucht nur einen Klick mehr.
document.addEventListener('DOMContentLoaded', () => {
  const holen = box => {
    if (box.dataset.eqloaded) return;
    box.dataset.eqloaded = '1';
    fetch(box.dataset.eqdetail, { headers: { 'X-Requested-With': 'fetch' } })
      .then(r => (r.ok ? r.text() : Promise.reject(r.status)))
      .then(html => {
        box.innerHTML = html;
        // Das Formular kommt nach dem Start der Seite an und muss deshalb
        // selbst verdrahtet werden — sonst blieben Besitzer und Lagerort
        // stehen, obwohl ein übergeordnetes Gerät gewählt ist.
        box.dispatchEvent(new CustomEvent('eqdetail', { bubbles: true }));
      })
      .catch(() => {
        // Den Knopf stehen lassen: er führt auf die Seite mit demselben Inhalt.
        box.dataset.eqloaded = '';
      });
  };

  // Aufklappen einer Gerätekarte
  document.querySelectorAll('details.acc').forEach(card => {
    const box = card.querySelector('[data-eqdetail]');
    if (!box) return;
    if (card.open) holen(box);
    card.addEventListener('toggle', () => { if (card.open) holen(box); });
  });

  // Dialog eines Bestandteils
  document.querySelectorAll('[data-eqopen]').forEach(btn => {
    const dlg = document.getElementById(btn.dataset.eqopen);
    const box = dlg && dlg.querySelector('[data-eqdetail]');
    if (box) btn.addEventListener('click', () => holen(box));
  });
});
