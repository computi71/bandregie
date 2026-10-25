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
  // cURL steht mit in der Liste, seit der Versand darueber laeuft (#374).
  // Fehlt es, soll die Anwendung sagen, dass sie keine Mitteilungen kann -
  // und nicht erst beim ersten Versand abstuerzen.
  return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf')
    && function_exists('curl_init')
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

/**
 * Wie lange ein Abo ohne Lebenszeichen überlebt.
 *
 * Ein Gerät, auf dem jemand die Mitteilungen abschaltet, meldet sich nicht ab —
 * und der Zustelldienst nimmt Nachrichten dorthin noch monatelang mit „201
 * angenommen" entgegen und verwirft sie still. Am Fehlercode ist so ein Abo
 * also nicht zu erkennen. Woran dann? Daran, dass sich das Gerät nicht mehr
 * meldet: Wer die Anwendung benutzt und ein Abo hat, sagt das beim Öffnen.
 *
 * Neunzig Tage, damit ein Sommer ohne Proben kein Abo kostet.
 */
const PUSH_MAX_STILL_DAYS = 90;

/**
 * Abos wegräumen, von denen lange nichts mehr zu hören war.
 *
 * Läuft nebenbei mit den übrigen fälligen Arbeiten und höchstens einmal am Tag;
 * dafür ist die Aufgabe zu klein, um einen eigenen Zeitgeber zu verdienen.
 *
 * @return int wie viele weg sind
 */
function push_prune(): int {
  if (setting('push_pruned_on') === date('Y-m-d')) return 0;
  set_setting('push_pruned_on', date('Y-m-d'));
  $st = q('DELETE FROM push_subscriptions
           WHERE COALESCE(last_seen_at, created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)',
          [PUSH_MAX_STILL_DAYS]);
  $weg = $st->rowCount();
  if ($weg > 0) error_log("Bandregie: $weg verwaiste Push-Abos entfernt");
  return $weg;
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
/**
 * Eine Mitteilung an ein Gerät.
 *
 * Erwartet die Zeile, die push_notify() zusammenstellt: **sub_id** ist das Abo,
 * **id** das Mitglied. Genau diese Unterscheidung ging hier zweimal verloren —
 * beide DELETE löschten „WHERE id", also das Abo mit der Nummer des MITGLIEDS.
 * Bei Mitgliedern 2–7 und Abos 2–4 trifft das fast immer ein fremdes Gerät:
 * Wer einmal abgelehnt wurde, nahm jemand anderem die Mitteilungen weg (#236).
 */
/**
 * Zeitgrenzen je Abo (#374).
 *
 * Klein gehalten, weil ein Lauf sie mit der Zahl der Abos multipliziert. Ein
 * Dienst, der in acht Sekunden nicht antwortet, antwortet auch in sechzig
 * nicht; die Mitteilung ist dann beim naechsten Mal wieder dran.
 */
const PUSH_CONNECT_TIMEOUT = 4;
const PUSH_TOTAL_TIMEOUT = 8;

function push_send_one(array $sub, string $json): bool {
  $aboId = (int) $sub['sub_id'];
  // Auch gespeicherte Abos werden hier noch geprüft: was in der Datenbank
  // steht, ist damit nicht automatisch ein erlaubtes Ziel.
  if (!push_endpoint_ok((string) $sub['endpoint'])) {
    q('DELETE FROM push_subscriptions WHERE id = ?', [$aboId]);
    return false;
  }
  $body = push_encrypt($json, $sub['p256dh'], $sub['auth']);
  $vapid = push_vapid_auth($sub['endpoint']);
  if ($body === null || $vapid === '') return false;
  // Ueber cURL statt ueber einen Stream-Kontext (#374): Dort ist 'timeout'
  // die LESE-Zeitgrenze; der Verbindungsaufbau faellt auf
  // default_socket_timeout zurueck, auf dem Produktionsserver sechzig
  // Sekunden. Sieben Abos mal sechzig, und ein Arbeiter haengt minutenlang.
  // CURLOPT_TIMEOUT deckt dagegen den ganzen Vorgang.
  $ch = curl_init((string) $sub['endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: ' . $vapid,
      'Content-Encoding: aes128gcm',
      'Content-Type: application/octet-stream',
      'TTL: 86400',
      'Urgency: normal',
    ],
    CURLOPT_CONNECTTIMEOUT => PUSH_CONNECT_TIMEOUT,
    CURLOPT_TIMEOUT => PUSH_TOTAL_TIMEOUT,
    // Ein Push-Dienst hat keinen Grund umzuleiten; wohin, entscheidet sonst er.
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_USERAGENT => 'Bandregie/' . BANDREGIE_VERSION,
  ]);
  curl_exec($ch);
  $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  // Zeitueberschreitung meldet Status 0. Das ist kein Grund, ein Abo
  // wegzuwerfen - der Dienst war nur gerade nicht da.
  if ($status === 0) return false;
  // Dauerhafte Ablehnungen wegräumen, nicht nur „weg" (404/410): ein Abo, das
  // der Dienst nicht mehr annimmt (falscher Schlüssel, ungültiger Pfad, zu
  // groß), würde sonst bei jeder Mitteilung erneut angefragt — und jede Anfrage
  // kostet bis zu PUSH_TOTAL_TIMEOUT Sekunden Wartezeit.
  if (in_array($status, [400, 401, 403, 404, 410, 413], true)) {
    q('DELETE FROM push_subscriptions WHERE id = ?', [$aboId]);
    return false;
  }
  // Eine gelungene Zustellung ist der Beweis, dass das Abo lebt. Ohne diese
  // Zeile zählte das Aufräumen „Tage ohne Anmeldung" und warf nach 90 Tagen ein
  // Abo weg, das jede Nachricht brav ausgeliefert hat (#262).
  if ($status >= 200 && $status < 300) {
    q('UPDATE push_subscriptions SET last_seen_at = NOW() WHERE id = ?', [$aboId]);
    return true;
  }
  return false;
}

