/**
 * Erzeugt die Bildschirmfotos für die Hilfeseite (#305).
 *
 * Aufruf:  node --experimental-websocket bin/help-shots.js <basis> <mail> <kennwort> [zielordner]
 * Beispiel: node --experimental-websocket bin/help-shots.js https://demo.bandregie.info admin@example.com demo
 *
 * Warum ein Skript und nicht von Hand geknipst: Ein Bildschirmfoto veraltet mit
 * der nächsten Änderung an der Oberfläche. Wenn es sich jederzeit neu erzeugen
 * lässt, ist das kein Problem mehr — wer die Oberfläche ändert, ruft das hier
 * auf und die Hilfe stimmt wieder.
 *
 * Genommen wird ausschließlich eine Installation mit Demodaten. Auf den Bildern
 * stehen sonst die Termine, Namen und Zahlen einer echten Band, und die gehören
 * nicht in ein öffentliches Verzeichnis.
 *
 * Gesteuert wird Chromium über sein eigenes Protokoll, ohne zusätzliche Pakete.
 * Node 20 kennt WebSocket nur mit --experimental-websocket; ab Node 21 entfällt
 * der Schalter.
 */
'use strict';

const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const [basis, mail, kennwort, zielArg] = process.argv.slice(2);
if (!basis || !mail || !kennwort) {
  console.error('Aufruf: node --experimental-websocket bin/help-shots.js <basis> <mail> <kennwort> [zielordner]');
  process.exit(1);
}
const ziel = zielArg || path.join(__dirname, '..', 'httpdocs', 'assets', 'help');

/**
 * Was fotografiert wird. `pfad` ist die Seite, `warten` ein Element, das da sein
 * muss, bevor geknipst wird, und `bild` der Ausschnitt: ein Auswahlausdruck,
 * damit das Foto die Stelle zeigt und nicht die halbe Anwendung.
 */
const AUFNAHMEN = [
  // Gezeigt wird immer das Stück, von dem die Hilfe spricht, nicht die ganze
  // Seite. Steht der erste Ausdruck nicht auf der Seite, greift der nächste.
  { name: 'termine',       pfad: '/intern/termine',       bild: ['.event-card'] },
  { name: 'songs',         pfad: '/intern/songs',         bild: ['section.card', 'table'] },
  { name: 'setlists',      pfad: '/intern/setlists',      bild: ['section.card'] },
  { name: 'orte',          pfad: '/intern/orte',          bild: ['section.card'] },
  { name: 'abwesenheiten', pfad: '/intern/abwesenheiten', bild: ['section.card', 'table'] },
  { name: 'aufgaben',      pfad: '/intern/aufgaben',      bild: ['section.card', 'ul.task-list'] },
  { name: 'themen',        pfad: '/intern/themen',        bild: ['section.card'] },
  { name: 'kasse',         pfad: '/intern/kasse',         bild: ['section.card'] },
  { name: 'equipment',     pfad: '/intern/equipment',     bild: ['section.card', 'table'] },
  { name: 'rider',         pfad: '/intern/stagerider',    bild: ['.stage-plan', 'section.card'] },
  { name: 'fotos',         pfad: '/intern/fotos',         bild: ['.photo-grid', '.gallery', 'section.card'] },
  { name: 'post',          pfad: '/intern/post',          bild: ['section.card'] },
  { name: 'musik',         pfad: '/intern/musik',         bild: ['section.card'] },
  { name: 'downloads',     pfad: '/intern/downloads',     bild: ['section.card'] },
  { name: 'mitglieder',    pfad: '/intern/mitglieder',    bild: ['section.card', 'table'] },
  { name: 'gaeste',        pfad: '/intern/gaeste',        bild: ['section.card'] },
  { name: 'angebote',      pfad: '/intern/angebote',      bild: ['section.card'] },
  { name: 'steuer',        pfad: '/intern/kasse/steuer',  bild: ['section.card', 'table'] },
];

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
      const nachricht = JSON.parse(ev.data);
      const warter = this.offen.get(nachricht.id);
      if (!warter) return;
      this.offen.delete(nachricht.id);
      if (nachricht.error) warter.ab(new Error(nachricht.error.message));
      else warter.auf(nachricht.result);
    });
  }
  send(method, params = {}) {
    const id = ++this.id;
    this.ws.send(JSON.stringify({ id, method, params }));
    return new Promise((auf, ab) => this.offen.set(id, { auf, ab }));
  }
  close() { this.ws.close(); }
}

