<?php
// Anmeldung über Apple, Google oder Facebook (OAuth 2.0 / OpenID Connect).
//
// Grundsätze (siehe #97):
//   * Jeder Anbieter ist AUS, bis die Bandverwaltung seine Zugangsdaten
//     einträgt — ohne Konfiguration erscheint kein Knopf und nichts ruft hinaus.
//   * Aus einem Login entsteht NIE ein Konto. Entweder ist die Anmeldung schon
//     mit einem Mitglied verknüpft, oder die vom Anbieter BESTÄTIGTE
//     E-Mail-Adresse gehört einem bestehenden Mitglied — sonst Abbruch.
//   * Das E-Mail-Passwort bleibt und funktioniert weiter: fällt ein Anbieter
//     aus, ist niemand ausgesperrt.
//
// Der Zustand (state) reist signiert und mit Ablaufzeit durch den Umweg über
// den Anbieter — bewusst OHNE Session: Apple liefert die Antwort als
// Cross-Site-POST (form_post), und dabei schickt der Browser das
// SameSite-Session-Cookie nicht mit. Eine HMAC-Signatur mit eigenem
// Schlüssel braucht keine Session und ist nicht fälschbar.

/** Anzeige-Reihenfolge und Konfiguration; 'ready' erst mit vollständigen Zugangsdaten. */
function oauth_providers(): array {
  return [
    'apple' => [
      'name' => 'Apple',
      'ready' => setting('oauth_apple_client_id') !== '' && setting('oauth_apple_team_id') !== ''
              && setting('oauth_apple_key_id') !== '' && oauth_secret('oauth_apple_key') !== '',
    ],
    'google' => [
      'name' => 'Google',
      'ready' => setting('oauth_google_client_id') !== '' && oauth_secret('oauth_google_secret') !== '',
    ],
    'facebook' => [
      'name' => 'Facebook',
      'ready' => setting('oauth_facebook_client_id') !== '' && oauth_secret('oauth_facebook_secret') !== '',
    ],
  ];
}

/** Nur die konfigurierten Anbieter — was die Login-Seite und das Profil zeigen. */
function oauth_enabled(): array {
  return array_filter(oauth_providers(), fn(array $p): bool => $p['ready']);
}

/**
 * Ein Geheimnis aus den Einstellungen, entsiegelt falls verschlüsselt abgelegt.
 *
 * Lässt sich ein versiegelter Wert NICHT öffnen (Schlüssel rotiert, beim Umzug
 * vergessen, libsodium fehlt), gilt er als nicht vorhanden. Der Geheimtext
 * zurückzugeben wäre das Gegenteil von sicher: er ginge als „client_secret"
 * hinaus zum Anbieter, und der Anbieter erschiene weiter als eingerichtet,
 * obwohl keine Anmeldung mehr gelingen kann.
 */
function oauth_secret(string $key): string {
  $raw = setting($key);
  if ($raw === '') return '';
  if (function_exists('crypt_looks_sealed') && crypt_looks_sealed($raw)) {
    $open = crypt_open($raw);
    return $open ?? '';
  }
  return $raw;
}

/** Wohin der Anbieter zurückleitet — je Anbieter fest, aus der festen Adresse. */
function oauth_redirect_uri(string $provider): string {
  return absolute_url('/auth/' . $provider . '/callback');
}

// ---------- Signierter Zustand (statt Session, siehe Kopfkommentar) ----------

/**
 * Der Schlüssel, mit dem Zustände signiert werden. Wer ihn kennt, kann jeden
 * Zustand fälschen — auch einen Verknüpfungs-Zustand auf das Administrator-
 * Konto, und hätte damit einen Weg hinein. Er gehört deshalb NICHT im Klartext
 * in die Datenbank, wo ihn jeder Abzug mitnimmt.
 *
 * Erste Wahl ist daher die Ableitung aus dem Schlüssel der Installation
 * (app/config.php, liegt außerhalb der Datenbank). Fehlt der, bleibt ein
 * versiegelt abgelegter Zufallswert — versiegeln kann die Anwendung ohne
 * data_key allerdings auch nicht, dann ist es ein Klartextwert wie bisher.
 * Das ist der ehrliche Kompromiss: ohne Schlüssel gibt es kein Geheimnis,
 * das ein Datenbankabzug nicht mitnimmt.
 */
