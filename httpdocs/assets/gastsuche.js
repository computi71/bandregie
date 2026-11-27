// Beim Buchen nachsehen, ob es den Gast schon gibt (#452).
//
// Der Fehler, den das verhindert: Wer den Namen tippt, statt ihn aus der Liste
// zu wählen, legt einen zweiten Gast an. Früher war das ein doppelter Eintrag
// in der Kontaktliste, mehr nicht. Seit seine Liedantworten am Gast hängen
// (#450), fängt die zweite Zeile mit leerer Liste an, und was er beim letzten
// Mal angekreuzt hat, liegt unerreichbar hinter der ersten.
//
// Erst ab dem dritten Zeichen gefragt — darunter käme eine halbe Namensliste
// zurück, und die geht eine Suchzeile nichts an. Der Server sieht beim
// Abschicken noch einmal nach: Ohne dieses Skript ist der Riegel derselbe,
// er meldet sich nur später.
document.addEventListener('DOMContentLoaded', () => {
  const MINDESTENS = 3;
  const WARTEN = 250;   // nicht bei jedem Tastendruck fragen

  document.querySelectorAll('form[action="/intern/gaeste/buchen"]').forEach(form => {
    const feld = form.querySelector('input[name="new_name"]');
    const auswahl = form.querySelector('select[name="guest_id"]');
    if (!feld || !auswahl) return;

    const hinweis = document.createElement('p');
    hinweis.className = 'muted small span2';
    hinweis.hidden = true;
    feld.parentElement.insertAdjacentElement('afterend', hinweis);

    let uhr = null;
    const zeige = (treffer) => {
      hinweis.textContent = '';
      hinweis.hidden = treffer.length === 0;
      if (!treffer.length) return;
      hinweis.append(feld.dataset.vorsatz || '');
      treffer.forEach(g => {
        const knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'btn btn-tiny btn-ghost';
        knopf.textContent = g.function_name ? g.name + ' (' + g.function_name + ')' : g.name;
        // Den Gefundenen nehmen heißt: oben auswählen, Tippfelder leeren.
        // Zusammengelegt wird nichts — zwei Leute dürfen gleich heißen, also
        // entscheidet das ein Mensch mit einem Tipp.
        knopf.addEventListener('click', () => {
          auswahl.value = String(g.id);
          feld.value = '';
          hinweis.hidden = true;
        });
        hinweis.append(' ', knopf);
      });
    };

    feld.addEventListener('input', () => {
      window.clearTimeout(uhr);
      const frage = feld.value.trim();
      if (frage.length < MINDESTENS) { hinweis.hidden = true; return; }
      uhr = window.setTimeout(() => {
        fetch('/intern/gaeste/suche?q=' + encodeURIComponent(frage), { credentials: 'same-origin' })
          .then(antwort => (antwort.ok ? antwort.json() : []))
          // Still bleiben, wenn die Anfrage scheitert: Der Riegel auf dem
          // Server greift ohnehin, und eine Fehlermeldung beim Tippen hilft
          // niemandem.
          .then(zeige)
          .catch(() => { hinweis.hidden = true; });
      }, WARTEN);
    });
  });
});
