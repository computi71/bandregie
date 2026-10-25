// Die Regeln von assets/entwurf.js gegen ein Scheinfenster prüfen (#381).
//
// Der Grund für diese Datei: Das Skript rettet Text, den jemand getippt hat.
// Geht es kaputt, merkt es niemand — bis zu dem Tag, an dem einer eine
// Stunde Arbeit verliert und es nicht beweisen kann. Die Seite sieht dabei
// die ganze Zeit richtig aus.
//
// Zwei Regeln sind die wichtigsten, und beide stehen unten als Zusicherung:
// Ein alter Entwurf wird ANGEBOTEN und nicht stillschweigend eingesetzt (sonst
// überschreibt er, was der Server gerade geliefert hat), und ein gesperrter
// Speicher — privates Fenster, blockierte Seitendaten — wirft die Seite nicht
// um.
//
// Geprüft wird die echte Datei. Eine Abschrift der Regeln wäre genau die
// zweite Stelle, die beim nächsten Mal nicht mitzieht.
//
// Schreibt nichts und redet mit nichts; läuft deshalb überall:
//
//   node bin/entwurf-pruefen.js httpdocs/assets/entwurf.js
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
const fs = require('fs');
const path = process.argv[2];

const speicher = new Map();
let jetzt = Date.UTC(2026, 9, 25, 12, 0, 0);

function fenster() {
  const el = (tag) => {
    const n = {
      tagName: tag, className: '', textContent: '', type: '', value: '', name: '',
      dataset: {}, kinder: [], hidden: false, weg: false, handler: {},
      append(...k) { n.kinder.push(...k); },
      appendChild(k) { n.kinder.push(k); },
      insertBefore(k) { n.kinder.push(k); },
      addEventListener(ev, fn) { n.handler[ev] = fn; },
      remove() { n.weg = true; },
      focus() {},
      querySelectorAll: () => [],
    };
    return n;
  };
  return { el };
}

function lauf(felder, starten = true) {
  const { el } = fenster();
  const g = {
    localStorage: {
      getItem: (k) => (speicher.has(k) ? speicher.get(k) : null),
      setItem: (k, v) => speicher.set(k, String(v)),
      removeItem: (k) => speicher.delete(k),
      get length() { return speicher.size; },
      key: (i) => [...speicher.keys()][i],
    },
    location: { pathname: '/intern/songs/5/edit' },
    Date: { now: () => jetzt },
    // Die Verzögerung sofort ausführen: Gewartet wird im Browser, nicht hier.
    setTimeout: (fn) => { fn(); return 1; },
    clearTimeout: () => {},
    JSON,
    document: {
      body: { dataset: { entwurfFound: 'Da lag was', entwurfRestore: 'Holen', entwurfDrop: 'Weg' } },
      createElement: el,
      createTextNode: () => el('text'),
      addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') g.__start = fn; },
      querySelectorAll: (sel) => (sel === 'textarea[name]' ? felder : []),
    },
  };
  const quelle = fs.readFileSync(path, 'utf8');
  const vm = require('vm');
  vm.createContext(g);
  vm.runInContext(quelle, g);
  if (starten) g.__start();
  return g;
}

function feld(name, wert, aktion) {
  const { el } = fenster();
  const f = el('textarea');
  f.name = name;
  f.value = wert;
  f.form = {
    getAttribute: () => aktion,
    addEventListener: (ev, fn) => { f.form.handler = f.form.handler || {}; f.form.handler[ev] = fn; },
    handler: {},
  };
  f.parentNode = el('div');
  return f;
}

let fehler = 0;
const p = (was, ok) => {
  if (!ok) fehler++;
  console.log('  ' + was.padEnd(54) + (ok ? 'ok' : 'FEHLER'));
};

// ---------------------------------------- 1. Mitschreiben und Aufräumen
let f = feld('notes', 'Vom Server geliefert', '/intern/songs/5/update');
lauf([f]);
f.value = 'Vom Server geliefert, und das hier dazu';
f.handler.input();
p('getippter Text wird gemerkt', speicher.size === 1);

const key = [...speicher.keys()][0];
p('der Schluessel traegt Pfad, Ziel und Feldnamen',
  key.includes('/intern/songs/5/edit') && key.includes('/intern/songs/5/update') && key.endsWith('|notes'));

// Zurueck auf den gelieferten Stand: dann ist nichts zu retten.
f.value = 'Vom Server geliefert';
f.handler.input();
p('unveraendert heisst: kein Entwurf', speicher.size === 0);

// ------------------------------------------- 2. Abschicken raeumt auf
f = feld('notes', 'Alt', '/intern/songs/5/update');
lauf([f]);
f.value = 'Neu getippt';
f.handler.input();
p('vor dem Abschicken liegt ein Entwurf', speicher.size === 1);
f.form.handler.submit();
p('abgeschickt heisst gerettet, Entwurf weg', speicher.size === 0);

// ------------------------------------- 3. Wiederfinden beim naechsten Mal
f = feld('notes', 'Alt', '/intern/songs/5/update');
lauf([f]);
f.value = 'Halb fertiger Gedanke';
f.handler.input();
const gemerkt = speicher.size === 1;

