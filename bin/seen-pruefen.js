// Wie viele Anfragen assets/seen.js losschickt (#400).
//
// Am 25.10.2026 waren 232 von 250 Schreibzugriffen auf der Beat-Cuisine-Instanz
// dieser eine Weg, in Schwaellen von bis zu 26 Anfragen in derselben Sekunde —
// eine je aufgeklappter Karte, in einen Pool mit fuenf Arbeitern. Dieselbe
// Form wie #374, nur mit billigen statt langsamen Anfragen.
//
// Diese Datei zaehlt nach. Sie prueft die echte Datei gegen ein Scheinfenster;
// eine Abschrift der Buendelung waere die zweite Stelle, die beim naechsten
// Mal nicht mitzieht.
//
// Schreibt nichts und redet mit nichts:
//
//   node bin/seen-pruefen.js httpdocs/assets/seen.js
//
// Rueckgabewert 0, wenn alles stimmt, sonst 1.
const fs = require('fs'), vm = require('vm');
const quelle = fs.readFileSync(process.argv[2], 'utf8');
const karte = (wert, offen) => ({
  dataset: { seen: wert, token: 'T' }, open: offen, handler: {},
  addEventListener(ev, fn) { this.handler[ev] = fn; },
});
const karten = [];
for (let i = 1; i <= 26; i++) karten.push(karte('event:' + i, true));
karten.push(karte('event:99', false));          // zugeklappt: wird erst beim Aufklappen gemeldet

let anfragen = [];
const ctx = {
  fetch: (url, opt) => { anfragen.push(opt.body); return Promise.resolve({}); },
  FormData: class { constructor() { this.f = []; } append(k, v) { this.f.push([k, v]); } },
  document: {
    addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') ctx.__start = fn; },
    querySelectorAll: () => karten,
  },
};
vm.createContext(ctx); vm.runInContext(quelle, ctx); ctx.__start();

const p = (was, ok) => console.log('  ' + was.padEnd(50) + (ok ? 'ok' : 'FEHLER'));
let fehler = 0; const P = (w, o) => { if (!o) fehler++; p(w, o); };

P('26 offene Karten -> eine einzige Anfrage', anfragen.length === 1);
const posten = anfragen[0].f.filter(([k]) => k === 'posten[]');
P('und sie traegt alle 26 Posten', posten.length === 26);
P('mit Token', anfragen[0].f.some(([k, v]) => k === '_token' && v === 'T'));
P('die zugeklappte ist nicht dabei', !posten.some(([, v]) => v === 'event:99'));

// Jetzt die zugeklappte aufklappen
anfragen = [];
karten[26].open = true;
karten[26].handler.toggle();
P('spaeteres Aufklappen meldet einzeln', anfragen.length === 1
  && anfragen[0].f.filter(([k]) => k === 'posten[]').length === 1);

// Zweites Aufklappen derselben Karte: nichts mehr
anfragen = [];
karten[26].handler.toggle();
P('zweimal aufklappen meldet nicht nochmal', anfragen.length === 0);

console.log('');
console.log('  ' + (fehler ? fehler + ' Fehler' : 'alles gruen'));
process.exit(fehler ? 1 : 0);
