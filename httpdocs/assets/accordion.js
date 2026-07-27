// Aufklappbare Karten: immer nur eine offen. Neuere Browser können das selbst
// über das name-Attribut von <details>; nur für die anderen springt dieses
// Skript ein. Fällt es aus, lassen sich eben mehrere Karten gleichzeitig
// öffnen — unbequem, aber nichts geht verloren.
document.addEventListener('DOMContentLoaded', () => {
  if ('name' in document.createElement('details')) return;

  document.querySelectorAll('details.acc[name]').forEach(item => {
    item.addEventListener('toggle', () => {
      if (!item.open) return;
      document.querySelectorAll(`details.acc[name="${item.getAttribute('name')}"]`)
        .forEach(other => { if (other !== item) other.open = false; });
    });
  });
});
