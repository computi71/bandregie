// Passkey anlegen und damit anmelden (#168).
//
// Was hier passiert: Der Browser erzeugt ein Schlüsselpaar im sicheren Bereich
// des Geräts. Der private Teil verlässt ihn nie — er ist nicht auslesbar, auch
// nicht von dieser Seite. Face ID oder Fingerabdruck entsperren ihn nur; was
// der Server bekommt, ist der öffentliche Teil und später eine Signatur.
//
// Kann der Browser das nicht, bleibt alles verborgen. Das Passwort funktioniert
// unverändert weiter — der Passkey ist ein zweiter Weg, kein Ersatz.

const pkB64 = bin => btoa(String.fromCharCode(...new Uint8Array(bin)))
  .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const pkBin = s => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

const pkMoeglich = () => !!(window.PublicKeyCredential && navigator.credentials);

// Wie bei den Mitteilungen: als Formular mit dem Token, damit die
// CSRF-Prüfung greift, die für jede schreibende Anfrage gilt — ohne Ausnahme.
async function pkPost(url, daten) {
  const bereich = document.querySelector('[data-passkey]');
  const fd = new FormData();
  fd.append('_token', (bereich && bereich.dataset.token) || '');
  for (const [k, v] of Object.entries(daten || {})) fd.append(k, v);
  const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
  return { ok: r.ok, data: await r.json().catch(() => ({})) };
}

// ---------- Anlegen (im Profil) ----------

/**
 * Ein Name für das Gerät.
 *
 * Den echten Gerätenamen („Michas iPhone") gibt kein Browser heraus, und das
 * ist Absicht: Er trägt meist einen Vornamen und wäre ein Wiedererkennungs-
 * merkmal quer über alle Seiten. Was manche Browser hergeben, ist das Modell —
 * Chrome auf Android etwa nennt „Pixel 8". Safari nennt gar nichts, dort bleibt
 * es bei der Plattform, die der Server aus der Kennung errät.
 *
 * Selbst getippt schlägt beides: Wer etwas ins Feld schreibt, bekommt das.
 */
async function pkGeraetename() {
  const feld = document.getElementById('pk-label');
  if (feld && feld.value.trim()) return feld.value.trim();
  try {
    const uad = navigator.userAgentData;
    if (uad && uad.getHighEntropyValues) {
      const d = await uad.getHighEntropyValues(['model', 'platform']);
      const teile = [d.model, d.platform].filter(Boolean);
      if (teile.length) return teile.join(' · ').slice(0, 60);
    }
  } catch (e) { /* dann eben nicht */ }
  return '';
}

async function pkAnlegen(knopf, meldung) {
  const setze = (text, fehler) => {
    if (!meldung) return;
    meldung.textContent = text;
    meldung.className = 'muted small' + (fehler ? ' warn' : '');
  };
  knopf.disabled = true;
  try {
    const { data: vorbereitung } = await pkPost('/intern/profil/passkey/challenge');
    if (vorbereitung.error) { setze(knopf.dataset.unsupported, true); return; }

    const cred = await navigator.credentials.create({
      publicKey: {
        challenge: pkBin(vorbereitung.challenge),
        rp: { id: vorbereitung.rpId, name: vorbereitung.rpName },
        user: {
          id: pkBin(vorbereitung.userId),
          name: vorbereitung.userName,
          displayName: vorbereitung.userDisplay,
        },
        // ES256 zuerst, RS256 als Rückfall — mehr braucht es nicht, und beides
        // kann OpenSSL auf der Gegenseite prüfen.
        pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
        // Der Schlüssel soll auf dem Gerät bleiben und ohne Eingabe eines
        // Kontonamens funktionieren; entsperrt wird er durch das Gerät selbst.
        authenticatorSelection: { residentKey: 'required', userVerification: 'preferred' },
        // Dasselbe Gerät soll sich nicht zweimal eintragen.
        excludeCredentials: (vorbereitung.vorhanden || []).map(id => ({ type: 'public-key', id: pkBin(id) })),
        attestation: 'none',
        timeout: 120000,
      },
    });
    if (!cred) { setze(knopf.dataset.failed, true); return; }

    // getPublicKey() liefert den öffentlichen Teil bequem als SPKI — aber längst
    // nicht überall: Passwortverwalter wie LastPass legen den Passkey an und
    // geben hier nichts heraus. Dann reisen die Rohdaten mit, und der Server
    // holt den Schlüssel selbst aus dem CBOR. Absagen wäre falsch: Das Gerät
    // hat den Schlüssel dann ja schon angelegt.
    const spki = cred.response.getPublicKey && cred.response.getPublicKey();

    const { ok, data } = await pkPost('/intern/profil/passkey', {
      id: cred.id,
      publicKey: spki ? pkB64(spki) : '',
      attestationObject: pkB64(cred.response.attestationObject),
      clientDataJSON: pkB64(cred.response.clientDataJSON),
      label: await pkGeraetename(),
    });
    if (!ok) { setze(data.error || knopf.dataset.failed, true); return; }
    // Gleich merken: Dann fragt schon die nächste Anmeldung direkt nach dem
    // Gesicht, statt erst wissen zu wollen, welcher Schlüssel es sein soll.
    pkMerken(cred.id);
    location.reload();
  } catch (e) {
    // Abbruch durch die Person ist kein Fehler — sie hat sich anders entschieden.
    setze(e && e.name === 'NotAllowedError' ? knopf.dataset.cancelled : knopf.dataset.failed,
          !(e && e.name === 'NotAllowedError'));
  } finally {
    knopf.disabled = false;
  }
}

