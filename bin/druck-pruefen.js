/**
 * Steht auf dem gedruckten Setlisten-Blatt jedes Lied auf einer Zeile? (#428)
 *
 * Der Grund für diese Datei: Ein Umbruch ist nichts, was sich serverseitig
 * sehen ließe. Ob „Karma Chameleon – Me First and the Gimme Gimmes" in die
 * Breite passt, entscheidet die Schrift im Browser, und print-fit.js maß bis
 * #428 nur die Höhe. Eine umgebrochene Zeile fiel dabei nie auf: Die Liste
 * wurde höher, die Suche nahm eine kleinere Schrift, alles passte aufs Blatt —
 * und der Bandname hing allein unter seinem Lied. Eine Seite, die sich selbst
 * zurechtschrumpft, sieht dabei die ganze Zeit richtig aus.
 *
 * Gemessen wird im echten Browser, weil es nirgends sonst zu messen ist.
 * Chromium wird über sein eigenes Protokoll gesteuert, ohne Zusatzpakete.
 *
 * Angemeldet wird über eine vorhandene Sitzung statt über ein Kennwort — auf
 * einer Testinstallation legt man die mit
 *   printf "uid|i:1;" > /var/lib/php/sessions/sess_<kennung>
 * an. Nur auf Staging oder der Demo, nie auf einer Installation mit echten
 * Banddaten.
 *
 * Aufruf:
 *   node --experimental-websocket bin/druck-pruefen.js <basis> <sitzung> <setliste> [ip]
 *
 * Die [ip] ist für Installationen, deren Name nur im Server selbst steht —
 * Staging heißt bandroadie.local und löst nirgends auf. curl bekommt das mit
 * --resolve, Chromium über seine eigene Namensauflösung; ohne das landet der
 * Lauf auf einer Fehlerseite und misst ein leeres Blatt.
 *
 * Rückgabewert 0, wenn alles stimmt, sonst 1.
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');

const [basis, sitzung, setliste, ip] = process.argv.slice(2);
if (!basis || !sitzung || !setliste) {
  console.error('Aufruf: node --experimental-websocket bin/druck-pruefen.js <basis> <sitzung> <setliste> [ip]');
  process.exit(2);
}

// Ein langer, aber möglicher Bandname. Der Härtefall gehört dazu: Ohne ihn
// prüft der Lauf nur, dass die Daten dieser Installation zufällig kurz genug
// sind — und das ist keine Zusicherung, sondern ein Zufall.
const LANGER_NAME = 'Me First and the Gimme Gimmes All Stars';

let fehler = 0;
const pruefe = (was, gut) => {
  console.log('  ' + was.padEnd(58) + (gut ? 'ok' : 'FEHLER'));
  if (!gut) fehler++;
};

const schlaf = (ms) => new Promise((r) => setTimeout(r, ms));

/** Eine schmale Hülle um das DevTools-Protokoll: senden, auf Antwort warten. */
class Draht {
  constructor(url) {
    this.ws = new WebSocket(url);
    this.id = 0;
    this.offen = new Map();
    this.bereit = new Promise((auf, ab) => {
      this.ws.addEventListener('open', () => auf());
      this.ws.addEventListener('error', (e) => ab(new Error('WebSocket: ' + (e.message || 'Fehler'))));
    });
    this.ws.addEventListener('message', (ev) => {
      const n = JSON.parse(ev.data);
      const w = this.offen.get(n.id);
      if (!w) return;
      this.offen.delete(n.id);
      if (n.error) w.ab(new Error(n.error.message)); else w.auf(n.result);
    });
  }
  send(method, params = {}, sessionId = undefined) {
    const id = ++this.id;
    this.ws.send(JSON.stringify(sessionId ? { id, sessionId, method, params } : { id, method, params }));
    return new Promise((auf, ab) => this.offen.set(id, { auf, ab }));
  }
}

