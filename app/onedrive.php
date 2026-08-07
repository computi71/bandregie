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
 * Files.Read.All fürs Verknüpfen, Files.ReadWrite seit dem Sicherungsziel
 * (#50): Die Sicherung muss schreiben, löschen (Aufbewahrung) und nichts
 * weiter. Eine Verbindung von vor dem Schreibrecht bleibt fürs Lesen gültig —
 * erst das Sichern verlangt, einmal neu zu verbinden, und dabei fragt
 * Microsoft um die neue Zustimmung.
 */
const OD_SCOPES = 'offline_access User.Read Files.Read.All Files.ReadWrite';

/** Wie lange vor dem Ablauf schon erneuert wird. Eine Minute Vorlauf genügt. */
const OD_REFRESH_MARGIN = 60;

// Grenzen für das Absteigen (#205). Sechs Ebenen decken „Bilder/Jahr/Termin/
// Fotograf" mit Luft ab; zweitausend Dateien und zwanzig Seiten je Ordner
// halten einen Durchgang in einer Zeit, die ein Aufruf im Netz aushält.
const OD_MAX_DEPTH = 6;
const OD_MAX_FILES = 2000;
const OD_MAX_PAGES = 20;

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
 * Ein Graph-Aufruf mit Verb und Rumpf — für alles, was nicht nur liest (#50).
 * Gibt ['status' => int, 'data' => array] zurück; status 0 heißt: gar keine
 * Antwort. Der Aufrufer entscheidet, was ein Fehlschlag bedeutet — beim
 * Sichern ist das eine Meldung, nie ein Abbruch der lokalen Sicherung.
 */
function od_graph_send(string $methode, string $pfad, ?array $rumpf = null): array {
  $zeichen = od_access_token();
  if ($zeichen === '') return ['status' => 0, 'data' => []];
  // Kopfzeilen werden mit CRLF getrennt, und das muss hier als Escape-Folge
  // stehen: Mit einem echten Umbruch im Quelltext schickt PHP ein zerlegtes
  // Kopffeld, Graph verwirft die Anfrage mit 400 und ohne Fehlertext (#225).
  $kopf = "Authorization: Bearer $zeichen\r\nAccept: application/json\r\n";
  if ($rumpf !== null) $kopf .= "Content-Type: application/json\r\n";
  $ctx = stream_context_create(['http' => [
    'method' => $methode,
    'header' => $kopf,
    'content' => $rumpf === null ? '' : (string) json_encode($rumpf),
    'timeout' => 30,
    'ignore_errors' => true,
    'follow_location' => 0,
    'max_redirects' => 0,
  ]]);
  $roh = @file_get_contents('https://graph.microsoft.com/v1.0' . $pfad, false, $ctx);
  $status = 0;
  foreach ($http_response_header ?? [] as $h) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int) $m[1];
  }
  if ($roh === false) return ['status' => 0, 'data' => []];
  $data = json_decode((string) $roh, true);
  return ['status' => $status, 'data' => is_array($data) ? $data : []];
}

/**
 * Einen Ordnerpfad im Laufwerk sicherstellen und seine Kennung liefern (#50).
 * Segment für Segment: gibt es ihn, wird er genommen; fehlt er, wird er
 * angelegt. Leerer Rückgabewert heißt: nicht möglich (kein Recht, kein Netz).
 */
function od_folder_ensure(string $pfad): string {
  $pfad = trim($pfad, "/ 	");
  if ($pfad === '') return '';
  $eltern = '';
  foreach (explode('/', $pfad) as $teil) {
    $teil = trim($teil);
    if ($teil === '') continue;
    // od_children statt einer eigenen Liste: Es blättert — eine Wurzel mit
    // mehr als 200 Einträgen hätte den Zielordner sonst versteckt, und der
    // Anlege-Versuch wäre am vorhandenen Namen gescheitert (Review 06.08.).
    $a = od_children($eltern);
    if ($a === null) return '';
    $gefunden = '';
    foreach ($a['folders'] as $e) {
      if (mb_strtolower((string) $e['name']) === mb_strtolower($teil)) {
        $gefunden = (string) $e['id'];
        break;
      }
    }
    if ($gefunden === '') {
      $r = od_graph_send('POST',
        $eltern === '' ? '/me/drive/root/children' : '/me/drive/items/' . rawurlencode($eltern) . '/children',
        ['name' => $teil, 'folder' => new stdClass(), '@microsoft.graph.conflictBehavior' => 'fail']);
      $gefunden = (string) ($r['data']['id'] ?? '');
      if ($gefunden === '') return '';
    }
    $eltern = $gefunden;
  }
  return $eltern;
}