// ---------- Anmelden (auf der Login-Seite) ----------

// Welcher Passkey auf DIESEM Gerät zuletzt gegangen ist. Der Merker ist der
// Unterschied zwischen „welchen möchtest du?" und sofortiger Gesichtsabfrage:
// Nennen wir dem Gerät den Schlüssel, hat es nichts mehr zu fragen und geht
// direkt an die Entsperrung. Er liegt lokal, denn der Server weiß nicht, wer
// da anklopft — und er ist kein Geheimnis: Ohne den privaten Teil im Gerät
// lässt sich mit einer Kennung nichts anfangen.
const PK_MERKER = 'bandregie-passkey-id';
const PK_FEHL = 'bandregie-passkey-fehl';
const pkKennt = () => { try { return localStorage.getItem(PK_MERKER) || ''; } catch (e) { return ''; } };
const pkMerken = id => {
  try { localStorage.setItem(PK_MERKER, id); localStorage.removeItem(PK_FEHL); } catch (e) { /* egal */ }
};
const pkVergessen = () => {
  try { localStorage.removeItem(PK_MERKER); localStorage.removeItem(PK_FEHL); } catch (e) { /* egal */ }
};

/**
 * Eine gescheiterte Abfrage zählen — und nach der dritten in Folge aufhören,
 * von selbst zu fragen.
 *
 * Einmal wegtippen heißt nur „jetzt gerade nicht", das darf die direkte
 * Anmeldung nicht kosten. Wer aber seinen Passkey im Schlüsselbund gelöscht
 * hat, bekäme sonst bei jedem Besuch eine Abfrage, die nie gelingen kann. Eine
 * gelungene Anmeldung setzt den Zähler wieder auf null.
 */
function pkFehlschlag() {
  try {
    const n = (parseInt(localStorage.getItem(PK_FEHL) || '0', 10) || 0) + 1;
    if (n >= 3) pkVergessen(); else localStorage.setItem(PK_FEHL, String(n));
  } catch (e) { /* egal */ }
}

// Die laufende Anfrage, damit sich zwei nicht in die Quere kommen: Es gibt nur
// eine Zufallsfrage in der Sitzung, und die zweite machte die erste ungültig.
let pkLaufend = null;

/**
 * @param nurDieser Kennung des bekannten Passkeys — dann fragt das Gerät nicht
 *                  nach, welcher es sein soll, sondern entsperrt sofort.
 */