/**
 * Was auf jedem Blatt steht: übergelaufene Titel, zu hohe Beiwerk-Kästen,
 * Platz und Schriftgröße (#428, #430).
 *
 * Zwei verschiedene Maße, weil es zwei verschiedene Regeln sind: Der Titel
 * steht nie umgebrochen da und läuft bei zu wenig Platz seitlich über;
 * Interpret, Notiz, BPM und Länge daneben dürfen zwei Zeilen füllen.
 */
const MESSUNG = `(() => {
  const zeilenZahl = (k) => {
    const hoch = parseFloat(getComputedStyle(k).lineHeight);
    return hoch ? Math.round(k.getBoundingClientRect().height / hoch) : 1;
  };
  return [...document.querySelectorAll('.sheet')].map(b => {
    const liste = b.querySelector('.songs');
    const kurz = (z) => z.textContent.trim().replace(/\\s+/g, ' ').slice(0, 44);
    const uebergelaufen = [...liste.querySelectorAll('.songline')]
      .filter(z => z.scrollWidth > z.clientWidth + 1).map(kurz);
    const zuHoch = [...liste.querySelectorAll('.songline .meta')]
      .filter(m => m.textContent.trim() !== '' && zeilenZahl(m) > 2)
      .map(m => ({ text: kurz(m), zeilen: zeilenZahl(m) }));
    // Kein einzelner Punkt darf mitten durchbrechen (#432): Trennzeichen und
    // Wert gehoeren zusammen, und ein Bandname wird nicht zerrissen. Eine
    // inline-Spanne liefert je Textzeile ein Rechteck - zwei sind ein Bruch.
    // Nicht die Zahl der Rechtecke: Das ::before mit dem Trennzeichen ist ein
    // eigenes Kaestchen und liefert immer ein zweites. Gezaehlt wird, auf wie
    // vielen ZEILEN sie liegen - gleiche Oberkante, gleiche Zeile.
    const zeilenEiner = (e) => new Set([...e.getClientRects()].map(r => Math.round(r.top))).size;
    const zerrissen = [...liste.querySelectorAll('.songline .meta .feld, .songline .meta .note')]
      .filter(e => getComputedStyle(e).display !== 'none' && zeilenEiner(e) > 1)
      .map(kurz);
    // Und zwei Zeilen Beiwerk duerfen zusammen nicht hoeher sein als eine
    // Zeile Titel (#431) - sonst waechst die Liedzeile, sobald etwas umbricht.
    const ueberTitel = [...liste.querySelectorAll('.songline')].filter(z => {
      const m = z.querySelector('.meta');
      const t = z.querySelector('.titel');
      if (!m || !t || m.textContent.trim() === '') return false;
      return m.getBoundingClientRect().height > parseFloat(getComputedStyle(t).lineHeight) + 1;
    }).map(kurz);
    const schmal = [...liste.querySelectorAll('.songline')].slice(0, 1).map(z => ({
      titel: getComputedStyle(z.querySelector('.titel')).fontFamily,
      meta: getComputedStyle(z.querySelector('.meta')).fontFamily,
    }))[0] || null;
    const stil = getComputedStyle(b);
    const oben = liste.getBoundingClientRect().top - b.getBoundingClientRect().top;
    const frei = b.clientHeight - (parseFloat(stil.paddingBottom) || 0) - oben;
    return { uebergelaufen, zuHoch, ueberTitel, zerrissen, schmal, passt: liste.scrollHeight <= frei + 1, schrift: liste.style.fontSize };
  });
})()`;

/**
 * Welche Schrift der Browser fuer dieses Element wirklich genommen hat.
 *
 * getComputedStyle nennt nur die Wunschliste; ob die erste darin existiert,
 * weiss allein der Browser. Genau daran haette sich #433 sonst vorbeimogeln
 * koennen: Die Liste zu lesen und zu glauben, das sei die Schrift.
 */
async function platzschrift(an, wahl) {
  const { root } = await an('DOM.getDocument', { depth: -1 });
  const { nodeId } = await an('DOM.querySelector', { nodeId: root.nodeId, selector: wahl });
  if (!nodeId) return '(Element nicht gefunden)';
  const { fonts } = await an('CSS.getPlatformFontsForNode', { nodeId });
  return fonts.map((f) => f.familyName).join(', ') || '(keine Angabe)';
}

