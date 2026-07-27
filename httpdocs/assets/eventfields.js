// Blendet im Terminformular die Felder aus, die zur gewählten Art nicht passen —
// nach einem freien Tag fragt niemand nach Gage, Setlist oder Bühnentechnik.
// Ohne dieses Skript bleibt schlicht alles sichtbar, es geht nichts verloren.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-eventfields]').forEach(form => {
    let allowedByType;
    try { allowedByType = JSON.parse(form.dataset.eventfields); } catch (e) { return; }
    const typeSelect = form.querySelector('select[name="type"]');
    if (!typeSelect) return;

    const apply = () => {
      const allowed = allowedByType[typeSelect.value] || [];
      form.querySelectorAll('[data-eventfield]').forEach(el => {
        el.hidden = !allowed.includes(el.dataset.eventfield);
      });
    };
    typeSelect.addEventListener('change', apply);
    apply();
  });
});
