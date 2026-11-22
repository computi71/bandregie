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

  // Vor dem Speichern sagen, was eine Verschiebung kostet (#402) — und seit
  // #417 das Richtige. Vorher drohte derselbe Satz mit fünf verlorenen
  // Antworten, wenn jemand eine Probe um eine Stunde schob; seit #413 kostet
  // das gar nichts, und wer der Warnung glaubte, nahm eine harmlose Änderung
  // zurück. Ein Hinweis, der eine Folge ERFINDET, ist schlimmer als keiner.
  //
  // Die Regel kommt vom Server (data-reset-fields, data-time-fields,
  // data-shift-max), damit die drei Stunden an einer Stelle stehen. Hier wird
  // nur verglichen.
  document.querySelectorAll('form[data-reset-fields]').forEach(form => {
    let felder, zeitFelder;
    try {
      felder = JSON.parse(form.dataset.resetFields);
      zeitFelder = JSON.parse(form.dataset.timeFields || '[]');
    } catch (e) { return; }
    if (!Array.isArray(felder)) return;
    const grenze = parseInt(form.dataset.shiftMax, 10);

    // „19:30" als Minuten seit Mitternacht; null, wenn da nichts Lesbares
    // steht. Dieselbe Regel wie event_time_minutes() auf dem Server.
    const minuten = wert => {
      const m = /^(\d{1,2}):(\d{2})$/.exec((wert || '').trim());
      if (!m) return null;
      const h = parseInt(m[1], 10);
      const i = parseInt(m[2], 10);
      return (h > 23 || i > 59) ? null : h * 60 + i;
    };

    // Der Stand beim Laden: Das ist die Frage, die die Band beantwortet hat.
    const vorher = {};
    felder.forEach(name => {
      const el = form.elements[name];
      if (el) vorher[name] = el.value;
    });

    form.addEventListener('submit', event => {
      const geaendert = felder.filter(name => {
        const el = form.elements[name];
        return el && el.value !== vorher[name];
      });
      if (!geaendert.length) return;

      // Ein kleiner Zeitsprung: nur Zeitfelder, jedes lesbar vorher wie
      // nachher, keines weiter als die Grenze. Dieselben drei Bedingungen wie
      // event_time_small_shift(); eine Zeit, die dazukommt oder wegfällt, ist
      // keine Verschiebung, sondern neue Auskunft.
      const klein = Number.isFinite(grenze) && geaendert.every(name => {
        if (!zeitFelder.includes(name)) return false;
        const alt = minuten(vorher[name]);
        const neu = minuten(form.elements[name].value);
        return alt !== null && neu !== null && Math.abs(neu - alt) <= grenze;
      });

      const satz = klein ? form.dataset.proposeConfirm : form.dataset.resetConfirm;
      if (satz && !confirm(satz)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });

  // Auf dem iPhone druckt eine App vom Startbildschirm nicht (#435).
  //
  // iOS verwirft window.print() dort stillschweigend: kein Dialog, kein
  // Fehler, kein Ereignis, an dem sich das merken ließe. Der Knopf stand
  // also da und tat nichts — und wer darauf drückt, sucht den Fehler bei
  // sich oder beim Drucker. Hinführen lässt sich auch nirgends: Safari ist
  // von innen nicht erreichbar, und eine Adresse an ihn zu übergeben erlaubt
  // iOS einer installierten App nicht. Bleibt, es zu sagen und die Adresse
  // zum Mitnehmen hinzulegen.
  //
  // Die beiden Merkmale zusammen heißen „iPhone oder iPad vom
  // Startbildschirm“: navigator.standalone gibt es nur in Safari — eine
  // Android-App vom Startbildschirm druckt ganz normal und kennt die
  // Eigenschaft gar nicht —, und Finger hat von den Safari-Apps nur das
  // Gerät in der Hand; die Mac-App am Dock hat ein Druckmenü und behält
  // ihren Knopf.
  //
  // Ohne den Hinweis im HTML bleibt der Knopf stehen: Ein Ausdruck ohne
  // Erklärung wäre schlimmer dran als einer mit einem Knopf, der schweigt.
  const appVomStartbildschirm = navigator.standalone === true && navigator.maxTouchPoints > 1;
  const ohneDruck = appVomStartbildschirm ? document.querySelector('[data-print-ios]') : null;
  if (ohneDruck) {
    const adresse = document.getElementById('print-adresse');
    if (adresse) adresse.textContent = location.href;
    ohneDruck.hidden = false;
    document.querySelectorAll('[data-print]').forEach(button => { button.hidden = true; });

    // Das Teilen-Blatt von iOS, wo der Browser es anbietet (#437).
    //
    // Es nimmt die Adresse entgegen, und von dort geht sie in einem Griff per
    // AirDrop an den Rechner, an dem der Drucker steht, oder per Mail an einen
    // selbst. Drucken steht nicht darin: Eine Adresse ist nichts Druckbares,
    // dafuer braeuchte das Blatt eine Datei — und die gibt es hier nicht zu
    // geben. Deshalb bleibt der Hinweis auf Safari stehen, und Kopieren
    // daneben: Das ist der Weg, den jedes Geraet kann.
    //
    // Nur mit navigator.share, sonst gar nicht erst sichtbar — dieselbe Regel
    // wie beim Druckknopf daneben.
    const teilen = ohneDruck.querySelector('[data-print-share]');
    if (teilen && navigator.share) {
      teilen.hidden = false;
      teilen.addEventListener('click', () => {
        // Zuschieben ist keine Panne, sondern eine Entscheidung: Das Blatt
        // meldet den Abbruch als Fehler, und genau den schlucken wir hier.
        navigator.share({ title: document.title, url: location.href }).catch(() => {});
      });
    }
  }

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