function oauth_state_secret(): string {
  $config = @require BASE_DIR . '/app/config.php';
  $dataKey = is_array($config) ? trim((string) ($config['data_key'] ?? '')) : '';
  if ($dataKey !== '') return hash_hmac('sha256', 'oauth-state', $dataKey);
  $s = oauth_secret('oauth_state_secret');
  if ($s === '') {
    $s = bin2hex(random_bytes(32));
    set_setting('oauth_state_secret', function_exists('crypt_available') && crypt_available()
      ? (string) crypt_seal($s) : $s);
  }
  return $s;
}

function oauth_b64(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function oauth_b64_decode(string $s): string {
  return (string) base64_decode(strtr($s, '-_', '+/'));
}

/**
 * Der Browser, der den Weg begonnen hat, bekommt eine kurzlebige Kennung als
 * Cookie; ihr Abdruck reist im Zustand mit. Erst beide zusammen gelten.
 *
 * Ohne diese Bindung wäre eine abgefangene Rückweg-Adresse in JEDEM Browser
 * gültig: wer seinen eigenen Anmeldeweg beginnt und dem Opfer die Rückweg-
 * Adresse unterschiebt, meldet es im eigenen Konto an (Login-CSRF) — und
 * bekommt zu sehen, was das Opfer dort dann tut.
 *
 * SameSite=None ist hier nötig und kein Versehen: Apples Antwort kommt als
 * Cross-Site-POST, ein Lax-Cookie reiste dabei nicht mit. Das Cookie taugt
 * für nichts anderes — es ist ein Zufallswert mit zehn Minuten Laufzeit.
 */
// Der Name trägt bewusst __Host-: dieses Präfix verbietet dem Browser ein
// Domain-Attribut, also kann keine Nachbar-Subdomain ein gleichnamiges Cookie
// unterschieben und die Bindung damit auf einen fremden Wert setzen.
const OAUTH_BIND_COOKIE = '__Host-oauth_bind';

function oauth_bind_cookie(): string {
  $nonce = bin2hex(random_bytes(16));
  setcookie(OAUTH_BIND_COOKIE, $nonce, [
    'expires' => time() + 600, 'path' => '/', 'secure' => true,
    'httponly' => true, 'samesite' => 'None',
  ]);
  // Wo eine Sitzung läuft (Verknüpfen), zählt sie als zweiter Zeuge.
  if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['oauth_bind'] = $nonce;
  return $nonce;
}

/** $mode 'login' oder 'link'; bei 'link' trägt der Zustand das Mitglied. */
function oauth_state_make(string $provider, string $mode, int $uid = 0): string {
  $body = oauth_b64(json_encode(['p' => $provider, 'm' => $mode, 'u' => $uid,
                                 't' => time(), 'b' => hash('sha256', oauth_bind_cookie())]));
  return $body . '.' . oauth_b64(hash_hmac('sha256', $body, oauth_state_secret(), true));
}

/**
 * Gültig nur mit intakter Signatur, passendem Anbieter, binnen 10 Minuten und
 * im selben Browser (Abdruck des Bindungs-Cookies).
 */
function oauth_state_check(string $state, string $provider): ?array {
  $parts = explode('.', $state);
  if (count($parts) !== 2) return null;
  $want = oauth_b64(hash_hmac('sha256', $parts[0], oauth_state_secret(), true));
  if (!hash_equals($want, $parts[1])) return null;
  $data = json_decode(oauth_b64_decode($parts[0]), true);
  if (!is_array($data) || ($data['p'] ?? '') !== $provider) return null;
  if (time() - (int) ($data['t'] ?? 0) > 600) return null;
  $bind = (string) ($_COOKIE[OAUTH_BIND_COOKIE] ?? '');
  if ($bind === '' || !hash_equals((string) ($data['b'] ?? ''), hash('sha256', $bind))) return null;
  // Lief eine Sitzung, muss sie denselben Wert bezeugen — ein untergeschobenes
  // Cookie allein genügt dann nicht.
  if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['oauth_bind'])
      && !hash_equals((string) $_SESSION['oauth_bind'], $bind)) return null;
  $mode = (string) ($data['m'] ?? '');
  if (!in_array($mode, ['login', 'link'], true)) return null;
  return ['mode' => $mode, 'uid' => (int) ($data['u'] ?? 0)];
}

/** Nach dem Rückweg hat die Bindung ihren Zweck erfüllt. */
function oauth_bind_clear(): void {
  setcookie(OAUTH_BIND_COOKIE, '', ['expires' => time() - 3600, 'path' => '/',
    'secure' => true, 'httponly' => true, 'samesite' => 'None']);
  unset($_SESSION['oauth_bind']);
}

