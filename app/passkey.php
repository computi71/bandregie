<?php
declare(strict_types=1);

/**
 * Anmeldung mit Passkey (#168) — zusätzlich zum Passwort, nicht statt dessen.
 *
 * Was auf dem Handy als Gesichtserkennung oder Fingerabdruck erscheint, ist
 * kein eigenes Verfahren: Es entsperrt den Schlüssel im sicheren Bereich des
 * Geräts, und der signiert damit eine Zufallsfrage von uns. Ein Gesicht
 * erreicht diesen Server nie, ein Fingerabdruck ebenso wenig — hier liegt nur
 * der öffentliche Teil des Schlüssels, mit dem sich nichts anderes anfangen
 * lässt, als eine Signatur zu prüfen.
 *
 * Ohne Fremdbibliothek. Zwei Vereinfachungen machen das möglich, beide
 * bewusst:
 *
 *  1. Beim Anlegen liefert der Browser den öffentlichen Schlüssel über
 *     getPublicKey() fertig als SPKI — damit entfällt das Auspacken von CBOR,
 *     das sonst der aufwendigste Teil wäre. Verlassen muss man sich darauf
 *     nicht: Verlässt sich jemand einen falschen Schlüssel einzutragen, kann
 *     er anschließend nur mit dem passenden privaten Teil signieren, und den
 *     hat er dann eben. Gewonnen ist damit nichts, was er nicht ohnehin hätte.
 *  2. Wir prüfen keine Attestation. Die beantwortet die Frage „ist das ein
 *     echter YubiKey?", und die stellt eine Band nicht.
 *
 * Was dagegen bei JEDER Anmeldung geprüft wird, weil daran alles hängt:
 * Herkunft, Zufallsfrage, Art der Anfrage, Bindung an diese Installation, die
 * Anwesenheit der Person am Gerät — und zuletzt die Signatur selbst.
 */

/** Kann diese Installation Passkeys? Ohne OpenSSL geht die Prüfung nicht. */
function passkey_supported(): bool {
  return function_exists('openssl_verify') && function_exists('openssl_pkey_get_public');
}

/**
 * Sind Passkeys nutzbar? Zusätzlich zum Können die feste Adresse.
 *
 * Ein Passkey gilt für genau einen Namen. Ohne feste Adresse käme die Kennung
 * aus dem Host der laufenden Anfrage — und dieselbe Installation heißt dann
 * unter www anders als ohne. Wer seinen Passkey mit www anlegt, käme ohne www
 * nicht mehr herein: das Gerät antwortet, die Prüfung weist zu Recht ab, und
 * niemand versteht warum. Lieber gar nicht anbieten als so.
 */
function passkey_available(): bool {
  return passkey_supported() && trim(setting('site_url')) !== '';
}

/**
 * Die Kennung dieser Installation für WebAuthn — der reine Hostname aus der
 * festen Adresse. Nicht aus dem Host der Anfrage: der ließe sich von außen
 * setzen, und er wechselt mit www.
 */
function passkey_rp_id(): string {
  return (string) parse_url(rtrim(trim(setting('site_url')), '/') . '/', PHP_URL_HOST);
}

/** Die Herkunft, die im clientDataJSON stehen muss. */
function passkey_origin(): string {
  $u = rtrim(trim(setting('site_url')), '/') . '/';
  $scheme = parse_url($u, PHP_URL_SCHEME);
  $host = parse_url($u, PHP_URL_HOST);
  $port = parse_url($u, PHP_URL_PORT);
  return $scheme . '://' . $host . ($port ? ':' . $port : '');
}

