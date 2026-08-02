<?php
declare(strict_types=1);

/**
 * Zweiter Faktor beim Anmelden mit Passwort (#169) — TOTP nach RFC 6238.
 *
 * Ein Passwort kann abgeschaut, erraten oder aus einem fremden Datenleck
 * wiederverwendet werden. Der zweite Faktor hilft genau dagegen: Er wechselt
 * alle dreißig Sekunden und steht nur auf dem Gerät, das ihn erzeugt.
 *
 * Warum TOTP und nichts anderes: Es braucht keinen Anbieter, keine SMS, kein
 * Geld und funktioniert ohne Netz. Was die Band dafür benutzt — Google
 * Authenticator, Aegis, 1Password, der eingebaute Schlüsselbund — ist ihre
 * Sache; sie sprechen alle dasselbe Verfahren.
 *
 * Wer sich mit Passkey anmeldet, wird nicht gefragt: Dort steckt der zweite
 * Faktor schon im Entsperren des Geräts.
 */

/** Wie lange ein Code gilt, in Sekunden. Dreißig ist der Standard. */
const TOTP_STEP = 30;

/** Stellen je Code. Sechs ist das, was jede App anzeigt. */
const TOTP_DIGITS = 6;

/**
 * Wie viele Schritte Abweichung erlaubt sind, in jede Richtung.
 *
 * Handyuhren gehen ungenau, und wer den Code abtippt, braucht ein paar
 * Sekunden. Einer bedeutet: eine halbe Minute Nachsicht nach beiden Seiten.
 * Mehr wäre bequem und zugleich eine größere Fläche zum Durchprobieren.
 */
const TOTP_WINDOW = 1;

/** Das Alphabet aus RFC 4648 — so, wie die Apps es erwarten. */
const TOTP_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Ein neues Geheimnis: 20 Bytes, wie im Standard vorgesehen. */
function totp_secret_new(): string {
  return totp_base32_encode(random_bytes(20));
}

function totp_base32_encode(string $bin): string {
  $bits = '';
  for ($i = 0; $i < strlen($bin); $i++) $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
  $out = '';
  foreach (str_split($bits, 5) as $fuenf) {
    $out .= TOTP_BASE32[bindec(str_pad($fuenf, 5, '0', STR_PAD_RIGHT))];
  }
  return $out;
}

function totp_base32_decode(string $s): string {
  $s = strtoupper(preg_replace('~[^A-Z2-7]~i', '', $s) ?? '');
  $bits = '';
  for ($i = 0; $i < strlen($s); $i++) {
    $pos = strpos(TOTP_BASE32, $s[$i]);
    if ($pos === false) return '';
    $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
  }
  $out = '';
  foreach (str_split($bits, 8) as $acht) {
    if (strlen($acht) === 8) $out .= chr(bindec($acht));
  }
  return $out;
}

/**
 * Der Code zu einem Zeitpunkt.
 *
 * Der Zähler ist die Zahl der Zeitschritte seit 1970, als acht Bytes. Aus dem
 * HMAC wird per „dynamic truncation" eine Stelle ausgewählt und von dort vier
 * Bytes gelesen — das steht so in RFC 4226 und ist der Grund, warum jede App
 * dasselbe Ergebnis liefert.
 */