async function hauptlauf() {
  const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'druckpruef-'));
  const argumente = [
    '--headless=new', '--remote-debugging-port=9334', '--no-sandbox',
    '--hide-scrollbars', '--ignore-certificate-errors',
    '--user-data-dir=' + profil,
  ];
  if (ip) argumente.push('--host-resolver-rules=MAP ' + new URL(basis).hostname + ' ' + ip);
  const chromium = spawn('chromium', argumente.concat(['about:blank']), { stdio: 'ignore' });

  try {
    // Chromium meldet sich nicht; wir fragen so lange nach, bis es antwortet.
    let version = null;
    for (let i = 0; i < 40 && !version; i++) {
      await schlaf(250);
      try { version = await (await fetch('http://127.0.0.1:9334/json/version')).json(); } catch (e) { /* noch nicht oben */ }
    }
    if (!version) throw new Error('Chromium antwortet nicht auf Port 9334');

    const draht = new Draht(version.webSocketDebuggerUrl);
    await draht.bereit;
    const { targetId } = await draht.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await draht.send('Target.attachToTarget', { targetId, flatten: true });
    const an = (method, params = {}) => draht.send(method, params, sessionId);
    const werte = async (ausdruck) =>
      (await an('Runtime.evaluate', { expression: ausdruck, returnByValue: true })).result.value;

    await an('Page.enable');
    await an('Runtime.enable');
    await an('Network.enable');
    await an('DOM.enable');
    await an('CSS.enable');
    // Breit genug, dass nicht die Fensterbreite den Umbruch macht: Gemessen
    // werden soll das Blatt, nicht der Bildschirm, auf dem es gerade liegt.
    await an('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1600, deviceScaleFactor: 1, mobile: false });
    await an('Network.setCookie', { name: 'PHPSESSID', value: sitzung, domain: new URL(basis).hostname, path: '/' });
    // window.print zählen statt drucken: Der Knopf soll den Befehl auslösen,
    // und zwar genau einmal. Zweimal hieße, der Griff ist doppelt angemeldet.
    await an('Page.addScriptToEvaluateOnNewDocument', {
      source: 'window.__gedruckt = 0; window.print = function () { window.__gedruckt++; };',
    });

    await an('Page.navigate', { url: basis + '/intern/setlists/' + setliste + '/print' });
    await schlaf(2500);

    const blaetter = await werte("document.querySelectorAll('.sheet').length");
    const zeilen = await werte("document.querySelectorAll('.songline').length");
    pruefe('die Druckseite ist da', blaetter > 0);
    pruefe('sie zeigt Liedzeilen', zeilen > 2);
    // Ohne Blatt misst alles Weitere an nichts, und „keine umgebrochene Zeile"
    // wäre dann wahr, weil es gar keine Zeile gibt. Eine Zusicherung, die am
    // leeren Blatt gilt, sichert nichts zu.
    if (blaetter === 0 || zeilen <= 2) {
      throw new Error('Keine Druckseite geladen — stimmen Adresse, Sitzung und Setlistennummer?');
    }

    // Alles anschalten: Der Umbruch entsteht erst mit den Zusatzfeldern.
    await werte(`(() => {
      ['interpret', 'jahr', 'bpm', 'zeit'].forEach(n => {
        const k = document.querySelector('[data-feld="' + n + '"]');
        if (k) { k.checked = true; k.dispatchEvent(new Event('change')); }
      });
      return true;
    })()`);
    await schlaf(1500);

    const befund = (blaetter, wie) => {
      const ueber = blaetter.flatMap((b) => b.uebergelaufen);
      const hoch = blaetter.flatMap((b) => b.zuHoch);
      const dicker = blaetter.flatMap((b) => b.ueberTitel);
      const entzwei = blaetter.flatMap((b) => b.zerrissen);
      if (ueber.length) console.log('    übergelaufen: ' + ueber.join(' | '));
      if (hoch.length) console.log('    zu hoch: ' + hoch.map((h) => h.zeilen + ' Zeilen · ' + h.text).join(' | '));
      if (dicker.length) console.log('    höher als der Titel: ' + dicker.join(' | '));
      pruefe(wie + ': kein Titel läuft über', ueber.length === 0);
      pruefe(wie + ': das Beiwerk bleibt in zwei Zeilen', hoch.length === 0);
      pruefe(wie + ': und bleibt dabei so hoch wie eine Titelzeile', dicker.length === 0);
      if (entzwei.length) console.log('    mittendrin umgebrochen: ' + entzwei.join(' | '));
      pruefe(wie + ': kein einzelner Punkt bricht mittendrin', entzwei.length === 0);
      pruefe(wie + ': jedes Set passt auf sein Blatt', blaetter.every((b) => b.passt));
    };

    const mit = await werte(MESSUNG);
    console.log('  Schriftgrößen: ' + mit.map((b) => b.schrift).join(' / '));
    // Ein Ausdruck, eine Groesse (#434): Drei Blaetter in drei Groessen lesen
    // sich wie drei Zettel, und beim Umblaettern springt die Schrift.
    pruefe('alle Blätter stehen in derselben Schriftgröße',
      new Set(mit.map((b) => b.schrift)).size <= 1);
    if (mit[0] && mit[0].schmal) {
      console.log('  Schrift Titel:  ' + mit[0].schmal.titel);
      console.log('  Schrift Beiwerk: ' + mit[0].schmal.meta);
      // Dass die Ausnahme auf dem TITEL landet und nicht irgendwo, ist eine
      // Frage ans gerenderte Dokument (#430). Ob die schmale Schrift auf
      // diesem Rechner überhaupt installiert ist, ist keine Frage an unseren
      // Code — die beantwortet quelltext() an der Deklaration.
      pruefe('der Titel steht in einer anderen Schrift als das Beiwerk',
        mit[0].schmal.titel !== mit[0].schmal.meta);
      // Und zwar in der mitgelieferten (#433), nicht in irgendeiner, die der
      // Rechner zufällig hat. Welche Schrift wirklich gesetzt wurde, steht in
      // keiner CSS-Eigenschaft — danach muss man den Browser selbst fragen.
      const benutzt = await platzschrift(an, '.songline .meta .feld-interpret');
      console.log('  gesetzt mit:     ' + benutzt);
      pruefe('das Beiwerk steht in der mitgelieferten Schrift',
        /Saira Extra ?Condensed/i.test(benutzt));
    }
    befund(mit, 'mit allen Feldern');

    // Kann das Beiwerk ueberhaupt noch umbrechen? (#432)
    //
    // Seit die einzelnen Punkte unzertrennlich sind, haengt die zweite Zeile
    // aus #430 an genau einem Leerzeichen zwischen den Spannen. Faellt das
    // weg, ist nichts kaputt - es meldet keine Pruefzeile etwas, die Band
    // verliert nur stillschweigend Schriftgroesse, weil alles auf eine Zeile
    // muss. Also wird die Gelegenheit selbst geprueft: den Kasten eng machen
    // und nachsehen, ob er hoeher wird.
    const kannUmbrechen = await werte(`(() => {
      const m = document.querySelector('.songline .meta');
      if (!m) return null;
      const vorher = m.getBoundingClientRect().height;
      const alt = m.style.maxWidth;
      m.style.maxWidth = '6em';
      const nachher = m.getBoundingClientRect().height;
      m.style.maxWidth = alt;
      return nachher > vorher + 1;
    })()`);
    pruefe('das Beiwerk kann zwischen den Punkten umbrechen', kannUmbrechen === true);

    // Härtefall: ein Bandname, länger als alles in dieser Installation. Er darf
    // jetzt in die zweite Zeile rutschen — nur über das Blatt hinaus nicht.
    await werte('(() => {'
      + '  const f = document.querySelector(".songline .feld-interpret");'
      + '  if (f) f.textContent = ' + JSON.stringify(LANGER_NAME) + ';'
      + '  document.dispatchEvent(new CustomEvent("setlist:refit"));'
      + '  return true;'
      + '})()');
    await schlaf(1500);
    befund(await werte(MESSUNG), 'mit sehr langem Bandnamen');

    // Und der Knopf selbst (#429): Auf der Setliste lud die Ansicht actions.js
    // früher selbst, auf der druckbaren Hilfe niemand. Beide müssen drucken,
    // und beide genau einmal.
    for (const seite of ['/intern/setlists/' + setliste + '/print', '/intern/hilfe/druck']) {
      await an('Page.navigate', { url: basis + seite });
      await schlaf(2000);
      const knopf = await werte("!!document.querySelector('[data-print]')");
      if (!knopf) throw new Error('Kein Drucken-Knopf auf ' + seite);
      await werte("document.querySelector('[data-print]').click()");
      await schlaf(300);
      const wieOft = await werte('window.__gedruckt');
      pruefe('der Knopf druckt auf ' + seite, wieOft === 1);
      if (wieOft > 1) console.log('    ' + wieOft + ' Aufrufe — der Griff ist mehrfach angemeldet');
    }

    // Und der Knopf, der auf dem iPhone nichts ausrichten kann (#435).
    //
    // Dass iOS window.print() in einer App vom Startbildschirm verwirft, ist
    // hier nicht nachzustellen — Chromium druckt. Nachstellen lässt sich, was
    // unser Code zum Erkennen hat: die beiden Merkmale. Geprüft wird also,
    // dass die Leiste daraufhin umschaltet — und ohne sie eben nicht.
    //
    // Gemessen wird die gerechnete Darstellung, nicht das hidden-Attribut: Die
    // Leiste setzt display auf inline-flex, und das sticht ein hidden aus,
    // wenn keine Regel dagegenhält. Der Knopf stünde dann weiter da, und der
    // Lauf sähe nichts davon.
    const leiste = `(() => {
      const sicht = (e) => (e ? getComputedStyle(e).display !== 'none' : null);
      const a = document.getElementById('print-adresse');
      return {
        knopf: sicht(document.querySelector('[data-print]')),
        hinweis: sicht(document.querySelector('[data-print-ios]')),
        adresse: a ? a.textContent : '',
        hier: location.href,
      };
    })()`;

    const druckseite = basis + '/intern/setlists/' + setliste + '/print';
    await an('Page.navigate', { url: druckseite });
    await schlaf(2000);
    const amRechner = await werte(leiste);
    pruefe('am Rechner steht der Druckknopf da', amRechner.knopf === true);
    pruefe('und der iPhone-Hinweis bleibt weg', amRechner.hinweis === false);

    const { identifier } = await an('Page.addScriptToEvaluateOnNewDocument', {
      source: "Object.defineProperty(navigator, 'standalone', { value: true });"
        + "Object.defineProperty(navigator, 'maxTouchPoints', { value: 5 });",
    });
    await an('Page.navigate', { url: druckseite });
    await schlaf(2000);
    const amTelefon = await werte(leiste);
    await an('Page.removeScriptToEvaluateOnNewDocument', { identifier });
    pruefe('als App vom Startbildschirm verschwindet der Knopf', amTelefon.knopf === false);
    pruefe('und der Hinweis tritt an seine Stelle', amTelefon.hinweis === true);
    pruefe('er trägt die Adresse dieser Seite zum Mitnehmen',
      amTelefon.hier !== '' && amTelefon.adresse === amTelefon.hier);
  } finally {
    chromium.kill();
    // Chromium räumt sein Profil noch auf, während wir es schon löschen wollen.
    await schlaf(400);
    fs.rmSync(profil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  }
}

/**
 * Dieselbe Regel an der Quelle (#429): Die Leiste bringt actions.js mit, und
 * keine Ansicht bringt eine zweite Kopie.
 *
 * Der Browserlauf prüft zwei Seiten; diese Zeilen prüfen alle. Eine Ansicht,
 * die morgen dazukommt und die Datei selbst lädt, druckt auf einen Klick
 * zweimal — und das sieht man auf dem Papier, nicht im Code.
 */
function quelltext() {
  const views = path.join(__dirname, '..', 'app', 'views', 'intern');
  const bar = fs.readFileSync(path.join(views, '_printbar.php'), 'utf8');
  pruefe('die Druckleiste bringt actions.js mit', bar.includes("asset('/assets/actions.js')"));

  const doppelt = [];
  for (const datei of fs.readdirSync(views)) {
    if (!datei.endsWith('.php') || datei === '_printbar.php') continue;
    const inhalt = fs.readFileSync(path.join(views, datei), 'utf8');
    if (inhalt.includes('_printbar.php') && inhalt.includes("asset('/assets/actions.js')")) {
      doppelt.push(datei);
    }
  }
  if (doppelt.length) console.log('    zweite Kopie in: ' + doppelt.join(', '));
  pruefe('keine Ansicht lädt actions.js ein zweites Mal', doppelt.length === 0);

  // Der Hinweis für die iPhone-App gehört in die Leiste und nirgendwo sonst
  // (#435): Er steht damit über allen acht Ausdrucken, nicht nur über dem, an
  // dem er aufgefallen ist. Und die Regel daneben ist kein Schmuck — ohne sie
  // bleibt der verborgene Knopf sichtbar, weil die Leiste display setzt.
  pruefe('die Leiste bringt den Hinweis für die iPhone-App mit',
    bar.includes('data-print-ios') && bar.includes("t('print_ios_hint')"));
  pruefe('und verbirgt Verborgenes wirklich',
    /\.printbar \[hidden\] \{\s*display: none/.test(bar));

  // Welche Schrift auf dem Papier erscheint, hängt am druckenden Rechner —
  // eine Messung im Browser sagt nur, was DORT installiert ist. Was unser Code
  // verspricht, steht in der Deklaration, und genau das wird hier geprüft:
  // schmale Schnitte für das Blatt, und der Titel nimmt sie zurück (#430).
  const blatt = fs.readFileSync(path.join(views, 'setlist_print.php'), 'utf8');
  pruefe('das Setlistenblatt verlangt eine schmale Schrift',
    /Arial Narrow/.test(blatt) && /Liberation Sans Narrow/.test(blatt) && /font-stretch:\s*condensed/.test(blatt));
  pruefe('und der Liedtitel nimmt sie zurück',
    /\.songline \.titel \{[^}]*font-family:/.test(blatt) && /\.songline \.titel \{[^}]*font-stretch:\s*normal/.test(blatt));

  // Die Schrift liegt bei (#433) — sonst hängt das Aussehen des Blatts daran,
  // was auf dem druckenden Gerät zufällig installiert ist.
  const schriften = path.join(__dirname, '..', 'httpdocs', 'assets', 'fonts');
  const dateien = ['saira-extracondensed-400.woff2', 'saira-extracondensed-700.woff2'];
  pruefe('die schmale Schrift liegt im Verzeichnis',
    dateien.every((f) => fs.existsSync(path.join(schriften, f))));
  pruefe('mit ihrer Lizenz daneben', fs.existsSync(path.join(schriften, 'OFL.txt')));
  pruefe('das Blatt bindet beide Schnitte ein',
    dateien.every((f) => blatt.includes(f)) && (blatt.match(/@font-face/g) || []).length >= 2);
  // Im Vorrat des Service Workers, sonst steht die Band offline ohne sie da.
  const sw = fs.readFileSync(path.join(__dirname, '..', 'httpdocs', 'sw.js'), 'utf8');
  pruefe('und der Offline-Vorrat kennt sie', dateien.every((f) => sw.includes(f)));
}

quelltext();
hauptlauf()
  .then(() => {
    console.log('\n' + (fehler === 0 ? 'Alles grün.' : fehler + ' Fehler'));
    process.exit(fehler === 0 ? 0 : 1);
  })
  .catch((e) => { console.error('Abbruch: ' + e.message); process.exit(2); });