/**
 * Eine Datei in Stücken auf eine Upload-Adresse legen (#50).
 *
 * Getrennt vom Anlegen der Sitzung, damit genau dieses Stück — das Stückeln,
 * die Content-Range-Zeilen, das Ende — gegen einen eigenen Server prüfbar ist.
 * Die Stückgröße ist ein Vielfaches von 320 KiB, wie Graph es verlangt.
 *
 * @return array{ok: bool, message: string}
 */
function od_upload_put(string $url, string $datei, int $stueck = 10485760): array {
  if (!is_file($datei)) return ['ok' => false, 'message' => 'Datei fehlt'];
  $gesamt = (int) filesize($datei);
  if ($gesamt <= 0) return ['ok' => false, 'message' => 'Datei leer oder unlesbar'];
  $h = fopen($datei, 'rb');
  if (!$h) return ['ok' => false, 'message' => 'Datei nicht lesbar'];
  $ab = 0;
  while ($ab < $gesamt) {
    $teil = fread($h, $stueck);
    if ($teil === false || $teil === '') { fclose($h); return ['ok' => false, 'message' => 'Lesefehler bei Byte ' . $ab]; }
    $bis = $ab + strlen($teil) - 1;
    $ctx = stream_context_create(['http' => [
      'method' => 'PUT',
      // Kein Authorization-Kopf: Die Upload-Adresse trägt ihre Berechtigung
      // selbst, und ein zusätzliches Zeichen lehnt Graph ab.
      'header' => "Content-Length: " . strlen($teil) . "\r\n"
                . "Content-Range: bytes $ab-$bis/$gesamt\r\n",
      'content' => $teil,
      'timeout' => 120,
      'ignore_errors' => true,
    ]]);
    $roh = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $zeile) {
      if (preg_match('~^HTTP/\S+\s+(\d{3})~', $zeile, $m)) $status = (int) $m[1];
    }
    if ($roh === false || !in_array($status, [200, 201, 202], true)) {
      fclose($h);
      return ['ok' => false, 'message' => "Übertragung abgebrochen bei Byte $ab (HTTP $status)"];
    }
    $ab = $bis + 1;
  }
  fclose($h);
  return ['ok' => true, 'message' => ''];
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
  // Vielfaches davon, und über eine Fotosammlung summiert sich das. `photo`
  // bringt das Aufnahmedatum mit — dieselbe Antwort, kein zweiter Aufruf, und
  // ohne dieses Datum lässt sich später keine Serie bilden (#198).
  // `photo` bringt Aufnahmedatum und Kamera, `location` das GPS, `image` die
  // Maße, `file` die Prüfsumme. Alles aus derselben Antwort — Microsoft hat das
  // EXIF beim Hochladen schon gelesen, und es ein zweites Mal aus einer 15-MB-
  // Datei zu holen wäre dieselbe Auskunft für tausendfachen Aufwand (#206).
  $frage = $pfad . '?$top=200&$select=id,name,size,file,folder,photo,location,image,webUrl,lastModifiedDateTime';
  $ordner = $dateien = [];
  // Graph gibt höchstens eine Seite und verweist auf die nächste. Ein Ordner mit
  // 200 Bildern sah deshalb aus wie ein Ordner mit genau 200 Bildern — und die
  // 201. Datei fehlte, ohne dass es jemand gemerkt hätte.
  $seiten = 0;
  while ($frage !== '' && $seiten < OD_MAX_PAGES) {
    $antwort = od_graph($frage);
    if ($antwort === null) return null;
    $seiten++;
    foreach ((array) ($antwort['value'] ?? []) as $eintrag) {
      $satz = [
        'id'       => (string) ($eintrag['id'] ?? ''),
        'name'     => (string) ($eintrag['name'] ?? ''),
        'size'     => (int) ($eintrag['size'] ?? 0),
        'mime'     => (string) ($eintrag['file']['mimeType'] ?? ''),
        'modified' => (string) ($eintrag['lastModifiedDateTime'] ?? ''),
        'taken'    => (string) ($eintrag['photo']['takenDateTime'] ?? ''),
        'web_url'  => (string) ($eintrag['webUrl'] ?? ''),
        'camera'   => trim((string) ($eintrag['photo']['cameraMake'] ?? '')
                     . ' ' . (string) ($eintrag['photo']['cameraModel'] ?? '')),
        'lat'      => $eintrag['location']['latitude'] ?? null,
        'lng'      => $eintrag['location']['longitude'] ?? null,
        'width'    => (int) ($eintrag['image']['width'] ?? 0),
        'height'   => (int) ($eintrag['image']['height'] ?? 0),
        // Privates OneDrive liefert sha256, geschäftliches nur quickXorHash.
        // Ohne sha256 lässt sich ein verknüpftes Bild nicht gegen ein
        // hochgeladenes vergleichen (#199) — leer ist hier also eine Aussage.
        'sha256'   => strtolower((string) ($eintrag['file']['hashes']['sha256Hash'] ?? '')),
      ];
      if ($satz['id'] === '') continue;
      if (isset($eintrag['folder'])) {
        $satz['count'] = (int) ($eintrag['folder']['childCount'] ?? 0);
        $ordner[] = $satz;
      } elseif (isset($eintrag['file'])) {
        $dateien[] = $satz;
      }
    }
    // Der Verweis kommt als vollständige Adresse; od_graph() erwartet den Teil
    // hinter der Version.
    $weiter = (string) ($antwort['@odata.nextLink'] ?? '');
    $frage = $weiter === '' ? '' : (string) preg_replace('~^https://graph\.microsoft\.com/v1\.0~', '', $weiter);
    if ($frage === $weiter) $frage = ''; // unerwartete Adresse: lieber aufhören
  }
  return ['folders' => $ordner, 'files' => $dateien];
}

