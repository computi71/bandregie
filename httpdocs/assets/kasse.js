// Beim Dauerauftrag hängt davon, für wen er gilt, ab welche Felder noch etwas
// zu entscheiden geben: eine Einzahlung ist immer eine Einnahme unter
// „Einzahlung Mitglieder" — die beiden Auswahlfelder wären dort nur Fallen.
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('form[action="/intern/kasse/dauerauftrag"]');
  if (!form) return;
  var scope = form.elements.scope;
  var fixed = form.querySelectorAll('[data-orderfield]');
  if (!scope || !fixed.length) return;

  function update() {
    var deposit = scope.value === 'einzahlung';
    fixed.forEach(function (el) { el.hidden = deposit; });
  }
  scope.addEventListener('change', update);
  update();
});
