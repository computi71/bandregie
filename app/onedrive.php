<?php
declare(strict_types=1);

/**
 * Verbindung zu OneDrive über Microsoft Graph (#20).
 *
 * Der Zweck ist nicht, Dateien zu kopieren, sondern auf sie zu zeigen: Die
 * Fotos einer Band liegen meist längst irgendwo, und sie ein zweites Mal in
 * diese Anwendung zu laden verdoppelt nur den Platz und die Frage, welche
 * Fassung die richtige ist. Diese Datei baut allein die Verbindung; das
 * Verknüpfen von Ordnern kommt darauf.
 *
 * Ohne Bibliothek und ohne SDK — wie beim Push und beim Passkey. OAuth 2.0 ist
 * ein Weiterleiten mit anschließendem Tausch, und Graph antwortet auf HTTP mit
 * JSON. Das Microsoft-SDK brächte Composer mit, und das verspricht das Projekt
 * nicht.
 *
 * Nichts davon läuft von selbst: Ohne eingetragene Anwendungskennung ist die
 * Verbindung nicht einmal einschaltbar, und eingeschaltet wird sie unter den
 * Verbindungen nach außen — dort, wo alles steht, was diese Anwendung nach
 * draußen tut.
 */

/**
 * Was wir dürfen wollen, und nicht mehr.
 *
 * offline_access ist der Punkt, an dem eine Verbindung von einer Anmeldung zu
 * einer Verbindung wird: Ohne dieses Recht gibt es kein Erneuerungszeichen, und
 * die Verbindung wäre nach einer Stunde wieder tot.
 *
 * Files.Read.All statt ReadWrite: Verknüpfen heißt lesen. Schreiben braucht
 * erst das Sicherungsziel (#50), und ein Recht, das niemand nutzt, ist eines,
 * das im Schadensfall trotzdem gilt.
 */
const OD_SCOPES = 'offline_access User.Read Files.Read.All';

/** Wie lange vor dem Ablauf schon erneuert wird. Eine Minute Vorlauf genügt. */
const OD_REFRESH_MARGIN = 60;

/**
 * Der Mandant. „common" lässt sowohl geschäftliche als auch private
 * Microsoft-Konten herein — und ein Bandkonto ist meistens ein privates.
 * Wer eine eigene Organisation hat, trägt deren Kennung ein und schließt damit
 * alle anderen aus.
 */
function od_tenant(): string {
  $t = trim(setting('onedrive_tenant', 'common'));
  return preg_match('~^[A-Za-z0-9.\-]{1,80}$~', $t) ? $t : 'common';
}

function od_client_id(): string {
  return trim(setting('onedrive_client_id'));
}

/** Das Geheimnis liegt versiegelt, wenn ein Schlüssel gesetzt ist. */
function od_client_secret(): string {
  return crypt_reveal(setting('onedrive_client_secret'));
}

/** Ist überhaupt eine Anwendung eingetragen? Ohne die geht nichts. */
function od_configured(): bool {
  return od_client_id() !== '' && od_client_secret() !== '';
}

/** Darf diese Installation zu Microsoft sprechen? */
function od_enabled(): bool {
  return setting('onedrive_enabled') === '1' && od_configured();
}

/**
 * Die Adresse, die bei Microsoft als Rückleitung eingetragen sein muss.
 *
 * Sie kommt aus site_url und nicht aus dem Host der Anfrage: Microsoft
 * vergleicht sie zeichengenau mit dem, was in der Anwendungsregistrierung
 * steht. Käme sie aus dem Host, hieße dieselbe Installation je nach Aufruf
 * einmal mit und einmal ohne www — und der Tausch scheiterte mit einer Meldung,
 * die das nicht verrät.
 */
function od_redirect_uri(): string {
  return absolute_url('/intern/einstellungen/onedrive/zurueck');
}

/**
 * Der Weg hin: Adresse, auf die der Browser geschickt wird.
 *
 * PKCE, obwohl wir ein Geheimnis haben und es nicht müssten. Der Code steht
 * nach der Rückleitung in der Adresszeile, im Verlauf und womöglich im Log
 * eines Proxys; ohne Prüfsumme genügte er zusammen mit einer abgefangenen
 * Rückleitung. Mit PKCE ist er allein wertlos.
 *
 * Der Zustandswert liegt in der Sitzung und wird beim Zurückkommen verglichen —
 * sonst könnte jemand eine fremde Rückleitung in einen angemeldeten Browser
 * schicken und dessen Konto mit seinem OneDrive verbinden.
 */