// ---------- Der Weg zum Anbieter ----------

/** Die Adresse, zu der der Login-Knopf schickt. Leer, wenn nicht konfiguriert. */
function oauth_authorize_url(string $provider, string $state): string {
  $redirect = oauth_redirect_uri($provider);
  switch ($provider) {
    case 'google':
      return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'response_type' => 'code', 'client_id' => setting('oauth_google_client_id'),
        'redirect_uri' => $redirect, 'scope' => 'openid email', 'state' => $state,
      ]);
    case 'apple':
      // response_mode=form_post ist bei Apple Pflicht, sobald scope gesetzt ist —
      // die Antwort kommt dann als POST (siehe CSRF-Ausnahme im Frontcontroller).
      return 'https://appleid.apple.com/auth/authorize?' . http_build_query([
        'response_type' => 'code', 'client_id' => setting('oauth_apple_client_id'),
        'redirect_uri' => $redirect, 'scope' => 'email', 'response_mode' => 'form_post',
        'state' => $state,
      ]);
    case 'facebook':
      return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
        'response_type' => 'code', 'client_id' => setting('oauth_facebook_client_id'),
        'redirect_uri' => $redirect, 'scope' => 'email', 'state' => $state,
      ]);
  }
  return '';
}

// ---------- Serverseitige Abrufe ----------

