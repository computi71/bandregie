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

// Ein Zusatz hängt an der Herkunft der Technik: der Hinweis, wohin Angebote
// und Rechnungen gehören. Er erscheint nur bei Leihmaterial.
//
// Die Packliste hängt nicht daran — sie richtet sich nach der Terminart und
// wird oben mit den übrigen Feldern ein- und ausgeblendet.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-eventfields]').forEach(form => {
    const sources = ['pa_source', 'light_source']
      .map(n => form.querySelector(`select[name="${n}"]`))
      .filter(Boolean);
    if (!sources.length) return;

    const apply = () => {
      const values = sources.map(s => s.value);
      form.querySelectorAll('[data-prodhint]').forEach(el => { el.hidden = !values.includes('leih'); });
    };
    sources.forEach(s => s.addEventListener('change', apply));
    apply();

    // Wer den Koffer mitnimmt, nimmt die Mikrofone darin mit — der Haken am
    // Gerät setzt die Bestandteile gleich mit. Einzeln abwählen geht weiterhin.
    form.querySelectorAll('[data-gearparent]').forEach(parent => {
      parent.addEventListener('change', () => {
        form.querySelectorAll(`[data-gearchild="${parent.dataset.gearparent}"]`)
          .forEach(child => { child.checked = parent.checked; });
      });
    });
  });
});
