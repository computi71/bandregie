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

/** Die Herkunft laut fester Adresse — die, unter der wir uns selbst kennen. */
function passkey_origin(): string {
  $u = rtrim(trim(setting('site_url')), '/') . '/';
  $scheme = parse_url($u, PHP_URL_SCHEME);
  $host = parse_url($u, PHP_URL_HOST);
  $port = parse_url($u, PHP_URL_PORT);
  return $scheme . '://' . $host . ($port ? ':' . $port : '');
}

/**
 * Welche Herkünfte gelten. Neben der festen Adresse auch ihr Gegenstück mit
 * bzw. ohne www — dieselbe Installation, nur anders getippt.
 *
 * Der Browser lässt das ausdrücklich zu: Als Kennung darf eine Seite die
 * übergeordnete Domain nennen, deshalb meldet www.beispiel.de brav
 * „beispiel.de" zurück. Nur die Herkunft trägt dann eben www, und ohne diese
 * Ergänzung würde eine sonst einwandfreie Anmeldung abgewiesen, weil jemand
 * drei Buchstaben mitgetippt hat.
 *
 * Weiter geht die Nachsicht nicht: Was hier nicht steht, wird abgelehnt.
 *
 * @return string[]
 */
function passkey_origins(): array {
  $o = passkey_origin();
  $host = (string) parse_url($o, PHP_URL_HOST);
  $zwilling = str_starts_with($host, 'www.')
    ? substr($host, 4)
    : 'www.' . $host;
  return array_values(array_unique([$o, str_replace('://' . $host, '://' . $zwilling, $o)]));
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
  $herkunft = (string) ($d['origin'] ?? '');
  $passt = false;
  foreach (passkey_origins() as $erlaubt) {
    if (hash_equals($erlaubt, $herkunft)) $passt = true;
  }
  if (!$passt) return 'fl_pk_bad_origin';
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

/**
 * Ein winziger CBOR-Leser — nur so viel, wie WebAuthn braucht.
 *
 * Nötig geworden, weil getPublicKey() nicht überall etwas liefert: Passwort-
 * verwalter wie LastPass legen den Passkey an, geben den öffentlichen Teil
 * aber nicht in dieser bequemen Form heraus. Dann steht er nur im
 * attestationObject, und das ist CBOR. Die Abkürzung war also eine, die manche
 * Geräte aussperrt — dieser Leser holt das nach.
 *
 * Unterstützt werden die fünf Typen, die hier vorkommen: Zahlen (auch
 * negative, denn COSE-Schlüssel sind mit -1, -2, -3 benannt), Bytefolgen,
 * Text, Listen und Zuordnungen. Alles andere ist in diesen Daten nicht
 * vorgesehen und führt zum Abbruch, statt zu einer Vermutung.
 *
 * @return mixed null bei allem, was nicht sauber gelesen werden kann
 */
function cbor_read(string $b, int &$pos) {
  if ($pos >= strlen($b)) return null;
  $kopf = ord($b[$pos++]);
  $typ = $kopf >> 5;
  $kurz = $kopf & 0x1f;

  // Die Länge steht entweder im Kopf selbst oder in den folgenden Bytes.
  $laenge = $kurz;
  if ($kurz >= 24 && $kurz <= 27) {
    $n = 1 << ($kurz - 24);                       // 1, 2, 4 oder 8 Bytes
    if ($pos + $n > strlen($b)) return null;
    $laenge = 0;
    for ($i = 0; $i < $n; $i++) $laenge = ($laenge << 8) | ord($b[$pos++]);
  } elseif ($kurz >= 28) {
    return null;                                   // unbestimmte Länge: nicht hier
  }

  switch ($typ) {
    case 0: return $laenge;                        // positive Zahl
    case 1: return -1 - $laenge;                   // negative Zahl
    case 2:                                        // Bytefolge
    case 3:                                        // Text
      if ($pos + $laenge > strlen($b)) return null;
      $wert = substr($b, $pos, $laenge);
      $pos += $laenge;
      return $wert;
    case 4:                                        // Liste
      $out = [];
      for ($i = 0; $i < $laenge; $i++) {
        $v = cbor_read($b, $pos);
        if ($v === null) return null;
        $out[] = $v;
      }
      return $out;
    case 5:                                        // Zuordnung
      $out = [];
      for ($i = 0; $i < $laenge; $i++) {
        $k = cbor_read($b, $pos);
        $v = cbor_read($b, $pos);
        if ($k === null || $v === null) return null;
        $out[is_int($k) ? $k : (string) $k] = $v;
      }
      return $out;
  }
  return null;
}

/**
 * Den öffentlichen Schlüssel aus dem attestationObject holen, als SPKI.
 *
 * Der Weg: CBOR auspacken → authData → den Teil hinter Kennung und Zähler →
 * darin der COSE-Schlüssel. Von den COSE-Feldern brauchen wir kty (1), alg (3),
 * crv (-1) sowie x (-2) und y (-3).
 *
 * Nur P-256/ES256. RS256 käme mit deutlich mehr DER-Bastelei und kommt bei
 * Passkeys praktisch nicht vor; ein ehrliches Nein ist dort besser als ein
 * halb geratener Schlüssel.
 *
 * Nebenbei fällt die AAGUID ab — die Kennung des Anbieters, der den Passkey
 * verwahrt. Sie benennt nicht das Gerät, sondern den Schlüsselbund: iCloud,
 * Google, Windows Hello, 1Password. Genau das unterscheidet zwei Einträge auf
 * demselben Rechner voneinander.
 *
 * @return array{0: string, 1: string, 2: string} SPKI, Credential-ID, AAGUID
 */
function passkey_from_attestation(string $attestationObject): array {
  $pos = 0;
  $att = cbor_read($attestationObject, $pos);
  if (!is_array($att) || !isset($att['authData']) || !is_string($att['authData'])) return ['', '', ''];
  $auth = $att['authData'];
  // rpIdHash 32 + Flags 1 + Zähler 4, dann muss das Flag „enthält Schlüssel"
  // gesetzt sein — sonst steht dahinter gar keiner.
  if (strlen($auth) < 55 || !(ord($auth[32]) & 0x40)) return ['', '', ''];
  // Die AAGUID steht direkt hinter dem Zähler, 16 Bytes, in der üblichen
  // Strichschreibweise ausgegeben.
  $roh = substr($auth, 37, 16);
  $hex = bin2hex($roh);
  $aaguid = $roh === str_repeat("\x00", 16) ? ''
    : substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
      . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
  $p = 37 + 16;
  $idLen = (ord($auth[$p]) << 8) | ord($auth[$p + 1]);
  $p += 2;
  if ($idLen <= 0 || $p + $idLen > strlen($auth)) return ['', '', ''];
  $credId = substr($auth, $p, $idLen);
  $p += $idLen;

  $kPos = 0;
  $cose = cbor_read(substr($auth, $p), $kPos);
  if (!is_array($cose)) return ['', '', ''];
  $kty = $cose[1] ?? null;
  $alg = $cose[3] ?? null;
  $crv = $cose[-1] ?? null;
  $x = $cose[-2] ?? null;
  $y = $cose[-3] ?? null;
  if ($kty !== 2 || $alg !== -7 || $crv !== 1) return ['', '', ''];
  if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) return ['', '', ''];

  // Der DER-Vorspann für einen P-256-Punkt ist konstant; nur der Punkt wechselt.
  $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
  return [$der, $credId, $aaguid];
}