/**
 * Alle Dateien unterhalb eines Ordners, samt ihrem Weg dorthin.
 *
 * Ohne Absteigen war das Merkmal wirkungslos: Niemand legt Bilder direkt in den
 * obersten Ordner, und ein verknüpfter Ordner, in dem nur Unterordner liegen,
 * ergab null Einträge (#205).
 *
 * Das Auflisten kommt als Rückruf herein, nicht als Aufruf von od_children() —
 * so lässt sich der Lauf mit einem erfundenen Baum prüfen, ohne ein Konto bei
 * Microsoft zu haben. Genau dieselbe Überlegung wie bei od_reconcile().
 *
 * Grenzen sind Pflicht, nicht Vorsicht: Ein verknüpftes Laufwerk mit
 * fünfzigtausend Dateien würde einen einzigen Aufruf zur halben Stunde machen.
 * Was ausgelassen wurde, steht im Ergebnis — stilles Abschneiden liest sich wie
 * Vollständigkeit.
 *
 * @param callable(string): ?array $liste  Kennung -> ['folders'=>…, 'files'=>…]
 * @return array{files: list<array>, folders: int, deep: list<string>, capped: bool, unreachable: list<string>}
 */
function od_walk(callable $liste, string $wurzelId, int $maxTiefe = OD_MAX_DEPTH, int $maxDateien = OD_MAX_FILES): array {
  $dateien = [];
  $zuTief = [];
  $unerreichbar = [];
  $ordnerZahl = 0;
  $voll = false;
  // Eigene Schlange statt Rekursion: Die Grenze für die Zahl der Dateien lässt
  // sich so an einer Stelle prüfen, und ein tiefer Baum kostet keinen Stapel.
  $schlange = [['id' => $wurzelId, 'weg' => '', 'tiefe' => 0]];
  while ($schlange) {
    $jetzt = array_shift($schlange);
    $inhalt = $liste($jetzt['id']);
    if ($inhalt === null) { $unerreichbar[] = $jetzt['weg'] === '' ? '/' : $jetzt['weg']; continue; }
    $ordnerZahl++;
    foreach ($inhalt['files'] as $f) {
      if (count($dateien) >= $maxDateien) { $voll = true; break; }
      $f['rel_path'] = $jetzt['weg'];
      $dateien[] = $f;
    }
    if ($voll) break;
    foreach ($inhalt['folders'] as $u) {
      $weg = $jetzt['weg'] === '' ? (string) $u['name'] : $jetzt['weg'] . '/' . $u['name'];
      // Zu tief heißt nicht „leer": Der Weg wird gemeldet, damit man weiß, wo
      // noch etwas liegt, das nicht angesehen wurde.
      if ($jetzt['tiefe'] + 1 > $maxTiefe) { $zuTief[] = $weg; continue; }
      $schlange[] = ['id' => (string) $u['id'], 'weg' => $weg, 'tiefe' => $jetzt['tiefe'] + 1];
    }
  }
  return ['files' => $dateien, 'folders' => $ordnerZahl, 'deep' => $zuTief,
          'capped' => $voll, 'unreachable' => $unerreichbar];
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
        || od_zeit((string) $f['modified']) !== ($alt['modified_at'] ?? null)
        // Auch der Weg zählt (#213): Wird ein Ordner umbenannt, behalten seine
        // Dateien Kennung, Name und Änderungszeit — nur der Weg ist neu. Ohne
        // diesen Vergleich stünde der alte Weg fest, bis sich die Datei selbst
        // ändert, und der Weg ist die Auskunft, um die es geht.
        || (string) ($alt['rel_path'] ?? '') !== (string) ($f['rel_path'] ?? '')) {
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
  $leer = ['ok' => false, 'neu' => 0, 'geaendert' => 0, 'fehlt' => 0, 'zurueck' => 0,
           'folders' => 0, 'deep' => [], 'capped' => false, 'unreachable' => []];
  $ordner = row('SELECT * FROM od_folders WHERE id = ?', [$folderId]);
  if (!$ordner) return $leer;
  $lauf = od_walk('od_children', (string) $ordner['item_id']);
  // Nicht erreichbar heißt nicht verschwunden: Konnte nicht einmal der
  // verknüpfte Ordner selbst gelesen werden, wird nichts als fehlend vermerkt —
  // sonst meldete ein Netzausfall den ganzen Ordner als weg.
  if ($lauf['folders'] === 0) return $leer;

  $jetzt = date('Y-m-d H:i:s');
  $bekannt = rows('SELECT * FROM od_items WHERE folder_id = ?', [$folderId]);
  // Ein Unterordner, der diesmal nicht antwortete, darf seine Dateien nicht als
  // fehlend erscheinen lassen. Also gilt der Abgleich nur für die Wege, die
  // wirklich gelesen wurden.
  $gelesen = [];
  foreach ($lauf['files'] as $f) $gelesen[(string) $f['rel_path']] = true;
  foreach ($lauf['unreachable'] as $w) unset($gelesen[$w === '/' ? '' : $w]);
  $vergleichbar = array_values(array_filter($bekannt,
    fn($b) => isset($gelesen[(string) ($b['rel_path'] ?? '')])));
  $d = od_reconcile($vergleichbar, $lauf['files'], $jetzt);

  foreach ([...$d['neu'], ...$d['geaendert']] as $f) {
    q('INSERT INTO od_items (folder_id, item_id, name, rel_path, size, mime, modified_at, taken_at,
                             camera, lat, lng, img_w, img_h, sha256, web_url, seen_at, missing_since)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)
       ON DUPLICATE KEY UPDATE name = VALUES(name), rel_path = VALUES(rel_path), size = VALUES(size),
         mime = VALUES(mime), modified_at = VALUES(modified_at), taken_at = VALUES(taken_at),
         camera = VALUES(camera), lat = VALUES(lat), lng = VALUES(lng),
         img_w = VALUES(img_w), img_h = VALUES(img_h), sha256 = VALUES(sha256),
         web_url = VALUES(web_url), seen_at = VALUES(seen_at), missing_since = NULL',
      [$folderId, $f['id'], mb_substr((string) $f['name'], 0, 190),
       mb_substr((string) ($f['rel_path'] ?? ''), 0, 400), (int) $f['size'],
       mb_substr((string) $f['mime'], 0, 120), od_zeit((string) $f['modified']),
       od_zeit((string) ($f['taken'] ?? '')),
       mb_substr((string) ($f['camera'] ?? ''), 0, 120),
       $f['lat'] ?? null, $f['lng'] ?? null,
       (int) ($f['width'] ?? 0), (int) ($f['height'] ?? 0),
       (string) ($f['sha256'] ?? ''),
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
          'fehlt' => count($d['fehlt']), 'zurueck' => count($d['zurueck']),
          'folders' => $lauf['folders'], 'deep' => $lauf['deep'],
          'capped' => $lauf['capped'], 'unreachable' => $lauf['unreachable']];
}

