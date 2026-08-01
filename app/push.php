<?php
// Web-Push ohne Fremdbibliothek (#24): Mitteilungen für neue Termine, neue
// Kommentare und Zusage-Änderungen — je Mitglied Opt-in mit Themen-Schaltern,
// je Gerät ein Abo. Ohne Browser-Unterstützung fällt alles still zurück; der
// Bandbereich funktioniert unverändert ohne Push.
//
// Die Verschlüsselung folgt RFC 8291 (aes128gcm), die Absender-Signatur
// RFC 8292 (VAPID, ES256). Alles Nötige bringt PHP selbst mit:
// openssl_pkey_derive (ECDH), hash_hkdf und AES-128-GCM.

/**
 * Base64 in der URL-Schreibweise, wie JWT und Web Push sie verlangen: ohne
 * Füllzeichen, mit - und _ statt + und /.
 */
function push_b64(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function push_b64_decode(string $s): string {
  return (string) base64_decode(strtr($s, '-_', '+/'));
}

/**
 * OpenSSL liefert ECDSA-Signaturen DER-kodiert; ein JWT verlangt die rohen
 * r‖s-Werte mit je 32 Bytes. Hier wird ausgepackt, entnullt und aufgefüllt.
 */
function push_ecdsa_der_to_raw(string $der): string {
  $pos = 0;
  $len = strlen($der);
  if ($len < 8 || ord($der[$pos++]) !== 0x30) return '';
  // Nur die Kurzform: eine ES256-Signatur ist immer kürzer als 128 Byte. Alles
  // andere ist kein P-256-DER — dann lieber abbrechen als raten.
  if (ord($der[$pos]) & 0x80) return '';
  $pos++;
  $out = '';
  for ($i = 0; $i < 2; $i++) {
    if ($pos + 2 > $len || ord($der[$pos++]) !== 0x02) return '';
    $l = ord($der[$pos++]);
    // Abgeschnittenes DER darf keine formal gültige, inhaltlich falsche
    // Signatur ergeben: substr() würde stillschweigend kürzen und das Ergebnis
    // anschließend links mit Nullen aufgefüllt.
    if ($l === 0 || $pos + $l > $len) return '';
    $val = substr($der, $pos, $l);
    $pos += $l;
    $val = ltrim($val, "\x00");
    if (strlen($val) > 32) return '';
    $out .= str_pad($val, 32, "\x00", STR_PAD_LEFT);
  }
  return $out;
}

/** Kann diese Installation überhaupt Push? (Voraussetzungen von PHP) */
function push_supported(): bool {
  return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf')
    && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
}

/**
 * Ist Push nutzbar? Zusätzlich zum Können auch das Wollen: ein Schalter für die
 * ganze Installation, wie beim Geocoding. Ohne ihn ließe sich der Kanal nicht an
 * einer Stelle stilllegen, wenn eine Band ihn grundsätzlich nicht möchte.
 * Standardmäßig aus — wie jede Kommunikation nach außen.
 */
function push_available(): bool {
  return push_supported() && setting('push_enabled') === '1';
}

/**
 * Nur die Push-Dienste der Browserhersteller sind erlaubte Ziele. Der Endpunkt
 * kommt vom Browser des Mitglieds — aber der Server ruft ihn später auf, und
 * eine frei wählbare Adresse machte ihn zum Werkzeug gegen das eigene Netz
 * (interne Dienste, Cloud-Metadaten). Geprüft beim Anlegen UND beim Versand:
 * ein Abo, das schon in der Datenbank liegt, ist damit noch nicht vertraut.
 */
function push_endpoint_ok(string $endpoint): bool {
  if (!str_starts_with($endpoint, 'https://')) return false;
  $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
  if ($host === '' || parse_url($endpoint, PHP_URL_PORT) !== null) return false;
  $erlaubt = [
    'fcm.googleapis.com',                       // Chrome, Edge, Android
    'web.push.apple.com',                       // Safari, iOS
    'updates.push.services.mozilla.com',        // Firefox
  ];
  if (in_array($host, $erlaubt, true)) return true;
  // Windows/Edge-Alt: wns2-*.notify.windows.com — nur echte Unterdomänen.
  return str_ends_with($host, '.notify.windows.com');
}

// ---------- Schlüssel ----------

/** Der rohe öffentliche Punkt (04‖x‖y) eines EC-Schlüssels. */
function push_raw_public($key): string {
  $d = openssl_pkey_get_details($key);
  if (!isset($d['ec']['x'], $d['ec']['y'])) return '';
  return "\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
}

/**
 * Ein roher P-256-Punkt als PEM-PublicKey — openssl_pkey_derive braucht ihn
 * so. Der DER-Vorspann ist für P-256 konstant, nur der Punkt wechselt.
 */
function push_pem_from_raw(string $raw): string {
  $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
  return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** VAPID-Schlüsselpaar: einmalig erzeugt, privat versiegelt abgelegt. */
function push_keys(): array {
  // Entsiegeln erledigt crypt_reveal() — eine Stelle für dieselbe Sache.
  $pem = crypt_reveal(setting('push_vapid_key'));
  if ($pem === '') {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($key === false) return ['pem' => '', 'public' => ''];
    openssl_pkey_export($key, $pem);
    set_setting('push_vapid_key', function_exists('crypt_available') && crypt_available()
      ? (string) crypt_seal($pem) : $pem);
  }
  $key = openssl_pkey_get_private($pem);
  return $key === false ? ['pem' => '', 'public' => '']
    : ['pem' => $pem, 'public' => push_b64(push_raw_public($key))];
}

/** Der öffentliche VAPID-Schlüssel, wie ihn pushManager.subscribe erwartet. */
function push_public_key(): string {
  return push_keys()['public'];
}

// ---------- VAPID (RFC 8292): wer schickt hier eigentlich? ----------

/** Authorization-Kopf für einen Endpunkt: ES256-JWT auf dessen Ursprung. */
function push_vapid_auth(string $endpoint): string {
  $keys = push_keys();
  $key = openssl_pkey_get_private($keys['pem']);
  if ($key === false) return '';
  $origin = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
  $header = push_b64(json_encode(['alg' => 'ES256', 'typ' => 'JWT']));
  $claims = push_b64(json_encode([
    'aud' => $origin, 'exp' => time() + 12 * 3600,
    'sub' => 'mailto:' . (setting('contact_email') ?: 'no-reply@' . parse_url(absolute_url('/'), PHP_URL_HOST)),
  ]));
  if (!openssl_sign($header . '.' . $claims, $der, $key, OPENSSL_ALGO_SHA256)) return '';
  $sig = push_ecdsa_der_to_raw($der);
  return $sig === '' ? '' : 'vapid t=' . $header . '.' . $claims . '.' . push_b64($sig) . ', k=' . $keys['public'];
}

// ---------- Verschlüsselung (RFC 8291, aes128gcm) ----------

/**
 * Verschlüsselt eine Nachricht für ein Abo. Ephemerer Schlüssel und Salt sind
 * injizierbar, damit der Testvektor aus RFC 8291 Anhang A nachrechenbar ist —
 * im Betrieb entstehen beide frisch je Nachricht.
 */
function push_encrypt(string $payload, string $p256dh, string $auth, ?string $ephemeralPem = null, ?string $salt = null): ?string {
  $uaPub = push_b64_decode($p256dh);
  $authSecret = push_b64_decode($auth);
  if (strlen($uaPub) !== 65 || strlen($authSecret) !== 16) return null;
  // Ein einzelner Datensatz von 4096 Bytes, abzüglich Prüfsumme und Trenner —
  // mehr passt nicht hinein, und ein zu großer würde erst beim Dienst auffallen.
  if (strlen($payload) > 4078) return null;
  if ($ephemeralPem === null) {
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($eph === false) return null;
    openssl_pkey_export($eph, $ephemeralPem);
  }
  $asKey = openssl_pkey_get_private($ephemeralPem);
  if ($asKey === false) return null;
  $asPub = push_raw_public($asKey);
  $shared = openssl_pkey_derive(openssl_pkey_get_public(push_pem_from_raw($uaPub)), $asKey);
  if (!is_string($shared)) return null;
  $salt = $salt ?? random_bytes(16);
  // Schlüsselplan nach RFC 8291: erst das gemeinsame Geheimnis mit dem
  // auth-Secret des Abos binden, daraus Inhaltsschlüssel und Nonce ziehen.
  $ikm = hash_hkdf('sha256', $shared, 32, 'WebPush: info' . "\x00" . $uaPub . $asPub, $authSecret);
  $cek = hash_hkdf('sha256', $ikm, 16, 'Content-Encoding: aes128gcm' . "\x00", $salt);
  $nonce = hash_hkdf('sha256', $ikm, 12, 'Content-Encoding: nonce' . "\x00", $salt);
  // 0x02 markiert den letzten Datensatz (Padding-Trennzeichen).
  $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
  if ($cipher === false) return null;
  // Kopf: Salt ‖ Datensatzgröße ‖ Länge des Absender-Schlüssels ‖ Schlüssel.
  return $salt . pack('N', 4096) . chr(65) . $asPub . $cipher . $tag;
}

// ---------- Versand ----------

/** Schickt eine Nachricht an ein Abo; bei 404/410 ist das Abo tot → löschen. */
function push_send_one(array $sub, string $json): bool {
  // Auch gespeicherte Abos werden hier noch geprüft: was in der Datenbank
  // steht, ist damit nicht automatisch ein erlaubtes Ziel.
  if (!push_endpoint_ok((string) $sub['endpoint'])) {
    q('DELETE FROM push_subscriptions WHERE id = ?', [(int) $sub['id']]);
    return false;
  }
  $body = push_encrypt($json, $sub['p256dh'], $sub['auth']);
  $vapid = push_vapid_auth($sub['endpoint']);
  if ($body === null || $vapid === '') return false;
  $ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Authorization: $vapid\r\nContent-Encoding: aes128gcm\r\n"
      . "Content-Type: application/octet-stream\r\nTTL: 86400\r\nUrgency: normal\r\n",
    'content' => $body,
    'timeout' => 10,
    'ignore_errors' => true,
    // Ein Push-Dienst hat keinen Grund umzuleiten; wohin, entscheidet sonst er.
    'follow_location' => 0,
    'max_redirects' => 0,
  ]]);
  @file_get_contents($sub['endpoint'], false, $ctx);
  $status = 0;
  foreach ($http_response_header ?? [] as $h) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int) $m[1];
  }
  // Dauerhafte Ablehnungen wegräumen, nicht nur „weg" (404/410): ein Abo, das
  // der Dienst nicht mehr annimmt (falscher Schlüssel, ungültiger Pfad, zu
  // groß), würde sonst bei jeder Mitteilung erneut angefragt — und jede Anfrage
  // kostet bis zu zehn Sekunden Wartezeit.
  if (in_array($status, [400, 401, 403, 404, 410, 413], true)) {
    q('DELETE FROM push_subscriptions WHERE id = ?', [(int) $sub['id']]);
    return false;
  }
  return $status >= 200 && $status < 300;
}