function totp_code(string $secretBase32, ?int $zeit = null, int $digits = TOTP_DIGITS): string {
  $key = totp_base32_decode($secretBase32);
  if ($key === '') return '';
  $zaehler = intdiv($zeit ?? time(), TOTP_STEP);
  $hash = hash_hmac('sha1', pack('J', $zaehler), $key, true);
  $offset = ord($hash[19]) & 0x0f;
  $zahl = ((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
  return str_pad((string) ($zahl % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Stimmt der eingetippte Code?
 *
 * Verglichen wird mit hash_equals, damit die Laufzeit nicht verrät, wie viele
 * Stellen schon stimmen. Geprüft werden die Nachbarschritte mit, aber alle —
 * ein vorzeitiges Abbrechen beim Treffer wäre wieder ein Zeitunterschied.
 */
function totp_verify(string $secretBase32, string $eingabe): bool {
  $eingabe = preg_replace('~\D~', '', $eingabe) ?? '';
  if (strlen($eingabe) !== TOTP_DIGITS) return false;
  $jetzt = time();
  $treffer = false;
  for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
    if (hash_equals(totp_code($secretBase32, $jetzt + $i * TOTP_STEP), $eingabe)) $treffer = true;
  }
  return $treffer;
}

/**
 * Die Adresse, die in den QR-Code kommt. Der Name der Band steht davor, damit
 * in der App nicht sechs Einträge „micha@…" ohne Zusammenhang stehen.
 *
 * Der Doppelpunkt bleibt buchstäblich stehen und wird nicht mitkodiert: Beide
 * Schreibweisen sind erlaubt, aber jedes kodierte Zeichen macht die Adresse
 * um zwei Stellen länger — und ein längerer Text braucht einen größeren
 * QR-Code, der auf demselben Bildschirm feiner und schlechter scanbar wird.
 */
function totp_uri(string $secretBase32, string $konto, string $band): string {
  $band = $band !== '' ? $band : 'Bandregie';
  return 'otpauth://totp/' . rawurlencode($band) . ':' . rawurlencode($konto)
    . '?secret=' . $secretBase32
    . '&issuer=' . rawurlencode($band)
    . '&digits=' . TOTP_DIGITS
    . '&period=' . TOTP_STEP;
}

// ---------- Rückwege ----------

/** Wie viele Rückwege es gibt. Zehn reichen für ein Handy, das kaputtgeht. */
const TOTP_RECOVERY_COUNT = 10;

/**
 * Rückweg-Codes erzeugen. Sie werden einmal gezeigt und danach nur noch als
 * Abdruck gespeichert — wie ein Passwort, denn genau das sind sie.
 *
 * Ohne Ziffern, die man verwechselt: keine 0/O, keine 1/l. Wer sie abschreibt,
 * soll sich beim Abtippen nicht selbst aussperren.
 */
function totp_recovery_new(): array {
  $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $codes = [];
  for ($i = 0; $i < TOTP_RECOVERY_COUNT; $i++) {
    $code = '';
    for ($j = 0; $j < 10; $j++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
  }
  return $codes;
}

/** Der Abdruck eines Rückwegs, zum Ablegen. */
function totp_recovery_hash(string $code): string {
  return hash('sha256', strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $code) ?? ''));
}

// ---------- Was die Band eingestellt hat, und was ein Konto hat ----------

/**
 * aus / freiwillig / vorgeschrieben.
 *
 * Voreingestellt ist freiwillig: Der zweite Faktor steht im Profil bereit,
 * aber niemand wird beim ersten Update von einer Anmeldemaske überrascht, die
 * er nicht kennt. Ihn vorzuschreiben ist eine Entscheidung, die die Band
 * bewusst trifft.
 */
function totp_mode(): string {
  $modus = setting('totp_mode', 'optional');
  return in_array($modus, ['off', 'optional', 'required'], true) ? $modus : 'optional';
}

/** Gibt es den zweiten Faktor in dieser Installation überhaupt? */
function totp_available(): bool {
  return totp_mode() !== 'off';
}

/**
 * Hat dieses Konto einen bestätigten zweiten Faktor?
 *
 * Bestätigt heißt: Es wurde einmal ein Code aus der App eingegeben. Ohne diese
 * Bestätigung bleibt das Geheimnis unbenutzt liegen — sonst sperrt sich aus,
 * wer den QR-Code scannt und die App gleich wieder löscht.
 */
function totp_active_for(array $u): bool {
  return !empty($u['totp_confirmed_at']) && ($u['totp_secret'] ?? '') !== '';
}

/**
 * Die drei Spalten des zweiten Faktors zu einem Konto.
 *
 * Eine eigene Abfrage und nicht in current_user(): Die angemeldete Person wird
 * auf jeder Seite geladen, und dort steht bewusst eine knappe Spaltenliste.
 * Das Geheimnis auf jeder Seite mitzuschleppen wäre nicht nur überflüssig,
 * sondern hätte es in jedem Speicherabbild und jeder Fehlerausgabe liegen.
 *
 * Wer bereits eine volle Zeile hat — das Profil, die Mitgliederliste — nimmt
 * totp_active_for() direkt und spart die Abfrage.
 */
function totp_state(int $userId): array {
  return row('SELECT id, totp_secret, totp_confirmed_at, totp_recovery FROM users WHERE id = ?', [$userId]) ?? [];
}

/** Dasselbe für den häufigen Fall, dass nur die Frage zählt. */
function totp_active(int $userId): bool {
  return totp_active_for(totp_state($userId));
}

/** Das entsiegelte Geheimnis eines Kontos; '' wenn keines bestätigt ist. */
function totp_secret_for(array $u): string {
  if (!totp_active_for($u)) return '';
  return crypt_reveal((string) $u['totp_secret']);
}

/**
 * Geheimnis und Rückwege ablegen — versiegelt, wenn ein Schlüssel liegt.
 *
 * Wie beim VAPID-Schlüssel und beim FTP-Passwort der Sicherung: Ohne
 * Schlüssel im Klartext, denn ein zweiter Faktor, der nicht funktioniert,
 * weil die Verschlüsselung fehlt, hilft niemandem.
 */
function totp_store(int $userId, string $secretBase32, array $rueckwege): void {
  $abdruecke = array_map('totp_recovery_hash', $rueckwege);
  q('UPDATE users SET totp_secret = ?, totp_confirmed_at = NOW(), totp_recovery = ? WHERE id = ?', [
    crypt_available() ? crypt_seal($secretBase32) : $secretBase32,
    json_encode($abdruecke),
    $userId,
  ]);
}

/** Zweiten Faktor entfernen — beim Abschalten im Profil und beim Zurücksetzen durch den Admin. */
function totp_clear(int $userId): void {
  q("UPDATE users SET totp_secret = '', totp_confirmed_at = NULL, totp_recovery = '' WHERE id = ?", [$userId]);
}

/** Wie viele Rückwege noch übrig sind. */
function totp_recovery_left(array $u): int {
  $abdruecke = json_decode((string) ($u['totp_recovery'] ?? ''), true);
  return is_array($abdruecke) ? count($abdruecke) : 0;
}

/**
 * Einen Rückweg einlösen. Er gilt genau einmal und wird dabei verbraucht.
 *
 * Verglichen wird über alle hinweg ohne vorzeitigen Abbruch, damit die
 * Laufzeit nicht verrät, an welcher Stelle der Treffer sitzt.
 */
function totp_recovery_use(array $u, string $eingabe): bool {
  $abdruecke = json_decode((string) ($u['totp_recovery'] ?? ''), true);
  if (!is_array($abdruecke) || !$abdruecke) return false;
  $gesucht = totp_recovery_hash($eingabe);
  $treffer = false;
  $rest = [];
  foreach ($abdruecke as $abdruck) {
    if (hash_equals((string) $abdruck, $gesucht) && !$treffer) $treffer = true;
    else $rest[] = $abdruck;
  }
  if ($treffer) q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode($rest), (int) $u['id']]);
  return $treffer;
}
