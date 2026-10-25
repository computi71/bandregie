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

  // Vor dem Abschicken eines Chatbeitrags zeigen, was ankommt (#380).
  //
  // Der Satz davor steht fertig im Attribut — Ein- und Mehrzahl gehören in die
  // Sprache. Hier kommt nur dazu, was ohne den getippten Text nicht zu wissen
  // ist: der Anriss, genau so geschnitten wie beim Versand.
  //
  // Ohne JavaScript entfällt die Rückfrage und der Beitrag geht wie bisher
  // raus. Das ist richtig so: Eine Seite, die ohne Skript gar nichts mehr
  // abschickt, wäre schlimmer dran als eine ohne Rückfrage.
  document.querySelectorAll('form[data-confirm-send]').forEach(form => {
    form.addEventListener('submit', event => {
      const feld = form.querySelector('textarea[name="text"]');
      const text = (feld ? feld.value : '').trim();
      if (!text) return;   // leer: dafür ist das required-Attribut da
      // Zeichen zählen, nicht Bytes und nicht UTF-16-Einheiten: Nur so steht
      // hier derselbe Schnitt wie in topic_teaser() auf dem Server.
      const zeichen = Array.from(text);
      const anriss = zeichen.length > 120
        ? zeichen.slice(0, 119).join('') + '…' : text;
      const wer = form.dataset.confirmWho || '';
      const vorschau = wer ? wer + ': ' + anriss : anriss;
      if (!confirm(form.dataset.confirmSend + '\n\n' + vorschau)) event.preventDefault();
    });
  });

  // Vor dem Speichern sagen, was eine Verschiebung kostet (#402).
  //
  // Nur, wenn sich wirklich eines der Felder geändert hat, die eine Zusage
  // ungueltig machen. Wer den Titel berichtigt, soll nicht gefragt werden —
  // eine Rückfrage, die immer kommt, liest nach dem dritten Mal niemand mehr.
  //
  // Welche Felder das sind, sagt der Server (data-reset-fields), und die Zahl
  // der Antworten steht fertig im Satz. Das Skript vergleicht nur Werte.
  document.querySelectorAll('form[data-reset-fields]').forEach(form => {
    let felder;
    try { felder = JSON.parse(form.dataset.resetFields); } catch (e) { return; }
    if (!Array.isArray(felder)) return;
    // Der Stand beim Laden: Das ist die Frage, die die Band beantwortet hat.
    const vorher = {};
    felder.forEach(name => {
      const el = form.elements[name];
      if (el) vorher[name] = el.value;
    });
    form.addEventListener('submit', event => {
      const geaendert = felder.some(name => {
        const el = form.elements[name];
        return el && el.value !== vorher[name];
      });
      if (!geaendert) return;
      if (!confirm(form.dataset.resetConfirm)) event.preventDefault();
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
