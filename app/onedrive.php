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
 * Der Inhalt eines Ordners im Laufwerk. null heißt: nicht erreichbar.
 *
 * @param string $itemId Kennung des Ordners, leer für die Wurzel
 * @return array{folders: array<int, array>, files: array<int, array>}|null
 */
function od_children(string $itemId = ''): ?array {
  $pfad = $itemId === ''
    ? '/me/drive/root/children'
    : '/me/drive/items/' . rawurlencode($itemId) . '/children';
  // Nur die Felder, die gebraucht werden. Graph liefert sonst je Eintrag ein
  // Vielfaches davon, und über eine Fotosammlung summiert sich das.
  $antwort = od_graph($pfad . '?$top=200&$select=id,name,size,file,folder,webUrl,lastModifiedDateTime,parentReference');
  if ($antwort === null) return null;
  $ordner = $dateien = [];
  foreach ((array) ($antwort['value'] ?? []) as $eintrag) {
    $satz = [
      'id'       => (string) ($eintrag['id'] ?? ''),
      'name'     => (string) ($eintrag['name'] ?? ''),
      'size'     => (int) ($eintrag['size'] ?? 0),
      'mime'     => (string) ($eintrag['file']['mimeType'] ?? ''),
      'modified' => (string) ($eintrag['lastModifiedDateTime'] ?? ''),
      'web_url'  => (string) ($eintrag['webUrl'] ?? ''),
      'path'     => (string) ($eintrag['parentReference']['path'] ?? ''),
    ];
    if ($satz['id'] === '') continue;
    if (isset($eintrag['folder'])) {
      $satz['count'] = (int) ($eintrag['folder']['childCount'] ?? 0);
      $ordner[] = $satz;
    } elseif (isset($eintrag['file'])) {
      $dateien[] = $satz;
    }
  }
  return ['folders' => $ordner, 'files' => $dateien];
}