/**
 * Wer den Passkey verwahrt, nach AAGUID.
 *
 * Aus der gemeinschaftlich gepflegten Liste des passkey-developer-Projekts.
 * Sie ist nicht vollständig — LastPass etwa fehlt dort —, und was hier nicht
 * steht, bekommt eben den Namen der Plattform. Ein falscher Name wäre
 * schlechter als ein ungenauer.
 */
const PASSKEY_ANBIETER = [
  'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'Apple Passwörter',
  'dd4ec289-e01d-41c9-bb89-70fa845d4bf2' => 'iCloud Schlüsselbund',
  'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google Passwortmanager',
  '08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
  '9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',
  '6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
  'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',
  'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',
  '531126d6-e717-415c-9320-3d9aa6981239' => 'Dashlane',
  '0ea242b4-43c4-4a1b-8b17-dd6d0b6baec6' => 'Keeper',
  'b84e4048-15dc-4dd0-8640-f4f60813c8af' => 'NordPass',
  'f3809540-7f14-49c1-a8b3-8f813b225541' => 'Enpass',
  '53414d53-554e-4700-0000-000000000000' => 'Samsung Pass',
  'adce0002-35bc-c60a-648b-0b25f1f05503' => 'Chrome auf Mac',
  '771b48fd-d3d4-4f74-9232-fc157ab0507a' => 'Edge auf Mac',
];

/**
 * Der Name, unter dem ein neuer Passkey erscheint: der Schlüsselbund, wenn wir
 * ihn kennen, sonst die Plattform aus der Kennung.
 *
 * Der Schlüsselbund sagt mehr als das Gerät, sobald jemand zwei auf demselben
 * Rechner hat — „1Password" und „Windows Hello" unterscheiden sich, zweimal
 * „Windows" nicht.
 */
function passkey_label(string $aaguid, string $userAgent): string {
  return PASSKEY_ANBIETER[$aaguid] ?? passkey_label_from_agent($userAgent);
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
