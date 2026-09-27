// Kleine Handgriffe, die früher als onclick im HTML standen: Nachfragen vor
// dem Löschen, Drucken, Adresse kopieren.
//
// Der Grund für den Umzug ist nicht Ordnung, sondern Sicherheit: Solange
// Skripte im Dokument stehen dürfen, muss die Inhaltsregel 'unsafe-inline'
// erlauben — und damit würde auch eingeschleuster Code laufen. Ohne diese
// Zeilen im HTML darf die Regel Skripte auf die eigenen Dateien beschränken.
document.addEventListener('DOMContentLoaded', () => {
  // Absenden erst nach Rückfrage
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!confirm(form.dataset.confirm)) event.preventDefault();
    });
  });

  // Einzelne Knöpfe innerhalb eines Formulars (z. B. Löschen in einer Zeile)
  document.querySelectorAll('button[data-confirm]').forEach(button => {
    button.addEventListener('click', event => {
      if (!confirm(button.dataset.confirm)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });

  // Adresse in die Zwischenablage; klappt das nicht, bleibt sie lesbar stehen
  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', () => {
      const source = document.getElementById(button.dataset.copy);
      if (!source || !navigator.clipboard) return;
      navigator.clipboard.writeText(source.textContent.trim()).then(() => {
        button.textContent = '✔ ' + (button.dataset.copied || '');
      });
    });
  });
});

// Eine Auswahlliste, die beim Umstellen gleich abschickt (#372).
//
// Ohne JavaScript bleibt der Knopf daneben der Weg — deshalb steht er im HTML
// und verschwindet erst hier. Andersherum, also den Knopf per Skript
// hinzufügen, wäre eine Seite, die ohne Skript nicht bedienbar ist.
document.querySelectorAll('select[data-autosubmit]').forEach(select => {
  select.addEventListener('change', () => select.form && select.form.submit());
});
document.querySelectorAll('[data-autosubmit-hide]').forEach(el => {
  el.hidden = true;
});