function od_auth_url(): string {
  $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
  $state = bin2hex(random_bytes(16));
  $_SESSION['od_verifier'] = $verifier;
  $_SESSION['od_state'] = $state;
  $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
  return 'https://login.microsoftonline.com/' . rawurlencode(od_tenant()) . '/oauth2/v2.0/authorize?'
    . http_build_query([
      'client_id' => od_client_id(),
      'response_type' => 'code',
      'redirect_uri' => od_redirect_uri(),
      'response_mode' => 'query',
      'scope' => OD_SCOPES,
      'state' => $state,
      'code_challenge' => $challenge,
      'code_challenge_method' => 'S256',
      // Immer fragen, welches Konto: Ein Rechner, an dem jemand geschäftlich
      // angemeldet ist, verbände sonst stillschweigend das Firmenkonto.
      'prompt' => 'select_account',
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * Ein POST an den Token-Endpunkt.
 *
 * @return array{ok: bool, data: array, message: string}
 */
function od_token_post(array $felder): array {
  $ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => http_build_query($felder),
    'timeout' => 15,
    'ignore_errors' => true,
    // Der Token-Endpunkt leitet nicht um. Täte er es, wüsste nur er, wohin —
    // und dorthin ginge das Anwendungsgeheimnis mit.
    'follow_location' => 0,
    'max_redirects' => 0,
  ]]);
  $url = 'https://login.microsoftonline.com/' . rawurlencode(od_tenant()) . '/oauth2/v2.0/token';
  $roh = @file_get_contents($url, false, $ctx);
  $status = 0;
  foreach ($http_response_header ?? [] as $h) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int) $m[1];
  }
  if ($roh === false) return ['ok' => false, 'data' => [], 'message' => 'Microsoft nicht erreichbar'];
  $data = json_decode((string) $roh, true);
  if (!is_array($data)) return ['ok' => false, 'data' => [], 'message' => 'Antwort unlesbar'];
  if ($status !== 200) {
    // Microsofts Fehlerbeschreibung ist ausführlich und für den Betreiber
    // gedacht — sie sagt etwa, dass die Rückleitung nicht übereinstimmt. Genau
    // das braucht er, statt „Verbindung fehlgeschlagen".
    $text = (string) ($data['error_description'] ?? $data['error'] ?? ('HTTP ' . $status));
    return ['ok' => false, 'data' => $data, 'message' => mb_substr(trim(explode("\r\n", $text)[0]), 0, 300)];
  }
  return ['ok' => true, 'data' => $data, 'message' => ''];
}

/** Zeichen ablegen — versiegelt, wenn ein Schlüssel liegt. */
function od_store_tokens(array $data): void {
  if (($data['access_token'] ?? '') !== '') {
    set_setting('onedrive_access', crypt_available() ? crypt_seal($data['access_token']) : $data['access_token']);
    set_setting('onedrive_expires', (string) (time() + max(60, (int) ($data['expires_in'] ?? 3600))));
  }
  // Microsoft schickt bei jeder Erneuerung ein neues Erneuerungszeichen. Fehlt
  // eines, bleibt das alte gültig — überschrieben wird nur, was da ist, sonst
  // wäre die Verbindung nach der ersten Erneuerung weg.
  if (($data['refresh_token'] ?? '') !== '') {
    set_setting('onedrive_refresh', crypt_available() ? crypt_seal($data['refresh_token']) : $data['refresh_token']);
  }
}

/**
 * Den Code gegen Zeichen tauschen. Danach steht die Verbindung.
 *
 * @return array{ok: bool, message: string}
 */
function od_exchange_code(string $code, string $verifier): array {
  $res = od_token_post([
    'client_id' => od_client_id(),
    'client_secret' => od_client_secret(),
    'code' => $code,
    'redirect_uri' => od_redirect_uri(),
    'grant_type' => 'authorization_code',
    'code_verifier' => $verifier,
    'scope' => OD_SCOPES,
  ]);
  if (!$res['ok']) return ['ok' => false, 'message' => $res['message']];
  if (($res['data']['refresh_token'] ?? '') === '') {
    // Ohne Erneuerungszeichen wäre die Verbindung in einer Stunde tot. Das ist
    // keine Verbindung, sondern eine Anmeldung — also gar nicht erst ablegen.
    return ['ok' => false, 'message' => 'Microsoft hat kein Erneuerungszeichen geschickt — fehlt der Bereich offline_access in der Anwendungsregistrierung?'];
  }
  od_store_tokens($res['data']);
  $wer = od_graph('/me');
  $laufwerk = od_graph('/me/drive');
  set_setting('onedrive_account', json_encode([
    'name' => (string) ($wer['displayName'] ?? ''),
    'email' => (string) ($wer['userPrincipalName'] ?? $wer['mail'] ?? ''),
    'drive' => (string) ($laufwerk['name'] ?? ''),
    'drive_id' => (string) ($laufwerk['id'] ?? ''),
    'since' => date('c'),
  ], JSON_UNESCAPED_UNICODE));
  return ['ok' => true, 'message' => ''];
}