async function pkFrageStellen(nurDieser, mediation) {
  if (pkLaufend) {
    pkLaufend.abort();
    pkLaufend = null;
    // Dem Browser einen Moment lassen, die alte Anfrage wirklich abzuräumen.
    // Safari wies die neue sonst ab, weil die vorige noch als offen galt —
    // beim zweiten Versuch ging es dann. Genau dieses Holpern.
    await new Promise(r => setTimeout(r, 60));
  }
  const { data } = await pkPost('/passkey/challenge');
  if (data.error) return null;
  const abbruch = new AbortController();
  pkLaufend = abbruch;
  const wunsch = {
    challenge: pkBin(data.challenge),
    rpId: data.rpId,
    // Bei der direkten Abfrage verlangen wir die Entsperrung ausdrücklich —
    // sonst genügte manchen Geräten ein Antippen, und aus „Face ID" würde ein
    // Knopfdruck.
    userVerification: nurDieser ? 'required' : 'preferred',
    timeout: 120000,
  };
  // Sonst keine Liste: Das Gerät weiß selbst, welche Schlüssel es für diese
  // Seite hat. Eine Liste vom Server müsste vorher verraten, wer hier ein
  // Konto hat.
  if (nurDieser) wunsch.allowCredentials = [{ type: 'public-key', id: pkBin(nurDieser) }];
  return navigator.credentials.get({ publicKey: wunsch, mediation, signal: abbruch.signal });
}

/**
 * Die stille Bereitschaft: Der Passkey hängt im Vorschlag über dem E-Mail-Feld,
 * neben den gespeicherten Passwörtern. Beides zusammen ist so vorgesehen — das
 * Verfahren verdrängt den Passwortsafe nicht, es stellt sich dazu.
 *
 * Beim ersten Versuch tat es das doch, und der Grund lag nicht hier, sondern
 * am Formular: Nur das E-Mail-Feld war ausgezeichnet, das Passwortfeld gar
 * nicht. Ohne autocomplete="current-password" erkennt der Safe das Gebilde
 * nicht mehr als Anmeldeformular und füllt nichts mehr aus.
 *
 * Zusätzlich der Notausgang unten: Wer das Passwortfeld anfasst, bekommt die
 * Anfrage abgebrochen. Ein zweiter Anmeldeweg darf den ersten nie blockieren —
 * lieber kein Passkey-Vorschlag als ein Formular, in das man nichts eintragen
 * kann.
 */
async function pkBereitstehen(meldung) {
  if (!window.PublicKeyCredential || !PublicKeyCredential.isConditionalMediationAvailable) return false;
  try {
    if (!(await PublicKeyCredential.isConditionalMediationAvailable())) return false;
  } catch (e) {
    return false;
  }
  // Der Notausgang wird vor der Anfrage scharf gemacht, nicht danach: Sonst
  // gäbe es einen Moment, in dem sie schon läuft und noch niemand sie stoppen
  // kann.
  const pw = document.querySelector('input[type="password"]');
  if (pw) {
    const frei = () => { if (pkLaufend) { pkLaufend.abort(); pkLaufend = null; } };
    pw.addEventListener('focus', frei, { once: true });
    pw.addEventListener('input', frei);
  }
  try {
    const cred = await pkFrageStellen(null, 'conditional');
    if (cred) await pkAbsenden(cred, meldung);
  } catch (e) {
    // Abbruch ist hier der Normalfall — wer tippt, will kein Passkey-Fenster.
  }
  return true;
}

/** Die Antwort des Geräts an den Server geben und weitergehen. */
async function pkAbsenden(cred, meldung) {
  const { ok, data } = await pkPost('/passkey/login', {
    id: cred.id,
    authenticatorData: pkB64(cred.response.authenticatorData),
    clientDataJSON: pkB64(cred.response.clientDataJSON),
    signature: pkB64(cred.response.signature),
  });
  if (!ok || !data.ok) {
    if (meldung) { meldung.textContent = data.error || ''; meldung.className = 'muted small warn'; }
    // Merker weg: Wer seinen Passkey entfernt hat, soll nicht bei jedem Besuch
    // eine Abfrage bekommen, die nur scheitern kann. Eine gelungene Anmeldung
    // setzt ihn gleich wieder.
    pkVergessen();
    return;
  }
  pkMerken(cred.id);
  location.href = data.weiter || '/intern';
}