const f2 = feld('notes', 'Alt', '/intern/songs/5/update');
lauf([f2]);
p('beim naechsten Oeffnen wird er angeboten', gemerkt && f2.parentNode.kinder.length === 1);
p('aber NICHT stillschweigend eingesetzt', f2.value === 'Alt');

// Der Knopf setzt ihn ein.
const leiste = f2.parentNode.kinder[0];
const knopf = leiste.kinder.find((k) => k.textContent === 'Holen');
knopf.handler.click();
p('der Knopf holt ihn zurueck', f2.value === 'Halb fertiger Gedanke');

// Verwerfen loescht ihn.
const f3 = feld('notes', 'Alt', '/intern/songs/5/update');
lauf([f3]);
const weg = f3.parentNode.kinder[0].kinder.find((k) => k.textContent === 'Weg');
weg.handler.click();
p('verwerfen loescht ihn', speicher.size === 0);

// --------------------------------- 4. Zwei Datensaetze kommen sich nicht ins Gehege
speicher.clear();
const a = feld('notes', '', '/intern/songs/5/update');
lauf([a]);
a.value = 'Für Lied fuenf';
a.handler.input();
const merkeA = [...speicher.entries()];

const g2 = lauf([], false);
g2.location.pathname = '/intern/songs/6/edit';
const b = feld('notes', '', '/intern/songs/6/update');
// Zweiter Lauf mit anderem Pfad
const vm = require('vm');
const quelle = fs.readFileSync(path, 'utf8');
const ctx = {
  localStorage: g2.localStorage, location: { pathname: '/intern/songs/6/edit' },
  Date: { now: () => jetzt }, setTimeout: (fn) => { fn(); return 1; }, clearTimeout: () => {}, JSON,
  document: {
    body: { dataset: {} }, createElement: fenster().el, createTextNode: () => fenster().el('t'),
    addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') ctx.__start = fn; },
    querySelectorAll: (sel) => (sel === 'textarea[name]' ? [b] : []),
  },
};
vm.createContext(ctx);
vm.runInContext(quelle, ctx);
ctx.__start();
b.value = 'Für Lied sechs';
b.handler.input();
p('zwei Lieder, zwei Entwuerfe', speicher.size === 2);
p('und der erste ist unveraendert',
  speicher.get(merkeA[0][0]) === merkeA[0][1]);

// ------------------------------------------------ 5. Nach einer Woche weg
speicher.clear();
const c = feld('notes', '', '/intern/songs/5/update');
lauf([c]);
c.value = 'Vor langer Zeit';
c.handler.input();
p('frisch gemerkt', speicher.size === 1);
jetzt += 8 * 24 * 60 * 60 * 1000;          // acht Tage spaeter
const d = feld('notes', '', '/intern/songs/5/update');
lauf([d]);
p('nach acht Tagen wird er nicht mehr angeboten', d.parentNode.kinder.length === 0);
p('und ist aus dem Speicher geraeumt', speicher.size === 0);

// ------------------------------------------------ 6. Die Groessengrenze
speicher.clear();
const e = feld('notes', '', '/intern/songs/5/update');
lauf([e]);
e.value = 'x'.repeat(99 * 1024);
e.handler.input();
p('knapp unter der Grenze wird gemerkt', speicher.size === 1);
e.value = 'x'.repeat(101 * 1024);
e.handler.input();
p('darueber nicht, und das Alte faellt weg', speicher.size === 0);

// ------------------------------- 7. Ohne Speicher laeuft die Seite weiter
const kaputt = feld('notes', '', '/intern/songs/5/update');
const quelle2 = fs.readFileSync(path, 'utf8');
const ctx2 = {
  localStorage: {
    getItem() { throw new Error('verboten'); },
    setItem() { throw new Error('verboten'); },
    removeItem() { throw new Error('verboten'); },
    get length() { throw new Error('verboten'); },
    key() { throw new Error('verboten'); },
  },
  location: { pathname: '/x' }, Date: { now: () => jetzt },
  setTimeout: (fn) => { fn(); return 1; }, clearTimeout: () => {}, JSON,
  document: {
    body: { dataset: {} }, createElement: fenster().el, createTextNode: () => fenster().el('t'),
    addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') ctx2.__start = fn; },
    querySelectorAll: (sel) => (sel === 'textarea[name]' ? [kaputt] : []),
  },
};
let geplatzt = false;
try {
  vm.createContext(ctx2);
  vm.runInContext(quelle2, ctx2);
  ctx2.__start();
  kaputt.value = 'egal';
  kaputt.handler.input();
  kaputt.form.handler.submit();
} catch (err) { geplatzt = true; }
p('ein gesperrter Speicher wirft die Seite nicht um', !geplatzt);

console.log('');
console.log('  ' + (speicher.size >= 0 ? '' : '') + (fehler ? fehler + ' Fehler' : 'alles gruen'));
process.exit(fehler ? 1 : 0);
