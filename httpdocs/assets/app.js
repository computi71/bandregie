// Meldet den Service Worker an. Ohne ihn funktioniert alles wie bisher —
// die Seite ist dann nur nicht installierbar und hat nichts für unterwegs.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    // updateViaCache: 'none' — den Service-Worker selbst nie aus dem HTTP-Cache
    // holen, sondern immer frisch prüfen. Sonst bemerkt eine als App installierte
    // Seite (iPhone Home-Screen) neue Fassungen nicht und hängt am alten Stand.
    //
    // Übernimmt ein neuer Worker, wird EINMAL neu geladen, damit die frischen
    // Dateien greifen — sonst liefe die alte Seite weiter, bis jemand sie von
    // Hand schließt. Nur, wenn schon ein Worker die Kontrolle hatte: beim
    // Erstbesuch gibt es nichts zu erneuern und kein überflüssiges Neuladen.
    // Bis #144 stand das in swkick.js, einer Datei mit nie zwischengespeichertem
    // Namen, die auch festhängende Installationen erreichte; die sind durch.
    let reloaded = false;
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloaded) return;
        reloaded = true;
        window.location.reload();
      });
    }
    navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' })
      .then((reg) => reg.update())
      .catch(() => {
        // Kein Grund zur Aufregung: ohne HTTPS oder in alten Browsern geht das
        // nicht, und die Anwendung braucht es auch nicht.
      });

    // Wer die Anwendung öffnet, hat die Mitteilungen gesehen — dann gehört die
    // Zahl am Symbol weg. Auch beim Zurückkehren aus dem Hintergrund, sonst
    // bliebe sie stehen, bis jemand die App einmal ganz neu startet.
    //
    // „Gesehen" heißt aber: etwas gesehen. Wer die App nach zwei Wochen öffnet
    // und auf einem Anmeldeformular landet, hat die Mitteilungen nicht gesehen —
    // und trotzdem fiel der Zähler auf null, weil die Anmeldeseite mit HTTP 200
    // antwortete und das hier als Auskunft durchging (#231). Gemeldet wird nur
    // nach einer Antwort, die sagt, dass jemand angemeldet ist.
    const gesehen = async () => {
      if (document.visibilityState !== 'visible') return;
      // Was noch zu tun ist, weiß nur der Server — die Mitteilungen dagegen sind
      // mit dem Öffnen gesehen. Deshalb hier die frische Zahl holen und dem
      // Service Worker mitgeben, statt die Marke blind zu löschen: eine offene
      // Aufgabe verschwindet nicht dadurch, dass man die App aufmacht.
      let offen = null;        // null heißt: nicht erfahren, also nichts überschreiben
      let angemeldet = false;  // und ohne Anmeldung wird gar nichts gemeldet
      try {
        const r = await fetch('/intern/badge', { credentials: 'same-origin' });
        // Nur eine echte Auskunft zählt: Statuscode, Inhaltstyp und der Inhalt
        // selbst müssen zusammenpassen. Eine ausgelieferte Seite tut das nicht.
        if (r.ok && (r.headers.get('content-type') || '').includes('json')) {
          const daten = await r.json();
          if (daten && daten.angemeldet === true) {
            angemeldet = true;
            offen = Number(daten.offen) || 0;
          }
        }
      } catch (e) { /* kein Netz — der letzte bekannte Stand bleibt stehen */ }
      if (!angemeldet) return;   // Zahl und Mitteilungen bleiben, wie sie waren
      try {
        if (offen > 0 && navigator.setAppBadge) await navigator.setAppBadge(offen);
        else if (navigator.clearAppBadge) await navigator.clearAppBadge();
      } catch (e) { /* Gerät kann keine Marke — kein Beinbruch */ }
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'gesehen', offen });
      }
    };
    gesehen();
    document.addEventListener('visibilitychange', gesehen);
  });
}

// Die Seite meldet sich, wenn sich hinter ihrem Rücken etwas getan hat (#378).
//
// Sie fängt nichts selbst neu an: Wer eine Übersicht offen liegen hat, hat oft
// ein Formular offen oder zielt gerade auf einen Knopf. Etwas, das sich unter
// dem Finger bewegt, ist genau der Fehler aus #377. Deshalb erscheint nur ein
// Balken, und geladen wird, wenn jemand darauf tippt.
//
// Gefragt wird nur nach der Zahl — derselbe billige Weg, den die Seite beim
// Öffnen ohnehin geht. In großen Abständen und nur, solange das Fenster
// wirklich sichtbar ist: Der Pool dahinter hält fünf Arbeiter, und jedes
// offene Fenster, das nachfragt, kostet.
(function () {
  const ABSTAND = 120000;   // zwei Minuten
  const balken = document.getElementById('neues-balken');
  if (!balken) return;      // nur im Bandbereich, und ohne Balken kein Nachfragen

  let stand = null;         // die Zahl, mit der diese Seite gebaut wurde
  let timer = null;

  const anhalten = () => { clearInterval(timer); timer = null; };

  const nachsehen = async () => {
    if (document.visibilityState !== 'visible') return;
    try {
      const r = await fetch('/intern/badge', { credentials: 'same-origin' });
      if (!r.ok || !(r.headers.get('content-type') || '').includes('json')) return;
      const daten = await r.json();
      if (!daten || daten.angemeldet !== true) { anhalten(); return; }
      const offen = Number(daten.offen) || 0;
      if (stand === null) { stand = offen; return; }
      if (offen === stand) return;
      balken.hidden = false;
      anhalten();           // gemeldet ist gemeldet; weiter zu fragen bringt nichts
    } catch (e) { /* kein Netz: beim nächsten Mal wieder */ }
  };

  document.getElementById('neues-knopf')?.addEventListener('click', () => location.reload());
  nachsehen();
  timer = setInterval(nachsehen, ABSTAND);
  // Aus dem Hintergrund zurück ist der beste Zeitpunkt zum Nachsehen: Dort hat
  // der Browser die Zeitgeber ohnehin gedrosselt oder angehalten.
  document.addEventListener('visibilitychange', () => { if (timer) nachsehen(); });
})();

// Ein Link auf einen Abschnitt der Hilfe soll ihn auch aufklappen (#305).
// Die Abschnitte sind <details>, und ein Sprung auf ein zugeklapptes <details>
// zeigt in vielen Browsern nichts als die Überschrift — der Leser landet dort
// und glaubt, der Link sei kaputt.
(function () {
  const aufklappen = () => {
    const ziel = location.hash ? document.getElementById(location.hash.slice(1)) : null;
    if (!ziel || ziel.tagName !== 'DETAILS') return;
    ziel.open = true;
    ziel.scrollIntoView({ block: 'start', behavior: 'smooth' });
  };
  window.addEventListener('hashchange', aufklappen);
  document.addEventListener('DOMContentLoaded', aufklappen);
})();
