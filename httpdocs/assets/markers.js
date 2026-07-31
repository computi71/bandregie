// Abschnittsmarken per Klick einfügen: der Knopf schiebt [Refrain] o. Ä. an die
// Cursor-Stelle des zugehörigen Feldes, auf eine eigene Zeile, und lässt den
// Cursor dahinter stehen — bereit zum Weitertippen. Kein Ersatz fürs Tippen,
// nur die Abkürzung für die immer gleichen Klammern.
(function () {
  document.querySelectorAll('.marker-bar').forEach((bar) => {
    const ta = document.querySelector('textarea[name="' + bar.dataset.target + '"]');
    if (!ta) return;
    bar.querySelectorAll('button[data-mark]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const mark = btn.dataset.mark;
        const start = ta.selectionStart, end = ta.selectionEnd;
        const before = ta.value.slice(0, start), after = ta.value.slice(end);
        // Auf eine eigene Zeile: davor ein Umbruch, falls nicht ohnehin Zeilenanfang.
        const lead = before === '' || before.endsWith('\n') ? '' : '\n';
        const insert = lead + mark + '\n';
        ta.value = before + insert + after;
        const pos = start + insert.length;
        ta.focus();
        ta.setSelectionRange(pos, pos);
      });
    });
  });
})();