/**
 * Ein UI-Text in der Sprache des Empfängers — t() kennt nur die Sitzung.
 *
 * Je Sprache eine Abfrage, dann aus dem Speicher — genau wie t(). Vorher stand
 * hier eine Abfrage je Aufruf, was stimmte, solange nur Mails so übersetzt
 * wurden. Seit fmt_date() darüber läuft, ist das eine Abfrage je Datum in
 * jeder Liste: eine Seite mit dreihundert Terminen kostete dreihundert Mal
 * dieselbe Zeile. Auf Deutsch fiel es nie auf, weil der Zweig darüber
 * abkürzt — und Deutsch ist die Sprache, in der geprüft wird.
 */
function push_t(string $lang, string $key): string {
  static $cache = [];
  if ($lang !== 'de') {
    if (!isset($cache[$lang])) {
      $cache[$lang] = [];
      foreach (rows('SELECT tkey, value FROM translations WHERE lang = ?', [$lang]) as $r) {
        $cache[$lang][$r['tkey']] = $r['value'];
      }
    }
    // Vorhanden-und-leer gilt, wie bei t() (#351).
    if (array_key_exists($key, $cache[$lang])) return $cache[$lang][$key];
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
 * $topicId tut dasselbe für ein Chatthema (#316). Ein Konto von außen sieht nur
 * seine eigenen Themen und die, zu denen die Band es geholt hat; Titel und
 * Anriss eines fremden Themas dürfen es auch auf dem Handy nicht erreichen.
 *
 * Gesendet wird erst NACH der Antwort (fastcgi_finish_request): niemand wartet
 * beim Speichern eines Kommentars auf die Push-Dienste von Google und Apple.
 */
// ---------- Warteschlange für Mitteilungen (#383) ----------
//
// push_notify() gab die Seite frei und hielt den Arbeiter trotzdem fest:
// fastcgi_finish_request() beendet die Antwort, nicht den Prozess. Jeder
// Versand ist ein blockierender HTTPS-Aufruf je Gerät, einer nach dem
// anderen, und bei pm.max_children = 5 sind fünf davon der ganze Pool. Genau
// daran stand die Seite am 20.10.2026 siebzehn Minuten (#374).
//
// Jetzt landet die Mitteilung in einer Tabelle, und bin/push-senden.php holt
// sie ab. Der Webarbeiter ist sofort frei.

/** Wie oft ein Versand wiederholt wird, bevor die Zeile aufgibt. */
const PUSH_QUEUE_TRIES = 4;

/**
 * Wie lange eine Mitteilung noch etwas wert ist, in Stunden.
 *
 * Eine Erinnerung an die Probe von gestern gehört verworfen und nicht
 * verspätet zugestellt — sie wäre nicht nur nutzlos, sondern irreführend.
 */
const PUSH_QUEUE_TTL_HOURS = 12;

/** Wie lange gleichartige Mitteilungen gesammelt werden, in Sekunden. */
const PUSH_QUEUE_COLLAPSE = 300;

/**
 * Eine Mitteilung einreihen (#383).
 *
 * Gerendert wird HIER, in der Sprache des Empfängers: Zu diesem Zeitpunkt ist
 * bekannt, wer sie bekommt und welche Sprache der eingestellt hat. Der
 * Abholer bleibt damit dumm — er muss nichts über Termine, Themen oder
 * Sprachen wissen, nur verschicken.
 *
 * Eine Zeile je EMPFÄNGER und nicht je Gerät: Wer zwischen Einreihen und
 * Abholen ein zweites Telefon anmeldet, soll sie dort auch bekommen.
 *
 * @param array<int, array>       $empfaenger Zeilen aus users
 * @param callable(string): array $build      Sprache => ['title','body','url']
 * @param string                  $sammeln    Sammelschlüssel; leer heißt: sofort und einzeln
 * @param null|callable(int, string): array $buildViele Sprache + Anzahl, für die gesammelte Fassung
 */
function push_queue_add(string $topic, array $empfaenger, callable $build,
                        string $sammeln = '', ?callable $buildViele = null): void {
  foreach ($empfaenger as $u) {
    $uid = (int) ($u['id'] ?? 0);
    if (!$uid) continue;
    // Wer das Thema abgewählt hat, kommt gar nicht erst in die Schlange —
    // sonst füllt sie sich mit Zeilen, die beim Abholen verworfen werden.
    if (!in_array($topic, push_topics($u), true)) continue;
    $lang = array_key_exists($u['pref_lang'] ?? '', LANGS) ? (string) $u['pref_lang'] : 'de';

    // Liegt schon eine gleichartige Zeile und ist sie noch nicht los, wird
    // daraus eine gesammelte: „3 neue Antworten zu X" statt dreimal klingeln.
    if ($sammeln !== '' && $buildViele !== null) {
      $alt = row('SELECT id, collapse_count FROM push_queue
                  WHERE user_id = ? AND collapse_key = ? AND send_after > NOW()',
                 [$uid, $sammeln]);
      if ($alt) {
        $n = (int) $alt['collapse_count'] + 1;
        q('UPDATE push_queue SET collapse_count = ?, payload = ? WHERE id = ?',
          [$n, (string) json_encode($buildViele($n, $lang), JSON_UNESCAPED_UNICODE), (int) $alt['id']]);
        continue;
      }
    }
    // Die erste sofort, erst die folgenden sammeln (#404). Das Sammeln kostet
    // sonst JEDE Antwort fuenf Minuten, auch die einzelne, auf die nie eine
    // zweite folgt - und das ist der haeufige Fall. Ob gerade erst etwas
    // hinausging, sagt das Versandprotokoll (#405); dafuer braucht es keine
    // eigene Spalte.
    $wann = ($sammeln !== '' && push_recently_sent($sammeln, PUSH_QUEUE_COLLAPSE))
      ? PUSH_QUEUE_COLLAPSE : 0;
    q('INSERT INTO push_queue (user_id, topic, payload, collapse_key, collapse_count, send_after)
       VALUES (?,?,?,?,1, DATE_ADD(NOW(), INTERVAL ? SECOND))',
      [$uid, $topic, (string) json_encode($build($lang), JSON_UNESCAPED_UNICODE), $sammeln, $wann]);
  }
}

/** Wie lange das Versandprotokoll aufgehoben wird, in Tagen. */
const PUSH_LOG_DAYS = 30;

/**
 * Eine Zeile ins Versandprotokoll (#405).
 *
 * Ohne Wortlaut: Ein Chat-Anriss im Protokoll wäre eine Abschrift von
 * Gesprächen, um die niemand gebeten hat.
 */
function push_log(array $zeile, string $ausgang, int $geraete = 0, int $angenommen = 0): void {
  q('INSERT INTO push_log (user_id, topic, collapse_key, collapse_count, devices, accepted, outcome)
     VALUES (?,?,?,?,?,?,?)',
    [(int) $zeile['user_id'], (string) $zeile['topic'], (string) $zeile['collapse_key'],
     (int) $zeile['collapse_count'], $geraete, $angenommen, $ausgang]);
}

/**
 * Ist zu diesem Sammelschlüssel gerade erst etwas hinausgegangen? (#404)
 *
 * Die eine Tatsache, die fehlte, um die erste Mitteilung sofort zu schicken
 * und erst die folgenden zu sammeln. Sie steht jetzt im Protokoll, und
 * deshalb braucht es dafür keine eigene Spalte.
 */
function push_recently_sent(string $sammeln, int $sekunden): bool {
  if ($sammeln === '') return false;
  return (bool) row("SELECT 1 FROM push_log
                     WHERE collapse_key = ? AND outcome = 'gesendet'
                       AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                    [$sammeln, $sekunden]);
}

/**
 * Die Schlange abarbeiten. Ruft bin/push-senden.php, nicht der Webarbeiter.
 *
 * @return array{gesendet: int, verworfen: int, spaeter: int}
 */
function push_queue_run(int $hoechstens = 200): array {
  $gesendet = 0;
  $verworfen = 0;
  $spaeter = 0;

  // Was zu alt ist, geht ungesehen weg — vor allem anderen, damit eine
  // abgelaufene Zeile nicht noch einen Versuch kostet.
  $alt = rows('SELECT id FROM push_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)',
              [PUSH_QUEUE_TTL_HOURS]);
  if ($alt) {
    foreach (rows('SELECT * FROM push_queue WHERE id IN ('
                  . implode(',', array_column($alt, 'id')) . ')') as $z) {
      push_log($z, 'abgelaufen');
    }
    q('DELETE FROM push_queue WHERE id IN (' . implode(',', array_column($alt, 'id')) . ')');
    $verworfen += count($alt);
  }
  // Das Protokoll ist eine Auskunft und kein Archiv: Nach dreissig Tagen ist
  // „hat es am Dienstag geklappt?“ beantwortet, und laenger aufzuheben, wer
  // wann worueber benachrichtigt wurde, hat niemand verlangt.
  q('DELETE FROM push_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [PUSH_LOG_DAYS]);

  $faellig = rows('SELECT * FROM push_queue
                   WHERE send_after <= NOW() AND attempts < ?
                   ORDER BY id LIMIT ' . (int) $hoechstens, [PUSH_QUEUE_TRIES]);
  foreach ($faellig as $zeile) {
    $nr = (int) $zeile['id'];
    $subs = rows("SELECT s.id AS sub_id, s.endpoint, s.p256dh, s.auth,
                         u.id, u.role, u.substitute_for, u.pref_lang, u.push_topics
                  FROM push_subscriptions s JOIN users u ON u.id = s.user_id
                  WHERE s.user_id = ?", [(int) $zeile['user_id']]);
    // Kein Gerät (mehr), oder das Thema inzwischen abgewählt: nichts zu tun.
    $subs = array_values(array_filter($subs,
      static fn(array $s): bool => in_array((string) $zeile['topic'], push_topics($s), true)));
    if (!$subs) {
      push_log($zeile, 'kein-geraet');
      q('DELETE FROM push_queue WHERE id = ?', [$nr]);
      $verworfen++;
      continue;
    }
    // Die Zahl am Symbol entsteht JETZT und nicht beim Einreihen: Zwischen
    // beidem kann eine Minute liegen, und in der Minute hakt jemand etwas ab.
    // Eine Mitteilung, die eine veraltete Zahl mitbringt, setzt sie am Symbol
    // auch wieder auf den alten Stand.
    $erster = $subs[0];
    $inhalt = (array) json_decode((string) $zeile['payload'], true);
    $inhalt['offen'] = open_items_count(['id' => (int) $zeile['user_id'],
                                         'role' => $erster['role'],
                                         'substitute_for' => $erster['substitute_for']]);
    // Ein neuer Termin steckt schon in „offen“ (er ist unbeantwortet) — ihn
    // zusaetzlich als ungelesene Mitteilung zu zaehlen ergaebe eine 2 fuer
    // einen einzigen offenen Punkt. Kommentare und Zusagen aendern nichts an
    // dem, was zu tun ist, und zaehlen deshalb obendrauf.
    $inhalt['zaehlt'] = (string) $zeile['topic'] !== 'events';
    $fertig = (string) json_encode($inhalt, JSON_UNESCAPED_UNICODE);

    $ok = 0;
    foreach ($subs as $sub) {
      if (push_send_one($sub, $fertig)) $ok++;
    }
    if ($ok) {
      push_log($zeile, 'gesendet', count($subs), $ok);
      q('DELETE FROM push_queue WHERE id = ?', [$nr]);
      $gesendet++;
      continue;
    }
    // Nicht angekommen. Der Dienst war vielleicht nur gerade weg — also
    // später noch einmal, mit wachsendem Abstand, und irgendwann gar nicht
    // mehr. Ein totes Gerät hat push_send_one() schon selbst weggeräumt.
    $versuche = (int) $zeile['attempts'] + 1;
    if ($versuche >= PUSH_QUEUE_TRIES) {
      push_log($zeile, 'aufgegeben', count($subs), 0);
      q('DELETE FROM push_queue WHERE id = ?', [$nr]);
      $verworfen++;
      continue;
    }
    q('UPDATE push_queue SET attempts = ?, send_after = DATE_ADD(NOW(), INTERVAL ? MINUTE)
       WHERE id = ?', [$versuche, 5 * $versuche, $nr]);
    $spaeter++;
  }
  return ['gesendet' => $gesendet, 'verworfen' => $verworfen, 'spaeter' => $spaeter];
}

/**
 * Eine Mitteilung an genau eine Person (#382).
 *
 * push_notify() ist eine Rundsendung: alle Abonnenten eines Themas, minus
 * einen. Eine Antwort auf die eigene Frage hat eine andere Gestalt und braucht
 * deshalb einen eigenen Weg — nicht „alle außer dir", sondern „nur du".
 *
 * Das Thema wird trotzdem geachtet: Wer es abgewählt hat, bekommt nichts. Eine
 * Mitteilung, die sich nicht abstellen lässt, ist genau der Grund, warum Leute
 * irgendwann alle abstellen.
 *
 * @param callable(string): array $build Sprache => ['title','body','url']
 */
function push_to_user(int $userId, string $topic, callable $build): void {
  if (!push_available()) return;
  $u = row('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id = ?', [$userId]);
  if (!$u) return;
  // Die Warteschlange prueft das Thema noch einmal; hier steht es, damit eine
  // Zeile gar nicht erst entsteht.
  if (!in_array($topic, push_topics($u), true)) return;
  push_queue_add($topic, [$u], $build);
}

function push_notify(string $topic, int $exceptUserId, callable $build, int $eventId = 0,
                     int $topicId = 0, string $sammeln = '', ?callable $buildViele = null): void {
  if (!push_available()) return;
  // Empfaenger sind MITGLIEDER und nicht Geraete (#383): Die Warteschlange
  // haelt eine Zeile je Person, und welche Geraete die hat, entscheidet sich
  // beim Abholen. Wer zwischendurch ein zweites Telefon anmeldet, bekommt sie
  // dort auch.
  //
  // Nur, wer ueberhaupt ein Geraet angemeldet hat: Eine Zeile fuer jemanden
  // ohne Telefon waere eine, die der Abholer nur wegwirft.
  $leute = rows("SELECT DISTINCT u.id, u.role, u.substitute_for, u.pref_lang, u.push_topics
                 FROM push_subscriptions s JOIN users u ON u.id = s.user_id
                 WHERE u.id <> ?", [$exceptUserId]);
  // Die Themen werden hier gefiltert, nicht in SQL: „nichts eingestellt“ heisst
  // alle Themen, und das laesst sich mit FIND_IN_SET nicht ausdruecken.
  $leute = array_values(array_filter($leute,
    fn(array $u): bool => in_array($topic, push_topics($u), true)));
  if ($eventId) {
    $leute = array_values(array_filter($leute, fn(array $u): bool => may_see_event($u, $eventId)));
  }
  if ($topicId) {
    $leute = array_values(array_filter($leute, fn(array $u): bool => may_see_topic($u, $topicId)));
  }
  if (!$leute) return;
  push_queue_add($topic, $leute, $build, $sammeln, $buildViele);
}