/** Die verknüpften Ordner, der zuletzt verknüpfte zuerst. */
function od_folders(): array {
  return rows('SELECT f.*, u.name AS linked_by_name FROM od_folders f
               LEFT JOIN users u ON u.id = f.linked_by ORDER BY f.name, f.id');
}

/**
 * Einen Ordner verknüpfen. Zweimal derselbe legt keinen zweiten an — der
 * Schlüssel auf der Kennung sorgt dafür, und wer zweimal klickt, meint einmal.
 */
function od_folder_link(string $itemId, string $name, string $path, ?int $wer): void {
  q('INSERT INTO od_folders (item_id, name, path, linked_by) VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), path = VALUES(path)',
    [$itemId, mb_substr($name, 0, 190), mb_substr($path, 0, 400), $wer]);
}

/** Die Verknüpfung lösen. Die Dateien bei Microsoft bleiben unberührt. */
function od_folder_unlink(int $id): void {
  q('DELETE FROM od_items WHERE folder_id = ?', [$id]);
  q('DELETE FROM od_folders WHERE id = ?', [$id]);
}

/**
 * Den Zwischenstand eines Ordners mit einer frischen Liste abgleichen.
 *
 * Absichtlich ohne Datenbank und ohne Netz: Der Vergleich ist die eigentliche
 * Entscheidung — was ist neu, was hat sich geändert, was ist verschwunden, was
 * ist wieder da — und die will prüfbar sein, ohne ein Microsoft-Konto zu haben.
 *
 * @param array<int, array> $bekannt Zeilen aus od_items
 * @param array<int, array> $frisch  Einträge aus od_children()['files']
 * @param string $jetzt              Zeitpunkt als 'Y-m-d H:i:s'
 * @return array{neu: array, geaendert: array, fehlt: array, zurueck: array}
 */
function od_reconcile(array $bekannt, array $frisch, string $jetzt): array {
  $nachId = [];
  foreach ($bekannt as $b) $nachId[(string) $b['item_id']] = $b;
  $frischNachId = [];
  foreach ($frisch as $f) $frischNachId[(string) $f['id']] = $f;

  $neu = $geaendert = $fehlt = $zurueck = [];
  foreach ($frischNachId as $id => $f) {
    $alt = $nachId[$id] ?? null;
    if (!$alt) { $neu[] = $f; continue; }
    // Wieder aufgetaucht: Der Vermerk muss weg, sonst steht „fehlt" an einer
    // Datei, die man gerade sieht.
    if (($alt['missing_since'] ?? null) !== null) $zurueck[] = $f;
    // Nur melden, was sich wirklich geändert hat — sonst schreibt jeder Blick
    // jede Zeile neu und der Zwischenstand sagt nichts mehr über Bewegung.
    if ((int) $alt['size'] !== (int) $f['size']
        || (string) $alt['name'] !== (string) $f['name']
        || od_zeit((string) $f['modified']) !== ($alt['modified_at'] ?? null)) {
      $geaendert[] = $f;
    }
  }
  foreach ($nachId as $id => $b) {
    // Schon als fehlend vermerkt? Dann bleibt der erste Zeitpunkt stehen: Er
    // sagt, seit wann es fehlt, und das ist die nützlichere Angabe.
    if (!isset($frischNachId[$id]) && ($b['missing_since'] ?? null) === null) $fehlt[] = $b;
  }
  return ['neu' => $neu, 'geaendert' => $geaendert, 'fehlt' => $fehlt, 'zurueck' => $zurueck];
}

/** Graphs Zeitangabe (ISO 8601, UTC) als Datenbankzeit. Leer bleibt null. */
function od_zeit(string $iso): ?string {
  if (trim($iso) === '') return null;
  $t = strtotime($iso);
  return $t === false ? null : date('Y-m-d H:i:s', $t);
}

/**
 * Einen verknüpften Ordner frisch ansehen und den Zwischenstand nachziehen.
 *
 * @return array{ok: bool, neu: int, geaendert: int, fehlt: int, zurueck: int}
 */
function od_folder_refresh(int $folderId): array {
  $ordner = row('SELECT * FROM od_folders WHERE id = ?', [$folderId]);
  if (!$ordner) return ['ok' => false, 'neu' => 0, 'geaendert' => 0, 'fehlt' => 0, 'zurueck' => 0];
  $inhalt = od_children((string) $ordner['item_id']);
  // Nicht erreichbar heißt nicht verschwunden: Ohne Antwort wird nichts als
  // fehlend vermerkt, sonst meldete ein Netzausfall den ganzen Ordner als weg.
  if ($inhalt === null) return ['ok' => false, 'neu' => 0, 'geaendert' => 0, 'fehlt' => 0, 'zurueck' => 0];

  $jetzt = date('Y-m-d H:i:s');
  $bekannt = rows('SELECT * FROM od_items WHERE folder_id = ?', [$folderId]);
  $d = od_reconcile($bekannt, $inhalt['files'], $jetzt);

  foreach ([...$d['neu'], ...$d['geaendert']] as $f) {
    q('INSERT INTO od_items (folder_id, item_id, name, size, mime, modified_at, web_url, seen_at, missing_since)
       VALUES (?,?,?,?,?,?,?,?,NULL)
       ON DUPLICATE KEY UPDATE name = VALUES(name), size = VALUES(size), mime = VALUES(mime),
         modified_at = VALUES(modified_at), web_url = VALUES(web_url), seen_at = VALUES(seen_at),
         missing_since = NULL',
      [$folderId, $f['id'], mb_substr((string) $f['name'], 0, 190), (int) $f['size'],
       mb_substr((string) $f['mime'], 0, 120), od_zeit((string) $f['modified']),
       mb_substr((string) $f['web_url'], 0, 600), $jetzt]);
  }
  foreach ($d['zurueck'] as $f) {
    q('UPDATE od_items SET missing_since = NULL, seen_at = ? WHERE folder_id = ? AND item_id = ?',
      [$jetzt, $folderId, $f['id']]);
  }
  foreach ($d['fehlt'] as $b) {
    q('UPDATE od_items SET missing_since = ? WHERE id = ?', [$jetzt, (int) $b['id']]);
  }
  q('UPDATE od_folders SET checked_at = ?, name = ?, path = ? WHERE id = ?',
    [$jetzt, $ordner['name'], $ordner['path'], $folderId]);

  return ['ok' => true, 'neu' => count($d['neu']), 'geaendert' => count($d['geaendert']),
          'fehlt' => count($d['fehlt']), 'zurueck' => count($d['zurueck'])];
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
  // Die Verknüpfungen bleiben stehen: Wer die Verbindung erneuert, will seine
  // Ordner wiederfinden und nicht von vorn anfangen. Ohne Zeichen ist ohnehin
  // nichts davon erreichbar, und der Zwischenstand verrät keine Dateiinhalte.
}
