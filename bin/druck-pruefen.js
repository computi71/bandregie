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

/** Was auf jedem Blatt steht: umgebrochene Zeilen, Platz, Schriftgröße. */
const MESSUNG = `(() => {
  return [...document.querySelectorAll('.sheet')].map(b => {
    const liste = b.querySelector('.songs');
    const umbrochen = [...liste.querySelectorAll('.songline')]
      .filter(z => z.getClientRects().length > 1)
      .map(z => z.textContent.trim().replace(/\\s+/g, ' ').slice(0, 44));
    const stil = getComputedStyle(b);
    const oben = liste.getBoundingClientRect().top - b.getBoundingClientRect().top;
    const frei = b.clientHeight - (parseFloat(stil.paddingBottom) || 0) - oben;
    return { umbrochen, passt: liste.scrollHeight <= frei + 1, schrift: liste.style.fontSize };
  });
})()`;

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
    // Breit genug, dass nicht die Fensterbreite den Umbruch macht: Gemessen
    // werden soll das Blatt, nicht der Bildschirm, auf dem es gerade liegt.
    await an('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1600, deviceScaleFactor: 1, mobile: false });
    await an('Network.setCookie', { name: 'PHPSESSID', value: sitzung, domain: new URL(basis).hostname, path: '/' });

    await an('Page.navigate', { url: basis + '/intern/setlists/' + setliste + '/druck' });
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

    const mit = await werte(MESSUNG);
    console.log('  Schriftgrößen: ' + mit.map((b) => b.schrift).join(' / '));
    const umbrueche = mit.flatMap((b) => b.umbrochen);
    if (umbrueche.length) console.log('    umgebrochen: ' + umbrueche.join(' | '));
    pruefe('mit allen Feldern steht jedes Lied auf einer Zeile', umbrueche.length === 0);
    pruefe('und die Liste passt auf das Blatt', mit.every((b) => b.passt));

    // Härtefall: ein Bandname, länger als alles in dieser Installation.
    await werte('(() => {'
      + '  const f = document.querySelector(".songline .feld-interpret");'
      + '  if (f) f.textContent = ' + JSON.stringify(LANGER_NAME) + ';'
      + '  document.dispatchEvent(new CustomEvent("setlist:refit"));'
      + '  return true;'
      + '})()');
    await schlaf(1500);
    const hart = await werte(MESSUNG);
    const hartUm = hart.flatMap((b) => b.umbrochen);
    if (hartUm.length) console.log('    umgebrochen: ' + hartUm.join(' | '));
    pruefe('auch mit einem sehr langen Bandnamen', hartUm.length === 0);
    pruefe('und das Blatt bleibt heil', hart.every((b) => b.passt));
  } finally {
    chromium.kill();
    // Chromium räumt sein Profil noch auf, während wir es schon löschen wollen.
    await schlaf(400);
    fs.rmSync(profil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  }
}

hauptlauf()
  .then(() => {
    console.log('\n' + (fehler === 0 ? 'Alles grün.' : fehler + ' Fehler'));
    process.exit(fehler === 0 ? 0 : 1);
  })
  .catch((e) => { console.error('Abbruch: ' + e.message); process.exit(2); });
