// Wann der Absage-Hinweis erscheint und wann nicht (#401).
//
// Zwei Fehler sind hier still möglich. Er erscheint nie — dann sagt die
// Anwendung weiterhin nichts, und niemand merkt, dass da etwas gebaut wurde.
// Oder er erscheint zu oft: Wer eine Antwort hin und her ändert, bekommt
// jedes Mal dasselbe Fenster, und dann klickt man es irgendwann weg, ohne es
// zu lesen.
//
// Geprüft wird die echte Datei gegen ein Scheinfenster.
//
//   node bin/absage-pruefen.js httpdocs/assets/absage.js
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
const fs = require('fs');
const vm = require('vm');
const pfad = process.argv[2];
const quelle = fs.readFileSync(pfad, 'utf8');

const speicher = new Map();

function bauen(autoNr) {
  const link = { attrs: {}, setAttribute(k, v) { this.attrs[k] = v; } };
  const knoepfe = [];
  const dlg = {
    dataset: { absageAuto: String(autoNr || '') },
    offen: 0,
    handler: {},
    showModal() { this.offen++; },
    close() { if (this.handler.close) this.handler.close(); },
    querySelector: () => link,
    querySelectorAll: () => knoepfe,
    addEventListener(ev, fn) { this.handler[ev] = fn; },
  };
  for (let i = 0; i < 2; i++) {
    knoepfe.push({ handler: null, addEventListener(e, f) { this.handler = f; } });
  }
  const ctx = {
    localStorage: {
      getItem: (k) => (speicher.has(k) ? speicher.get(k) : null),
      setItem: (k, v) => speicher.set(k, String(v)),
      removeItem: (k) => speicher.delete(k),
    },
    document: {
      getElementById: (id) => (id === 'absage-dialog' ? dlg : null),
      addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') ctx.__start = fn; },
    },
    encodeURIComponent,
    Number,
    String,
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(quelle, ctx);
  return { ctx, dlg, link, knoepfe };
}

let fehler = 0;
const p = (was, ok) => {
  if (!ok) fehler++;
  console.log('  ' + was.padEnd(54) + (ok ? 'ok' : 'FEHLER'));
};

// ------------------------------------- 1. Der Weg über die Terminkarte
speicher.clear();
let a = bauen(42);
a.ctx.__start();
p('mit Nummer im Seitentext: Fenster geht auf', a.dlg.offen === 1);
p('der Knopf zeigt auf die Vorlage',
  a.link.attrs.href === '/intern/termine?vorlage=42#ev-neu');

// ------------------------------------------ 2. Der Weg über die Übersicht
speicher.clear();
let b = bauen(0);
b.ctx.__start();
p('ohne Nummer bleibt es zu', b.dlg.offen === 0);
b.ctx.window.absageHinweis(7);
p('auf Zuruf geht es auf', b.dlg.offen === 1);
p('und zeigt auf den richtigen Termin',
  b.link.attrs.href === '/intern/termine?vorlage=7#ev-neu');

// ------------------------------------- 3. Weggeklickt heißt: nicht nochmal
speicher.clear();
let c = bauen(0);
c.ctx.__start();
c.ctx.window.absageHinweis(9);
p('erstes Mal: geht auf', c.dlg.offen === 1);
c.knoepfe[0].handler();                 // auf ✕ getippt
p('das Wegklicken wird gemerkt', speicher.size === 1);
c.ctx.window.absageHinweis(9);
p('derselbe Termin: bleibt zu', c.dlg.offen === 1);
c.ctx.window.absageHinweis(10);
p('ein anderer Termin: geht auf', c.dlg.offen === 2);

// Auch Esc und das Tippen daneben zählen.
speicher.clear();
let d = bauen(0);
d.ctx.__start();
d.ctx.window.absageHinweis(11);
d.dlg.handler.close();
p('Esc zaehlt auch als weggeklickt', speicher.size === 1);
d.ctx.window.absageHinweis(11);
p('und danach bleibt es zu', d.dlg.offen === 1);

// ---------------------------- 4. Ohne <dialog> passiert gar nichts
const ohne = (() => {
  const ctx = {
    localStorage: { getItem: () => null, setItem: () => {} },
    document: { getElementById: () => null, addEventListener: (e, f) => { if (e === 'DOMContentLoaded') ctx.__start = f; } },
    encodeURIComponent, Number, String,
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(quelle, ctx);
  return ctx;
})();
let geplatzt = false;
try { ohne.__start(); ohne.window.absageHinweis(5); } catch (e) { geplatzt = true; }
p('ohne Fenster im Seitentext bleibt alles ruhig', !geplatzt);

// ------------------- 5. Ein gesperrter Speicher wirft nichts um
speicher.clear();
const hart = (() => {
  const link = { setAttribute() {} };
  const dlg = {
    dataset: { absageAuto: '3' }, offen: 0, handler: {},
    showModal() { this.offen++; }, close() {},
    querySelector: () => link, querySelectorAll: () => [],
    addEventListener(ev, fn) { this.handler[ev] = fn; },
  };
  const ctx = {
    localStorage: {
      getItem() { throw new Error('verboten'); },
      setItem() { throw new Error('verboten'); },
    },
    document: {
      getElementById: (id) => (id === 'absage-dialog' ? dlg : null),
      addEventListener: (e, f) => { if (e === 'DOMContentLoaded') ctx.__start = f; },
    },
    encodeURIComponent, Number, String,
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(quelle, ctx);
  return { ctx, dlg };
})();
let geplatzt2 = false;
try { hart.ctx.__start(); } catch (e) { geplatzt2 = true; }
p('gesperrter Speicher: kein Absturz', !geplatzt2);
p('und das Fenster geht trotzdem auf', hart.dlg.offen === 1);

console.log('');
console.log('  ' + (fehler ? fehler + ' Fehler' : 'alles gruen'));
process.exit(fehler ? 1 : 0);