/**
 * Welche gerechnete Fassung lokal liegen bleibt (#206).
 *
 * Nur das Vorschaubild — das ist der Zweck der Verknüpfung: wenig Webspace.
 * `large` sind 800 Pixel an der langen Kante; daraus rechnet thumb_file() die
 * 480er-Kachel, und die Großansicht zeigt eben diese 800. Für 518 Bilder sind
 * das etwa 50 MB statt 7,3 GB Originale.
 *
 * Alles darüber — scharfe Großansicht, Druck, Weitergabe — läuft über den
 * Verweis zum Original bei OneDrive. Lokal mehr vorzuhalten hieße, den Platz
 * doch wieder zu verbrauchen, den die Verknüpfung sparen soll.
 */
const OD_THUMB_SIZE = 'large';

/** Höchstens so viele Bilder je Durchgang holen. Ein Aufruf im Netz hat ein Ende. */
const OD_IMPORT_BATCH = 60;

/** Und keine Fassung über dieser Größe annehmen — sonst ist es nicht die Fassung. */
const OD_THUMB_MAX_BYTES = 4194304;

/**
 * Die Adresse der gerechneten Fassung. Sie gilt nur etwa eine Stunde, lässt sich
 * also nicht speichern — deshalb wird sie unmittelbar vor dem Holen erfragt.
 */
