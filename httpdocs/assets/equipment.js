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