async function pkAnmelden(knopf, meldung, nurDieser) {
  const setze = (text, fehler) => {
    if (!meldung) return;
    meldung.textContent = text;
    meldung.className = 'muted small' + (fehler ? ' warn' : '');
  };
  if (knopf) knopf.disabled = true;
  try {
    const cred = await pkFrageStellen(nurDieser);
    if (!cred) { setze(knopf && knopf.dataset.unsupported, true); return; }
    await pkAbsenden(cred, meldung);
  } catch (e) {
    // Nur die Abfrage, die von selbst kam, zählt mit. Wer den Knopf gedrückt
    // hat, weiß ja, was er tut.
    if (!knopf) { pkFehlschlag(); return; }
    // Ein Abbruch, den wir selbst ausgelöst haben, ist keine Nachricht wert:
    // Der Knopfdruck stoppt die stille Bereitschaft, und die meldet sich dann
    // mit AbortError. „Hier liegt kein Passkey" wäre dazu schlicht gelogen —
    // das war der holprige erste Versuch.
    if (e && e.name === 'AbortError') return;
    // Am Knopf endet es nie mit einer bloßen Absage. Ob hier noch kein Passkey
    // liegt oder jemand abgebrochen hat, lässt sich nicht unterscheiden — beide
    // Male ist derselbe Satz richtig: mit Passwort herein, danach einen
    // anlegen. Der Blick geht gleich aufs Feld, damit die Hand weiß wohin.
    setze(knopf.dataset.none, false);
    const feld = document.querySelector('input[type="email"]');
    if (feld) feld.focus();
  } finally {
    if (knopf) knopf.disabled = false;
  }
}

/**
 * Das Angebot auf der Startseite: Wer sich mit Passwort angemeldet hat und auf
 * diesem Gerät noch keinen Passkey benutzt, wird einmal gefragt. „Später"
 * heißt später und nicht gleich wieder — das merkt sich der Browser.
 */
function pkAngebot() {
  const karte = document.querySelector('[data-passkey-offer]');
  if (!karte || !pkMoeglich() || pkKennt()) return;
  try { if (localStorage.getItem('bandregie-passkey-spaeter') === '1') return; } catch (e) { /* egal */ }
  karte.hidden = false;
  const spaeter = karte.querySelector('[data-passkey-later]');
  if (spaeter) spaeter.addEventListener('click', () => {
    karte.hidden = true;
    try { localStorage.setItem('bandregie-passkey-spaeter', '1'); } catch (e) { /* egal */ }
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  pkAngebot();
  const bereich = document.querySelector('[data-passkey]');
  if (!bereich) return;
  // Ohne Unterstützung gar nicht erst zeigen: ein Knopf, der nichts kann, ist
  // schlimmer als kein Knopf.
  if (!pkMoeglich()) return;
  bereich.hidden = false;

  const meldung = document.getElementById('pk-msg');
  const anlegen = document.getElementById('pk-add');
  if (anlegen) anlegen.addEventListener('click', () => pkAnlegen(anlegen, meldung));

  const anmelden = document.getElementById('pk-login');
  if (!anmelden) return;
  anmelden.addEventListener('click', () => pkAnmelden(anmelden, meldung));

  // Ohne Knopfdruck geht es auf zwei Wegen, und welcher greift, entscheidet
  // der Browser:
  //
  //  1. Safari und Chrome auf heutigen Geräten können die stille Bereitschaft.
  //     Das ist dort auch der einzige Weg: Eine Passkey-Abfrage ohne
  //     Nutzeraktion weist Safari ab — Conditional UI ist die ausdrückliche
  //     Ausnahme davon. Der Passkey hängt dann im Vorschlag über dem Feld.
  //  2. Wo das fehlt, aber auf diesem Gerät schon ein Passkey lief, fragt die
  //     Seite beim Öffnen direkt nach dem Gesicht.
  //
  // Nur eins von beidem: Es gibt eine Zufallsfrage je Sitzung, und die zweite
  // machte die erste ungültig.
  if (await pkBereitstehen(meldung)) return;
  const bekannt = pkKennt();
  if (bekannt) pkAnmelden(null, meldung, bekannt);
});