function od_thumb_url(string $itemId, string $groesse = OD_THUMB_SIZE): string {
  $a = od_graph('/me/drive/items/' . rawurlencode($itemId) . '/thumbnails?$select=' . rawurlencode($groesse));
  foreach ((array) ($a['value'] ?? []) as $satz) {
    if (isset($satz[$groesse]['url'])) return (string) $satz[$groesse]['url'];
  }
  return '';
}

/**
 * Die Fassung holen und ablegen. Gibt die Zahl der geschriebenen Bytes zurück,
 * 0 bei Misserfolg — und lässt dann keine halbe Datei liegen.
 *
 * Geprüft wird, was ankommt, nicht was versprochen war: Die Adresse zeigt auf
 * einen Zwischenspeicher von Microsoft, und was von dort kommt, ist so lange
 * fremde Eingabe wie alles andere aus dem Netz.
 */
function od_thumb_fetch(string $url, string $ziel): int {
  if ($url === '') return 0;
  $ctx = stream_context_create(['http' => [
    'method' => 'GET', 'timeout' => 30, 'ignore_errors' => true,
    'follow_location' => 1, 'max_redirects' => 3,
  ]]);
  $roh = @file_get_contents($url, false, $ctx, 0, OD_THUMB_MAX_BYTES + 1);
  if ($roh === false || $roh === '' || strlen($roh) > OD_THUMB_MAX_BYTES) return 0;
  if (@file_put_contents($ziel, $roh) === false) return 0;
  // Kein Bild? Dann liegt es nicht in den Uploads herum.
  if (!str_starts_with((string) (@mime_content_type($ziel) ?: ''), 'image/')) {
    @unlink($ziel);
    return 0;
  }
  return strlen($roh);
}