function passkey_b64(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function passkey_b64_decode(string $s): string {
  return (string) base64_decode(strtr(trim($s), '-_', '+/'));
}

/**
 * Eine neue Zufallsfrage, gemerkt in der Sitzung.
 *
 * Sie ist der Kern des Verfahrens: Die Antwort gilt nur einmal und nur für
 * diese Frage. Ohne sie wäre eine abgefangene Signatur beliebig wiederholbar.
 */
function passkey_challenge_new(string $zweck): string {
  $c = random_bytes(32);
  $_SESSION['passkey_challenge'] = ['v' => passkey_b64($c), 'zweck' => $zweck, 'ts' => time()];
  return passkey_b64($c);
}

/**
 * Die gemerkte Frage einlösen — höchstens einmal und höchstens fünf Minuten
 * lang. Verbraucht wird sie in jedem Fall, auch bei Misserfolg: sonst ließe
 * sich beliebig oft gegen dieselbe Frage probieren.
 */
function passkey_challenge_take(string $zweck): ?string {
  $c = $_SESSION['passkey_challenge'] ?? null;
  unset($_SESSION['passkey_challenge']);
  if (!is_array($c) || ($c['zweck'] ?? '') !== $zweck) return null;
  if (time() - (int) ($c['ts'] ?? 0) > 300) return null;
  return (string) $c['v'];
}

/**
 * Die Angaben des Browsers zur Anmeldung prüfen — ohne die Signatur, die
 * kommt danach. Gibt den Fehlergrund zurück oder null, wenn alles stimmt.
 *
 * @param string $typ 'webauthn.create' oder 'webauthn.get'
 */
function passkey_client_data_error(string $clientDataJson, string $challenge, string $typ): ?string {
  $d = json_decode($clientDataJson, true);
  if (!is_array($d)) return 'fl_pk_bad_data';
  if (($d['type'] ?? '') !== $typ) return 'fl_pk_bad_type';
  // hash_equals, damit die Prüfung nicht an der Laufzeit verrät, wie weit zwei
  // Werte übereinstimmen.
  if (!hash_equals($challenge, (string) ($d['challenge'] ?? ''))) return 'fl_pk_bad_challenge';
  if (!hash_equals(passkey_origin(), (string) ($d['origin'] ?? ''))) return 'fl_pk_bad_origin';
  return null;
}

/**
 * Die Angaben des Geräts prüfen: Gilt die Antwort dieser Installation, und war
 * die Person beim Entsperren dabei?
 *
 * Der erste Teil ist die Bindung an den Namen — ohne sie nähme ein Passkey,
 * der für eine andere Seite ausgestellt wurde, hier ebenfalls die Tür auf. Der
 * zweite ist das Anwesenheitsbit: Ohne es könnte ein herumliegendes Gerät
 * unbemerkt antworten.
 *
 * @return string|null Fehlerschlüssel oder null
 */
function passkey_auth_data_error(string $authData): ?string {
  if (strlen($authData) < 37) return 'fl_pk_bad_data';
  if (!hash_equals(hash('sha256', passkey_rp_id(), true), substr($authData, 0, 32))) return 'fl_pk_bad_rp';
  $flags = ord($authData[32]);
  if (!($flags & 0x01)) return 'fl_pk_no_presence';   // User Present
  return null;
}

/** Der Zählstand des Geräts, aus den letzten vier Bytes des Kopfes. */
function passkey_sign_count(string $authData): int {
  return strlen($authData) >= 37 ? (int) unpack('N', substr($authData, 33, 4))[1] : 0;
}

/**
 * Die Signatur prüfen. Signiert wird über die Gerätedaten und den Abdruck der
 * Browserdaten — genau in dieser Reihenfolge, so steht es in der Spezifikation.
 */
function passkey_signature_ok(string $spkiPem, string $authData, string $clientDataJson, string $signature): bool {
  $key = openssl_pkey_get_public($spkiPem);
  if ($key === false) return false;
  $signed = $authData . hash('sha256', $clientDataJson, true);
  return openssl_verify($signed, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
}

/** Aus dem rohen SPKI ein PEM machen, wie openssl es lesen will. */
function passkey_pem(string $spkiDer): string {
  return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spkiDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Die Passkeys eines Mitglieds, neueste Benutzung zuerst. */
function passkey_list(int $userId): array {
  return rows('SELECT id, label, created_at, last_used_at FROM passkeys WHERE user_id = ?
               ORDER BY last_used_at IS NULL, last_used_at DESC, id DESC', [$userId]);
}

/**
 * Ein Gerätename, der etwas sagt. Der Browser nennt uns nichts Brauchbares,
 * also raten wir aus der Kennung — grob, aber besser als „Passkey 3".
 */
function passkey_label_from_agent(string $ua): string {
  $ua = strtolower($ua);
  foreach (['iphone' => 'iPhone', 'ipad' => 'iPad', 'macintosh' => 'Mac', 'android' => 'Android',
            'windows' => 'Windows', 'linux' => 'Linux'] as $nadel => $name) {
    if (str_contains($ua, $nadel)) return $name;
  }
  return t('pk_device');
}
