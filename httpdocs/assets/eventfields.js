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

// Zur Bühnentechnik gehören zwei Zusätze, die nur zur jeweiligen Herkunft
// passen: der Hinweis, wohin Angebote und Rechnungen gehören (bei Leihmaterial),
// und die Packliste aus dem Inventar (bei eigenem Material).
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-eventfields]').forEach(form => {
    const sources = ['pa_source', 'light_source']
      .map(n => form.querySelector(`select[name="${n}"]`))
      .filter(Boolean);
    if (!sources.length) return;

    const apply = () => {
      const values = sources.map(s => s.value);
      form.querySelectorAll('[data-prodhint]').forEach(el => { el.hidden = !values.includes('leih'); });
      form.querySelectorAll('[data-prodgear]').forEach(el => { el.hidden = !values.includes('eigene'); });
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