async function hauptlauf() {
  fs.mkdirSync(ziel, { recursive: true });

  const profil = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'helpshots-'));
  const chromium = spawn('chromium', [
    '--headless=new', '--remote-debugging-port=9333', '--no-sandbox',
    '--hide-scrollbars', '--force-device-scale-factor=2',
    '--user-data-dir=' + profil, 'about:blank',
  ], { stdio: 'ignore' });

  // Chromium meldet sich nicht; wir fragen so lange nach, bis es antwortet.
  let version = null;
  for (let versuch = 0; versuch < 40 && !version; versuch++) {
    await schlaf(250);
    try { version = await (await fetch('http://127.0.0.1:9333/json/version')).json(); } catch (e) { /* noch nicht oben */ }
  }
  if (!version) { chromium.kill(); throw new Error('Chromium antwortet nicht auf Port 9333'); }

  const draht = new Draht(version.webSocketDebuggerUrl);
  await draht.bereit;

  const { targetId } = await draht.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await draht.send('Target.attachToTarget', { targetId, flatten: true });
  const an = async (method, params = {}) => {
    const id = ++draht.id;
    draht.ws.send(JSON.stringify({ id, sessionId, method, params }));
    return new Promise((auf, ab) => draht.offen.set(id, { auf, ab }));
  };

  await an('Page.enable');
  await an('Runtime.enable');
  await an('Emulation.setDeviceMetricsOverride', { width: 1100, height: 1400, deviceScaleFactor: 2, mobile: false });

  const gehe = async (url) => { await an('Page.navigate', { url }); await schlaf(1400); };
  const werte = async (ausdruck) => (await an('Runtime.evaluate', { expression: ausdruck, returnByValue: true })).result.value;

  // Anmelden. Englisch, damit die Bilder zur englischen Oberfläche passen —
  // eine deutsche Aufnahme wäre in fünf von sechs Sprachen falsch.
  await gehe(basis + '/login?lang=en');
  await werte(`(() => {
    const f = document.querySelector('form[method="post"]');
    f.querySelector('input[type=email]').value = ${JSON.stringify(mail)};
    f.querySelector('input[type=password]').value = ${JSON.stringify(kennwort)};
    f.submit();
    return true;
  })()`);
  await schlaf(2000);
  const angemeldet = await werte("location.pathname.startsWith('/intern')");
  if (!angemeldet) { chromium.kill(); throw new Error('Anmeldung fehlgeschlagen — stimmen Adresse und Kennwort?'); }
  // Der Sprachschalter wirkt erst für Angemeldete dauerhaft: Er schreibt die
  // Wunschsprache ins Konto. Vor der Anmeldung gilt er nur für die eine Seite,
  // und die Aufnahmen wären wieder in der Sprache der Installation.
  await gehe(basis + '/intern?lang=en');

  let gemacht = 0;
  for (const aufnahme of AUFNAHMEN) {
    await gehe(basis + aufnahme.pfad);
    const kasten = await werte(`(() => {
      // Gesucht ist der erste Kasten mit Inhalt: bevorzugt der genannte, sonst
      // die erste Karte, die kein zugeklapptes Formular ist. Die Anlegen-Formulare
      // stehen auf jeder Seite ganz oben und würden sonst jedes Bild belegen.
      const sichtbar = (el) => el && el.getBoundingClientRect().height > 60;
      let el = null;
      for (const sel of ${JSON.stringify(aufnahme.bild)}) {
        const k = document.querySelector(sel);
        if (sichtbar(k)) { el = k; break; }
      }
      if (!el) el = [...document.querySelectorAll('.card')].find(k => k.tagName !== 'DETAILS' && sichtbar(k)) || null;
      if (!el) el = [...document.querySelectorAll('.card')].find(sichtbar) || null;
      if (!el) el = document.querySelector('main');
      if (!el) return null;
      // Der Ausschnitt zählt vom Seitenanfang, nicht vom Fensterrand. Ohne die
      // Umrechnung sitzt das Bild um die Scrollhöhe daneben — und zeigt die
      // Kopfzeile statt der Karte.
      const r = el.getBoundingClientRect();
      const x = r.x + window.scrollX, y = r.y + window.scrollY;
      // Höhe begrenzen: Ein Bild, das zehnmal so hoch wie breit ist, hilft
      // niemandem und bläht die Seite auf.
      return { x: Math.max(0, x - 8), y: Math.max(0, y - 8),
               width: Math.min(1084, r.width + 16), height: Math.min(520, r.height + 16) };
    })()`);
    if (!kasten || kasten.width < 40 || kasten.height < 40) {
      console.log('  übersprungen (nichts zu sehen):', aufnahme.name);
      continue;
    }
    const foto = await an('Page.captureScreenshot', {
      format: 'png', clip: { ...kasten, scale: 1 }, captureBeyondViewport: true,
    });
    const datei = path.join(ziel, 'help-' + aufnahme.name + '.png');
    fs.writeFileSync(datei, Buffer.from(foto.data, 'base64'));
    console.log('  ' + aufnahme.name.padEnd(16), Math.round(fs.statSync(datei).size / 1024) + ' KB');
    gemacht++;
  }

  draht.close();
  chromium.kill();
  // Chromium hält seine Profildateien noch einen Moment; ein misslungenes
  // Aufräumen ist kein Grund, den ganzen Lauf für gescheitert zu erklären.
  try { fs.rmSync(profil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 }); }
  catch (e) { console.log('Hinweis: Profilordner blieb liegen:', profil); }
  console.log(gemacht + ' Bilder in ' + ziel);
}

hauptlauf().catch((fehler) => { console.error('Fehlgeschlagen:', fehler.message); process.exit(1); });
