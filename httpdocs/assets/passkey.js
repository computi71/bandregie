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
        authenticatorSelection: { residentKey: 'preferred', userVerification: 'preferred' },
        // Dasselbe Gerät soll sich nicht zweimal eintragen.
        excludeCredentials: (vorbereitung.vorhanden || []).map(id => ({ type: 'public-key', id: pkBin(id) })),
        attestation: 'none',
        timeout: 120000,
      },
    });
    if (!cred) { setze(knopf.dataset.failed, true); return; }

    // getPublicKey() liefert den öffentlichen Teil fertig als SPKI. Fehlt die
    // Methode (sehr alte Browser), ließe sich der Schlüssel nur aus CBOR
    // herausholen — dann lieber ehrlich absagen als halb funktionieren.
    const spki = cred.response.getPublicKey && cred.response.getPublicKey();
    if (!spki) { setze(knopf.dataset.unsupported, true); return; }

    const { ok, data } = await pkPost('/intern/profil/passkey', {
      id: cred.id,
      publicKey: pkB64(spki),
      clientDataJSON: pkB64(cred.response.clientDataJSON),
      label: (document.getElementById('pk-label') || {}).value || '',
    });
    if (!ok) { setze(data.error || knopf.dataset.failed, true); return; }
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

async function pkAnmelden(knopf, meldung) {
  const setze = (text, fehler) => {
    if (!meldung) return;
    meldung.textContent = text;
    meldung.className = 'muted small' + (fehler ? ' warn' : '');
  };
  knopf.disabled = true;
  try {
    const { data: vorbereitung } = await pkPost('/passkey/challenge');
    if (vorbereitung.error) { setze(knopf.dataset.unsupported, true); return; }

    const cred = await navigator.credentials.get({
      publicKey: {
        challenge: pkBin(vorbereitung.challenge),
        rpId: vorbereitung.rpId,
        // Keine Liste erlaubter Schlüssel: Das Gerät weiß selbst, welchen es
        // für diese Seite hat. Eine Liste vom Server müsste vorher verraten,
        // wer hier ein Konto hat.
        userVerification: 'preferred',
        timeout: 120000,
      },
    });
    if (!cred) { setze(knopf.dataset.failed, true); return; }

    const { ok, data } = await pkPost('/passkey/login', {
      id: cred.id,
      authenticatorData: pkB64(cred.response.authenticatorData),
      clientDataJSON: pkB64(cred.response.clientDataJSON),
      signature: pkB64(cred.response.signature),
    });
    if (!ok || !data.ok) { setze(data.error || knopf.dataset.failed, true); return; }
    location.href = data.weiter || '/intern';
  } catch (e) {
    setze(e && e.name === 'NotAllowedError' ? knopf.dataset.cancelled : knopf.dataset.failed,
          !(e && e.name === 'NotAllowedError'));
  } finally {
    knopf.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const bereich = document.querySelector('[data-passkey]');
  if (!bereich) return;
  // Ohne Unterstützung gar nicht erst zeigen: ein Knopf, der nichts kann, ist
  // schlimmer als kein Knopf.
  if (!pkMoeglich()) return;
  bereich.hidden = false;

  const anlegen = document.getElementById('pk-add');
  if (anlegen) anlegen.addEventListener('click', () => pkAnlegen(anlegen, document.getElementById('pk-msg')));
  const anmelden = document.getElementById('pk-login');
  if (anmelden) anmelden.addEventListener('click', () => pkAnmelden(anmelden, document.getElementById('pk-msg')));
});