/** Ein UI-Text in der Sprache des Empfängers — t() kennt nur die Sitzung. */
function push_t(string $lang, string $key): string {
  if ($lang !== 'de') {
    $r = row('SELECT value FROM translations WHERE lang = ? AND tkey = ?', [$lang, $key]);
    if ($r && $r['value'] !== '') return $r['value'];
  }
  return UI_STRINGS[$key] ?? $key;
}

/**
 * Mitteilung an alle Mitglieder mit dem Thema — außer den Auslösenden selbst.
 * $build baut die Nachricht je Sprache: fn(string $lang): array{title,body,url}.
 *
 * $eventId bindet die Mitteilung an einen Termin: Wer den Termin im Bandbereich
 * nicht sehen darf, darf ihn auch nicht als Mitteilung bekommen. Ersatzleute
 * sehen nur die Termine, für die sie angefragt wurden — eine Mitteilung mit
 * Termintitel und Kommentar-Anriss wäre sonst genau die Umgehung der
 * Sichtbarkeit, die die Listen sorgfältig einhalten.
 *
 * Gesendet wird erst NACH der Antwort (fastcgi_finish_request): niemand wartet
 * beim Speichern eines Kommentars auf die Push-Dienste von Google und Apple.
 */
function push_notify(string $topic, int $exceptUserId, callable $build, int $eventId = 0): void {
  if (!push_available()) return;
  // Die Themen werden hier gefiltert, nicht in SQL: „nichts eingestellt" heißt
  // alle Themen, und das lässt sich mit FIND_IN_SET nicht ausdrücken.
  // Die Abo-Kennung heißt bewusst sub_id: hieße sie 'id', hielte jede Prüfung,
  // die ein Mitglied erwartet, sie für die Mitglieds-Kennung — genau daran ist
  // die Sichtbarkeitsprüfung unten schon einmal vorbeigelaufen.
  $subs = rows("SELECT s.id AS sub_id, s.endpoint, s.p256dh, s.auth,
                       u.id, u.role, u.substitute_for, u.pref_lang, u.push_topics
                FROM push_subscriptions s JOIN users u ON u.id = s.user_id
                WHERE u.id <> ?", [$exceptUserId]);
  $subs = array_values(array_filter($subs,
    fn(array $s): bool => in_array($topic, push_topics($s), true)));
  if ($eventId) {
    $subs = array_values(array_filter($subs, fn(array $s): bool => may_see_event($s, $eventId)));
  }
  if (!$subs) return;
  register_shutdown_function(function () use ($subs, $topic, $build): void {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    $byLang = [];
    $offenJeNutzer = [];
    $failed = 0;
    foreach ($subs as $sub) {
      $lang = array_key_exists($sub['pref_lang'] ?? '', LANGS) ? $sub['pref_lang'] : 'de';
      $byLang[$lang] ??= $build($lang);
      // Die Zahl am Symbol ist für jeden eine andere — sie reist deshalb je
      // Empfänger mit, nicht je Sprache. Je Mitglied nur einmal ermittelt,
      // auch wenn es mehrere Geräte hat.
      $uid = (int) $sub['user_id'];
      $offenJeNutzer[$uid] ??= open_items_count(['id' => $uid, 'role' => $sub['role'],
                                                 'substitute_for' => $sub['substitute_for']]);
      // Ein neuer Termin steckt bereits in „offen" (er ist unbeantwortet) —
      // ihn zusätzlich als ungelesene Mitteilung zu zählen, ergäbe eine 2 für
      // einen einzigen offenen Punkt. Kommentare und Zusagen dagegen ändern
      // nichts an dem, was zu tun ist, und zählen deshalb obendrauf.
      $inhalt = json_encode($byLang[$lang] + [
        'offen' => $offenJeNutzer[$uid],
        'zaehlt' => $topic !== 'events',
      ], JSON_UNESCAPED_UNICODE);
      if (!push_send_one($sub, $inhalt)) $failed++;
    }
    // Einmal je Vorgang vermerken, nicht je Abo: „warum kam nichts an?" ist
    // sonst nicht zu beantworten, und eine Zeile je Gerät flutet das Log.
    if ($failed) error_log("Bandregie: Push '$topic' — $failed von " . count($subs) . ' nicht zugestellt');
  });
}
