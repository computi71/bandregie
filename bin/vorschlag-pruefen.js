// Was der Hinweis vor dem Speichern behauptet (#402, #413, #417).
//
// Der Grund für diese Datei: Der Satz steht in einem confirm(), und ein
// falscher Satz dort kostet mehr als ein fehlender. Vor #417 drohte er mit
// fünf verworfenen Antworten, wenn jemand eine Probe um eine Stunde schob —
// seit #413 kostet das nichts, und wer der Warnung glaubte, nahm eine
// harmlose Änderung zurück. Eine Seite, die eine Folge ERFINDET, sieht dabei
// die ganze Zeit richtig aus.
//
// Geprüft wird die echte Datei gegen ein Scheinformular. Eine Abschrift der
// Regel wäre die zweite Stelle, die beim nächsten Mal nicht mitzieht — und
// die Schwelle selbst steht ohnehin nur auf dem Server, das Skript bekommt
// sie als Datenfeld.
//
// Schreibt nichts und redet mit nichts; läuft deshalb überall:
//
//   node bin/vorschlag-pruefen.js httpdocs/assets/actions.js
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
const fs = require('fs');
const vm = require('vm');

const pfad = process.argv[2];
if (!pfad) {
  console.error('Aufruf: node bin/vorschlag-pruefen.js httpdocs/assets/actions.js');
  process.exit(2);
}
const quelle = fs.readFileSync(pfad, 'utf8');

const FELDER = ['date', 'time', 'time_meet', 'time_end', 'venue_id', 'location'];
const ZEITEN = ['time_meet', 'time', 'time_end'];
const RESET = 'RESET-SATZ';
const VORSCHLAG = 'VORSCHLAG-SATZ';

// Ein Formular, das sich wie eines verhält: Felder mit Wert, ein submit, das
// sich abbrechen lässt, und das Datenfeld, über das der Server die Regel
// hereinreicht.
function bauen(start) {
  const elemente = {};
  FELDER.forEach(n => { elemente[n] = { value: start[n] ?? '' }; });
  const form = {
    dataset: {
      resetFields: JSON.stringify(FELDER),
      resetConfirm: RESET,
      timeFields: JSON.stringify(ZEITEN),
      shiftMax: '180',
      proposeConfirm: VORSCHLAG,
    },
    elements: elemente,
    handler: null,
    addEventListener(ev, fn) { if (ev === 'submit') this.handler = fn; },
  };
  const ctx = {
    document: {
      querySelectorAll: (wahl) => (wahl === 'form[data-reset-fields]' ? [form] : []),
      addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') ctx.__start = fn; },
    },
    JSON, Math, Number, parseInt, RegExp, Array,
    confirm: (satz) => { ctx.__gefragt = satz; return true; },
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(quelle, ctx);
  if (ctx.__start) ctx.__start();
  return { ctx, form, elemente };
}

// Ein Speichern durchspielen und sagen, welcher Satz kam — oder keiner.
function speichern(start, aenderung) {
  const { ctx, form, elemente } = bauen(start);
  Object.entries(aenderung).forEach(([k, v]) => { elemente[k].value = v; });
  ctx.__gefragt = null;
  let abgebrochen = false;
  form.handler({ preventDefault: () => { abgebrochen = true; } });
  return { satz: ctx.__gefragt, abgebrochen };
}

let fehler = 0;
const p = (was, ok) => {
  if (!ok) fehler++;
  console.log('  ' + was.padEnd(56) + (ok ? 'ok' : 'FEHLER'));
};

const PROBE = { date: '2027-11-05', time: '19:00', time_meet: '18:30',
                time_end: '22:00', venue_id: '3', location: '' };

// ------------------------------- 1. Was gar nichts kostet, fragt auch nicht
p('nichts geaendert: keine Rueckfrage',
  speichern(PROBE, {}).satz === null);
p('nur ein Feld ausserhalb der Liste: keine Rueckfrage',
  speichern(PROBE, { }).satz === null);

// ------------------------------- 2. Der kleine Sprung: der neue Satz
p('eine Stunde spaeter: der Vorschlags-Satz',
  speichern(PROBE, { time: '20:00', time_meet: '19:30', time_end: '23:00' }).satz === VORSCHLAG);
p('eine halbe Stunde nur am Ende: auch der Vorschlags-Satz',
  speichern(PROBE, { time_end: '22:30' }).satz === VORSCHLAG);
p('genau drei Stunden: noch der Vorschlags-Satz',
  speichern(PROBE, { time: '22:00' }).satz === VORSCHLAG);

// ------------------------------- 3. Alles andere: die Warnung wie bisher
p('drei Stunden und eine Minute: die Warnung',
  speichern(PROBE, { time: '22:01' }).satz === RESET);
p('das Ende von 22 auf 2 Uhr: die Warnung',
  speichern(PROBE, { time: '20:00', time_end: '02:00' }).satz === RESET);
p('ein anderer Tag: die Warnung',
  speichern(PROBE, { date: '2027-11-06', time: '20:00' }).satz === RESET);
p('ein anderer Ort: die Warnung',
  speichern(PROBE, { venue_id: '4' }).satz === RESET);
p('ein anderer Freitext-Ort: die Warnung',
  speichern(PROBE, { location: 'Woanders' }).satz === RESET);
p('eine Zeit, die dazukommt: die Warnung',
  speichern({ ...PROBE, time_end: '' }, { time_end: '23:00' }).satz === RESET);
p('eine Zeit, die wegfaellt: die Warnung',
  speichern(PROBE, { time_end: '' }).satz === RESET);
p('Unsinn im Zeitfeld: die Warnung und kein stilles Durchwinken',
  speichern(PROBE, { time: 'abends' }).satz === RESET);

// ------------------------------- 4. Nein heisst nein
const nein = (() => {
  const { ctx, form, elemente } = bauen(PROBE);
  elemente.time.value = '20:00';
  ctx.confirm = () => false;
  let ab = false;
  form.handler({ preventDefault: () => { ab = true; } });
  return ab;
})();
p('wer abbricht, speichert nicht', nein);

// ------------------------------- 5. Ohne die Angaben vom Server bleibt es
//     bei der Warnung — lieber zu viel gewarnt als still verschoben.
const ohne = (() => {
  const { ctx, form, elemente } = bauen(PROBE);
  delete form.dataset.timeFields;
  delete form.dataset.shiftMax;
  vm.runInContext(quelle, ctx);
  ctx.__start();
  elemente.time.value = '20:00';
  ctx.__gefragt = null;
  form.handler({ preventDefault: () => {} });
  return ctx.__gefragt;
})();
p('ohne Zeitangaben vom Server: die Warnung', ohne === RESET);

console.log(fehler ? `\n${fehler} Fehler` : '\nalles gruen');
process.exit(fehler ? 1 : 0);
