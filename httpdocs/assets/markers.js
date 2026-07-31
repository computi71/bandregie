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

  // „In meine kopieren": den Notizzettel eines anderen Musikers ins eigene Feld
  // übernehmen — client-seitig, gespeichert wird mit dem normalen Speichern.
  document.querySelectorAll('button[data-copy-into]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const ta = document.querySelector('textarea[name="' + btn.dataset.copyInto + '"]');
      const details = btn.closest('details');
      const pre = details ? details.querySelector('pre[data-chords]') : null;
      if (ta && pre) { ta.value = pre.textContent; ta.focus(); }
    });
  });

  // Tempo eintippen: neben dem Tempo-Feld den Takt tippen, die Abstände werden
  // gemittelt und als BPM ins Feld geschrieben. Lange Pause = neue Zählung.
  document.querySelectorAll('button[data-taptempo]').forEach((btn) => {
    const input = document.querySelector('input[name="' + btn.dataset.taptempo + '"]');
    if (!input) return;
    let taps = [];
    btn.addEventListener('click', () => {
      const now = performance.now();
      if (taps.length && now - taps[taps.length - 1] > 2000) taps = [];
      taps.push(now);
      if (taps.length > 6) taps.shift();
      if (taps.length >= 2) {
        const bpm = Math.round(60000 / ((taps[taps.length - 1] - taps[0]) / (taps.length - 1)));
        if (bpm >= 30 && bpm <= 260) input.value = bpm + ' BPM';
      }
    });
  });
})();
