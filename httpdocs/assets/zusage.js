// Zusagen in der Übersicht abschicken, ohne die Seite neu zu laden (#377).
//
// Der Fehler, den das behebt: Die offene Arbeit zeigt die Termine, zu denen
// noch keine Antwort vorliegt. Jede Antwort löschte also ihre eigene Zeile, und
// beim Neuladen rutschte alles darunter eine Position hoch — mitten in die
// Stelle, auf die der Finger gerade noch getippt hatte. Wer zwei Termine
// nacheinander beantwortete, traf beim zweiten Tipp einen anderen Termin, als
// er sah. Dazu verschwand die Zeile, ohne die Antwort je zu zeigen, sodass es
// aussah, als wäre die Zusage verlorengegangen.
//
// Deshalb hier: erst die Antwort sichtbar machen, dann die Zeile zuklappen, und
// erst danach darf der Rest nachrücken.
//
// Fällt das Skript aus oder ist kein Netz da, schickt das Formular ganz normal
// ab und die Seite lädt wie zuvor. Ohne JavaScript ändert sich gar nichts.
document.addEventListener('DOMContentLoaded', () => {
  const ZEIGEN = 700;   // wie lange die Antwort stehen bleibt
  const KLAPPEN = 250;  // wie lange das Zuklappen dauert

  const ruhig = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Die drei Knöpfe eines Formulars auf eine Antwort einstellen: Die gewählte
   * wird farbig, die beiden anderen treten zurück, und keiner nimmt mehr einen
   * zweiten Tipp an — die Zeile ist ja gleich weg.
   */
  const markiere = (form, status) => {
    form.querySelectorAll('button.chip').forEach(knopf => {
      const gewaehlt = knopf.value === status;
      knopf.classList.toggle('chip-' + status, gewaehlt);
      knopf.classList.toggle('chip-stumm', !gewaehlt);
      knopf.disabled = true;
    });
  };

  /**
   * Steht derselbe Termin weiter unten als Terminkarte, bekommt er dieselbe
   * Antwort. Sonst zeigte eine Seite zwei verschiedene Stände.
   */
  const gleicheTermine = (form, status) => {
    document.querySelectorAll('form.attendance').forEach(andere => {
      if (andere === form || andere.getAttribute('action') !== form.getAttribute('action')) return;
      andere.querySelectorAll('button.chip').forEach(knopf => {
        knopf.classList.remove('chip-yes', 'chip-maybe', 'chip-no');
        if (knopf.value === status) knopf.classList.add('chip-' + status);
      });
    });
  };

  /**
   * Ist nichts mehr offen, muss der Satz dazu erscheinen — sonst stünde die
   * Karte leer da. Er steht im Seitentext und wartet nur darauf.
   */
  const pruefeLeer = (liste) => {
    const karte = liste.closest('section');
    if (!karte) return;
    const nochWas = karte.querySelectorAll('.task-list > li').length > 0;
    const satz = karte.querySelector('[data-nothing-open]');
    if (satz) satz.hidden = nochWas;
  };

  /** Die beantwortete Zeile zuklappen und entfernen. */
  const schliesse = (zeile) => {
    const liste = zeile.parentElement;
    const fertig = () => { zeile.remove(); if (liste) pruefeLeer(liste); };
    if (ruhig) { fertig(); return; }
    // Die Höhe muss eine Zahl sein, bevor sie auf null gehen kann: Von "auto"
    // aus bewegt sich nichts, die Zeile verschwände schlagartig.
    zeile.style.height = zeile.offsetHeight + 'px';
    zeile.style.overflow = 'hidden';
    requestAnimationFrame(() => {
      zeile.style.transition = 'height ' + KLAPPEN + 'ms, opacity ' + KLAPPEN + 'ms';
      zeile.style.height = '0px';
      zeile.style.opacity = '0';
    });
    setTimeout(fertig, KLAPPEN + 50);
  };

  document.querySelectorAll('form[data-vote]').forEach(form => {
    form.addEventListener('submit', event => {
      const knopf = event.submitter;
      if (!knopf || !knopf.value) return;   // ohne Knopf bleibt es der normale Weg
      event.preventDefault();

      const daten = new FormData(form);
      daten.append('status', knopf.value);
      // Die Route soll ihr Merkzeichen für den Absage-Hinweis NICHT setzen
      // (#401): Hier lädt nichts neu, also öffnet das Fenster sich gleich
      // selbst. Ohne diese Zeile sähe man es zweimal — jetzt und beim
      // nächsten Seitenaufruf.
      daten.append('ajax', '1');
      markiere(form, knopf.value);

      fetch(form.action, { method: 'POST', body: daten, credentials: 'same-origin' })
        .then(antwort => {
          if (!antwort.ok) throw new Error('Status ' + antwort.status);
          gleicheTermine(form, knopf.value);
          const zeile = form.closest('li');
          setTimeout(() => (zeile ? schliesse(zeile) : null), ZEIGEN);
          // Abgesagt: nach einer Alternative fragen (#401). Erst nachdem die
          // Zeile zugeklappt ist — ein Fenster, das aufgeht, während darunter
          // etwas wegklappt, nimmt der Bewegung die Aussage.
          if (knopf.value === 'no' && typeof window.absageHinweis === 'function'
              && form.dataset.ownDate === '1') {
            const nr = (form.getAttribute('action') || '').match(/termine\/(\d+)\//);
            if (nr) setTimeout(() => window.absageHinweis(Number(nr[1])), ZEIGEN + 400);
          }
        })
        .catch(() => {
          // Nicht angekommen: den gewöhnlichen Weg gehen, damit die Antwort
          // nicht still verlorengeht. Die Knöpfe müssen dafür wieder
          // ansprechbar sein, sonst schickt das Formular nichts mit.
          form.querySelectorAll('button.chip').forEach(b => {
            b.disabled = false;
            b.classList.remove('chip-stumm');
          });
          const verborgen = document.createElement('input');
          verborgen.type = 'hidden';
          verborgen.name = 'status';
          verborgen.value = knopf.value;
          form.appendChild(verborgen);
          form.submit();
        });
    });
  });
});