/**
 * Aus einer Verknüpfung die Werte für ein Galeriebild.
 *
 * Rein rechnend und ohne Netz, damit die Zuordnung prüfbar ist: Sie entscheidet,
 * was von den Auskünften bei welchem Bild landet, und eine Verwechslung von
 * Länge und Breite oder von Aufnahme- und Änderungsdatum sieht man einer
 * Datenbankzeile später nicht mehr an.
 *
 * Die Herkunft ist der Weg im Ordner samt Dateinamen (#197) — daraus liest man
 * Termin und Fotograf ab. Sie ist zugleich der Schlüssel, an dem die Serien
 * erkennen, was zusammengehört (#198).
 *
 * @param array $item Zeile aus od_items
 * @param string $datei Name der abgelegten Fassung
 */
function od_item_photo_row(array $item, string $datei): array {
  $weg = trim((string) ($item['rel_path'] ?? ''), '/');
  return [
    'filename'   => $datei,
    'caption'    => '',
    'source'     => mb_substr(($weg === '' ? '' : $weg . '/') . (string) ($item['name'] ?? ''), 0, 400),
    'taken_at'   => $item['taken_at'] ?? null,
    'lat'        => $item['lat'] ?? null,
    'lng'        => $item['lng'] ?? null,
    'camera'     => mb_substr((string) ($item['camera'] ?? ''), 0, 120),
    'img_w'      => (int) ($item['img_w'] ?? 0),
    'img_h'      => (int) ($item['img_h'] ?? 0),
    'od_item_id' => (string) ($item['item_id'] ?? ''),
    'od_web_url' => mb_substr((string) ($item['web_url'] ?? ''), 0, 600),
  ];
}

/**
 * Verknüpfte Bilder in die Galerie holen: Fassung herunter, Auskünfte daneben,
 * Original verlinkt (#206).
 *
 * In Schritten und nicht auf einmal: Fünfhundert Aufrufe zu Microsoft sind kein
 * Seitenaufruf. Was übrig ist, steht im Ergebnis, damit der nächste Druck
 * weitermacht statt von vorn anzufangen.
 *
 * Ein Bild, das hier schon einmal geholt wurde, kommt nicht wieder — auch dann
 * nicht, wenn es in der Galerie gelöscht wurde. Löschen ist eine Entscheidung,
 * und der nächste Durchgang darf sie nicht rückgängig machen.
 *
 * @return array{done: int, left: int, failed: int, bytes: int}
 */
