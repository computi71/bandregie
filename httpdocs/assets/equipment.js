// Bestandteile erben Besitzer und Lagerort vom übergeordneten Gerät — sobald
// eines gewählt ist, verschwinden beide Felder und ein Hinweis erklärt warum.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('select[name="parent_id"]').forEach(select => {
    const form = select.closest('form');
    if (!form) return;
    const apply = () => {
      const isPart = select.value !== '';
      form.querySelectorAll('[data-eqinherit]').forEach(el => { el.hidden = isPart; });
      form.querySelectorAll('[data-eqhint]').forEach(el => { el.hidden = !isPart; });
    };
    select.addEventListener('change', apply);
    apply();
  });
});

// Bestandteile sind anklickbar und öffnen ihr Bearbeiten-Formular als Dialog.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-eqopen]').forEach(btn => {
    const dlg = document.getElementById(btn.dataset.eqopen);
    if (!dlg) return;
    btn.addEventListener('click', () => dlg.showModal());
    dlg.querySelectorAll('[data-eqclose]').forEach(c => c.addEventListener('click', () => dlg.close()));
    // Klick auf die Fläche neben dem Dialog schließt ihn ebenfalls
    dlg.addEventListener('click', ev => { if (ev.target === dlg) dlg.close(); });
  });
});