/** GET (ohne $post) oder Formular-POST; JSON-Antwort als Array, sonst null. */
function oauth_http(string $url, ?array $post = null): ?array {
  $ctx = stream_context_create(['http' => [
    'method' => $post === null ? 'GET' : 'POST',
    'header' => "Accept: application/json\r\n"
      . ($post !== null ? "Content-Type: application/x-www-form-urlencoded\r\n" : ''),
    'content' => $post !== null ? http_build_query($post) : '',
    'timeout' => 10,
    // Fehlerantworten mitlesen: der Grund einer Ablehnung steht im JSON-Körper.
    'ignore_errors' => true,
    // Keine Weiterleitungen: die ganze Sicherheit dieses Wegs beruht darauf,
    // dass die Antwort wirklich vom Token-Endpunkt des Anbieters kommt (nur
    // deshalb darf die Signatur des id_token ungeprüft bleiben). Eine
    // Weiterleitung würde diese Gewissheit aus der Hand geben.
    'follow_location' => 0,
    'max_redirects' => 0,
  ], 'ssl' => [
    // Ausdrücklich, statt auf Voreinstellungen zu vertrauen: fällt die Prüfung
    // des Zertifikats weg, fällt die Grundlage der Annahme oben mit.
    'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
  ]]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

/**
 * Die Nutzdaten eines ID-Tokens — OHNE Signaturprüfung, und das mit Absicht:
 * das Token kommt hier direkt aus der TLS-Antwort des Token-Endpunkts des
 * Anbieters, nicht über den Browser. Die Verbindung bürgt für die Herkunft;
 * geprüft werden trotzdem Aussteller, Empfänger und Ablauf.
 */
function oauth_id_token_claims(string $jwt, string $issuerHost, string $clientId): ?array {
  $parts = explode('.', $jwt);
  if (count($parts) !== 3) return null;
  $claims = json_decode(oauth_b64_decode($parts[1]), true);
  if (!is_array($claims)) return null;
  $iss = (string) ($claims['iss'] ?? '');
  if ($iss !== 'https://' . $issuerHost && $iss !== $issuerHost) return null;
  $aud = $claims['aud'] ?? '';
  if ((is_array($aud) ? !in_array($clientId, $aud, true) : $aud !== $clientId)) return null;
  if ((int) ($claims['exp'] ?? 0) < time()) return null;
  return $claims;
}

/**
 * Apples "Client-Secret" ist keines zum Eintragen, sondern ein kurzlebiges
 * ES256-JWT, signiert mit dem .p8-Schlüssel aus dem Developer-Konto — hier
 * je Anfrage frisch gebaut, damit nie ein abgelaufenes herumliegt.
 */
function apple_client_secret(): string {
  $pem = oauth_secret('oauth_apple_key');
  // Nur echtes Schlüsselmaterial, keine Verweise: openssl_pkey_get_private()
  // löst auch "file://…" auf und würde dann mit einer beliebigen Datei des
  // Servers signieren, ohne dass je ein Schlüssel eingetragen worden wäre.
  if (!str_starts_with(ltrim($pem), '-----BEGIN')) return '';
  $key = openssl_pkey_get_private($pem);
  if ($key === false) return '';
  $header = oauth_b64(json_encode(['alg' => 'ES256', 'kid' => setting('oauth_apple_key_id')]));
  $claims = oauth_b64(json_encode([
    'iss' => setting('oauth_apple_team_id'), 'iat' => time(), 'exp' => time() + 3600,
    'aud' => 'https://appleid.apple.com', 'sub' => setting('oauth_apple_client_id'),
  ]));
  if (!openssl_sign($header . '.' . $claims, $der, $key, OPENSSL_ALGO_SHA256)) return '';
  $sig = oauth_ecdsa_der_to_raw($der);
  return $sig === '' ? '' : $header . '.' . $claims . '.' . oauth_b64($sig);
}

/**
 * OpenSSL liefert ECDSA-Signaturen DER-kodiert; ein JWT verlangt die rohen
 * r‖s-Werte mit je 32 Bytes. Hier wird ausgepackt, entnullt und aufgefüllt.
 */
function oauth_ecdsa_der_to_raw(string $der): string {
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

// ---------- Der Rückweg: Code → wer ist das? ----------

/**
 * Tauscht den Code beim Anbieter ein und liefert die Identität:
 * ['subject' => ..., 'email' => ...] — die E-Mail nur, wenn der Anbieter sie
 * als bestätigt ausweist. Bei Fehlern ein t()-Schlüssel als Zeichenkette.
 */
function oauth_identity(string $provider, string $code) {
  $redirect = oauth_redirect_uri($provider);
  switch ($provider) {
    case 'google': {
      $tok = oauth_http('https://oauth2.googleapis.com/token', [
        'grant_type' => 'authorization_code', 'code' => $code,
        'client_id' => setting('oauth_google_client_id'),
        'client_secret' => oauth_secret('oauth_google_secret'),
        'redirect_uri' => $redirect,
      ]);
      $claims = oauth_id_token_claims((string) ($tok['id_token'] ?? ''),
        'accounts.google.com', setting('oauth_google_client_id'));
      if (!$claims || ($claims['sub'] ?? '') === '') return 'fl_oauth_failed';
      if (empty($claims['email']) || !filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
        return 'fl_oauth_no_email';
      }
      return ['subject' => (string) $claims['sub'], 'email' => strtolower((string) $claims['email'])];
    }
    case 'apple': {
      $secret = apple_client_secret();
      if ($secret === '') return 'fl_oauth_failed';
      $tok = oauth_http('https://appleid.apple.com/auth/token', [
        'grant_type' => 'authorization_code', 'code' => $code,
        'client_id' => setting('oauth_apple_client_id'), 'client_secret' => $secret,
        'redirect_uri' => $redirect,
      ]);
      $claims = oauth_id_token_claims((string) ($tok['id_token'] ?? ''),
        'appleid.apple.com', setting('oauth_apple_client_id'));
      if (!$claims || ($claims['sub'] ?? '') === '') return 'fl_oauth_failed';
      // Apple schickt email_verified je nach Laune als true oder "true"; die
      // Relay-Adresse (privaterelay.appleid.com) ist eine bestätigte Adresse.
      if (empty($claims['email']) || !filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
        return 'fl_oauth_no_email';
      }
      return ['subject' => (string) $claims['sub'], 'email' => strtolower((string) $claims['email'])];
    }
    case 'facebook': {
      $secret = oauth_secret('oauth_facebook_secret');
      $tok = oauth_http('https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
        'client_id' => setting('oauth_facebook_client_id'), 'client_secret' => $secret,
        'redirect_uri' => $redirect, 'code' => $code,
      ]));
      $access = (string) ($tok['access_token'] ?? '');
      if ($access === '') return 'fl_oauth_failed';
      // appsecret_proof: Graph akzeptiert das Token dann nur aus unserer Hand.
      $me = oauth_http('https://graph.facebook.com/v19.0/me?' . http_build_query([
        'fields' => 'id,email', 'access_token' => $access,
        'appsecret_proof' => hash_hmac('sha256', $access, $secret),
      ]));
      if (!$me || ($me['id'] ?? '') === '') return 'fl_oauth_failed';
      // Facebook liefert nur bestätigte Adressen aus — aber je nach App-Review
      // auch gar keine. Ohne Adresse keine Zuordnung.
      if (empty($me['email'])) return 'fl_oauth_no_email';
      return ['subject' => (string) $me['id'], 'email' => strtolower((string) $me['email'])];
    }
  }
  return 'fl_oauth_failed';
}
