// Klappmenü: schließt sich beim Klick daneben oder mit Escape. Ohne dieses
// Skript funktioniert es weiterhin — nur eben mit dem Burger als Umschalter.
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('nav-toggle');
  const header = document.querySelector('.site-header');
  if (!toggle || !header) return;

  document.addEventListener('click', e => {
    if (!toggle.checked) return;
    if (!header.contains(e.target)) toggle.checked = false;
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') toggle.checked = false;
  });
});