/** Ein gültiges Zugriffszeichen — erneuert, wenn es abgelaufen ist. */
function od_access_token(): string {
  if (!od_enabled()) return '';
  $ablauf = (int) setting('onedrive_expires', '0');
  $zeichen = crypt_reveal(setting('onedrive_access'));
  if ($zeichen !== '' && $ablauf > time() + OD_REFRESH_MARGIN) return $zeichen;

  $erneuern = crypt_reveal(setting('onedrive_refresh'));
  if ($erneuern === '') return '';
  $res = od_token_post([
    'client_id' => od_client_id(),
    'client_secret' => od_client_secret(),
    'refresh_token' => $erneuern,
    'grant_type' => 'refresh_token',
    'scope' => OD_SCOPES,
  ]);
  if (!$res['ok']) {
    // Ein abgelehntes Erneuerungszeichen kommt nicht von selbst zurück: Das
    // Konto hat die Zustimmung entzogen oder das Passwort gewechselt. Den
    // Fehlschlag vermerken, damit die Einstellungen es sagen können — und nicht
    // bei jedem Seitenaufruf erneut zehn Sekunden darauf warten.
    set_setting('onedrive_error', mb_substr($res['message'], 0, 300));
    return '';
  }
  set_setting('onedrive_error', '');
  od_store_tokens($res['data']);
  return crypt_reveal(setting('onedrive_access'));
}

/**
 * Ein GET gegen Graph. Gibt das entschlüsselte JSON oder null.
 *
 * @param string $pfad etwa '/me' oder '/me/drive/root/children'
 */
function od_graph(string $pfad): ?array {
  $zeichen = od_access_token();
  if ($zeichen === '') return null;
  $ctx = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => "Authorization: Bearer $zeichen\r\nAccept: application/json\r\n",
    'timeout' => 20,
    'ignore_errors' => true,
    'follow_location' => 0,
    'max_redirects' => 0,
  ]]);
  $roh = @file_get_contents('https://graph.microsoft.com/v1.0' . $pfad, false, $ctx);
  if ($roh === false) return null;
  $data = json_decode((string) $roh, true);
  if (!is_array($data)) return null;
  if (isset($data['error'])) {
    set_setting('onedrive_error', mb_substr((string) ($data['error']['message'] ?? 'Graph-Fehler'), 0, 300));
    return null;
  }
  return $data;
}

/**
 * Was von der Verbindung zu sehen ist.
 *
 * @return array{connected: bool, name: string, email: string, drive: string, since: string, error: string}
 */
function od_connection(): array {
  $konto = json_decode(setting('onedrive_account', '{}'), true);
  if (!is_array($konto)) $konto = [];
  return [
    'connected' => crypt_reveal(setting('onedrive_refresh')) !== '',
    'name' => (string) ($konto['name'] ?? ''),
    'email' => (string) ($konto['email'] ?? ''),
    'drive' => (string) ($konto['drive'] ?? ''),
    'since' => (string) ($konto['since'] ?? ''),
    'error' => setting('onedrive_error'),
  ];
}

/**
 * Verbindung lösen.
 *
 * Die Zeichen werden gelöscht, nicht nur vergessen: Ein Erneuerungszeichen, das
 * in der Datenbank bleibt, ist ein Zugang zu fremden Dateien. Bei Microsoft
 * selbst muss die Zustimmung getrennt entzogen werden — das kann diese
 * Anwendung nicht, und der Hinweis darauf gehört in die Oberfläche.
 */
function od_disconnect(): void {
  foreach (['onedrive_access', 'onedrive_refresh', 'onedrive_expires', 'onedrive_account', 'onedrive_error'] as $k) {
    set_setting($k, '');
  }
}
