// Getippten Text nicht verlieren (#381).
//
// Neunzehn Ansichten tragen ein Textfeld, und manche halten viel: Notizen zu
// einem Termin, der Liedtext, der Rider, ein Vertragstext über achtzehn
// Zeilen. Ein Klick daneben, und alles war weg — ohne Rückfrage, ohne Spur.
//
// Gewählt wurde der Entwurf und nicht die Rückfrage beim Verlassen. Eine
// Rückfrage greift nur beim bewussten Wegklicken; sie greift NICHT, wenn das
// Telefon den Tab im Hintergrund wegwirft, während man kurz in einer anderen
// App etwas nachsieht. Genau das ist der Fall, der am meisten weh tut, und
// genau den übersteht ein Entwurf im Speicher des Browsers.
//
// Angeboten statt eingesetzt: Ein alter Entwurf darf nicht stillschweigend
// überschreiben, was der Server gerade geliefert hat. Wer den Liedtext auf dem
// Server geändert hat und hier noch einen drei Tage alten Entwurf liegen hat,
// soll das sehen und wählen können — nicht den alten Stand untergeschoben
// bekommen.
document.addEventListener('DOMContentLoaded', () => {
  const ALTER_MAX = 7 * 24 * 60 * 60 * 1000;   // nach einer Woche ist es kein Entwurf mehr
  const GROESSE_MAX = 100 * 1024;              // ein Vertragstext passt, ein Roman nicht
  const WARTE = 400;                           // nicht bei jedem Tastendruck schreiben

  const texte = document.body.dataset;
  const halten = (fn) => { try { return fn(); } catch (e) { return null; } };

  // Der Schlüssel muss zwei Dinge auseinanderhalten, die gleich aussehen: das
  // Feld "notes" an Lied 5 und dasselbe an Lied 6. Der Pfad trägt die Nummer,
  // das Ziel des Formulars die Art der Aktion.
  const schluessel = (feld) => {
    const form = feld.form;
    const ziel = form ? (form.getAttribute('action') || '') : '';
    return 'entwurf:' + location.pathname + '|' + ziel + '|' + (feld.name || '');
  };

  const lies = (key) => halten(() => {
    const roh = localStorage.getItem(key);
    if (!roh) return null;
    const satz = JSON.parse(roh);
    if (!satz || typeof satz.text !== 'string') return null;
    if (Date.now() - (satz.zeit || 0) > ALTER_MAX) { localStorage.removeItem(key); return null; }
    return satz;
  });

  // Abgelaufene Entwürfe fliegen beim ersten Besuch raus, nicht irgendwann:
  // Sonst füllt sich der Speicher mit Texten, die niemand mehr will.
  halten(() => {
    for (let i = localStorage.length - 1; i >= 0; i--) {
      const key = localStorage.key(i);
      if (key && key.startsWith('entwurf:')) lies(key);
    }
  });

  document.querySelectorAll('textarea[name]').forEach(feld => {
    const key = schluessel(feld);
    const urtext = feld.value;
    let timer = null;

    // --- Anbieten, was noch dalag
    const alt = lies(key);
    if (alt && alt.text !== '' && alt.text !== urtext) {
      const leiste = document.createElement('p');
      leiste.className = 'warn small entwurf-leiste';
      const satz = document.createElement('span');
      satz.textContent = (texte.entwurfFound || '') + ' ';
      const holen = document.createElement('button');
      holen.type = 'button';
      holen.className = 'btn btn-tiny';
      holen.textContent = texte.entwurfRestore || '';
      const weg = document.createElement('button');
      weg.type = 'button';
      weg.className = 'btn btn-tiny btn-ghost';
      weg.textContent = texte.entwurfDrop || '';
      holen.addEventListener('click', () => {
        feld.value = alt.text;
        feld.focus();
        leiste.remove();
      });
      weg.addEventListener('click', () => {
        halten(() => localStorage.removeItem(key));
        leiste.remove();
      });
      leiste.append(satz, holen, document.createTextNode(' '), weg);
      feld.parentNode.insertBefore(leiste, feld);
    }

    // --- Mitschreiben
    feld.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const text = feld.value;
        halten(() => {
          // Unverändert heißt: nichts zu retten. Ein Entwurf, der dem
          // Gelieferten gleicht, wäre nur Ballast — und böte sich später als
          // Entwurf an, obwohl er keiner ist.
          if (text === urtext || text.length > GROESSE_MAX) {
            localStorage.removeItem(key);
            return;
          }
          localStorage.setItem(key, JSON.stringify({ text: text, zeit: Date.now() }));
        });
      }, WARTE);
    });

    // --- Abgeschickt heißt gerettet
    if (feld.form) {
      feld.form.addEventListener('submit', () => {
        clearTimeout(timer);
        halten(() => localStorage.removeItem(key));
      });
    }
  });
});