function od_import(int $folderId, int $hoechstens = OD_IMPORT_BATCH): array {
  $offen = rows("SELECT * FROM od_items
                 WHERE folder_id = ? AND imported_at IS NULL AND missing_since IS NULL
                   AND mime LIKE 'image/%'
                 ORDER BY taken_at, name", [$folderId]);
  $getan = $misslungen = $vermerkt = $bytes = 0;
  foreach ($offen as $item) {
    if ($getan + $misslungen >= $hoechstens) break;
    // Schon als Bild vorhanden? Dann nur vermerken, nicht ein zweites Mal holen.
    if (row('SELECT 1 FROM photos WHERE od_item_id = ?', [(string) $item['item_id']])) {
      q('UPDATE od_items SET imported_at = NOW() WHERE id = ?', [(int) $item['id']]);
      $vermerkt++;
      continue;
    }
    $endung = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo((string) $item['name'], PATHINFO_EXTENSION) ?: 'jpg'));
    $datei = 'od_' . bin2hex(random_bytes(12)) . '.' . ($endung ?: 'jpg');
    $n = od_thumb_fetch(od_thumb_url((string) $item['item_id']), UPLOADS_DIR . '/' . $datei);
    if ($n === 0) { $misslungen++; continue; }
    $w = od_item_photo_row($item, $datei);
    // Die Prüfsumme des ORIGINALS, nicht der Vorschau (#199): Sie macht ein
    // später hochgeladenes Duplikat desselben Originals erkennbar.
    q('INSERT INTO photos (filename, caption, is_public, uploaded_by, taken_at, lat, lng, source,
                           camera, img_w, img_h, od_item_id, od_web_url, checksum, created_at)
       VALUES (?,?,0,NULL,?,?,?,?,?,?,?,?,?,?,NOW())',
      [$w['filename'], $w['caption'], $w['taken_at'], $w['lat'], $w['lng'], $w['source'],
       $w['camera'], $w['img_w'], $w['img_h'], $w['od_item_id'], $w['od_web_url'],
       (string) ($item['sha256'] ?? '')]);
    q('UPDATE od_items SET imported_at = NOW() WHERE id = ?', [(int) $item['id']]);
    $getan++;
    $bytes += $n;
  }
  // Was nur vermerkt wurde, ist erledigt und nicht mehr offen — sonst behauptete
  // die Meldung eine Restarbeit, die es nicht gibt.
  return ['done' => $getan, 'left' => max(0, count($offen) - $getan - $misslungen - $vermerkt),
          'failed' => $misslungen, 'bytes' => $bytes];
}

/**
 * Ist der tägliche Blick fällig (#214)? Dasselbe Muster wie bei der Sicherung:
 * ein Fälligkeits-Check, zwei Auslöser — Seitenaufruf (gedrosselt) und Cron.
 * Nach einem Fehlschlag frühestens nach einer Stunde wieder, sonst hämmert
 * jeder Seitenaufruf gegen eine Verbindung, die gerade nicht antwortet.
 */
function od_refresh_due(): bool {
  if (is_demo()) return false;
  if (!od_enabled() || setting('od_auto_refresh', '1') !== '1') return false;
  if (!row('SELECT 1 FROM od_folders LIMIT 1')) return false;
  $versuch = (int) setting('od_auto_attempt', '0');
  if (time() - $versuch < 3600) return false;
  return (bool) row("SELECT 1 FROM od_folders
                     WHERE checked_at IS NULL OR checked_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     LIMIT 1");
}

/**
 * Alle fälligen Ordner nachsehen und bei Neuem Bescheid geben (#214).
 *
 * Gemeldet wird nur Gefundenes, nicht der Lauf: Eine tägliche Mitteilung
 * „nichts Neues" wäre nach einer Woche abbestellt — und mit ihr die eine, die
 * zählt. Geholt wird nichts: Nachsehen kostet nichts, Bilder holen bleibt
 * eine Entscheidung am Knopf.
 *
 * @return array{folders: int, neu: int, fehlt: int, ok: bool}
 */
function od_refresh_all(): array {
  set_setting('od_auto_attempt', (string) time());
  $neu = $fehlt = $gelaufen = 0;
  $alleOk = true;
  $namen = [];
  foreach (od_folders() as $ordner) {
    $r = od_folder_refresh((int) $ordner['id']);
    $gelaufen++;
    if (!$r['ok']) { $alleOk = false; continue; }
    $neu += $r['neu'];
    $fehlt += $r['fehlt'];
    if ($r['neu'] > 0) $namen[] = (string) $ordner['name'];
  }
  if ($neu > 0) {
    $anzahl = $neu;
    $wo = implode(', ', array_unique($namen));
    push_notify('photos', 0, fn(string $lang): array => [
      'title' => push_t($lang, 'push_od_title'),
      'body' => str_replace(['%1', '%2'], [(string) $anzahl, $wo], push_t($lang, 'push_od_body')),
      // In die Galerie, nicht auf die Ordner-Seite: Die Mitteilung geht an
      // alle Mitglieder, die Ordner-Seite gehört den Admins (Review 06.08.).
      'url' => '/intern/fotos',
    ]);
  }
  return ['folders' => $gelaufen, 'neu' => $neu, 'fehlt' => $fehlt, 'ok' => $alleOk];
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
