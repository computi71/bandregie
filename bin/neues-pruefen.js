// Wann die Seite meldet, dass sich etwas getan hat (#378).
//
// Zwei Dinge koennen hier still falsch werden, und beide sieht man der Seite
// nicht an: Sie meldet nie (dann liegt ein halbstundenalter Stand da, ohne
// dass etwas darauf hindeutet), oder sie fragt weiter nach, obwohl der Balken
// schon steht — und das kostet in einem Pool mit fuenf Arbeitern.
//
// Geprueft wird die echte Datei gegen ein Scheinfenster.
//
//   node bin/neues-pruefen.js httpdocs/assets/app.js
//
// Rueckgabewert 0, wenn alles stimmt, sonst 1.
const fs = require('fs'), vm = require('vm');
const quelle = fs.readFileSync(process.argv[2], 'utf8');
let zahl = 4, abrufe = 0, geladen = false, timerFn = null;
const balken = { hidden: true };
const knopf = { handler: null, addEventListener(e, f) { this.handler = f; } };
const ctx = {
  document: {
    visibilityState: 'visible',
    getElementById: (id) => (id === 'neues-balken' ? balken : id === 'neues-knopf' ? knopf : null),
    addEventListener() {}, querySelectorAll: () => [], querySelector: () => null,
  },
  navigator: { serviceWorker: { ready: Promise.resolve({}), addEventListener() {} } },
  location: { reload: () => { geladen = true; }, hash: '', pathname: '/intern' },
  window: { addEventListener() {}, matchMedia: () => ({ matches: false }) },
  setInterval: (fn) => { timerFn = fn; return 1; },
  clearInterval: () => { timerFn = null; },
  fetch: async () => { abrufe++; return {
    ok: true, headers: { get: () => 'application/json' },
    json: async () => ({ angemeldet: true, offen: zahl }) }; },
};
ctx.self = ctx; ctx.globalThis = ctx;
vm.createContext(ctx); vm.runInContext(quelle, ctx);

const p = (w, o) => { console.log('  ' + w.padEnd(50) + (o ? 'ok' : 'FEHLER')); return o; };
let fehler = 0; const P = (w, o) => { if (!P2(o)) fehler++; return o; }; const P2 = (o) => o;

(async () => {
  await new Promise(r => setTimeout(r, 10));     // der erste Abruf setzt den Stand
  if (!p('beim Laden wird einmal gefragt', abrufe === 1)) fehler++;
  if (!p('und noch kein Balken', balken.hidden === true)) fehler++;

  await timerFn(); await new Promise(r => setTimeout(r, 10));
  if (!p('gleiche Zahl: weiter still', balken.hidden === true)) fehler++;

  zahl = 5;
  await timerFn(); await new Promise(r => setTimeout(r, 10));
  if (!p('geaenderte Zahl: Balken erscheint', balken.hidden === false)) fehler++;
  if (!p('und das Nachfragen hoert auf', timerFn === null)) fehler++;

  knopf.handler();
  if (!p('der Knopf laedt neu', geladen === true)) fehler++;
  console.log('');
  console.log('  ' + (fehler ? fehler + ' Fehler' : 'alles gruen'));
  process.exit(fehler ? 1 : 0);
})();
