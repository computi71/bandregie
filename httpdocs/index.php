<?php
declare(strict_types=1);

// PHP-Entwicklungsserver: vorhandene Dateien (CSS etc.) direkt ausliefern
if (php_sapi_name() === 'cli-server') {
  $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  if ($p !== '/' && is_file(__DIR__ . $p)) return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/backup.php';
require_once dirname(__DIR__) . '/app/dauerauftrag.php';
require dirname(__DIR__) . '/app/equipmentbuchung.php';
require dirname(__DIR__) . '/app/mischpult.php';

// parse_url() liefert bei einer kaputten Adresse false, nicht null — ?? fängt
// das nicht, und rtrim(false) ist unter PHP 8 ein Fatal Error. Ein Scanner mit
// einer verunglückten Anfrage bekam so einen 500er statt einer normalen Antwort.
$rohPfad = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = rtrim(is_string($rohPfad) ? $rohPfad : '/', '/') ?: '/';
// HEAD soll genau das antworten, was GET antworten würde — nur ohne Rumpf.
// Jede Route prüft auf 'GET', also fiel bisher jede HEAD-Anfrage durch bis auf
// die 404-Seite: Suchmaschinen, Linkprüfer und Überwachungsdienste bekamen für
// eine tadellose Startseite „gibt es nicht" zu hören. Ein Wächter hätte die
// Seite als ausgefallen gemeldet, während sie einwandfrei lief.
//
// Der Rumpf wird nicht weggeworfen, sondern gar nicht erst erzeugt: head_only()
// steigt an den Stellen aus, die Dateien ausliefern. Sonst würde eine
// versiegelte Datei für eine Anfrage entschlüsselt, deren Antwort ohnehin nur
// aus Kopfzeilen besteht.
$method = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
$today = date('Y-m-d');

// Sicherheitskopfzeilen. Viele setzt schon der Webserver, aber die Anwendung
// soll auch auf einem Server sicher stehen, der das nicht tut. Die Regeln
// erlauben nur eigene Inhalte; die Musikseite bettet YouTube und Spotify ein,
// deshalb stehen genau diese beiden als Rahmenquellen darin.
// Skripte laufen ausschließlich aus eigenen Dateien: eingeschleuster Code im
// Dokument wird nicht ausgeführt, selbst wenn er es je hineinschaffte. Für
// Stile bleibt 'unsafe-inline' vorerst nötig, weil die Druckansichten ihr
// Blattlayout im Dokument tragen — ein Stil kann keinen Code ausführen.
if (!headers_sent()) {
  header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; font-src 'self'; connect-src 'self'; "
    . "frame-src https://www.youtube-nocookie.com https://open.spotify.com; "
    // form-action gilt auch für das Ziel einer Weiterleitung nach dem Absenden.
    // Der Knopf „Mit OneDrive verbinden" schickt an uns, und wir antworten mit
    // 302 zur Anmeldung bei Microsoft — mit 'self' allein bricht der Browser das
    // ab, ohne etwas anzuzeigen: der Knopf tat nichts. Deshalb steht genau diese
    // eine Adresse hier, und keine weitere.
    . "frame-ancestors 'self'; base-uri 'self'; "
    . "form-action 'self' https://login.microsoftonline.com; object-src 'none'");
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: same-origin');
  // Eine Demo gehört nicht in den Suchindex (#174): Ihre Inhalte sind erfunden
  // und werden stündlich zurückgesetzt, und sie stünde als zweite Fassung
  // derselben Anwendung neben der echten Seite. Als Kopfzeile UND als Meta im
  // Kopf der Seite — die Kopfzeile liest auch ein Sucher, der das HTML gar
  // nicht auswertet, etwa bei einem PDF oder einem Bild.
  if (is_demo()) header('X-Robots-Tag: noindex, nofollow');
}

// Überschreitet ein Upload post_max_size, verwirft PHP den gesamten Request-Body:
// $_POST und $_FILES sind dann leer und das Formular scheint wirkungslos.
if ($method === 'POST' && $_POST === [] && $_FILES === []
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes('post_max_size')) {
  flash(t('fl_upload_server_limit') . ' ' . fmt_bytes(max_upload_bytes()));
  back('/intern');
}

// Jede schreibende Anfrage braucht das Token aus dem Formular. Ohne gültiges
// Token wird nichts ausgeführt — fremde Seiten können so keine Aktionen im
// Namen eines angemeldeten Mitglieds auslösen.
//
if ($method === 'POST' && !csrf_valid()) {
  flash(t('fl_csrf'));
  back('/');
}

// ============================================================
// Öffentliche Seiten
// ============================================================

// ---------- robots.txt ----------
// Bisher gab es keine: Die Adresse landete auf der 404-Seite, und ein Sucher
// nahm mangels Auskunft alles mit. Was hier steht, hängt von der Installation
// ab — eine Anweisung, die für alle gleich lautet, wäre für die meisten falsch.
if ($path === '/robots.txt') {
  header('Content-Type: text/plain; charset=utf-8');
  $zeilen = ["User-agent: *"];
  if (is_demo()) {
    // Erfundene Inhalte, stündlich zurückgesetzt — davon gehört nichts in einen
    // Index, auch nicht die öffentliche Seite.
    $zeilen[] = 'Disallow: /';
  } elseif (setting('public_mode') === 'redirect') {
    // Wer die öffentliche Seite auf eine Weiterleitung gestellt hat, hat hier
    // nichts zu zeigen; zu indexieren gibt es entsprechend auch nichts.
    $zeilen[] = 'Disallow: /';
  } else {
    // Der Bandbereich ist ohnehin hinter der Anmeldung — aber ein Sucher soll
    // gar nicht erst anklopfen, und Uploads gehören niemandem als der Band.
    $zeilen[] = 'Disallow: /intern';
    $zeilen[] = 'Disallow: /login';
    $zeilen[] = 'Disallow: /uploads';
    $zeilen[] = 'Allow: /';
  }
  exit(implode("\n", $zeilen) . "\n");
}

// ---------- App: Manifest und Symbole ----------
// Das Manifest macht die Seite installierbar. Es trägt den Bandnamen, damit
// auf dem Startbildschirm nicht „Bandregie" steht, sondern die Band.
if ($path === '/manifest.webmanifest' && $method === 'GET') {
  header('Content-Type: application/manifest+json; charset=utf-8');
  header('Cache-Control: public, max-age=3600');
  $band = setting('band_name') ?: 'Bandregie';
  exit(json_encode([
    'name' => $band,
    'short_name' => mb_substr($band, 0, 12),
    'description' => t('app_description'),
    'start_url' => '/intern',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#17120f',
    'theme_color' => '#17120f',
    'lang' => current_lang(),
    'icons' => [
      ['src' => app_icon(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
      ['src' => app_icon(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Sprachwechsel (?lang=de|en|nl|fr|es|it, nur aktivierte Sprachen)
if (isset($_GET['lang']) && in_array($_GET['lang'], enabled_langs(), true)) {
  $_SESSION['pub_lang'] = $_GET['lang'];
  if ($u = current_user()) q('UPDATE users SET pref_lang = ? WHERE id = ?', [$_GET['lang'], $u['id']]);
  redirect($path ?: '/');
}

// Weiterleitungs-Modus: öffentliche Seiten leiten z. B. zu Facebook um.
// Interner Bereich, Login, Kalender-Feed, Uploads und die Pflichtseiten
// (als Impressums-Ziel für die Social-Profile) bleiben erreichbar.
if (setting('public_mode') === 'redirect' && $method === 'GET') {
  // /appicon und das Manifest bleiben erreichbar: ein Handy holt das Symbol
  // für den Startbildschirm ohne Sitzung, und wer weitergeleitet wird, hat
  // sonst das Zeichen einer fremden Seite auf dem Bildschirm.
  // /thumb/ gehört dazu wie /uploads/: Es liefert dieselben Bilder, nur klein.
  // Ohne diesen Eintrag wurde jede Vorschau im Bandbereich zu Facebook geleitet,
  // während das große Bild daneben lud — die Fotoseite bestand aus kaputten
  // Kästchen. Wer ein Bild sehen darf, entscheidet die Route selbst.
  $keepPrefixes = ['/intern', '/login', '/logout', '/passwort-vergessen', '/passwort-reset/', '/kalender/', '/uploads/', '/thumb/', '/impressum', '/datenschutz', '/assets/', '/downloads', '/download/', '/appicon/', '/manifest.webmanifest'];
  $keep = false;
  foreach ($keepPrefixes as $prefix) {
    if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) { $keep = true; break; }
  }
  if (!$keep) {
    header('Location: ' . setting('redirect_url', 'https://www.facebook.com/'), true, 302);
    exit;
  }
}

if ($path === '/' && $method === 'GET') {
  view('public/home', [
    'title' => t('nav_start'),
    'gigs' => rows("SELECT * FROM events WHERE type='gig' AND is_public=1 AND status='bestaetigt' AND date >= ? ORDER BY date, time LIMIT 3", [$today]),
    'photos' => rows('SELECT * FROM photos WHERE is_public=1 AND archived_at IS NULL ORDER BY created_at DESC LIMIT 6'),
  ]);
}

if ($path === '/termine' && $method === 'GET') {
  $limitUpcoming = (int) setting('public_limit_upcoming', '10');
  $limitPast = (int) setting('public_limit_past', '5');
  $showPast = setting('public_show_past') === '1';
  view('public/termine', [
    'title' => t('nav_termine'),
    'gigs' => rows("SELECT * FROM events WHERE type='gig' AND is_public=1 AND status='bestaetigt' AND date >= ? ORDER BY date, time"
      . ($limitUpcoming > 0 ? " LIMIT $limitUpcoming" : ''), [$today]),
    'past' => $showPast
      ? rows("SELECT * FROM events WHERE type='gig' AND is_public=1 AND status='bestaetigt' AND date < ? ORDER BY date DESC"
          . ($limitPast > 0 ? " LIMIT $limitPast" : ''), [$today])
      : [],
  ]);
}

if ($path === '/musik' && $method === 'GET') {
  $links = array_map(function (array $l): array {
    $l['embed'] = null; $l['etype'] = 'link';
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{6,})~', $l['url'], $m)) {
      $l['embed'] = 'https://www.youtube-nocookie.com/embed/' . $m[1]; $l['etype'] = 'youtube';
    } elseif (preg_match('~open\.spotify\.com/(track|album|playlist|artist)/(\w+)~', $l['url'], $m)) {
      $l['embed'] = "https://open.spotify.com/embed/{$m[1]}/{$m[2]}"; $l['etype'] = 'spotify';
    }
    return $l;
  }, rows('SELECT * FROM media_links ORDER BY id DESC'));
  view('public/musik', ['title' => t('nav_musik'), 'links' => $links]);
}

if ($path === '/fotos' && $method === 'GET') {
  // Archiviertes ist auch öffentlich weg (#200): aus der Galerie genommen heißt
  // aus jeder Galerie genommen — die Datei bleibt, aber gezeigt wird sie nicht.
  view('public/fotos', ['title' => t('nav_fotos'), 'photos' => rows('SELECT * FROM photos WHERE is_public=1 AND archived_at IS NULL ORDER BY created_at DESC')]);
}

if ($path === '/kontakt' && $method === 'GET') {
  view('public/kontakt', ['title' => t('nav_kontakt')]);
}

if ($path === '/impressum' && $method === 'GET') {
  // Der Bildnachweis gehört ins Impressum, nicht in die Datenschutzerklärung.
  require_once BASE_DIR . '/app/demo.php';
  view('public/rechtliches', ['title' => t('nav_impressum'), 'heading' => t('nav_impressum'),
                              'text' => content('impressum_text'), 'imageCredit' => demo_background_credit()]);
}

if ($path === '/datenschutz' && $method === 'GET') {
  view('public/rechtliches', ['title' => t('privacy_title'), 'heading' => t('privacy_title'),
                              'text' => content('privacy_text'), 'imageCredit' => null]);
}

// Veranstalter-Downloads: öffentlich oder über geheimen Link
if (preg_match('~^/downloads(?:/([a-f0-9]{32}))?$~', $path, $m) && $method === 'GET') {
  $mode = setting('downloads_mode', 'token');
  $token = $m[1] ?? '';
  $allowed = ($mode === 'public') || ($mode === 'token' && $token !== '' && hash_equals(setting('downloads_token'), $token));
  if (!$allowed) { http_response_code(404); view('404', ['title' => 'Nicht gefunden']); }
  view('public/downloads', [
    'title' => t('downloads_title'),
    'files' => rows("SELECT * FROM files WHERE entity_type = 'download' ORDER BY original_name"),
    'dlToken' => $mode === 'token' ? $token : '',
  ]);
}
if (preg_match('~^/download/(\d+)$~', $path, $m) && $method === 'GET') {
  $mode = setting('downloads_mode', 'token');
  $allowed = ($mode === 'public') || ($mode === 'token' && hash_equals(setting('downloads_token'), $_GET['t'] ?? ''));
  $f = $allowed ? row("SELECT * FROM files WHERE id = ? AND entity_type = 'download'", [$m[1]]) : null;
  if (!$f || !is_file(FILES_DIR . '/' . $f['filename'])) { http_response_code(404); exit('Nicht gefunden'); }
  file_serve($f);
}

// Verkleinerte Fassung für Galerien. Dieselbe Zugriffsprüfung wie beim
// Original — eine Vorschau ist genauso privat wie das Bild selbst.
if (preg_match('~^/thumb/([\w.\-]+)$~', $path, $m)) {
  $name = basename($m[1]);
  if (!is_file(UPLOADS_DIR . '/' . $name) || !may_see_upload(current_user(), $name)) {
    http_response_code(404);
    exit('Not found');
  }
  $small = thumb_file($name, 480) ?? UPLOADS_DIR . '/' . $name;
  header('Content-Type: ' . ($small === UPLOADS_DIR . '/' . $name
    ? (['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp']
       [strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream')
    : 'image/jpeg'));
  header('Cache-Control: private, max-age=86400');
  header('Content-Length: ' . filesize($small));
  if (head_only()) exit;
  readfile($small);
  exit;
}

// Das aus dem Favicon erzeugte App-Symbol. Öffentlich wie das Favicon selbst:
// Startbildschirm und Manifest holen es ohne Anmeldung, und es zeigt nichts,
// was nicht ohnehin in jedem Browsertab steht.
if (preg_match('~^/appicon/(icon-\d+-[a-f0-9]+\.png)$~', $path, $m) && $method === 'GET') {
  $iconFile = DATA_DIR . '/appicons/' . basename($m[1]);
  if (!is_file($iconFile)) { http_response_code(404); exit('Not found'); }
  header('Content-Type: image/png');
  header('Cache-Control: public, max-age=604800');
  header('Content-Length: ' . filesize($iconFile));
  if (head_only()) exit;
  readfile($iconFile);
  exit;
}

// Hochgeladene Bilder ausliefern (liegen außerhalb des Webroots).
//
// Nicht jedes Bild geht die Allgemeinheit etwas an: Logo, Hintergrund und
// Favicon stehen auf der öffentlichen Seite, ebenso als öffentlich markierte
// Fotos. Alles andere — das Fotoarchiv der Band und die Bilder der Mitglieder
// — gibt es nur für Angemeldete. Vorher genügte der Dateiname, und der ließ
// sich raten.
if (preg_match('~^/uploads/([\w.\-]+)$~', $path, $m)) {
  $file = UPLOADS_DIR . '/' . basename($m[1]);
  if (!is_file($file)) { http_response_code(404); exit('Not found'); }

  // Wer nichts sehen darf, erfährt nicht einmal, dass es die Datei gibt
  if (!may_see_upload(current_user(), basename($m[1]))) { http_response_code(404); exit('Not found'); }
  $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp']
    [strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
  header("Content-Type: $mime");
  header('Cache-Control: public, max-age=86400');
  header('Content-Length: ' . filesize($file));
  if (head_only()) exit;
  readfile($file);
  exit;
}

// iCal-Feed zum Abonnieren in Kalender-Apps (geheimer Link)
if (preg_match('~^/kalender/(\w+)\.ics$~', $path, $m)) {
  if (!hash_equals(setting('ical_token'), $m[1])) { http_response_code(404); exit; }
  header('Content-Type: text/calendar; charset=utf-8');
  $band = setting('band_name');
  echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//$band//DE\r\nX-WR-CALNAME:$band\r\n";
  foreach (rows('SELECT * FROM events ORDER BY date') as $ev) {
    $uid = "event-{$ev['id']}@" . ($_SERVER['HTTP_HOST'] ?? 'bandregie.local');
    if ($ev['status'] === 'abgesagt') continue;
    $summary = ($ev['type'] === 'probe' ? 'Probe: ' : 'Gig: ') . $ev['title']
      . ($ev['status'] === 'angefragt' ? ' (unbestätigt)' : '');
    $esc = fn(string $s): string => addcslashes(str_replace(["\r\n", "\n"], '\n', $s), ',;');
    echo "BEGIN:VEVENT\r\nUID:$uid\r\n";
    if ($ev['time']) {
      $startTs = strtotime("{$ev['date']} {$ev['time']}");
      $endTs = $ev['time_end'] ? strtotime("{$ev['date']} {$ev['time_end']}") : $startTs + 3 * 3600;
      if ($endTs <= $startTs) $endTs = $startTs + 3 * 3600;
      echo 'DTSTART:' . date('Ymd\THis', $startTs) . "\r\nDTEND:" . date('Ymd\THis', $endTs) . "\r\n";
    } else {
      echo 'DTSTART;VALUE=DATE:' . str_replace('-', '', $ev['date']) . "\r\n";
    }
    echo 'SUMMARY:' . $esc($summary) . "\r\n";
    if ($ev['location']) echo 'LOCATION:' . $esc($ev['location']) . "\r\n";
    if ($ev['notes']) echo 'DESCRIPTION:' . $esc($ev['notes']) . "\r\n";
    echo "END:VEVENT\r\n";
  }
  echo "END:VCALENDAR\r\n";
  exit;
}

// ============================================================
// Login / Logout
// ============================================================

if ($path === '/login') {
  if (current_user()) redirect('/intern');
  if ($method === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (throttle_blocked('login', $email)) {
      http_response_code(429);
      view('login', ['title' => 'Login', 'error' => t('fl_throttled')]);
    }
    $u = row('SELECT * FROM users WHERE email = ?', [$email]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
      throttle_clear('login', $email);
      session_regenerate_id(true);
      // Zweiter Faktor (#169): Ein richtiges Passwort meldet noch niemanden
      // an. Bis der Code stimmt, steht in der Sitzung nur eine Absicht und
      // keine uid — alles hinter require_login() bleibt damit zu, ohne dass
      // eine einzige Route etwas davon wissen muss.
      if (totp_available() && totp_active_for($u)) {
        $_SESSION['totp_wait'] = ['uid' => (int) $u['id'], 'seit' => time()];
        redirect('/login/code');
      }
      $_SESSION['uid'] = $u['id'];
      if (array_key_exists($u['pref_lang'] ?? '', LANGS)) $_SESSION['pub_lang'] = $u['pref_lang'];
      redirect(!empty($u['must_change_pw']) ? '/intern/passwort' : '/intern');
    }
    throttle_note('login', $email);
    http_response_code(401);
    view('login', ['title' => 'Login', 'error' => t('login_failed')]);
  }
  view('login', ['title' => 'Login', 'error' => null]);
}

/**
 * Der zweite Schritt der Anmeldung mit Passwort (#169).
 *
 * Getrennte Seite und nicht ein zweites Feld auf der Anmeldemaske: Die Zahl
 * wechselt alle dreißig Sekunden, und wer sie eintippt, während er noch das
 * Passwort sucht, tippt sie zweimal.
 */
if ($path === '/login/code') {
  if (current_user()) redirect('/intern');
  $warte = $_SESSION['totp_wait'] ?? null;
  // Zehn Minuten: lang genug, um die App zu suchen, kurz genug, dass ein
  // stehengelassener Rechner keine halbfertige Anmeldung offen hält.
  if (!is_array($warte) || time() - (int) ($warte['seit'] ?? 0) > 600) {
    unset($_SESSION['totp_wait']);
    redirect('/login');
  }
  $uid = (int) $warte['uid'];
  if ($method === 'POST') {
    if (throttle_blocked('totp', (string) $uid)) {
      http_response_code(429);
      view('login-code', ['title' => t('totp_step_title'), 'error' => t('fl_throttled')]);
    }
    $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
    $eingabe = trim((string) ($_POST['code'] ?? ''));
    // Der Rückweg steht im selben Feld: Wer sein Handy verloren hat, sucht
    // sonst erst nach einem zweiten Formular. Beide sind eindeutig zu
    // unterscheiden — sechs Ziffern oder zehn Buchstaben mit Bindestrich.
    $ok = $u && (totp_verify(totp_secret_for($u), $eingabe) || totp_recovery_use($u, $eingabe));
    if ($ok) {
      throttle_clear('totp', (string) $uid);
      unset($_SESSION['totp_wait']);
      session_regenerate_id(true);
      $_SESSION['uid'] = $uid;
      if (array_key_exists($u['pref_lang'] ?? '', LANGS)) $_SESSION['pub_lang'] = $u['pref_lang'];
      redirect(!empty($u['must_change_pw']) ? '/intern/passwort' : '/intern');
    }
    throttle_note('totp', (string) $uid);
    http_response_code(401);
    view('login-code', ['title' => t('totp_step_title'), 'error' => t('totp_wrong')]);
  }
  view('login-code', ['title' => t('totp_step_title'), 'error' => null]);
}

if ($path === '/logout' && $method === 'POST') {
  session_destroy();
  redirect('/');
}

// ---------- Anmeldung mit Passkey (#168) ----------
// Die Zufallsfrage holen. Ohne Konto-Angabe: Welche Passkeys es gibt, geht
// niemanden etwas an, der noch nicht angemeldet ist. Das Gerät bringt selbst
// mit, für wen es signiert.
if ($path === '/passkey/challenge' && $method === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  if (!passkey_available()) exit(json_encode(['error' => 'unsupported']));
  exit(json_encode(['challenge' => passkey_challenge_new('login'), 'rpId' => passkey_rp_id()]));
}

// Die Antwort des Geräts prüfen und anmelden.
if ($path === '/passkey/login' && $method === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $in = $_POST;
  $challenge = passkey_challenge_take('login');
  $credId = trim((string) ($in['id'] ?? ''));
  // Absichtlich eine einzige Absage für jeden Fehlschlag: Wer probiert, soll
  // nicht daran ablesen können, ob es diesen Passkey überhaupt gibt.
  $absage = static function (): never {
    http_response_code(400);
    exit(json_encode(['error' => t('fl_pk_failed')]));
  };
  if ($challenge === null || $credId === '') $absage();
  if (throttle_blocked('passkey', $credId, 10, 15)) {
    http_response_code(429);
    exit(json_encode(['error' => t('fl_throttled')]));
  }
  throttle_note('passkey', $credId);

  $pk = row('SELECT * FROM passkeys WHERE credential_id = ?', [$credId]);
  if (!$pk) $absage();
  $authData = passkey_b64_decode((string) ($in['authenticatorData'] ?? ''));
  $clientData = passkey_b64_decode((string) ($in['clientDataJSON'] ?? ''));
  $signature = passkey_b64_decode((string) ($in['signature'] ?? ''));
  if (passkey_client_data_error($clientData, $challenge, 'webauthn.get') !== null) $absage();
  if (passkey_auth_data_error($authData) !== null) $absage();
  if (!passkey_signature_ok($pk['public_key'], $authData, $clientData, $signature)) $absage();

  $u = row('SELECT * FROM users WHERE id = ?', [$pk['user_id']]);
  if (!$u) $absage();
  // Der Zählstand des Geräts wächst mit jeder Nutzung. Bleibt er stehen oder
  // fällt zurück, kann das eine Kopie des Schlüssels sein — ein Gerät zählt
  // nicht rückwärts. Manche Geräte zählen gar nicht (dann immer 0); nur wenn
  // vorher schon gezählt wurde, ist ein Rückschritt ein Grund zur Sorge.
  $zaehler = passkey_sign_count($authData);
  if ((int) $pk['sign_count'] > 0 && $zaehler > 0 && $zaehler <= (int) $pk['sign_count']) {
    error_log('Bandregie: Passkey-Zählstand nicht gewachsen (Konto ' . (int) $pk['user_id'] . ')');
  }
  q('UPDATE passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?', [$zaehler, $pk['id']]);
  throttle_clear('passkey', $credId);
  session_regenerate_id(true);
  $_SESSION['uid'] = $u['id'];
  if (array_key_exists($u['pref_lang'] ?? '', LANGS)) $_SESSION['pub_lang'] = $u['pref_lang'];
  exit(json_encode(['ok' => true, 'weiter' => !empty($u['must_change_pw']) ? '/intern/passwort' : '/intern']));
}

// Passwort vergessen: Link per E-Mail anfordern (ohne Konto-Enumeration)
if ($path === '/passwort-vergessen') {
  if ($method === 'POST') {
    // In der Demo der einzige Weg, über den ein Besucher die Anwendung dazu
    // brächte, Post an eine fremde Adresse zu schicken.
    deny_in_demo('/login');
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (throttle_blocked('reset', $email, 5, 60)) {
      flash(t('fl_throttled'));
      redirect('/login');
    }
    throttle_note('reset', $email);
    $u = $email !== '' ? row('SELECT * FROM users WHERE email = ?', [$email]) : null;
    if ($u) {
      $token = bin2hex(random_bytes(32));
      q('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?', [$token, $u['id']]);
      $band = setting('band_name');
      $link = absolute_url('/passwort-reset/' . $token);
      $body = "Hallo {$u['name']},\n\n"
        . "für deinen Zugang zum Bandbereich von $band wurde ein neues Passwort angefordert.\n\n"
        . "Zum Zurücksetzen hier klicken (1 Stunde gültig):\n$link\n\n"
        . "Wenn du das nicht warst, kannst du diese E-Mail einfach ignorieren.\n\n"
        . "Viele Grüße\n$band";
      $from = 'no-reply@' . preg_replace('~^www\.~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
      $replyTo = setting('contact_email') ? "\r\nReply-To: " . setting('contact_email') : '';
      // Der fünfte Parameter setzt den Umschlagabsender. Ohne ihn nimmt PHP den
      // Systembenutzer, und die SPF-Prüfung passt dann nicht zur Absenderdomain.
      @mail($email, "Passwort zurücksetzen - $band", $body,
        "From: $from$replyTo\r\nContent-Type: text/plain; charset=UTF-8", '-f' . $from);
    }
    flash(t('pwreset_sent'));
    redirect('/login');
  }
  view('pwreset_request', ['title' => t('pwreset_title')]);
}

// Passwort über den Link aus der E-Mail neu vergeben
if (preg_match('~^/passwort-reset/([a-f0-9]{64})$~', $path, $m)) {
  $u = row('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()', [$m[1]]);
  if (!$u) {
    flash(t('pwreset_invalid'));
    redirect('/passwort-vergessen');
  }
  if ($method === 'POST') {
    deny_in_demo('/login');
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) {
      flash(t('fl_pw_min'));
    } elseif ($pw !== ($_POST['password2'] ?? '')) {
      flash(t('fl_pw_mismatch'));
    } else {
      q('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, must_change_pw = 0 WHERE id = ?',
        [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
      flash(t('fl_pw_changed'));
      redirect('/login');
    }
    redirect('/passwort-reset/' . $m[1]);
  }
  view('pwreset_form', ['title' => t('pwreset_new_title'), 'token' => $m[1]]);
}

// ============================================================
// Interner Bereich
// ============================================================

if (str_starts_with($path, '/intern')) {
  $me = require_login();

  // Erzwungener Passwortwechsel: erst eigenes Passwort setzen, dann alles andere
  if (!empty($me['must_change_pw']) && $path !== '/intern/passwort') {
    redirect('/intern/passwort');
  }

  // Vorgeschriebener zweiter Faktor (#169): danach, nicht davor — ein frisch
  // vergebenes Startpasswort gehört gewechselt, bevor etwas daran hängt.
  //
  // totp_active() und nicht totp_active_for($me): current_user() lädt eine
  // knappe Spaltenliste ohne die Felder des zweiten Faktors. Mit $me wäre die
  // Antwort immer „hat keinen" — und damit stünde bei „vorgeschrieben" auch
  // der auf der Einrichtungsseite fest, der längst einen hat.
  //
  // Die Einstellung selbst bleibt offen, sonst schnappt eine Falle zu: Ein
  // Admin ohne zweiten Faktor, der „vorgeschrieben" einschaltet, käme an
  // keine Einstellung mehr heran — auch nicht an die, mit der er es
  // zurücknimmt. Die Route prüft weiterhin selbst auf Admin.
  if (totp_mode() === 'required'
      && $path !== '/intern/zwei-faktor'
      && $path !== '/intern/einstellungen/zwei-faktor'
      && !totp_active((int) $me['id'])) {
    redirect('/intern/zwei-faktor');
  }

  // Rechte je Bereich, an einer Stelle für alle Routen. Ein GET braucht das
  // Leserecht, alles Schreibende das Änderungsrecht. Pfade ohne Bereich —
  // Übersicht, eigenes Profil, Passwort — stehen jedem Angemeldeten offen,
  // und was nur einen selbst betrifft (siehe SELF_SERVICE_PATHS) reicht
  // ebenfalls das Leserecht; diese Routen prüfen selbst weiter.
  if ($permModule = perm_module_for($path)) {
    $permNeed = $method === 'GET' || is_self_service($path) ? 'read' : 'write';
    if (!perm_allows($me, $permModule, $permNeed)) {
      flash(t('fl_no_permission'));
      redirect('/intern');
    }
  }

  // Fällige Daueraufträge buchen. Das kostet fast nichts, wenn nichts fällig
  // ist — eine Abfrage auf ein Datum —, und erspart einen zweiten Zeitgeber.
  orders_run();

  // Aus demselben Grund gleich hier: Abos wegräumen, von denen seit Monaten
  // nichts mehr zu hören war. Höchstens einmal am Tag, sonst kostenlos.
  push_prune();

  // Fällige Sicherung nebenbei anstoßen. Ohne Cronjob gibt es keinen anderen
  // Zeitpunkt; die Sperre in backup_run() verhindert doppelte Läufe. Wo der
  // Server es kann, ist die Seite vorher ausgeliefert und niemand wartet.
  if ($method === 'GET' && backup_due()) {
    register_shutdown_function(function () {
      if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
      backup_run('auto');
    });
  }
  // Der tägliche Blick in die OneDrive-Ordner (#214), nach demselben Muster:
  // nach dem Ausliefern der Seite, damit niemand auf Microsoft wartet.
  if ($method === 'GET' && od_refresh_due()) {
    register_shutdown_function(function () {
      if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
      od_refresh_all();
    });
  }

  // Versionsabfrage hinter der Fußzeile. Ein Admin loest damit eine frische
  // Nachfrage aus; fuer alle anderen bleibt es bei dem, was zuletzt bekannt
  // war — sie koennen ohnehin nichts aktualisieren.
  if ($path === '/intern/version' && $method === 'GET') {
    require_once BASE_DIR . '/app/update.php';
    if (($me['role'] ?? '') === 'admin') set_setting('update_checked_at', '0');
    $latest = update_latest_version();
    $available = $latest !== null && version_compare($latest, BANDREGIE_VERSION, '>');
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode([
      'installedLabel' => t('up_installed') . ' ' . BANDREGIE_VERSION,
      'latestLabel'    => t('up_latest') . ' ' . ($latest ?? t('up_unknown')),
      'verdict'        => $available ? sprintf(t('up_available'), $latest) : t('up_current'),
      'available'      => $available,
    ], JSON_UNESCAPED_UNICODE));
  }
  if ($path === '/intern/passwort') {
    if ($method === 'POST') {
      deny_in_demo('/intern');
      $pw = $_POST['password'] ?? '';
      if (strlen($pw) < 8) {
        flash(t('fl_pw_min'));
      } elseif ($pw !== ($_POST['password2'] ?? '')) {
        flash(t('fl_pw_mismatch'));
      } else {
        q('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
        // Das Startpasswort ist damit verbraucht. Die Datei liegen zu lassen
        // heißt, dass sie irgendwann etwas anderes behauptet als die Wahrheit
        // — und bis dahin ein gültiges Passwort im Klartext herumliegt. Die
        // README bat den Betreiber, sie selbst zu löschen; das kann das
        // Programm auch allein.
        if (($me['role'] ?? '') === 'admin') @unlink(DATA_DIR . '/INITIAL-PASSWORD.txt');
        flash(t('fl_pw_changed'));
        redirect('/intern');
      }
      redirect('/intern/passwort');
    }
    view('intern/passwort', ['title' => t('pw_change_title'), 'forced' => !empty($me['must_change_pw'])]);
  }

  /**
   * Zweiten Faktor einrichten, erneuern, abschalten (#169).
   *
   * Eine Seite für beide Wege — freiwillig aus dem Profil und erzwungen nach
   * dem Anmelden. Zwei Fassungen derselben Anleitung würden auseinanderlaufen,
   * und die erzwungene wäre die, die niemand pflegt.
   */
  if ($path === '/intern/zwei-faktor') {
    $totpErzwungen = totp_mode() === 'required';
    // Die volle Zeile: current_user() führt die Felder des zweiten Faktors
    // absichtlich nicht mit.
    $totpMe = totp_state((int) $me['id']);
    // Abgeschaltet: Wer noch einen hat, darf ihn loswerden; alle anderen
    // haben hier nichts verloren.
    if (!totp_available() && !totp_active_for($totpMe)) redirect('/intern/profil');

    if ($method === 'POST') {
      deny_in_demo('/intern/profil');
      $totpTat = $_POST['tat'] ?? 'confirm';

      if ($totpTat === 'delete') {
        if ($totpErzwungen) {
          flash(t('totp_cannot_remove'));
          redirect('/intern/zwei-faktor');
        }
        totp_clear((int) $me['id']);
        flash(t('totp_removed'));
        redirect('/intern/profil');
      }

      // Bestätigen und Rückwege erneuern prüfen beide einen Code, also auch
      // beide gegen dieselbe Bremse — sonst wäre das Erneuern das offene Tor.
      if (throttle_blocked('totp', (string) $me['id'])) {
        flash(t('fl_throttled'));
        redirect('/intern/zwei-faktor');
      }
      // Beim Erneuern gilt das schon bestätigte Geheimnis, beim Einrichten
      // das aus der Sitzung — abgelegt wird es erst, wenn ein Code stimmt.
      $totpGeheim = $totpTat === 'codes'
        ? totp_secret_for($totpMe)
        : (string) ($_SESSION['totp_setup'] ?? '');
      if ($totpGeheim === '' || !totp_verify($totpGeheim, (string) ($_POST['code'] ?? ''))) {
        throttle_note('totp', (string) $me['id']);
        flash(t('totp_wrong'));
        redirect('/intern/zwei-faktor');
      }
      throttle_clear('totp', (string) $me['id']);

      $totpCodes = totp_recovery_new();
      totp_store((int) $me['id'], $totpGeheim, $totpCodes);
      unset($_SESSION['totp_setup']);
      // Einmal zeigen, dann nie wieder — gespeichert ist nur der Abdruck.
      // Über die Sitzung und nicht direkt gerendert, damit ein Neuladen der
      // Seite die Codes nicht ein zweites Mal aus einer POST-Antwort holt.
      $_SESSION['totp_codes'] = $totpCodes;
      redirect('/intern/zwei-faktor');
    }

    $totpAktiv = totp_active_for($totpMe);
    $totpNeueCodes = $_SESSION['totp_codes'] ?? null;
    unset($_SESSION['totp_codes']);
    // Das Geheimnis lebt bis zur Bestätigung nur in der Sitzung: Wer die
    // Einrichtung abbricht, lässt nichts im Konto zurück.
    if (!$totpAktiv && empty($_SESSION['totp_setup'])) $_SESSION['totp_setup'] = totp_secret_new();
    $totpGeheim = $totpAktiv ? '' : (string) $_SESSION['totp_setup'];

    view('intern/zwei-faktor', [
      'title'     => t('totp_setup_title'),
      'aktiv'     => $totpAktiv,
      'erzwungen' => $totpErzwungen,
      'geheim'    => $totpGeheim,
      'uri'       => $totpAktiv ? '' : totp_uri($totpGeheim, (string) $me['email'], setting('band_name', 'Bandregie')),
      'codes'     => is_array($totpNeueCodes) ? $totpNeueCodes : null,
      'uebrig'    => totp_recovery_left($totpMe),
      'seit'      => $totpMe['totp_confirmed_at'] ?? null,
    ]);
  }

  // ---------- Dashboard ----------
  if ($path === '/intern' && $method === 'GET') {
    // Die Übersicht zeigt nur, was das Mitglied auch aufrufen dürfte — sonst
    // steht auf der ersten Seite nach dem Anmelden, was das Menü verbirgt.
    [$dashWhere, $dashParams] = visible_clause(visible_event_ids($me));
    $events = perm_allows($me, 'termine')
      ? rows("SELECT * FROM events WHERE date >= ?$dashWhere ORDER BY date, time LIMIT 5", [$today, ...$dashParams])
      : [];
    view('intern/dashboard', [
      'title' => t('inav_intern'),
      'events' => $events,
      'deadlines' => perm_allows($me, 'equipment') ? rows('SELECT d.*, e.name AS eq_name FROM equipment_deadlines d
                           JOIN equipment e ON e.id = d.equipment_id
                           WHERE d.due_date <= DATE_ADD(?, INTERVAL 60 DAY) ORDER BY d.due_date', [$today]) : [],
      'tasks' => perm_allows($me, 'aufgaben') ? rows("SELECT t.*, u.name AS assignee FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
                       WHERE t.status='offen' ORDER BY CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date LIMIT 8") : [],
      'attendance' => attendance_map(array_column($events, 'id')),
      'mine' => my_attendance(array_column($events, 'id'), $me['id']),
    ]);
  }

  // ---------- Termine ----------
  if ($path === '/intern/termine' && $method === 'GET') {
    $showPast = ($_GET['alle'] ?? '') === '1';
    // Ersatzleute sehen nur die Termine, für die sie angefragt sind
    [$evWhere, $evParams] = visible_clause(visible_event_ids($me));
    $events = $showPast
      ? rows("SELECT * FROM events WHERE 1 = 1$evWhere ORDER BY date DESC, time", $evParams)
      : rows("SELECT * FROM events WHERE date >= ?$evWhere ORDER BY date, time", [$today, ...$evParams]);
    $ids = array_column($events, 'id');
    $comments = [];
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      foreach (rows("SELECT c.*, u.name AS author FROM comments c LEFT JOIN users u ON u.id = c.user_id
                     WHERE c.event_id IN ($in) ORDER BY c.created_at", $ids) as $c) {
        $comments[$c['event_id']][] = $c;
      }
    }
    // Abwesenheiten: welche Mitglieder sind an welchem Termintag verhindert?
    $absentByEvent = [];
    if ($events) {
      $ranges = rows('SELECT a.user_id, a.date_from, a.date_to, a.note, u.name FROM absences a JOIN users u ON u.id = a.user_id');
      foreach ($events as $ev) {
        foreach ($ranges as $r) {
          if ($ev['date'] >= $r['date_from'] && $ev['date'] <= $r['date_to']) {
            $absentByEvent[$ev['id']][] = $r['name'];
          }
        }
      }
    }
    $venues = rows('SELECT * FROM venues ORDER BY name');
    view('intern/termine', [
      'title' => t('nav_termine'),
      'events' => $events,
      'showPast' => $showPast,
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
      'setlists' => rows('SELECT id, name FROM setlists ORDER BY name'),
      'venues' => $venues,
      'venueMap' => array_column($venues, null, 'id'),
      'absentByEvent' => $absentByEvent,
      // Abgegebene Geräte kann niemand mehr einpacken.
      'equipment' => rows('SELECT id, name, category, parent_id FROM equipment
                           WHERE disposed_on IS NULL ORDER BY category, name'),
      'gearByEvent' => event_gear_map($ids),
      'gearConflicts' => event_gear_conflicts($ids),
      'filesByEvent' => files_map('event', $ids),
      'comments' => $comments,
      'attendance' => attendance_map($ids),
      'mine' => my_attendance($ids, $me['id']),
      'substitutes' => rows('SELECT id, name, substitute_for FROM users WHERE substitute_for IS NOT NULL'),
      'subRequests' => substitute_requests_map($ids),
      'ical_url' => '/kalender/' . setting('ical_token') . '.ics',
    ]);
  }

  if ($path === '/intern/termine' && $method === 'POST') {
    if (($_POST['title'] ?? '') && ($_POST['date'] ?? '')) {
      q('INSERT INTO events (type, title, date, time, location, notes, is_public, setlist_id,
                             time_meet, time_end, status, responsible_id, fee, invoice_no,
                             public_title, public_link, public_info, venue_id,
                             pa_source, light_source)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', event_values());
      $newEventId = (int) $db->lastInsertId();
      save_event_gear($newEventId);
      // Mitteilung an alle, die neue Termine abonniert haben — aber nur an die,
      // die den Termin auch sehen dürfen (#24, #149).
      $pushTitle = (string) $_POST['title'];
      $pushDate = (string) $_POST['date'];
      push_notify('events', (int) $me['id'], fn(string $lang): array => [
        'title' => push_t($lang, 'push_ev_title'),
        'body' => $pushTitle . ' · ' . fmt_date($pushDate),
        'url' => '/intern/termine',
      ], $newEventId);
    } else {
      flash(t('fl_title_date_required'));
    }
    redirect('/intern/termine');
  }

  if (preg_match('~^/intern/termine/(\d+)/(update|delete|zusage|kommentar)$~', $path, $m) && $method === 'POST') {
    [$_, $id, $action] = $m;
    if (in_array($action, ['update', 'delete'], true) && event_locked((int) $id)) {
      flash(t('fl_locked_event'));
      redirect('/intern/termine?alle=1');
    }
    if ($action === 'update') {
      q('UPDATE events SET type=?, title=?, date=?, time=?, location=?, notes=?, is_public=?, setlist_id=?,
                           time_meet=?, time_end=?, status=?, responsible_id=?, fee=?, invoice_no=?,
                           public_title=?, public_link=?, public_info=?, venue_id=?,
                           pa_source=?, light_source=? WHERE id=?',
        [...event_values(), $id]);
      save_event_gear((int) $id);
      redirect('/intern/termine');
    }
    if ($action === 'delete') {
      q('DELETE FROM events WHERE id = ?', [$id]);
      q('DELETE FROM attendance WHERE event_id = ?', [$id]);
      q('DELETE FROM comments WHERE event_id = ?', [$id]);
      q('DELETE FROM event_equipment WHERE event_id = ?', [$id]);
      q('DELETE FROM substitute_requests WHERE event_id = ?', [$id]);
      // Fotos bleiben, ihre Zuordnung nicht: sonst zeigte sie auf einen Termin,
      // den es nicht mehr gibt.
      q('UPDATE photos SET event_id = NULL WHERE event_id = ?', [$id]);
      redirect('/intern/termine');
    }
    if ($action === 'zusage') {
      $status = in_array($_POST['status'] ?? '', ['yes', 'no', 'maybe'], true) ? $_POST['status'] : 'maybe';
      q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)', [$id, $me['id'], $status]);
      // Mitteilung an die Zusagen-Abonnenten — wer plant, will das sofort wissen (#24).
      $pushEv = row('SELECT title FROM events WHERE id = ?', [$id]);
      $pushWho = (string) $me['name'];
      $pushEvTitle = (string) ($pushEv['title'] ?? '');
      $pushKey = 'push_att_' . $status;
      push_notify('attendance', (int) $me['id'], fn(string $lang): array => [
        'title' => str_replace(['%1', '%2'], [$pushWho, $pushEvTitle], push_t($lang, $pushKey)),
        'body' => '',
        'url' => '/intern/termine',
      ], (int) $id);
      // Sagt jemand ab, rückt der nächste Ersatz nach — sofern die Band das so
      // eingestellt hat. Sagt ein Ersatz ab, geht die Anfrage an den nächsten
      // für dieselbe Lücke weiter.
      if ($status === 'no') {
        $gap = row('SELECT for_user_id FROM substitute_requests WHERE event_id = ? AND user_id = ?', [$id, $me['id']]);
        substitute_auto_request((int) $id, (int) ($gap['for_user_id'] ?? $me['id']), (int) $me['id']);
      }
      back('/intern/termine');
    }
    if ($action === 'kommentar') {
      $text = trim($_POST['text'] ?? '');
      if ($text !== '') {
        q('INSERT INTO comments (event_id, user_id, text) VALUES (?,?,?)', [$id, $me['id'], $text]);
        // Mitteilung an die Kommentar-Abonnenten: wer schreibt was, wozu (#24).
        $pushEv = row('SELECT title FROM events WHERE id = ?', [$id]);
        $pushWho = (string) $me['name'];
        // Lange Kommentare kappen — die Mitteilung ist der Anriss, nicht der Text.
        $pushText = mb_strlen($text) > 120 ? mb_substr($text, 0, 119) . '…' : $text;
        push_notify('comments', (int) $me['id'], fn(string $lang): array => [
          'title' => push_t($lang, 'push_comment_title') . ' · ' . ($pushEv['title'] ?? ''),
          'body' => $pushWho . ': ' . $pushText,
          'url' => '/intern/termine',
        ], (int) $id);
      }
      back('/intern/termine');
    }
  }

  // Ersatz für einen Termin anfragen oder die Anfrage zurückziehen
  if (preg_match('~^/intern/termine/(\d+)/ersatz$~', $path, $m) && $method === 'POST') {
    $subId = (int) ($_POST['user_id'] ?? 0);
    $subUser = $subId ? row('SELECT id, substitute_for FROM users WHERE id = ?', [$subId]) : null;
    if ($subUser) {
      q('INSERT INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE for_user_id = VALUES(for_user_id)',
        [$m[1], $subId, $subUser['substitute_for'], $me['id']]);
      flash(t('fl_sub_requested'));
    }
    back('/intern/termine');
  }
  if (preg_match('~^/intern/termine/(\d+)/ersatz/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    q('DELETE FROM substitute_requests WHERE event_id = ? AND user_id = ?', [$m[1], $m[2]]);
    flash(t('fl_sub_withdrawn'));
    back('/intern/termine');
  }

  if (preg_match('~^/intern/kommentare/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    $c = row('SELECT * FROM comments WHERE id = ?', [$m[1]]);
    if ($c && ((int) $c['user_id'] === (int) $me['id'] || $me['role'] === 'admin')) {
      q('DELETE FROM comments WHERE id = ?', [$m[1]]);
    }
    back('/intern/termine');
  }

  // ---------- Songs ----------
  // Ersatzleute sehen nur die Songs, die auf den Setlists ihrer Termine stehen
  $songList = function () use ($today, $me): array {
    [$songWhere, $songParams] = visible_clause(visible_song_ids($me), 's.id');
    return rows(
      "SELECT s.*,
         (SELECT COUNT(*) FROM setlist_songs ss WHERE ss.song_id = s.id) AS setlist_count,
         (SELECT COUNT(DISTINCT e.id) FROM setlist_songs ss2 JOIN events e ON e.setlist_id = ss2.setlist_id
          WHERE ss2.song_id = s.id AND e.date < ?) AS played_count
       FROM songs s WHERE 1 = 1$songWhere
       ORDER BY FIELD(s.status, 'aktiv', 'in_arbeit', 'vorschlag', 'abgewiesen', 'archiv'), s.title",
      [$today, ...$songParams]
    );
  };
  if ($path === '/intern/songs' && $method === 'GET') {
    view('intern/songs', ['title' => t('inav_songs'), 'songs' => $songList(), 'edit' => null,
      'ratings' => song_ratings($me['id'])]);
  }
  // Ein Lied zum Lesen: Text, Tonart, Tempo, Noten. Das ist die Seite, die auf
  // dem Notenständer liegt; geändert wird unter /edit.
  if (preg_match('~^/intern/songs/(\d+)$~', $path, $m) && $method === 'GET') {
    $songOne = row('SELECT * FROM songs WHERE id = ?', [$m[1]]);
    // Die Liste filtert über visible_song_ids(); eine zweite Route auf denselben
    // Datensatz muss die Prüfung mitnehmen, sonst führt sie daran vorbei.
    if (!$songOne || !may_see_song($me, (int) $m[1])) redirect('/intern/songs');
    view('intern/song', [
      'title' => $songOne['title'],
      'song' => $songOne,
      'songFiles' => files_map('song', [(int) $songOne['id']])[(int) $songOne['id']] ?? [],
      'myChords' => song_chords_mine((int) $songOne['id'], $me['id']),
      'otherChordsCount' => count(array_filter(song_chords_all((int) $songOne['id'], $me['id']), fn($c) => !$c['mine'])),
    ]);
  }
  // Bühne: der Liedtext im Vollbild, groß und selbstlaufend — das Handy als
  // Notenständer. Optional ?sl= gibt die laufende Setlist mit, damit man ohne
  // das Vollbild zu verlassen zum nächsten Lied springen kann (der Wechsel
  // passiert im Browser, sonst bräche das Vollbild und der Offline-Stand).
  if (preg_match('~^/intern/songs/(\d+)/buehne$~', $path, $m) && $method === 'GET') {
    $songOne = row('SELECT id, title, lyrics, tempo FROM songs WHERE id = ?', [$m[1]]);
    if (!$songOne || !may_see_song($me, (int) $m[1])) redirect('/intern/songs');
    $slId = isset($_GET['sl']) ? (int) $_GET['sl'] : 0;
    $stage = [];
    if ($slId) {
      foreach (may_see_setlist($me, $slId) ? setlist_entries($slId) : [] as $entry) {
        if ($entry['is_break'] || $entry['id'] === null) continue; // Pausen und Lücken überspringen
        $stage[] = ['id' => (int) $entry['id'], 'title' => $entry['title'], 'bpm' => song_bpm($entry['tempo']), 'lines' => lyrics_lines($entry['lyrics'])];
      }
    }
    if (!$stage) {
      $stage[] = ['id' => (int) $songOne['id'], 'title' => $songOne['title'], 'bpm' => song_bpm($songOne['tempo']), 'lines' => lyrics_lines($songOne['lyrics'])];
    }
    view('intern/song_buehne', [
      'title' => $songOne['title'],
      'stageSongs' => $stage,
      'startId' => (int) $songOne['id'],
    ]);
  }
  // Noten: derselbe Vollbild-Modus, aber der Notizzettel (Akkorde) in fester
  // Zeichenbreite, damit Akkorde über den Silben stehen bleiben.
  if (preg_match('~^/intern/songs/(\d+)/noten$~', $path, $m) && $method === 'GET') {
    $songOne = row('SELECT id, title, tempo FROM songs WHERE id = ?', [$m[1]]);
    if (!$songOne || !may_see_song($me, (int) $m[1])) redirect('/intern/songs');
    $slId = isset($_GET['sl']) ? (int) $_GET['sl'] : 0;
    $stage = [];
    if ($slId) {
      foreach (may_see_setlist($me, $slId) ? setlist_entries($slId) : [] as $entry) {
        if ($entry['is_break'] || $entry['id'] === null) continue; // Pausen und Lücken überspringen
        $stage[] = noten_stage_entry($entry, $me['id']);
      }
    }
    if (!$stage) $stage[] = noten_stage_entry($songOne, $me['id']);
    view('intern/song_buehne', [
      'title' => $songOne['title'],
      'stageSongs' => $stage,
      'startId' => (int) $songOne['id'],
      'mono' => true,
    ]);
  }
  // Texte einpflegen: mehrere Liedtexte auf einmal. Bewusst als eigene Seite —
  // hier fügt die Band ihre Texte ein, das Werkzeug schreibt keine.
  if ($path === '/intern/songs/lyrics' && $method === 'GET') {
    view('intern/songs_lyrics', [
      'title' => t('song_lyrics_bulk'),
      // Auch hier gilt die Sichtbarkeit: sonst stünden auf einer Seite alle
      // Texte, die die Liste daneben sorgfältig filtert.
      'songs' => (function () use ($me) {
        [$wo, $werte] = visible_clause(visible_song_ids($me));
        return rows("SELECT id, title, artist, lyrics FROM songs
                     WHERE status <> 'archiv' $wo ORDER BY title", $werte);
      })(),
    ]);
  }
  if ($path === '/intern/songs/lyrics' && $method === 'POST') {
    foreach (($_POST['lyrics'] ?? []) as $id => $text) {
      if (!is_string($text)) continue; // verschachtelte Eingaben ignorieren, nicht als "Array" speichern
      q('UPDATE songs SET lyrics = ? WHERE id = ?', [$text, (int) $id]);
    }
    flash(t('song_lyrics_bulk_saved'));
    redirect('/intern/songs/lyrics');
  }
  if (preg_match('~^/intern/songs/(\d+)/edit$~', $path, $m) && $method === 'GET') {
    $edit = row('SELECT * FROM songs WHERE id = ?', [$m[1]]);
    if (!$edit || !may_see_song($me, (int) $m[1])) redirect('/intern/songs');
    view('intern/songs', [
      'title' => t('inav_songs'),
      'ratings' => song_ratings($me['id']),
      'songs' => $songList(),
      'edit' => $edit,
      'songFiles' => files_map('song', [(int) $m[1]])[(int) $m[1]] ?? [],
      'myChords' => song_chords_mine((int) $m[1], $me['id']),
      'otherChords' => array_values(array_filter(song_chords_all((int) $m[1], $me['id']), fn($c) => !$c['mine'])),
    ]);
  }
  if ($path === '/intern/songs' && $method === 'POST') {
    if (($_POST['title'] ?? '') !== '') {
      q('INSERT INTO songs (title, artist, composer, gema_werknr, song_key, tempo, duration_sec, status, notes, lyrics) VALUES (?,?,?,?,?,?,?,?,?,?)', song_values());
      song_chords_set((int) $db->lastInsertId(), $me['id'], $_POST['chords'] ?? '');
    }
    redirect('/intern/songs');
  }
  if (preg_match('~^/intern/songs/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'update') {
      q('UPDATE songs SET title=?, artist=?, composer=?, gema_werknr=?, song_key=?, tempo=?, duration_sec=?, status=?, notes=?, lyrics=? WHERE id=?', [...song_values(), $m[1]]);
      song_chords_set((int) $m[1], $me['id'], $_POST['chords'] ?? '');
    } else {
      // Songs in bereits gespielten Setlists sind Teil der Historie und bleiben erhalten
      $played = row('SELECT 1 FROM setlist_songs ss JOIN events e ON e.setlist_id = ss.setlist_id
                     WHERE ss.song_id = ? AND e.date < ? LIMIT 1', [$m[1], $today]);
      if ($played) {
        flash(t('fl_song_played'));
      } else {
        q('DELETE FROM songs WHERE id = ?', [$m[1]]);
        q('DELETE FROM setlist_songs WHERE song_id = ?', [$m[1]]);
        q('DELETE FROM song_chords WHERE song_id = ?', [$m[1]]);
      }
    }
    redirect('/intern/songs');
  }

  // ---------- Setlists ----------
  if ($path === '/intern/setlists' && $method === 'GET') {
    [$slWhere, $slParams] = visible_clause(visible_setlist_ids($me), 's.id');
    view('intern/setlists', ['title' => t('inav_setlists'), 'setlists' => rows(
      "SELECT s.*, COUNT(ss.song_id) AS song_count, COALESCE(SUM(so.duration_sec),0) AS total_sec,
              EXISTS(SELECT 1 FROM events e WHERE e.setlist_id = s.id AND e.date < ?) AS locked
       FROM setlists s LEFT JOIN setlist_songs ss ON ss.setlist_id = s.id LEFT JOIN songs so ON so.id = ss.song_id
       WHERE 1 = 1$slWhere
       GROUP BY s.id ORDER BY s.created_at DESC", [$today, ...$slParams])]);
  }
  if ($path === '/intern/setlists' && $method === 'POST') {
    if (($_POST['name'] ?? '') !== '') q('INSERT INTO setlists (name, notes) VALUES (?,?)', [$_POST['name'], $_POST['notes'] ?? '']);
    redirect('/intern/setlists');
  }
  if (preg_match('~^/intern/setlists/(\d+)$~', $path, $m) && $method === 'GET') {
    $setlist = row('SELECT * FROM setlists WHERE id = ?', [$m[1]]);
    if (!$setlist) redirect('/intern/setlists');
    // Wer die Setlist in der Liste nicht sieht, sieht sie auch nicht einzeln
    if (!may_see_setlist($me, (int) $m[1])) {
      flash(t('fl_no_permission'));
      redirect('/intern/setlists');
    }
    $entries = setlist_entries((int) $m[1]);
    $used = array_filter(array_column($entries, 'id'));
    $notIn = $used ? 'AND id NOT IN (' . implode(',', array_map('intval', $used)) . ')' : '';
    view('intern/setlist_edit', [
      'title' => $setlist['name'],
      'setlist' => $setlist,
      'entries' => $entries,
      'locked' => setlist_locked((int) $m[1]),
      'playedAt' => rows('SELECT e.id, e.title, e.date, v.name AS venue_name FROM events e
                          LEFT JOIN venues v ON v.id = e.venue_id
                          WHERE e.setlist_id = ? ORDER BY e.date', [$m[1]]),
      'available' => rows("SELECT * FROM songs WHERE status IN ('aktiv', 'in_arbeit') $notIn ORDER BY title"),
      'totalSec' => array_sum(array_map(fn($x) => (int) $x['duration_sec'], $entries)),
    ]);
  }
  if (preg_match('~^/intern/setlists/(\d+)/print$~', $path, $m) && $method === 'GET') {
    $setlist = row('SELECT * FROM setlists WHERE id = ?', [$m[1]]);
    if (!$setlist || !may_see_setlist($me, (int) $m[1])) redirect('/intern/setlists');
    view('intern/setlist_print', ['title' => $setlist['name'], 'setlist' => $setlist, 'entries' => setlist_entries((int) $m[1])]);
  }
  if (preg_match('~^/intern/setlists/(\d+)/gema$~', $path, $m) && $method === 'GET') {
    $setlist = row('SELECT * FROM setlists WHERE id = ?', [$m[1]]);
    if (!$setlist || !may_see_setlist($me, (int) $m[1])) redirect('/intern/setlists');
    $event = row('SELECT e.*, v.name AS venue_name, v.city AS venue_city FROM events e
                  LEFT JOIN venues v ON v.id = e.venue_id
                  WHERE e.setlist_id = ? ORDER BY e.date DESC LIMIT 1', [$m[1]]);
    view('intern/setlist_gema', [
      'title' => 'GEMA-Musikfolge',
      'setlist' => $setlist,
      'event' => $event,
      'entries' => array_values(array_filter(setlist_entries((int) $m[1]), fn($x) => !$x['is_break'])),
    ]);
  }
  if (preg_match('~^/intern/setlists/(\d+)/(delete|copy|add|addpause|addzugabe|remove|move)$~', $path, $m) && $method === 'POST') {
    [$_, $id, $action] = $m;
    if ($action !== 'copy' && setlist_locked((int) $id)) {
      flash(t('fl_setlist_locked'));
      redirect("/intern/setlists/$id");
    }
    if ($action === 'delete') {
      q('DELETE FROM setlists WHERE id = ?', [$id]);
      q('DELETE FROM setlist_songs WHERE setlist_id = ?', [$id]);
      q('UPDATE events SET setlist_id = NULL WHERE setlist_id = ?', [$id]);
      redirect('/intern/setlists');
    }
    if ($action === 'copy') {
      $src = row('SELECT * FROM setlists WHERE id = ?', [$id]);
      if ($src) {
        q('INSERT INTO setlists (name, notes) VALUES (?,?)', [$src['name'] . ' (Kopie)', $src['notes']]);
        $newId = (int) $GLOBALS['db']->lastInsertId();
        q('INSERT INTO setlist_songs (setlist_id, song_id, is_break, position)
           SELECT ?, song_id, is_break, position FROM setlist_songs WHERE setlist_id = ?', [$newId, $id]);
        redirect("/intern/setlists/$newId");
      }
      redirect('/intern/setlists');
    }
    $nextPos = fn(): int => (int) row('SELECT COALESCE(MAX(position),0) AS p FROM setlist_songs WHERE setlist_id = ?', [$id])['p'] + 1;
    if ($action === 'add' && ($_POST['song_id'] ?? '') !== '') {
      q('INSERT INTO setlist_songs (setlist_id, song_id, is_break, position) VALUES (?,?,0,?)', [$id, $_POST['song_id'], $nextPos()]);
    }
    if ($action === 'addpause') {
      q('INSERT INTO setlist_songs (setlist_id, song_id, is_break, position) VALUES (?,NULL,1,?)', [$id, $nextPos()]);
    }
    if ($action === 'addzugabe') {
      q('INSERT INTO setlist_songs (setlist_id, song_id, is_break, position) VALUES (?,NULL,2,?)', [$id, $nextPos()]);
    }
    if ($action === 'remove') {
      q('DELETE FROM setlist_songs WHERE setlist_id = ? AND id = ?', [$id, $_POST['item_id'] ?? 0]);
      $i = 1;
      foreach (rows('SELECT id FROM setlist_songs WHERE setlist_id = ? ORDER BY position', [$id]) as $r) {
        q('UPDATE setlist_songs SET position = ? WHERE id = ?', [$i++, $r['id']]);
      }
    }
    if ($action === 'move') {
      $list = rows('SELECT id, position FROM setlist_songs WHERE setlist_id = ? ORDER BY position', [$id]);
      $idx = array_search((string) ($_POST['item_id'] ?? ''), array_map(fn($r) => (string) $r['id'], $list), true);
      $swap = ($_POST['dir'] ?? '') === 'up' ? $idx - 1 : $idx + 1;
      if ($idx !== false && $swap >= 0 && $swap < count($list)) {
        q('UPDATE setlist_songs SET position = ? WHERE id = ?', [$list[$swap]['position'], $list[$idx]['id']]);
        q('UPDATE setlist_songs SET position = ? WHERE id = ?', [$list[$idx]['position'], $list[$swap]['id']]);
      }
    }
    redirect("/intern/setlists/$id");
  }

  // ---------- Aufgaben ----------
  if ($path === '/intern/aufgaben' && $method === 'GET') {
    view('intern/aufgaben', [
      'title' => t('task_title'),
      'tasks' => rows("SELECT t.*, u.name AS assignee FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
                       ORDER BY t.status = 'erledigt', CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date"),
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
    ]);
  }
  if ($path === '/intern/aufgaben' && $method === 'POST') {
    if (($_POST['title'] ?? '') !== '') {
      q('INSERT INTO tasks (title, notes, assigned_to, due_date, created_by) VALUES (?,?,?,?,?)',
        [$_POST['title'], $_POST['notes'] ?? '', ($_POST['assigned_to'] ?? '') !== '' ? $_POST['assigned_to'] : null, $_POST['due_date'] ?? '', $me['id']]);
    }
    redirect('/intern/aufgaben');
  }
  if (preg_match('~^/intern/aufgaben/(\d+)/(toggle|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'toggle') {
      q("UPDATE tasks SET status = CASE status WHEN 'offen' THEN 'erledigt' ELSE 'offen' END WHERE id = ?", [$m[1]]);
      back('/intern/aufgaben');
    }
    q('DELETE FROM tasks WHERE id = ?', [$m[1]]);
    redirect('/intern/aufgaben');
  }

  // ---------- Fotos ----------
  if ($path === '/intern/fotos' && $method === 'GET') {
    // Archiv (#200): Die Galerie zeigt das Unarchivierte; ?archiv=1 zeigt nur
    // das Archiv. Zwei Sichten, keine Mischung — halb sichtbare Bilder gibt es
    // nicht, und die Zahl am Umschalter sagt, was auf der anderen Seite liegt.
    $imArchiv = ($_GET['archiv'] ?? '') === '1';
    // Filter und Suche (#201–#204). Alles serverseitig und additiv: Jede
    // Bedingung engt ein, und was im Formular steht, entscheidet nicht — ein
    // unbekanntes Mitglied oder Schlagwort filtert schlicht auf nichts.
    $fTag = tag_norm((string) ($_GET['tag'] ?? ''));
    $fPresse = ($_GET['presse'] ?? '') === '1';
    $fPerson = (int) ($_GET['person'] ?? 0);
    $fSuche = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $gefiltert = $fTag !== '' || $fPresse || $fPerson > 0 || $fSuche !== '';
    $wo = [];
    $werte = [];
    // Die Suche sieht auch ins Archiv (#204): aus der Galerie genommen heißt
    // nicht aus dem Gedächtnis genommen. Ohne Suche gelten die zwei Sichten.
    if ($fSuche === '') $wo[] = 'p.archived_at IS ' . ($imArchiv ? 'NOT NULL' : 'NULL');
    if ($fTag !== '') { $wo[] = 'EXISTS (SELECT 1 FROM photo_tags ft WHERE ft.photo_id = p.id AND ft.tag = ?)'; $werte[] = $fTag; }
    if ($fPresse) $wo[] = 'p.is_press = 1';
    if ($fPerson > 0) { $wo[] = 'EXISTS (SELECT 1 FROM photo_people fp WHERE fp.photo_id = p.id AND fp.user_id = ?)'; $werte[] = $fPerson; }
    if ($fSuche !== '') {
      $like = '%' . addcslashes($fSuche, '\\%_') . '%';
      $wo[] = '(p.caption LIKE ? OR p.source LIKE ? OR e.title LIKE ?
                OR EXISTS (SELECT 1 FROM photo_tags qt WHERE qt.photo_id = p.id AND qt.tag LIKE ?)
                OR EXISTS (SELECT 1 FROM photo_people qp JOIN users qu ON qu.id = qp.user_id
                           WHERE qp.photo_id = p.id AND qu.name LIKE ?))';
      array_push($werte, $like, $like, $like, $like, $like);
    }
    $photos = rows('SELECT p.*, u.name AS uploader, e.title AS event_title, e.date AS event_date
                    FROM photos p LEFT JOIN users u ON u.id = p.uploaded_by
                    LEFT JOIN events e ON e.id = p.event_id
                    WHERE ' . implode(' AND ', $wo) . '
                    ORDER BY p.created_at DESC', $werte);
    // Schlagwörter und Personen der gezeigten Bilder in einem Griff — eine
    // Abfrage je Bild wären bei sechshundert Bildern sechshundert Abfragen.
    $photoIds = array_map(fn($r) => (int) $r['id'], $photos);
    $tagsJe = [];
    $leuteJe = [];
    if ($photoIds) {
      $ph = implode(',', array_fill(0, count($photoIds), '?'));
      foreach (rows("SELECT photo_id, tag FROM photo_tags WHERE photo_id IN ($ph) ORDER BY tag", $photoIds) as $z) {
        $tagsJe[(int) $z['photo_id']][] = $z['tag'];
      }
      foreach (rows("SELECT pp.photo_id, pp.user_id, u.name FROM photo_people pp
                     JOIN users u ON u.id = pp.user_id WHERE pp.photo_id IN ($ph) ORDER BY u.name", $photoIds) as $z) {
        $leuteJe[(int) $z['photo_id']][] = ['id' => (int) $z['user_id'], 'name' => $z['name']];
      }
    }
    foreach ($photos as &$phM) {
      $phM['tags'] = $tagsJe[(int) $phM['id']] ?? [];
      $phM['people'] = $leuteJe[(int) $phM['id']] ?? [];
    }
    unset($phM);
    $photoEvents = rows('SELECT e.id, e.title, e.date, v.lat, v.lng FROM events e
                         LEFT JOIN venues v ON v.id = e.venue_id ORDER BY e.date DESC');
    // Vorschlag je unzugeordnetem Foto mit Aufnahmedatum: der Termin an dem Tag,
    // bei mehreren am selben Tag der mit dem nächstgelegenen Ort (Foto-GPS).
    foreach ($photos as &$ph) {
      $ph['suggested'] = (!$ph['event_id'] && $ph['taken_at']) ? photo_suggest_event($ph, $photoEvents) : null;
    }
    unset($ph);
    // Neu-Markierung (#195): neu ist, was seit dem letzten Besuch dieses
    // Mitglieds dazugekommen ist. Beim allerersten Besuch wird nichts markiert —
    // sonst wäre die ganze Galerie neu, und das sagt nichts.
    $photoSeen = $me['photos_seen_at'] ?? row('SELECT photos_seen_at FROM users WHERE id = ?', [$me['id']])['photos_seen_at'] ?? null;
    foreach ($photos as &$phN) {
      $phN['is_new'] = $photoSeen !== null && $phN['created_at'] > $photoSeen;
    }
    unset($phN);
    // Erst nach dem Berechnen setzen, sonst wäre schon der eigene Aufruf zu spät.
    // Und nur in der ungefilterten Galerie: Ein Suchtreffer zeigt nicht alles,
    // und was nie zu sehen war, darf nicht als gesehen gelten (#204).
    if (!$gefiltert && !$imArchiv) q('UPDATE users SET photos_seen_at = NOW() WHERE id = ?', [$me['id']]);
    // Nach Termin gruppieren (#196): Was zugeordnet ist, gehört in seinen Ordner.
    // Die Unzugeordneten stehen oben, denn das ist der Stapel, an dem gearbeitet
    // wird. Innerhalb eines Ordners bleibt die Reihenfolge nach Datum.
    $photoOrdner = ['' => ['title' => null, 'date' => null, 'photos' => []]];
    foreach ($photos as $ph) {
      $schluessel = $ph['event_id'] ? (string) (int) $ph['event_id'] : '';
      if (!isset($photoOrdner[$schluessel])) {
        $photoOrdner[$schluessel] = ['title' => $ph['event_title'], 'date' => $ph['event_date'], 'photos' => []];
      }
      $photoOrdner[$schluessel]['photos'][] = $ph;
    }
    // Ohne unzugeordnete Bilder auch keinen leeren Ordner dafür zeigen.
    if (!$photoOrdner['']['photos']) unset($photoOrdner['']);
    // Die Ordner nach Termindatum, das Neueste zuerst; die Unzugeordneten bleiben
    // oben, weil sie kein Datum haben und die Arbeit sind.
    uasort($photoOrdner, fn($a, $b) => ($b['date'] ?? '9999') <=> ($a['date'] ?? '9999'));
    // Serien einklappen (#198): Von einem Stapel bleibt das Titelbild mit der
    // Zahl daran. Erst nach dem Ordnen, damit die Reihenfolge im Ordner steht.
    foreach ($photoOrdner as $s => $o) {
      // Die Zahl in der Überschrift zählt Bilder, nicht Kacheln — sonst würde
      // ein Ordner mit vierzig Bildern in einer Serie plötzlich „1" behaupten.
      $photoOrdner[$s]['total'] = count($o['photos']);
      // Gefiltert wird nicht eingeklappt: Der Treffer kann mitten in einer
      // Serie liegen, und eine Kachel, die ihn verdeckt, wäre kein Treffer.
      // Abgeschaltete Serien (#212) klappen nie ein — dann ist jedes Bild
      // seine eigene Kachel, und die Abzeichen entstehen gar nicht erst.
      $photoOrdner[$s]['photos'] = ($gefiltert || !stacks_enabled()) ? $o['photos'] : stacks_collapse($o['photos']);
    }
    view('intern/fotos', ['title' => $imArchiv ? t('photo_archive_title') : t('inav_fotos'),
                          'photos' => $photos, 'events' => $photoEvents,
                          'limits' => upload_limits(), 'ordner' => $photoOrdner,
                          'herkunft' => photo_folder_agg($photos), 'im_archiv' => $imArchiv,
                          'archiv_zahl' => (int) row('SELECT COUNT(*) n FROM photos WHERE archived_at IS ' . ($imArchiv ? 'NULL' : 'NOT NULL'))['n'],
                          'alle_tags' => photo_tags_all(), 'gefiltert' => $gefiltert,
                          'f_tag' => $fTag, 'f_presse' => $fPresse, 'f_person' => $fPerson, 'f_suche' => $fSuche,
                          'presse_zahl' => (int) row('SELECT COUNT(*) n FROM photos WHERE is_press = 1 AND archived_at IS NULL')['n'],
                          'members' => rows('SELECT id, name FROM users ORDER BY name')]);
  }
  // Eine Serie aufmachen (#198). Eigene Seite statt Aufklappen: Die Kacheln
  // haben je eigene Formulare, und das Blättern in der Großansicht bleibt so
  // innerhalb der Serie. Ohne JavaScript funktioniert es genauso.
  if (preg_match('~^/intern/fotos/stapel/(\d+)$~', $path, $m) && $method === 'GET') {
    if (!stacks_enabled()) redirect('/intern/fotos');
    $stapel = (int) $m[1];
    $bilder = rows('SELECT p.*, u.name AS uploader, e.title AS event_title, e.date AS event_date
                    FROM photos p LEFT JOIN users u ON u.id = p.uploaded_by
                    LEFT JOIN events e ON e.id = p.event_id
                    WHERE p.stack_id = ? ORDER BY p.taken_at, p.id', [$stapel]);
    if (!$bilder) { flash(t('photo_stack_gone')); redirect('/intern/fotos'); }
    view('intern/fotos_stapel', ['title' => str_replace('%1', (string) count($bilder), t('photo_stack_title')),
                                 'photos' => $bilder, 'stack' => $stapel,
                                 'events' => rows('SELECT id, title, date FROM events ORDER BY date DESC')]);
  }
  // Titelbild einer Serie von Hand wählen. Die Markierung überlebt das
  // Neurechnen, solange das Bild in seiner Serie bleibt.
  if (preg_match('~^/intern/fotos/(\d+)/titelbild$~', $path, $m) && $method === 'POST') {
    if (!stacks_enabled()) redirect('/intern/fotos');
    $foto = row('SELECT id, stack_id FROM photos WHERE id = ?', [$m[1]]);
    if ($foto && $foto['stack_id']) {
      $alt = (int) $foto['stack_id'];
      $neu = (int) $foto['id'];
      q('UPDATE photos SET stack_id = ?, stack_cover = 0 WHERE stack_id = ?', [$neu, $alt]);
      q('UPDATE photos SET stack_cover = 1 WHERE id = ?', [$neu]);
      flash(t('fl_photo_stack_cover'));
      redirect('/intern/fotos/stapel/' . $neu);
    }
    redirect('/intern/fotos');
  }
  // Schlagwort an einem Bild setzen oder entfernen (#201).
  if (preg_match('~^/intern/fotos/(\d+)/tag$~', $path, $m) && $method === 'POST') {
    $wort = tag_norm((string) ($_POST['tag'] ?? ''));
    if ($wort !== '' && row('SELECT 1 FROM photos WHERE id = ?', [$m[1]])) {
      if (isset($_POST['entfernen'])) {
        q('DELETE FROM photo_tags WHERE photo_id = ? AND tag = ?', [$m[1], $wort]);
      } else {
        q('INSERT IGNORE INTO photo_tags (photo_id, tag) VALUES (?,?)', [$m[1], $wort]);
      }
    }
    back('/intern/fotos');
  }
  // Ein Schlagwort für alles Angehakte (#201) — angehakte Serien-Kacheln meinen
  // ihre Serie, wie überall.
  if ($path === '/intern/fotos/massentag' && $method === 'POST') {
    $wort = tag_norm((string) ($_POST['tag'] ?? ''));
    $weg = ($_POST['mode'] ?? '') === 'unset';
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['pick'] ?? [])))));
    if ($wort === '') { flash(t('fl_photo_tag_empty')); redirect('/intern/fotos'); }
    $zahl = 0;
    if ($ids) {
      $platz = implode(',', array_fill(0, count($ids), '?'));
      $alle = array_unique(array_merge(
        array_map(fn($r) => (int) $r['id'], rows("SELECT id FROM photos WHERE id IN ($platz)", $ids)),
        stacks_enabled()
          ? array_map(fn($r) => (int) $r['id'], rows("SELECT id FROM photos WHERE stack_id IN ($platz)", $ids))
          : []));
      foreach ($alle as $pid) {
        if ($weg) q('DELETE FROM photo_tags WHERE photo_id = ? AND tag = ?', [$pid, $wort]);
        else q('INSERT IGNORE INTO photo_tags (photo_id, tag) VALUES (?,?)', [$pid, $wort]);
        $zahl++;
      }
    }
    flash($zahl
      ? str_replace(['%1', '%2'], [(string) $zahl, $wort], $weg ? t('fl_photo_tag_removed') : t('fl_photo_tag'))
      : t('fl_photo_mass_nothing'));
    redirect('/intern/fotos');
  }
  // Fürs Rausgeben markieren (#202). Je Bild und ohne Serien-Ausweitung: Die
  // Presse-Auswahl meint DIESES Foto — aus einer Serie nimmt man das beste,
  // nicht alle fünfunddreißig.
  if (preg_match('~^/intern/fotos/(\d+)/presse$~', $path, $m) && $method === 'POST') {
    q('UPDATE photos SET is_press = 1 - is_press WHERE id = ?', [$m[1]]);
    back('/intern/fotos');
  }
  // Wer ist auf dem Bild (#203). Von Hand und je Bild — kein Erkennen, kein
  // Raten: Ein falsch benannter Mensch ist schlimmer als ein unbenannter.
  if (preg_match('~^/intern/fotos/(\d+)/person$~', $path, $m) && $method === 'POST') {
    $wer = (int) ($_POST['user_id'] ?? 0);
    if ($wer > 0 && row('SELECT 1 FROM photos WHERE id = ?', [$m[1]]) && row('SELECT 1 FROM users WHERE id = ?', [$wer])) {
      if (isset($_POST['entfernen'])) {
        q('DELETE FROM photo_people WHERE photo_id = ? AND user_id = ?', [$m[1], $wer]);
      } else {
        q('INSERT IGNORE INTO photo_people (photo_id, user_id) VALUES (?,?)', [$m[1], $wer]);
      }
    }
    back('/intern/fotos');
  }
  // Archivieren und Zurückholen (#200). Eine Kachel, die für eine Serie steht,
  // archiviert die Serie — wie bei der Termin-Zuordnung: Was zu sehen war, wird
  // gefasst, nicht ein unsichtbarer Teil davon.
  if (preg_match('~^/intern/fotos/(\d+)/archiv$~', $path, $m) && $method === 'POST') {
    $foto = row('SELECT id, stack_id, archived_at FROM photos WHERE id = ?', [$m[1]]);
    if ($foto && $foto['archived_at'] !== null) {
      photo_archive((int) $foto['id'], false);
      flash(t('fl_photo_restored'));
      back('/intern/fotos?archiv=1');
    }
    if ($foto) {
      $glieder = (!empty($_POST['whole_stack']) && stacks_enabled() && $foto['stack_id'])
        ? array_map(fn($r) => (int) $r['id'], rows('SELECT id FROM photos WHERE stack_id = ?', [(int) $foto['stack_id']]))
        : [(int) $foto['id']];
      foreach ($glieder as $gid) photo_archive($gid, true);
      flash(str_replace('%1', (string) count($glieder), t('fl_photo_archived')));
    }
    back('/intern/fotos');
  }
  // Viele auf einmal ins Archiv — für den Tag, an dem 116 Messenger-Kopien
  // neben ihren Originalen liegen. Angehakte Titelbilder meinen ihre Serie.
  if ($path === '/intern/fotos/massenarchiv' && $method === 'POST') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['pick'] ?? [])))));
    $zahl = 0;
    if ($ids) {
      $platz = implode(',', array_fill(0, count($ids), '?'));
      $echte = array_map(fn($r) => (int) $r['id'],
        rows("SELECT id FROM photos WHERE id IN ($platz)", $ids));
      $serie = stacks_enabled() ? array_map(fn($r) => (int) $r['id'],
        rows("SELECT id FROM photos WHERE stack_id IN ($platz)", $ids)) : [];
      foreach (array_unique(array_merge($echte, $serie)) as $pid) {
        if (photo_archive($pid, true)) $zahl++;
      }
    }
    flash($zahl ? str_replace('%1', (string) $zahl, t('fl_photo_archived')) : t('fl_photo_mass_nothing'));
    redirect('/intern/fotos');
  }
  if (preg_match('~^/intern/fotos/(\d+)/event$~', $path, $m) && $method === 'POST') {
    $eid = (int) ($_POST['event_id'] ?? 0);
    // Nur echte Termine zuordnen — was im Formular steht, entscheidet nicht.
    // Eine unbekannte ID gilt als "kein Termin".
    if ($eid && !row('SELECT 1 FROM events WHERE id = ?', [$eid])) $eid = 0;
    // Von der Galerie aus steht eine Kachel für die ganze Serie — dort gilt die
    // Zuordnung für alle. Auf der Serienseite gilt sie für das einzelne Bild,
    // sonst könnte man ein verrutschtes Foto nie mehr allein zurechtrücken.
    $ganze = !empty($_POST['whole_stack']) && stacks_enabled();
    $stapel = $ganze ? (int) (row('SELECT stack_id FROM photos WHERE id = ?', [$m[1]])['stack_id'] ?? 0) : 0;
    if ($stapel) {
      q('UPDATE photos SET event_id = ? WHERE stack_id = ?', [$eid ?: null, $stapel]);
    } else {
      q('UPDATE photos SET event_id = ? WHERE id = ?', [$eid ?: null, $m[1]]);
    }
    back('/intern/fotos');
  }
  // Viele Fotos auf einen Termin. Von einem Auftritt kommen dreißig Bilder, und
  // dreißigmal dieselbe Auswahl zu treffen ist keine Arbeit, die ein Mensch tun
  // sollte. Angehakt wird oben, der Termin einmal gewählt.
  if ($path === '/intern/fotos/termin' && $method === 'POST') {
    $eid = (int) ($_POST['event_id'] ?? 0);
    if ($eid && !row('SELECT 1 FROM events WHERE id = ?', [$eid])) $eid = 0;
    // Nur Zahlen, nur vorhandene Fotos: Was im Formular steht, entscheidet nicht.
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['pick'] ?? [])))));
    $wieViele = 0;
    if ($ids) {
      $platz = implode(',', array_fill(0, count($ids), '?'));
      $echte = array_column(rows("SELECT id FROM photos WHERE id IN ($platz)", $ids), 'id');
      // Ein angehaktes Titelbild meint die ganze Serie (#198) — die Kachel steht
      // in der Galerie für alle ihre Bilder, und nur die eine zuzuordnen wäre
      // etwas anderes als das, was da zu sehen war.
      $mitSerie = stacks_enabled() ? rows("SELECT id FROM photos WHERE stack_id IN ($platz)", $ids) : [];
      $echte = array_values(array_unique(array_merge(
        array_map('intval', $echte), array_map(fn($r) => (int) $r['id'], $mitSerie))));
      foreach ($echte as $pid) {
        q('UPDATE photos SET event_id = ? WHERE id = ?', [$eid ?: null, (int) $pid]);
        $wieViele++;
      }
    }
    flash($wieViele
      ? str_replace('%1', (string) $wieViele, $eid ? t('fl_photo_mass') : t('fl_photo_mass_none'))
      : t('fl_photo_mass_nothing'));
    redirect('/intern/fotos');
  }
  // Einen ganzen Herkunftsordner einem Termin geben (#208). Der Ordner sagt
  // schon, was zusammengehört — 518 Bilder anzuhaken ist keine Arbeit für
  // Menschen. Gefasst wird alles in und unter dem Ordner.
  if ($path === '/intern/fotos/ordner' && $method === 'POST') {
    $eid = (int) ($_POST['event_id'] ?? 0);
    if ($eid && !row('SELECT 1 FROM events WHERE id = ?', [$eid])) $eid = 0;
    $ordner = trim((string) ($_POST['folder'] ?? ''), '/');
    // Nur ein Ordner, den es in den Herkunftspfaden wirklich gibt — was im
    // Formular steht, entscheidet nicht.
    $bekannt = array_column(photo_folder_agg(rows('SELECT source, taken_at FROM photos')), 'path');
    if ($ordner === '' || !in_array($ordner, $bekannt, true)) {
      flash(t('fl_photo_folder_unknown'));
      redirect('/intern/fotos');
    }
    // LIKE-Zeichen im Ordnernamen sind Zeichen, keine Platzhalter.
    $muster = addcslashes($ordner, '\%_') . '/%';
    $treffer = (int) row('SELECT COUNT(*) n FROM photos WHERE source LIKE ?', [$muster])['n'];
    q('UPDATE photos SET event_id = ? WHERE source LIKE ?', [$eid ?: null, $muster]);
    flash(str_replace(['%1', '%2'], [(string) $treffer, $ordner],
      $eid ? t('fl_photo_folder') : t('fl_photo_folder_none')));
    redirect('/intern/fotos');
  }
  if ($path === '/intern/fotos' && $method === 'POST') {
    // Zu große Absendung: PHP hat $_POST und $_FILES weggeworfen, wir bekommen
    // einen leeren POST. Ohne diesen Zweig täte die Seite schlicht nichts (#194).
    if (upload_too_big()) {
      $gr = upload_limits();
      flash(str_replace('%1', fmt_bytes($gr['per_request']), t('fl_photo_too_big_request')));
      redirect('/intern/fotos');
    }
    // Gezählt wird, was ankommt, was zu groß war und was kein Bild ist. Stilles
    // Überspringen war der eigentliche Fehler: Die Seite kam erfolgreich zurück,
    // und dass die Hälfte fehlte, merkte man nur durch Nachzählen (#194).
    $fotoOk = $fotoGross = $fotoKeinBild = $fotoFehler = 0;
    $fotoQuellen = [];
    $fotoGrenze = upload_limits()['per_file'];
    foreach ($_FILES['photos']['tmp_name'] ?? [] as $i => $tmp) {
      $fehler = (int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_OK);
      if ($fehler === UPLOAD_ERR_INI_SIZE || $fehler === UPLOAD_ERR_FORM_SIZE) { $fotoGross++; continue; }
      if (upload_rejected($fehler)) { $fotoFehler++; continue; }
      if (!is_uploaded_file($tmp)) { $fotoFehler++; continue; }
      if ($fotoGrenze > 0 && ($_FILES['photos']['size'][$i] ?? 0) > $fotoGrenze) { $fotoGross++; continue; }
      $mime = mime_content_type($tmp) ?: '';
      if (!str_starts_with($mime, 'image/')) { $fotoKeinBild++; continue; }
      // Der Name sagt nichts: die Zugriffsprüfung ist der Schutz, aber ein
      // sprechender Name wäre eine zweite Tür, falls sie je umgangen wird.
      // Wie die Datei ursprünglich hieß, steht in der Bildunterschrift.
      $photoExt = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION) ?: 'jpg'));
      $safe = 'foto_' . bin2hex(random_bytes(16)) . '.' . $photoExt;
      if (move_uploaded_file($tmp, UPLOADS_DIR . '/' . $safe)) {
        // Aufnahmedatum und GPS aus den EXIF-Daten mitnehmen — für den Vorschlag,
        // welchem Termin das Foto gehört. Zugeordnet wird nie automatisch.
        $exif = photo_exif(UPLOADS_DIR . '/' . $safe);
        // Die Prüfsumme VOR dem Entfernen der EXIF-Daten (#199): So ist sie die
        // Summe der Datei, wie sie ankam — und stimmt mit der überein, die
        // OneDrive für dasselbe Original nennt (#206). Nach dem Entfernen wäre
        // es die Summe einer Datei, die es nur hier gibt.
        $fotoSumme = (string) (hash_file('sha256', UPLOADS_DIR . '/' . $safe) ?: '');
        // Erst auslesen, dann aus der Datei entfernen: die Angaben stehen ab
        // jetzt in der Datenbank und sind nur intern sichtbar. In der Datei
        // gingen sie mit jedem öffentlichen Foto mit hinaus — und ein
        // Proberaum ist oft eine Privatadresse.
        photo_strip_exif(UPLOADS_DIR . '/' . $safe);
        // Die Herkunft mitschreiben (#197). Der Browser liefert den Dateinamen;
        // wählt jemand einen ganzen Ordner, steht der relative Pfad daneben und
        // wird bevorzugt — dann sieht man, aus welchem Ordner das Bild kam.
        $herkunft = trim((string) ($_POST['paths'][$i] ?? '')) ?: (string) $_FILES['photos']['name'][$i];
        q('INSERT INTO photos (filename, caption, is_public, uploaded_by, taken_at, lat, lng, source, checksum) VALUES (?,?,?,?,?,?,?,?,?)',
          [$safe, $_POST['caption'] ?? '', isset($_POST['is_public']) ? 1 : 0, $me['id'],
           $exif['taken_at'], $exif['lat'], $exif['lng'], mb_substr($herkunft, 0, 400), $fotoSumme]);
        $fotoQuellen[] = stack_key(['source' => $herkunft, 'uploaded_by' => $me['id']]);
        $fotoOk++;
      } else {
        $fotoFehler++;
      }
    }
    // Serien der eben berührten Quellen neu rechnen (#198). Nur diese, nicht die
    // ganze Galerie: Ein Upload sagt nichts über Ordner, die er nicht anfasst.
    if ($fotoOk && stacks_enabled()) stacks_rebuild(array_values(array_unique($fotoQuellen)));
    // Eine Meldung, die zählt statt zu beruhigen. Der Hinweis auf die Grenze der
    // Dateizahl kommt nur, wenn genau sie erreicht wurde — dann hat der Browser
    // mehr geschickt, als PHP annimmt, und der Rest ist gar nicht angekommen.
    $meldung = [str_replace('%1', (string) $fotoOk, t('fl_photo_stored'))];
    if ($fotoGross) $meldung[] = str_replace(['%1', '%2'], [(string) $fotoGross, fmt_bytes($fotoGrenze)], t('fl_photo_skipped_big'));
    if ($fotoKeinBild) $meldung[] = str_replace('%1', (string) $fotoKeinBild, t('fl_photo_skipped_nonimage'));
    if ($fotoFehler) $meldung[] = str_replace('%1', (string) $fotoFehler, t('fl_photo_skipped_error'));
    $fotoGesamt = count($_FILES['photos']['tmp_name'] ?? []);
    if ($fotoGesamt >= upload_limits()['max_files']) {
      $meldung[] = str_replace('%1', (string) upload_limits()['max_files'], t('fl_photo_cap'));
    }
    flash(implode(' ', $meldung));
    redirect('/intern/fotos');
  }
  if (preg_match('~^/intern/fotos/(\d+)/hintergrund$~', $path, $m) && $method === 'POST') {
    require_admin();
    $p = row('SELECT * FROM photos WHERE id = ?', [$m[1]]);
    if ($p && is_file(UPLOADS_DIR . '/' . $p['filename'])) {
      // Kopie anlegen, damit Galerie-Foto und Hintergrund unabhängig bleiben
      $ext = strtolower(pathinfo($p['filename'], PATHINFO_EXTENSION) ?: 'jpg');
      $name = 'background_' . time() . '.' . $ext;
      if (copy(UPLOADS_DIR . '/' . $p['filename'], UPLOADS_DIR . '/' . $name)) {
        // Der Hintergrund ist immer öffentlich sichtbar — Aufnahmedaten haben
        // in der Kopie also erst recht nichts zu suchen.
        photo_strip_exif(UPLOADS_DIR . '/' . $name);
        $old = setting('background_file');
        if ($old) @unlink(UPLOADS_DIR . '/' . $old);
        set_setting('background_file', $name);
        flash(t('fl_bg_set'));
      }
    }
    redirect('/intern/fotos');
  }
  if (preg_match('~^/intern/fotos/(\d+)/(toggle|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'toggle') {
      q('UPDATE photos SET is_public = 1 - is_public WHERE id = ?', [$m[1]]);
    } else {
      // photo_remove löscht die Datei nur, wenn keine zweite Zeile sie nennt,
      // und richtet die Serie dahinter (#199).
      photo_remove((int) $m[1]);
    }
    back('/intern/fotos');
  }

  // ---------- Veranstaltungsorte ----------
  if ($path === '/intern/orte' && $method === 'GET') {
    // Termin-Historie je Ort: gespielte Termine samt Setlist bleiben dauerhaft dokumentiert
    $eventsByVenue = [];
    foreach (rows('SELECT e.id, e.title, e.date, e.type, e.status, e.venue_id, e.setlist_id, s.name AS setlist_name
                   FROM events e LEFT JOIN setlists s ON s.id = e.setlist_id
                   WHERE e.venue_id IS NOT NULL ORDER BY e.date DESC') as $ev) {
      $eventsByVenue[$ev['venue_id']][] = $ev;
    }
    $venueList = rows('SELECT * FROM venues ORDER BY name');
    view('intern/orte', [
      'title' => t('venues_title'),
      'venues' => $venueList,
      'eventsByVenue' => $eventsByVenue,
      'filesByVenue' => files_map('venue', array_column($venueList, 'id')),
      'today' => $today,
    ]);
  }
  if ($path === '/intern/orte' && $method === 'POST') {
    if (($_POST['name'] ?? '') !== '') {
      q('INSERT INTO venues (name, city, address, notes, contact_name, contact_email, contact_phone, lat, lng) VALUES (?,?,?,?,?,?,?,?,?)', venue_values());
    }
    redirect('/intern/orte');
  }
  if (preg_match('~^/intern/orte/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'update') {
      q('UPDATE venues SET name=?, city=?, address=?, notes=?, contact_name=?, contact_email=?, contact_phone=?, lat=?, lng=? WHERE id=?', [...venue_values(), $m[1]]);
    } else {
      q('DELETE FROM venues WHERE id = ?', [$m[1]]);
      q('UPDATE events SET venue_id = NULL WHERE venue_id = ?', [$m[1]]);
    }
    redirect('/intern/orte');
  }
  // Adress-Suche (Geocoding): fragt OpenStreetMap serverseitig — aber nur, wenn
  // die Band es in den Einstellungen erlaubt hat. Der Browser ruft nur diesen
  // eigenen Endpunkt (die CSP lässt keinen Fremd-Abruf zu); nach außen geht es
  // hier auf dem Server, eine Anfrage je Aufruf (kein Typeahead).
  if ($path === '/intern/geo/suggest' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if (setting('geocoding_enabled') !== '1') { echo json_encode(['off' => true, 'results' => []]); exit; }
    $qStr = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($qStr) < 3) { echo json_encode(['results' => []]); exit; }
    // Nominatim lässt etwa eine Anfrage je Sekunde zu und sperrt nach IP-Adresse.
    // Ohne Bremse könnte eine festgehaltene Taste die Adresse der ganzen
    // Installation sperren lassen — für alle, auch für die Suche, die die Band
    // wirklich braucht. Deshalb je Mitglied gezählt, großzügig genug fürs Tippen.
    if (throttle_blocked('geo', (string) $me['id'], 40, 5)) {
      echo json_encode(['results' => [], 'throttled' => true]);
      exit;
    }
    throttle_note('geo', (string) $me['id']);
    echo json_encode(['results' => geocode_search($qStr)]);
    exit;
  }

  // ---------- Abwesenheiten ----------
  if ($path === '/intern/abwesenheiten' && $method === 'GET') {
    view('intern/abwesenheiten', [
      'title' => t('abs_title'),
      'absences' => rows('SELECT a.*, u.name FROM absences a JOIN users u ON u.id = a.user_id WHERE a.date_to >= ? ORDER BY a.date_from', [$today]),
      'past' => rows('SELECT a.*, u.name FROM absences a JOIN users u ON u.id = a.user_id WHERE a.date_to < ? ORDER BY a.date_from DESC LIMIT 10', [$today]),
    ]);
  }
  if ($path === '/intern/abwesenheiten' && $method === 'POST') {
    $from = $_POST['date_from'] ?? '';
    $to = ($_POST['date_to'] ?? '') !== '' ? $_POST['date_to'] : $from;
    if ($from !== '' && $to >= $from) {
      q('INSERT INTO absences (user_id, date_from, date_to, note) VALUES (?,?,?,?)', [$me['id'], $from, $to, $_POST['note'] ?? '']);
    } else {
      flash(t('fl_period_invalid'));
    }
    redirect('/intern/abwesenheiten');
  }
  if (preg_match('~^/intern/abwesenheiten/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    $a = row('SELECT * FROM absences WHERE id = ?', [$m[1]]);
    if ($a && ((int) $a['user_id'] === (int) $me['id'] || $me['role'] === 'admin')) {
      q('DELETE FROM absences WHERE id = ?', [$m[1]]);
    }
    redirect('/intern/abwesenheiten');
  }

  // ---------- Datei-Anhänge ----------
  if ($path === '/intern/dateien' && $method === 'POST') {
    $type = in_array($_POST['entity_type'] ?? '', ['event', 'song', 'venue', 'download', 'finance', 'equipment'], true) ? $_POST['entity_type'] : null;
    $entityId = (int) ($_POST['entity_id'] ?? 0);
    // Eine Rechnung nennt oft mehrere Geräte. Sie liegt einmal auf der Platte
    // und bekommt je Gerät eine Zeile — nur so steht sie an jedem, ohne dass
    // jemand dieselbe Datei dreimal hochlädt.
    $alsoIds = [];
    if ($type === 'equipment') {
      foreach ((array) ($_POST['also'] ?? []) as $alsoRaw) {
        $alsoId = (int) $alsoRaw;
        if ($alsoId > 0 && $alsoId !== $entityId) $alsoIds[$alsoId] = true;
      }
      $alsoIds = $alsoIds
        ? array_column(rows('SELECT id FROM equipment WHERE id IN ('
            . implode(',', array_fill(0, count($alsoIds), '?')) . ')', array_keys($alsoIds)), 'id')
        : [];
    }
    // Die Sammelrechnung nennt kein Hauptgerät: das erste angehakte übernimmt
    // die Rolle, die übrigen bekommen ihre eigene Zeile wie sonst auch.
    if ($type === 'equipment' && !$entityId && $alsoIds) $entityId = (int) array_shift($alsoIds);
    // Anhängen darf nur, wer das Ziel auch bearbeiten dürfte. Der
    // Frontcontroller prüft den Bereich; ob diese eine Buchung dem
    // Anfragenden gehört, weiß nur may_edit_finance().
    if ($type === 'finance' && !may_edit_finance(row('SELECT private_for FROM finances WHERE id = ?', [$entityId]))) {
      flash(t('fl_no_permission'));
      redirect('/intern/kasse');
    }
    if ($type && ($entityId || $type === 'download')) {
      foreach ($_FILES['files']['tmp_name'] ?? [] as $i => $tmp) {
        if (upload_rejected((int) ($_FILES['files']['error'][$i] ?? UPLOAD_ERR_OK))) continue;
        if (!is_uploaded_file($tmp)) continue;
        if (($_FILES['files']['size'][$i] ?? 0) > 20 * 1024 * 1024) { flash(t('fl_file_too_big')); continue; }
        $orig = $_FILES['files']['name'][$i];
        // Der Zufallsanteil ist nicht die Zugriffsprüfung — die steht in der
        // Route. Er sorgt nur dafür, dass Namen nichts verraten und sich
        // nicht durchzählen lassen.
        // Wie die Datei heißt, steht in original_name — auf der Platte muss
        // es nicht noch einmal stehen.
        $fileExt = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($orig, PATHINFO_EXTENSION)));
        $safe = 'datei_' . bin2hex(random_bytes(16)) . ($fileExt !== '' ? '.' . $fileExt : '');
        if (move_uploaded_file($tmp, FILES_DIR . '/' . $safe)) {
          // Anhänge liegen hinter der Rechteprüfung, aber auf der Platte lagen
          // sie im Klartext. Wer den Schlüssel gesetzt hat, bekommt sie
          // versiegelt; ausgeliefert werden sie in file_serve() wieder offen.
          if (crypt_available()) file_seal_at_rest(FILES_DIR . '/' . $safe);
          foreach ([$entityId, ...$alsoIds] as $target) {
            q('INSERT INTO files (entity_type, entity_id, filename, original_name, size, uploaded_by) VALUES (?,?,?,?,?,?)',
              [$type, (int) $target, $safe, $orig, $_FILES['files']['size'][$i], $me['id']]);
          }
        }
      }
    }
    back('/intern');
  }
  // Ein Bild übernehmen, das schon im Inventar liegt (#184). Die Datei bleibt
  // eine: Sie bekommt nur eine zweite Zeile. Beim Löschen zählt die Route unten
  // die Verweise, deshalb verliert der Zwilling sein Foto nicht.
  if ($path === '/intern/dateien/uebernehmen' && $method === 'POST') {
    $zielId = (int) ($_POST['entity_id'] ?? 0);
    $quelle = row("SELECT f.* FROM files f WHERE f.id = ? AND f.entity_type = 'equipment'",
      [(int) ($_POST['file_id'] ?? 0)]);
    $ziel = $zielId ? row('SELECT id FROM equipment WHERE id = ?', [$zielId]) : null;
    // Nur aus dem eigenen Inventar und nur ein Bild — und nichts, was hier
    // schon hängt. Sonst sammeln sich Zeilen auf dieselbe Datei.
    $istBild = $quelle && in_array(strtolower(pathinfo($quelle['original_name'], PATHINFO_EXTENSION)),
      ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    $schonDa = $quelle && $ziel && row("SELECT id FROM files WHERE entity_type = 'equipment'
        AND entity_id = ? AND filename = ?", [$zielId, $quelle['filename']]);
    if (!$quelle || !$ziel || !$istBild || $schonDa) {
      flash(t('fl_eq_photo_failed'));
    } else {
      q('INSERT INTO files (entity_type, entity_id, filename, original_name, size, uploaded_by) VALUES (?,?,?,?,?,?)',
        ['equipment', $zielId, $quelle['filename'], $quelle['original_name'], (int) $quelle['size'], $me['id']]);
      flash(t('fl_eq_photo_taken'));
    }
    back('/intern/equipment/' . $zielId . '/detail');
  }
  if (preg_match('~^/intern/datei/(\d+)$~', $path, $m) && $method === 'GET') {
    $f = row('SELECT * FROM files WHERE id = ?', [$m[1]]);
    // Der Anhang erbt die Sichtbarkeit seines Gegenstands; unbekannt und
    // gesperrt antworten gleich, damit die Antwort nichts verrät.
    if (!$f || !may_see_file($me, $f) || !is_file(FILES_DIR . '/' . $f['filename'])) {
      http_response_code(404);
      exit('Datei nicht gefunden');
    }
    file_serve($f, isset($_GET['speichern']));
  }
  // Anhang mit Rahmen: In der installierten App gibt es kein Zurück, wenn eine
  // PDF das Fenster übernimmt. Diese Seite trägt den Weg zurück im Inhalt.
  if (preg_match('~^/intern/datei/(\d+)/ansicht$~', $path, $m) && $method === 'GET') {
    $f = row('SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
              WHERE f.id = ?', [$m[1]]);
    if (!$f || !may_see_file($me, $f) || !is_file(FILES_DIR . '/' . $f['filename'])) {
      http_response_code(404);
      exit('Datei nicht gefunden');
    }
    view('intern/datei', [
      'title'    => $f['original_name'],
      'file'     => $f,
      'backUrl'  => file_entity_url($f),
    ]);
  }

  // ---------- Veranstalter-Downloads verwalten ----------
  if ($path === '/intern/downloads' && $method === 'GET') {
    view('intern/downloads', [
      'title' => t('dl_title'),
      'files' => rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                       WHERE f.entity_type = 'download' ORDER BY f.original_name"),
      'mode' => setting('downloads_mode', 'token'),
      'shareUrl' => '/downloads/' . setting('downloads_token'),
    ]);
  }
  if ($path === '/intern/downloads/modus' && $method === 'POST') {
    require_admin();
    $mode = in_array($_POST['mode'] ?? '', ['off', 'token', 'public'], true) ? $_POST['mode'] : 'token';
    set_setting('downloads_mode', $mode);
    if (isset($_POST['new_token'])) set_setting('downloads_token', bin2hex(random_bytes(16)));
    flash(t('fl_dl_saved'));
    redirect('/intern/downloads');
  }
  if (preg_match('~^/intern/datei/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    $f = row('SELECT * FROM files WHERE id = ?', [$m[1]]);
    if ($f && ((int) $f['uploaded_by'] === (int) $me['id'] || $me['role'] === 'admin')) {
      q('DELETE FROM files WHERE id = ?', [$f['id']]);
      // Dieselbe Datei kann an mehreren Geräten hängen (eine Rechnung über
      // mehrere Teile). Von der Platte kommt sie erst, wenn die letzte Zeile
      // weg ist — sonst zeigen die anderen ins Leere.
      $stillUsed = (int) (row('SELECT COUNT(*) AS c FROM files WHERE filename = ?', [$f['filename']])['c'] ?? 0);
      if ($stillUsed === 0) @unlink(FILES_DIR . '/' . $f['filename']);
    }
    back('/intern');
  }

  // ---------- Eigenes Profil ----------
  if ($path === '/intern/profil' && $method === 'GET') {
    view('intern/profil', ['title' => t('mem_my_profile'),
      'profile' => row('SELECT * FROM users WHERE id = ?', [$me['id']])]);
  }
  // Push (#24): Themen-Auswahl (kontoweit) und Geräte-Abos. Ein Abo gehört
  // dem, der es angelegt hat — abmelden kann es nur derselbe.
  if ($path === '/intern/profil/push-topics' && $method === 'POST') {
    $gewaehlt = array_values(array_intersect(PUSH_TOPICS, (array) ($_POST['topics'] ?? [])));
    // Alles abgewählt wird als solches gespeichert, sonst käme beim nächsten
    // Laden wieder alles zurück.
    q('UPDATE users SET push_topics = ? WHERE id = ?',
      [$gewaehlt ? implode(',', $gewaehlt) : PUSH_NICHTS, $me['id']]);
    flash(t('fl_push_saved'));
    redirect('/intern/profil');
  }
  // Wie viele offene Punkte hat der Anfragende? Die Seite holt sich das beim
  // Öffnen und setzt damit die Zahl am Symbol — ohne diesen Abruf bliebe sie
  // auf dem Stand der letzten Mitteilung stehen.
  if ($path === '/intern/badge' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['offen' => open_items_count($me)]));
  }
  // Lebenszeichen eines Geräts: „mein Abo gibt es noch". Ohne das lässt sich
  // ein totes Abo von einem stillen nicht unterscheiden — der Zustelldienst
  // nimmt beide an. Die Seite meldet sich höchstens einmal am Tag.
  if ($path === '/intern/push/seen' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    q('UPDATE push_subscriptions SET last_seen_at = NOW() WHERE endpoint_hash = ? AND user_id = ?',
      [hash('sha256', trim((string) ($_POST['endpoint'] ?? ''))), $me['id']]);
    exit('{"ok":true}');
  }
  if ($path === '/intern/push/subscribe' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    // Nicht in der Demo: sonst meldet ein Besucher sein Gerät an, und jeder
    // weitere Besucher löst mit einem Kommentar echten Push-Verkehr dieses
    // Servers an Google, Apple und Mozilla aus.
    if (is_demo()) { http_response_code(403); exit('{"ok":false}'); }
    $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
    $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
    $auth = trim((string) ($_POST['auth'] ?? ''));
    // Nur die echten Push-Dienste als Ziel, und nur Schlüssel in der Form, die
    // der Standard vorschreibt (65 bzw. 16 Bytes, url-sicheres Base64). Der
    // Browser liefert beides korrekt — die Route verlässt sich nicht darauf.
    $keyOk = strlen(push_b64_decode($p256dh)) === 65 && strlen(push_b64_decode($auth)) === 16;
    if (push_endpoint_ok($endpoint) && $keyOk) {
      q('INSERT INTO push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth)
         VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
         p256dh = VALUES(p256dh), auth = VALUES(auth)',
        [$me['id'], hash('sha256', $endpoint), $endpoint, $p256dh, $auth]);
      // Geräte je Mitglied begrenzen: Der Endpunkt-Pfad ist frei wählbar, also
      // ließen sich sonst beliebig viele Abos anlegen — und jede Mitteilung
      // müsste sie alle der Reihe nach anfragen. Das älteste weicht.
      q('DELETE FROM push_subscriptions WHERE user_id = ? AND id NOT IN
         (SELECT id FROM (SELECT id FROM push_subscriptions WHERE user_id = ?
                          ORDER BY id DESC LIMIT 10) AS keep)',
        [$me['id'], $me['id']]);
      exit('{"ok":true}');
    }
    http_response_code(400);
    exit('{"ok":false}');
  }
  if ($path === '/intern/push/unsubscribe' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    q('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?',
      [hash('sha256', trim((string) ($_POST['endpoint'] ?? ''))), $me['id']]);
    exit('{"ok":true}');
  }
  // Einen Passkey für dieses Gerät anlegen. Die Frage stellt der Server, damit
  // die Antwort nur zu dieser Sitzung passt.
  if ($path === '/intern/profil/passkey/challenge' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!passkey_available()) exit(json_encode(['error' => 'unsupported']));
    exit(json_encode([
      'challenge' => passkey_challenge_new('register'),
      'rpId' => passkey_rp_id(),
      'rpName' => setting('band_name') ?: 'Bandregie',
      // Die Kennung des Kontos ist die interne Nummer und nicht die E-Mail:
      // Sie bleibt gleich, wenn sich die Adresse ändert, und sie steht sonst
      // auf dem Gerät für jeden lesbar.
      'userId' => passkey_b64((string) $me['id']),
      'userName' => $me['email'],
      'userDisplay' => $me['name'],
      // Was schon da ist, damit dasselbe Gerät nicht zweimal einträgt.
      'vorhanden' => array_column(rows('SELECT credential_id FROM passkeys WHERE user_id = ?', [$me['id']]), 'credential_id'),
    ]));
  }
  if ($path === '/intern/profil/passkey' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = $_POST;
    $challenge = passkey_challenge_take('register');
    $credId = trim((string) ($in['id'] ?? ''));
    $spki = passkey_b64_decode((string) ($in['publicKey'] ?? ''));
    $clientData = passkey_b64_decode((string) ($in['clientDataJSON'] ?? ''));
    // getPublicKey() liefert nicht überall etwas — Passwortverwalter legen den
    // Passkey an, geben den Schlüssel aber nur im attestationObject heraus.
    // Dann wird er dort herausgeholt, statt die Anmeldung abzulehnen, obwohl
    // das Gerät sie längst eingerichtet hat.
    // Das Attestat wird immer ausgepackt: Auch wenn getPublicKey() etwas
    // geliefert hat, steht nur dort, welcher Schlüsselbund den Passkey
    // verwahrt — und danach heißt er in der Liste.
    [$ausAtt, $rohId, $aaguid] = passkey_from_attestation(passkey_b64_decode((string) ($in['attestationObject'] ?? '')));
    if ($spki === '') $spki = $ausAtt;
    // Die Kennung aus dem Attestat muss die sein, die der Browser nennt —
    // sonst legten wir einen Schlüssel unter fremdem Namen ab.
    if ($rohId !== '' && !hash_equals(passkey_b64($rohId), $credId)) $spki = '';
    $fehler = $challenge === null ? 'fl_pk_bad_challenge'
      : passkey_client_data_error($clientData, $challenge, 'webauthn.create');
    if ($fehler === null && ($credId === '' || $spki === '')) $fehler = 'fl_pk_bad_data';
    // Der Schlüssel muss sich lesen lassen — sonst stünde eine Zeile in der
    // Tabelle, mit der sich später nie jemand anmelden kann.
    if ($fehler === null && openssl_pkey_get_public(passkey_pem($spki)) === false) $fehler = 'fl_pk_bad_key';
    if ($fehler !== null) {
      http_response_code(400);
      exit(json_encode(['error' => t($fehler)]));
    }
    $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 60);
    if ($label === '') {
      $label = passkey_label($aaguid, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
      // Zweimal „iPhone" in einer Liste hilft niemandem. Erst wenn der Name
      // schon vergeben ist, kommt Datum und Uhrzeit dahinter — im Regelfall
      // bleibt es beim kurzen Namen, und umbenennen kann man ihn ohnehin.
      if (row('SELECT 1 FROM passkeys WHERE user_id = ? AND label = ?', [$me['id'], $label])) {
        $label = mb_substr($label . ' · ' . date('d.m.Y H:i'), 0, 60);
      }
    }
    try {
      // Die AAGUID wird mitgeschrieben, auch wenn wir sie heute nicht benennen
      // können: Die Liste der Anbieter wächst, und dann lässt sich ein alter
      // Eintrag nachträglich richtig beschriften, ohne ihn neu anzulegen.
      q('INSERT INTO passkeys (user_id, credential_id, public_key, label, aaguid) VALUES (?,?,?,?,?)',
        [$me['id'], $credId, passkey_pem($spki), $label, $aaguid]);
    } catch (PDOException) {
      // Schon eingetragen — kein Fehler, das Gerät ist ja bereits verbunden.
      exit(json_encode(['ok' => true, 'schon' => true]));
    }
    exit(json_encode(['ok' => true]));
  }
  // Umbenennen — nur den eigenen; die Bedingung im UPDATE entscheidet.
  if (preg_match('~^/intern/profil/passkey/(\d+)/name$~', $path, $m) && $method === 'POST') {
    $neu = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 60);
    if ($neu !== '') {
      q('UPDATE passkeys SET label = ? WHERE id = ? AND user_id = ?', [$neu, $m[1], $me['id']]);
      flash(t('fl_pk_renamed'));
    }
    redirect('/intern/profil#passkey');
  }
  if (preg_match('~^/intern/profil/passkey/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    // Nur den eigenen — die Bedingung im DELETE entscheidet, nicht die Anzeige.
    q('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [$m[1], $me['id']]);
    flash(t('fl_pk_removed'));
    redirect('/intern/profil');
  }
  if ($path === '/intern/profil' && $method === 'POST') {
    if (display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $me['name']) !== '' && ($_POST['email'] ?? '') !== '') {
      try {
        $prefLang = array_key_exists($_POST['pref_lang'] ?? '', LANGS) ? $_POST['pref_lang'] : 'de';
        // Am eigenen Profil darf in der Demo alles geändert werden — nur die
        // E-Mail nicht: mit ihr meldet man sich an, sie steht als Zugangsdatum
        // auf der Werbeseite, und eine Änderung sperrte den nächsten Besucher
        // aus. Der Rest ist gerade das, was man ausprobieren will.
        $email = is_demo() ? $me['email'] : strtolower(trim($_POST['email']));
        q('UPDATE users SET name=?, stage_name=?, instrument=?, email=?, pref_lang=?,
                            first_name=?, last_name=?, phone=?, mobile=?, stage_figure=?, on_stage=? WHERE id=?', [
          display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $me['name']),
          $_POST['stage_name'] ?? '', $_POST['instrument'] ?? '',
          $email, $prefLang,
          $_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $_POST['phone'] ?? '', $_POST['mobile'] ?? '',
          // Nur eine der angebotenen Figuren; alles andere wird neutral.
          array_key_exists($_POST['stage_figure'] ?? '', STAGE_FIGURES) ? $_POST['stage_figure'] : '',
          isset($_POST['on_stage']) ? 1 : 0,
          $me['id'],
        ]);
        $_SESSION['pub_lang'] = $prefLang;
        flash(t('fl_profile_saved'));
      } catch (PDOException) {
        flash(t('fl_email_taken'));
      }
    } else {
      flash(t('fl_name_email_required'));
    }
    upload_rejected((int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE));
    $tmp = $_FILES['avatar']['tmp_name'] ?? '';
    if (is_uploaded_file($tmp) && ($_FILES['avatar']['size'] ?? 0) <= 5 * 1024 * 1024
        && str_starts_with(mime_content_type($tmp) ?: '', 'image/')) {
      $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION) ?: 'jpg');
      $name = 'avatar_' . $me['id'] . '_' . bin2hex(random_bytes(6)) . '.' . preg_replace('~[^a-z0-9]~', '', $ext);
      if (move_uploaded_file($tmp, UPLOADS_DIR . '/' . $name)) {
        $old = row('SELECT avatar_file FROM users WHERE id = ?', [$me['id']])['avatar_file'] ?? '';
        if ($old) @unlink(UPLOADS_DIR . '/' . $old);
        q('UPDATE users SET avatar_file = ? WHERE id = ?', [$name, $me['id']]);
      }
    }
    redirect('/intern/profil');
  }
  if ($path === '/intern/profil/avatar/delete' && $method === 'POST') {
    $old = row('SELECT avatar_file FROM users WHERE id = ?', [$me['id']])['avatar_file'] ?? '';
    if ($old) @unlink(UPLOADS_DIR . '/' . $old);
    q("UPDATE users SET avatar_file = '' WHERE id = ?", [$me['id']]);
    redirect('/intern/profil');
  }

  // ---------- Bühnenplan ----------
  if (preg_match('~^/intern/stagerider/plan/(add|update|delete|vorlage)$~', $path, $m) && $method === 'POST') {
    if (!perm_allows($me, 'rider', 'write')) { flash(t('fl_no_permission')); redirect('/intern/stagerider'); }
    // Leer bleibt leer: Ein Maß, das niemand eingetragen hat, ist NULL und
    // holt sich damit das Übliche seiner Art — nicht null Zentimeter.
    $stageMass = static function (mixed $v): ?int {
      $v = trim((string) $v);
      return $v === '' ? null : max(0, min(2000, (int) $v));
    };
    if ($m[1] === 'vorlage') {
      q('DELETE FROM stage_items');
      $pos = 0;
      foreach (stage_default_items(rows('SELECT id, name, stage_name, instrument, on_stage FROM users ORDER BY name')) as $it) {
        q('INSERT INTO stage_items (kind, label, x, y, note, position, width_cm, depth_cm, user_id) VALUES (?,?,?,?,?,?,?,?,?)',
          [$it['kind'], $it['label'], $it['x'], $it['y'], $it['note'], $pos++,
           $it['width_cm'] ?? null, $it['depth_cm'] ?? null, $it['user_id'] ?? null]);
      }
    } elseif ($m[1] === 'add') {
      $neuArt = array_key_exists($_POST['kind'] ?? '', STAGE_KINDS) ? $_POST['kind'] : 'sonstiges';
      $neuWer = ((int) ($_POST['user_id'] ?? 0)) ?: null;
      q('INSERT INTO stage_items (kind, label, x, y, note, position, width_cm, depth_cm, user_id) VALUES (?,?,?,?,?,?,?,?,?)', [
        $neuArt,
        ($neuArt === 'musiker' && $neuWer) ? '' : trim($_POST['label'] ?? ''),
        max(0, min(100, (int) ($_POST['x'] ?? 50))), max(0, min(100, (int) ($_POST['y'] ?? 50))),
        trim($_POST['note'] ?? ''),
        (int) (row('SELECT COALESCE(MAX(position), 0) + 1 AS p FROM stage_items')['p'] ?? 1),
        $stageMass($_POST['width_cm'] ?? ''), $stageMass($_POST['depth_cm'] ?? ''),
        $neuWer,
      ]);
    } elseif ($m[1] === 'update' && ($_POST['remove'] ?? '') !== '') {
      // Der Löschknopf steckt im selben Formular; ein eigenes wäre verschachtelt
      q('DELETE FROM stage_items WHERE id = ?', [(int) $_POST['remove']]);
      flash(t('fl_stage_deleted'));
      redirect('/intern/stagerider');
    } elseif ($m[1] === 'update') {
      // Alle Einträge auf einmal — beim Ziehen im Plan ändern sich mehrere
      foreach ((array) ($_POST['item'] ?? []) as $id => $vals) {
        $stageArt  = array_key_exists($vals['kind'] ?? '', STAGE_KINDS) ? $vals['kind'] : 'sonstiges';
        $stageWer  = ((int) ($vals['user_id'] ?? 0)) ?: null;
        // Steht ein Mitglied dahinter, ist sein Name der Name. Der getippte wird
        // dann geleert statt mitgeschleppt: Zwei Namen in einer Zeile, von denen
        // nur einer zählt, liest sich wie ein Fehler — und ist einer, sobald sich
        // der Name des Mitglieds ändert (#187).
        $stageText = ($stageArt === 'musiker' && $stageWer) ? '' : trim($vals['label'] ?? '');
        q('UPDATE stage_items SET kind = ?, label = ?, x = ?, y = ?, note = ?, width_cm = ?, depth_cm = ?, user_id = ? WHERE id = ?', [
          $stageArt,
          $stageText,
          max(0, min(100, (int) ($vals['x'] ?? 50))), max(0, min(100, (int) ($vals['y'] ?? 50))),
          trim($vals['note'] ?? ''),
          $stageMass($vals['width_cm'] ?? ''), $stageMass($vals['depth_cm'] ?? ''),
          $stageWer,
          (int) $id,
        ]);
      }
    }
    flash(t('fl_stage_saved'));
    redirect('/intern/stagerider');
  }
  // Das Bühnenmaß. Es gehört zum Plan und nicht in die allgemeinen
  // Einstellungen: Wer den Rider pflegt, weiß, auf welcher Bühne die Band steht.
  if ($path === '/intern/stagerider/mass' && $method === 'POST') {
    if (!perm_allows($me, 'rider', 'write')) { flash(t('fl_no_permission')); redirect('/intern/stagerider'); }
    deny_in_demo('/intern/stagerider');
    set_setting('stage_width_m', (string) max(2, min(30, (int) ($_POST['stage_width_m'] ?? 8))));
    set_setting('stage_depth_m', (string) max(2, min(20, (int) ($_POST['stage_depth_m'] ?? 6))));
    flash(t('fl_stage_saved'));
    redirect('/intern/stagerider');
  }
  if (preg_match('~^/intern/stagerider/plan/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    if (!perm_allows($me, 'rider', 'write')) { flash(t('fl_no_permission')); redirect('/intern/stagerider'); }
    q('DELETE FROM stage_items WHERE id = ?', [$m[1]]);
    flash(t('fl_stage_deleted'));
    redirect('/intern/stagerider');
  }

  // ---------- Musik & Videos für die öffentliche Seite ----------
  if ($path === '/intern/musik' && $method === 'GET') {
    view('intern/musik', ['title' => t('inav_musik'), 'links' => rows('SELECT * FROM media_links ORDER BY id DESC')]);
  }
  if ($path === '/intern/musik' && $method === 'POST') {
    if (($_POST['url'] ?? '') !== '') {
      q('INSERT INTO media_links (title, url) VALUES (?,?)', [$_POST['title'] ?? '', trim($_POST['url'])]);
      flash(t('fl_media_saved'));
    }
    redirect('/intern/musik');
  }
  if (preg_match('~^/intern/musik/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    q('DELETE FROM media_links WHERE id = ?', [$m[1]]);
    flash(t('fl_media_deleted'));
    redirect('/intern/musik');
  }

  // ---------- Hilfe ----------
  if ($path === '/intern/hilfe' && $method === 'GET') {
    view('intern/hilfe', ['title' => t('help_title')]);
  }

  // ---------- Rechte je Bereich ----------
  // Die Rechte stehen jetzt beim jeweiligen Mitglied; alte Lesezeichen und
  // Verweise sollen trotzdem irgendwo landen.
  if ($path === '/intern/rechte' && $method === 'GET') {
    redirect('/intern/mitglieder');
  }
  if (preg_match('~^/intern/rechte/(\d+)$~', $path, $m) && $method === 'POST') {
    require_admin();
    $permTarget = row('SELECT id, role FROM users WHERE id = ?', [$m[1]]);
    if ($permTarget) {
      // Bei einem Admin ist nur zu vergeben, was auch ein Admin nicht von
      // selbst hat — an allem anderen gäbe es nichts einzustellen.
      $permEditable = $permTarget['role'] === 'admin' ? PERM_EXPLICIT_MODULES : array_keys(PERM_MODULES);
      if (($_POST['template'] ?? '') !== '' && $permTarget['role'] !== 'admin') {
        perm_apply_template((int) $m[1], $_POST['template'] === 'ersatz' ? 'ersatz' : 'member');
      } else {
        foreach ($permEditable as $permModuleKey) {
          $permWrite = isset($_POST['perm'][$permModuleKey]['write']) ? 1 : 0;
          // Ändern ohne Sehen gibt es nicht — das Häkchen zieht das andere mit
          $permRead = $permWrite || isset($_POST['perm'][$permModuleKey]['read']) ? 1 : 0;
          q('INSERT INTO permissions (user_id, module, can_read, can_write) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE can_read = VALUES(can_read), can_write = VALUES(can_write)',
            [$m[1], $permModuleKey, $permRead, $permWrite]);
        }
      }
      flash(t('fl_perm_saved'));
    }
    redirect('/intern/mitglieder');
  }

  // ---------- Mitglieder ----------
  if ($path === '/intern/mitglieder' && $method === 'GET') {
    $permByUser = [];
    foreach (rows('SELECT * FROM permissions') as $permRow) {
      $permByUser[(int) $permRow['user_id']][$permRow['module']] = $permRow;
    }
    view('intern/mitglieder', [
      'title' => t('mem_title'),
      'perms' => $permByUser,
      'members' => rows('SELECT u.*, s.name AS substitute_for_name FROM users u
                         LEFT JOIN users s ON s.id = u.substitute_for ORDER BY u.name'),
      'instruments' => array_column(rows("SELECT name FROM equipment
                                          WHERE category = 'instrument' AND disposed_on IS NULL ORDER BY name"), 'name'),
    ]);
  }
  if (preg_match('~^/intern/mitglieder/(\d+)/update$~', $path, $m) && $method === 'POST') {
    require_admin();
    if (($_POST['first_name'] ?? '') !== '' && ($_POST['email'] ?? '') !== '') {
      try {
        // In der Demo bleiben E-Mail und Rolle, wie sie sind. Mit der Adresse
        // meldet man sich an, und die Rolle entscheidet, wie viel jemand sieht
        // — beides steht auf der Werbeseite und gilt für alle Besucher
        // gleichzeitig. Name, Instrument und Vertretung darf man ändern; das
        // ist gerade das, was man an dieser Seite sehen will.
        $email = is_demo()
          ? (row('SELECT email FROM users WHERE id = ?', [$m[1]])['email'] ?? '')
          : strtolower(trim($_POST['email']));
        // Der Haken zur Gewinnbeteiligung steht nur im Formular, wenn die Kasse
        // sichtbar ist. Wo er fehlt, bleibt der bisherige Wert — ein fehlendes
        // Feld darf keine stille Abwahl bedeuten.
        $anteil = perm_allows($me, 'kasse')
          ? (isset($_POST['profit_share']) ? 1 : 0)
          : (int) (row('SELECT profit_share FROM users WHERE id = ?', [$m[1]])['profit_share'] ?? 1);
        // Figur und Bühnenzugehörigkeit sind Angaben für den Bühnenplan, und den
        // pflegt die Verwaltung. Sie hier zu setzen erspart es, jedes Mitglied um
        // einen Klick im eigenen Profil zu bitten — sonst sehen im Plan alle
        // gleich aus (#187). Ein unbekannter Wert wird auf „nicht gewählt"
        // gebracht statt übernommen.
        $figur = array_key_exists($_POST['stage_figure'] ?? '', STAGE_FIGURES) ? (string) $_POST['stage_figure'] : '';
        q('UPDATE users SET name=?, stage_name=?, instrument=?, email=?,
                            first_name=?, last_name=?, phone=?, mobile=?, substitute_for=?,
                            substitute_rank=?, profit_share=?, stage_figure=?, on_stage=? WHERE id=?', [
          display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '',
                       row('SELECT name FROM users WHERE id = ?', [$m[1]])['name'] ?? ''),
          $_POST['stage_name'] ?? '', $_POST['instrument'] ?? '',
          $email,
          $_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $_POST['phone'] ?? '', $_POST['mobile'] ?? '',
          ((int) ($_POST['substitute_for'] ?? 0) ?: null),
          max(0, min(99, (int) ($_POST['substitute_rank'] ?? 0))),
          $anteil,
          $figur,
          isset($_POST['on_stage']) ? 1 : 0,
          $m[1],
        ]);
        // Rolle: nur Admin, und nicht die eigene (sonst sperrt man sich aus).
        // In der Demo gar nicht — siehe oben.
        if (!is_demo() && (int) $m[1] !== (int) $me['id']
            && in_array($_POST['role'] ?? '', ['admin', 'member', 'ersatz'], true)) {
          $roleBefore = row('SELECT role FROM users WHERE id = ?', [$m[1]])['role'] ?? '';
          q('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $m[1]]);
          // Eine neue Rolle bringt ihre Rechte mit; einzeln nachbessern geht
          // danach weiterhin unter „Rechte".
          if ($roleBefore !== $_POST['role'] && $_POST['role'] !== 'admin') {
            perm_apply_template((int) $m[1], $_POST['role']);
          }
        }
        flash(t('fl_member_updated'));
      } catch (PDOException) {
        flash(t('fl_email_taken'));
      }
    }
    redirect('/intern/mitglieder');
  }
  if ($path === '/intern/mitglieder' && $method === 'POST') {
    require_admin();
    // Legt ein Konto an und verschickt das Startpasswort per Mail — beides
    // hat in einer öffentlichen Demo nichts verloren.
    deny_in_demo('/intern/mitglieder');
    if (($_POST['first_name'] ?? '') && ($_POST['email'] ?? '')) {
      // Start-Passwort erzeugen (ohne verwechselbare Zeichen), Wechsel beim ersten Login erzwingen
      $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
      $startPw = '';
      for ($i = 0; $i < 12; $i++) $startPw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
      $email = strtolower(trim($_POST['email']));
      try {
        q('INSERT INTO users (name, first_name, last_name, email, password_hash, role, instrument, must_change_pw)
         VALUES (?,?,?,?,?,?,?,1)', [
          display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? ''),
          trim($_POST['first_name'] ?? ''), trim($_POST['last_name'] ?? ''),
          $email, password_hash($startPw, PASSWORD_DEFAULT),
          in_array($_POST['role'] ?? '', ['admin', 'ersatz'], true) ? $_POST['role'] : 'member', $_POST['instrument'] ?? '',
        ]);
        // Rechte nach der Vorlage der Rolle; Admins brauchen keine Zeilen
        $newRole = in_array($_POST['role'] ?? '', ['admin', 'ersatz'], true) ? $_POST['role'] : 'member';
        if ($newRole !== 'admin') perm_apply_template((int) $db->lastInsertId(), $newRole);
        $band = setting('band_name');
        $body = "Hallo " . trim($_POST['first_name'] ?? '') . ",\n\n"
          . "für dich wurde ein Zugang zum Bandbereich von $band angelegt.\n\n"
          . 'Login: ' . absolute_url('/login') . "\n"
          . "E-Mail: $email\n"
          . "Start-Passwort: $startPw\n\n"
          . "Beim ersten Login musst du ein eigenes Passwort vergeben.\n\n"
          . "Viele Grüße\n$band";
        // Absender von der eigenen Domain (SPF), Antworten gehen an die Band-Adresse
        $from = 'no-reply@' . preg_replace('~^www\.~', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $replyTo = setting('contact_email') ? "\r\nReply-To: " . setting('contact_email') : '';
        $sent = @mail($email, "Dein Zugang zum Bandbereich von $band", $body,
          "From: $from$replyTo\r\nContent-Type: text/plain; charset=UTF-8", '-f' . $from);
        flash($sent ? t('fl_member_created_mail') : t('fl_member_created_nomail') . ' ' . $startPw);
      } catch (PDOException) {
        flash(t('fl_email_taken'));
      }
    } else {
      flash(t('fl_name_email_required'));
    }
    redirect('/intern/mitglieder');
  }
  if (preg_match('~^/intern/mitglieder/(\d+)/(delete|passwort|zwei-faktor)$~', $path, $m) && $method === 'POST') {
    [$_, $id, $action] = $m;
    // Ein gelöschtes Konto und ein geändertes Kennwort treffen beide den
    // nächsten Besucher, nicht den, der es tut.
    deny_in_demo('/intern/mitglieder');
    if ($action === 'delete') {
      require_admin();
      if ((int) $id === (int) $me['id']) {
        flash(t('fl_no_self_delete'));
      } else {
        // Erst alles daneben, dann das Mitglied selbst — user_purge() ist die
        // eine Stelle, die weiß, was gelöscht und was nur entkoppelt wird.
        user_purge((int) $id);
        q('DELETE FROM users WHERE id = ?', [$id]);
      }
      redirect('/intern/mitglieder');
    }
    // Zweiten Faktor zurücksetzen (#169): der Weg zurück, wenn Handy und
    // Rückwege zugleich weg sind. Nur Admins, denn es nimmt einem fremden
    // Konto eine Schutzschicht — und deshalb steht es auch im Protokoll.
    if ($action === 'zwei-faktor') {
      require_admin();
      totp_clear((int) $id);
      error_log('Bandregie: Zweiter Faktor zurückgesetzt für Konto ' . (int) $id . ' durch Konto ' . (int) $me['id']);
      flash(t('fl_totp_reset'));
      redirect('/intern/mitglieder');
    }

    // Passwort ändern: selbst oder als Admin
    if ((int) $id !== (int) $me['id'] && $me['role'] !== 'admin') {
      flash(t('fl_only_admin_pw'));
    } elseif (strlen($_POST['password'] ?? '') < 8) {
      flash(t('fl_pw_min'));
    } else {
      // Setzt ein Admin ein fremdes Passwort, muss es beim nächsten Login geändert werden
      q('UPDATE users SET password_hash = ?, must_change_pw = ? WHERE id = ?',
        [password_hash($_POST['password'], PASSWORD_DEFAULT), (int) $id !== (int) $me['id'] ? 1 : 0, $id]);
      flash(t('fl_pw_changed'));
    }
    redirect('/intern/mitglieder');
  }

  // ---------- Kalender-Abo mit Einrichtungshilfe ----------
  if ($path === '/intern/kalender' && $method === 'GET') {
    $icsPath = '/kalender/' . setting('ical_token') . '.ics';
    view('intern/kalender', [
      'title' => t('cal_title'),
      'icalUrl' => absolute_url($icsPath),
      'webcalUrl' => preg_replace('~^https?~', 'webcal', absolute_url($icsPath)),
    ]);
  }

  // ---------- Übersetzungen ----------
  if ($path === '/intern/uebersetzungen' && $method === 'GET') {
    require_admin();
    $editLang = array_key_exists($_GET['sprache'] ?? '', LANGS) && ($_GET['sprache'] ?? '') !== 'de' ? $_GET['sprache'] : 'en';
    $existing = [];
    foreach (rows('SELECT tkey, value FROM translations WHERE lang = ?', [$editLang]) as $r) $existing[$r['tkey']] = $r['value'];
    view('intern/uebersetzungen', ['title' => t('tr_title'), 'editLang' => $editLang, 'existing' => $existing]);
  }
  if ($path === '/intern/uebersetzungen' && $method === 'POST') {
    require_admin();
    $editLang = array_key_exists($_POST['sprache'] ?? '', LANGS) && ($_POST['sprache'] ?? '') !== 'de' ? $_POST['sprache'] : null;
    if ($editLang) {
      foreach (array_keys(UI_STRINGS) as $key) {
        if (!isset($_POST['t_' . $key])) continue;
        $value = trim($_POST['t_' . $key]);
        if ($value === '') {
          q('DELETE FROM translations WHERE lang = ? AND tkey = ?', [$editLang, $key]);
        } else {
          q('INSERT INTO translations (lang, tkey, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$editLang, $key, $value]);
        }
      }
      flash(t('fl_translations_saved'));
    }
    redirect('/intern/uebersetzungen?sprache=' . ($editLang ?? 'en'));
  }

  // ---------- Über Bandregie ----------
  if ($path === '/intern/ueber' && $method === 'GET') {
    // CONTRIBUTORS (eine Zeile pro Person) ist optional — fehlt sie, bleibt der Block leer
    $file = BASE_DIR . '/CONTRIBUTORS';
    $names = is_file($file) ? array_filter(array_map('trim', explode("\n", (string) file_get_contents($file))),
      fn($l) => $l !== '' && !str_starts_with($l, '#')) : [];
    view('intern/ueber', ['title' => t('about_title'), 'contributors' => implode(', ', $names)]);
  }

  // ---------- Equipment ----------
  if ($path === '/intern/equipment' && $method === 'GET') {
    $filesByEq = [];
    foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.entity_type = 'equipment'") as $f) {
      $filesByEq[$f['entity_id']][] = $f;
    }
    $deadlinesByEq = [];
    foreach (rows('SELECT * FROM equipment_deadlines ORDER BY due_date') as $dl) {
      $deadlinesByEq[$dl['equipment_id']][] = $dl;
    }
    // Rechnungen und ihre Anhaenge: bereits nach Sichtbarkeit gefiltert, damit
    // die Ansicht nicht selbst entscheiden muss, was sie zeigen darf.
    $invList = invoice_list($me);
    $invFiles = [];
    foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                   WHERE f.entity_type = 'invoice'") as $invF) {
      $invFiles[(int) $invF['entity_id']][] = $invF;
    }
    view('intern/equipment', [
      'title' => t('inav_equipment'),
      'items' => rows('SELECT e.*, u.name AS owner_name, p.name AS parent_name FROM equipment e
                       LEFT JOIN users u ON u.id = e.owner_id
                       LEFT JOIN equipment p ON p.id = e.parent_id
                       ORDER BY FIELD(e.category, "instrument","pa","licht","transport","sonstiges"), e.name'),
      'filesByEq' => $filesByEq,
      'bookingsByEq' => eq_bookings_by_equipment($me),
      'deadlinesByEq' => $deadlinesByEq,
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
      'invoices' => $invList,
      'invoicesFiles' => $invFiles,
    ]);
  }
  // Der Bearbeiten-Block eines einzelnen Geräts. Die Liste holt ihn nach,
  // sobald jemand ein Gerät aufklappt; ohne JavaScript ist dieselbe Adresse
  // eine gewöhnliche Seite. Beides dieselbe Vorlage.
  if (preg_match('~^/intern/equipment/(\d+)/detail$~', $path, $m) && $method === 'GET') {
    $detailEq = row('SELECT e.*, u.name AS owner_name FROM equipment e
                     LEFT JOIN users u ON u.id = e.owner_id WHERE e.id = ?', [$m[1]]);
    if (!$detailEq) { http_response_code(404); exit('Nicht gefunden'); }
    $detailFiles = [];
    foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                   WHERE f.entity_type = 'equipment' AND f.entity_id = ?", [$detailEq['id']]) as $f) {
      $detailFiles[$f['entity_id']][] = $f;
    }
    view(isset($_GET['teil']) ? 'intern/_equipment_detail_fragment' : 'intern/equipment_detail', [
      'title' => $detailEq['name'],
      'detailEq' => $detailEq,
      'filesByEq' => $detailFiles,
      // Ohne die Buchungen hielte das Formular jedes Gerät für ungekauft und
      // böte den Kauf ein zweites Mal an.
      'bookingsByEq' => [(int) $detailEq['id'] => eq_bookings((int) $detailEq['id'], $me)],
      // Zur Auswahl der Rechnung — gefiltert, damit die Liste keinen fremden
      // Privatbeleg verrät, indem sie ihn zur Auswahl anbietet.
      'invoices' => invoice_list($me),
      // Für die Auswahl des übergeordneten Geräts und den Schleifenschutz
      'items' => rows('SELECT id, name, parent_id FROM equipment ORDER BY name'),
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
    ]);
  }
  if ($path === '/intern/equipment' && $method === 'POST') {
    if (($_POST['name'] ?? '') !== '') {
      $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;
      [$ownerId, $location] = equipment_inherit($parentId, (int) ($_POST['owner_id'] ?? 0) ?: null, trim($_POST['location'] ?? ''));
      // Anzahl > 1 legt durchnummerierte Geräte an — gedacht für Kabel und
      // anderes Zubehör, das man nicht einzeln benennen mag.
      $count = min(99, max(1, (int) ($_POST['count'] ?? 1)));
      $name  = trim($_POST['name']);
      for ($i = 1; $i <= $count; $i++) {
        q('INSERT INTO equipment (name, category, owner_id, location, is_standard, notes, parent_id, slot, purchased_on, price_cents, afa_years, acquired_as, article_no, invoice_id, quantity) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
          $count > 1 ? $name . ' #' . $i : $name,
          array_key_exists($_POST['category'] ?? '', EQ_CATEGORIES) ? $_POST['category'] : 'sonstiges',
          $ownerId,
          $location,
          isset($_POST['is_standard']) ? 1 : 0,
          trim($_POST['notes'] ?? ''),
          $parentId,
          trim($_POST['slot'] ?? ''),
          trim($_POST['purchased_on'] ?? '') ?: null,
          price_to_cents((string) ($_POST['price'] ?? '')),
          tax_afa_years_input($_POST['afa_years'] ?? null),
          array_key_exists($_POST['acquired_as'] ?? '', EQ_ACQUIRED) ? $_POST['acquired_as'] : '',
          trim((string) ($_POST['article_no'] ?? '')),
          // Nur ein Beleg, den dieser Mensch auch sehen darf — sonst haengte
          // sich ein Geraet an eine fremde Privatrechnung.
          eq_invoice_input($_POST['invoice_id'] ?? null, $me),
          eq_quantity_input($_POST['quantity'] ?? null),
        ]);
      }
      flash($count > 1 ? sprintf(t('fl_eq_saved_n'), $count) : t('fl_eq_saved'));
    }
    redirect('/intern/equipment');
  }
  // Kauf oder Abgang eines Geräts in der Kasse buchen.
  if (preg_match('~^/intern/equipment/(\d+)/(kauf|abgang)$~', $path, $m) && $method === 'POST') {
    $eq = row('SELECT * FROM equipment WHERE id = ?', [$m[1]]);
    $payer = in_array($_POST['payer'] ?? '', EQ_PAYERS, true) ? $_POST['payer'] : 'band';
    $mayBook = $m[2] === 'abgang' ? eq_may_dispose($eq, $me, $payer) : eq_may_book($eq, $me, $payer);
    if (!$mayBook) { flash(t('fl_no_permission')); redirect('/intern/equipment'); }
    // Beim Kauf stehen Betrag und Datum am Gerät; beim Abgang nennt sie das
    // Formular, denn verkauft wird selten zum Kaufpreis.
    $cents = $m[2] === 'kauf'
      ? (int) ($eq['price_cents'] ?? 0)
      : (int) (price_to_cents((string) ($_POST['amount'] ?? '')) ?? 0);
    $date = $m[2] === 'kauf'
      ? ($eq['purchased_on'] ?: date('Y-m-d'))
      : (trim($_POST['date'] ?? '') ?: date('Y-m-d'));
    if ($m[2] === 'kauf' && $cents <= 0) { flash(t('fl_eq_book_needs_price')); redirect('/intern/equipment'); }
    // Ein Kauf ist einmal. Dass das Formular danach verschwindet, ist nur die
    // Anzeige — geprüft wird es hier.
    if ($m[2] === 'kauf' && row('SELECT id FROM finances WHERE equipment_id = ? AND type = ?', [$m[1], 'ausgabe'])) {
      flash(t('fl_eq_booked_already'));
      redirect('/intern/equipment');
    }
    eq_book($eq, $me, $payer, $m[2], $cents, $date);
    // Ein Abgang beendet das Gerät im Bestand — die Zeile bleibt als
    // Geschichte stehen, taucht aber auf keiner Packliste mehr auf.
    if ($m[2] === 'abgang') {
      q('UPDATE equipment SET disposed_on = ? WHERE id = ?', [$date, $m[1]]);
      q('DELETE FROM event_equipment WHERE equipment_id = ?', [$m[1]]);
    }
    flash(t($m[2] === 'kauf' ? 'fl_eq_booked' : 'fl_eq_disposed'));
    redirect('/intern/equipment');
  }
  // Ein Abgang, der ein Versehen war: Kennzeichen weg, Buchung bleibt stehen.
  if (preg_match('~^/intern/equipment/(\d+)/reaktivieren$~', $path, $m) && $method === 'POST') {
    $eq = row('SELECT * FROM equipment WHERE id = ?', [$m[1]]);
    if (!eq_may_edit_owner_fields($eq, $me)) { flash(t('fl_no_permission')); redirect('/intern/equipment'); }
    q('UPDATE equipment SET disposed_on = NULL WHERE id = ?', [$m[1]]);
    flash(t('fl_eq_reactivated'));
    redirect('/intern/equipment');
  }
  /**
   * Rechnungen erfassen und ändern (#180).
   *
   * Unter /intern/equipment/, damit das Bereichsrecht der Geräte greift: Wer
   * Geräte pflegen darf, darf auch ihre Belege eintragen. Wer einen Beleg
   * hinterher lesen darf, ist eine andere und strengere Frage —
   * may_see_invoice() beantwortet sie über den Besitz.
   */
  if ($path === '/intern/equipment/rechnung' && $method === 'POST') {
    deny_in_demo('/intern/equipment');
    $invId = (int) ($_POST['invoice_id'] ?? 0);
    $invFelder = [
      trim((string) ($_POST['supplier'] ?? '')),
      trim((string) ($_POST['order_no'] ?? '')),
      trim((string) ($_POST['invoice_no'] ?? '')),
      trim((string) ($_POST['invoice_date'] ?? '')) ?: null,
      price_to_cents((string) ($_POST['total'] ?? '')),
      trim((string) ($_POST['notes'] ?? '')),
    ];
    // Ein Beleg ohne jede Angabe ist kein Beleg, sondern eine leere Zeile, die
    // hinterher niemand zuordnen kann.
    if ($invFelder[0] === '' && $invFelder[1] === '' && $invFelder[2] === '') {
      flash(t('inv_needs_something'));
      redirect('/intern/equipment');
    }
    if ($invId && ($invAlt = row('SELECT * FROM invoices WHERE id = ?', [$invId]))) {
      if (!may_see_invoice($me, $invId)) { flash(t('fl_no_permission')); redirect('/intern/equipment'); }
      q('UPDATE invoices SET supplier=?, order_no=?, invoice_no=?, invoice_date=?, total_cents=?, notes=? WHERE id=?',
        [...$invFelder, $invId]);
    } else {
      q('INSERT INTO invoices (supplier, order_no, invoice_no, invoice_date, total_cents, notes) VALUES (?,?,?,?,?,?)', $invFelder);
    }
    flash(t('inv_saved'));
    redirect('/intern/equipment');
  }
  if (preg_match('~^/intern/equipment/rechnung/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    deny_in_demo('/intern/equipment');
    $invId = (int) $m[1];
    if (!may_see_invoice($me, $invId)) { flash(t('fl_no_permission')); redirect('/intern/equipment'); }
    // Die Geräte bleiben. Nur der Verweis fällt weg — ein gelöschter Beleg darf
    // kein Gerät aus dem Inventar reißen.
    q('UPDATE equipment SET invoice_id = NULL WHERE invoice_id = ?', [$invId]);
    foreach (rows("SELECT * FROM files WHERE entity_type = 'invoice' AND entity_id = ?", [$invId]) as $invFile) {
      @unlink(FILES_DIR . '/' . $invFile['filename']);
      q('DELETE FROM files WHERE id = ?', [(int) $invFile['id']]);
    }
    q('DELETE FROM invoices WHERE id = ?', [$invId]);
    flash(t('inv_deleted'));
    redirect('/intern/equipment');
  }

  // Eine Zeile, die für mehrere gleiche Geräte steht, in einzelne aufteilen.
  // Nur bei Geräten ohne Bestandteile: was in einem Rack steckt, mitzukopieren
  // hieße raten, welches Zubehör mitgehört.
  if (preg_match('~^/intern/equipment/(\d+)/aufteilen$~', $path, $m) && $method === 'POST') {
    $eq = row('SELECT * FROM equipment WHERE id = ?', [$m[1]]);
    if (!$eq || !eq_may_edit_owner_fields($eq, $me)) { flash(t('fl_no_permission')); redirect('/intern/equipment'); }
    $count = min(99, max(1, (int) ($_POST['count'] ?? 1)));
    $hasParts = (int) row('SELECT COUNT(*) AS n FROM equipment WHERE parent_id = ?', [$m[1]])['n'] > 0;
    if ($count < 2 || $hasParts) {
      flash(t('fl_eq_split_impossible'));
      redirect('/intern/equipment');
    }
    // Die Stückzahl verschwindet aus Name, Steckplatz und Mengenfeld — sie steht
    // jetzt in der Zahl der Zeilen. Der Preis war der eines Geräts und bleibt es.
    $baseName = eq_strip_quantity((string) $eq['name']);
    $baseSlot = eq_strip_quantity((string) $eq['slot']);
    q('UPDATE equipment SET name = ?, slot = ?, quantity = 1 WHERE id = ?', [$baseName . ' #1', $baseSlot, $m[1]]);
    $neue = [];
    for ($i = 2; $i <= $count; $i++) {
      q('INSERT INTO equipment (name, category, owner_id, location, is_standard, notes, parent_id, slot, purchased_on, price_cents, afa_years, acquired_as, article_no, invoice_id, quantity)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)', [
        $baseName . ' #' . $i, $eq['category'], $eq['owner_id'], $eq['location'],
        $eq['is_standard'], $eq['notes'], $eq['parent_id'], $baseSlot,
        // Zehn aufgeteilte Kabel kamen aus derselben Bestellung: Datum, Preis
        // und Zustand gelten für jedes einzelne.
        $eq['purchased_on'], $eq['price_cents'], $eq['afa_years'], $eq['acquired_as'],
        $eq['article_no'], $eq['invoice_id'],
      ]);
      $neue[] = (int) $db->lastInsertId();
    }
    // Das Foto gehört jedem Stück: gleiche Datei, eigene Zeile — wie beim
    // Übernehmen eines vorhandenen Bildes (#184). Ohne das stünden neun der zehn
    // aufgeteilten Zeilen ohne Bild in der Liste.
    foreach (rows("SELECT * FROM files WHERE entity_type = 'equipment' AND entity_id = ?", [$m[1]]) as $eqFile) {
      if (!in_array(strtolower(pathinfo($eqFile['original_name'], PATHINFO_EXTENSION)),
            ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;
      foreach ($neue as $neuId) {
        q('INSERT INTO files (entity_type, entity_id, filename, original_name, size, uploaded_by) VALUES (?,?,?,?,?,?)',
          ['equipment', (int) $neuId, $eqFile['filename'], $eqFile['original_name'], (int) $eqFile['size'], $me['id']]);
      }
    }
    flash(sprintf(t('fl_eq_split'), $count));
    redirect('/intern/equipment');
  }
  if (preg_match('~^/intern/equipment/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'delete') {
      // Die Anhänge gehen mit. Ohne das blieben Zeilen auf eine Gerätenummer
      // zeigen, die es nicht mehr gibt, und die Datei auf der Platte dazu —
      // unsichtbar, denn nichts listet sie mehr auf (#188).
      files_purge('equipment', (int) $m[1]);
      q('DELETE FROM equipment WHERE id = ?', [$m[1]]);
      q('DELETE FROM equipment_deadlines WHERE equipment_id = ?', [$m[1]]);
      q('DELETE FROM event_equipment WHERE equipment_id = ?', [$m[1]]);
      flash(t('fl_eq_deleted'));
    } elseif (($_POST['name'] ?? '') !== '') {
      $eqBefore = row('SELECT * FROM equipment WHERE id = ?', [$m[1]]);
      if (!$eqBefore) redirect('/intern/equipment');
      // Angaben zum Eigentum ändert nur, wem das Gerät gehört, und die
      // Verwaltung. Das Umhängen zählt dazu: über ein anderes übergeordnetes
      // Gerät würde sonst der Besitzer mitwechseln.
      $mayOwn = eq_may_edit_owner_fields($eqBefore, $me);
      // Sich selbst oder einen eigenen Bestandteil als übergeordnetes Gerät zu
      // wählen wäre eine Schleife — die Anzeige käme aus dem Baum nicht mehr
      // heraus. Das Formular bietet beides nicht an, hier steht die Prüfung.
      $parentId = $mayOwn ? ((int) ($_POST['parent_id'] ?? 0) ?: null) : ($eqBefore['parent_id'] !== null ? (int) $eqBefore['parent_id'] : null);
      if ($parentId) {
        $blocked = [(int) $m[1], ...eq_descendants((int) $m[1], rows('SELECT id, parent_id FROM equipment'))];
        if (in_array($parentId, $blocked, true)) $parentId = null;
      }
      $postedOwner = $mayOwn
        ? ((int) ($_POST['owner_id'] ?? 0) ?: null)
        : ($eqBefore['owner_id'] !== null ? (int) $eqBefore['owner_id'] : null);
      [$ownerId, $location] = equipment_inherit($parentId, $postedOwner, trim($_POST['location'] ?? ''));
      q('UPDATE equipment SET name=?, category=?, owner_id=?, location=?, is_standard=?, notes=?, parent_id=?, slot=?, purchased_on=?, price_cents=?, afa_years=?, acquired_as=?, article_no=?, invoice_id=?, quantity=? WHERE id=?', [
        trim($_POST['name']),
        array_key_exists($_POST['category'] ?? '', EQ_CATEGORIES) ? $_POST['category'] : 'sonstiges',
        $ownerId,
        $location,
        isset($_POST['is_standard']) ? 1 : 0,
        trim($_POST['notes'] ?? ''),
        $parentId,
        trim($_POST['slot'] ?? ''),
        $mayOwn ? (trim($_POST['purchased_on'] ?? '') ?: null) : $eqBefore['purchased_on'],
        $mayOwn ? price_to_cents((string) ($_POST['price'] ?? '')) : $eqBefore['price_cents'],
        // Die Nutzungsdauer steht hinter derselben Schranke wie der Preis: wer
        // ihn nicht sehen darf, ändert auch nichts an der Abschreibung.
        $mayOwn ? tax_afa_years_input($_POST['afa_years'] ?? null) : $eqBefore['afa_years'],
        // Ebenso der Anschaffungszustand: Er steht im Formular hinter derselben
        // Schranke, also darf ihn ein fremder Absender auch nicht überschreiben.
        $mayOwn
          ? (array_key_exists($_POST['acquired_as'] ?? '', EQ_ACQUIRED) ? $_POST['acquired_as'] : '')
          : (string) $eqBefore['acquired_as'],
        $mayOwn ? trim((string) ($_POST['article_no'] ?? '')) : (string) $eqBefore['article_no'],
        $mayOwn ? eq_invoice_input($_POST['invoice_id'] ?? null, $me) : ($eqBefore['invoice_id'] !== null ? (int) $eqBefore['invoice_id'] : null),
        // Die Menge steht nicht hinter der Preisschranke: Wie viele Kabel im
        // Koffer liegen, gehört zum Bestand und nicht zum Kaufpreis.
        eq_quantity_input($_POST['quantity'] ?? null),
        $m[1],
      ]);
      // Ändert sich der Besitzer eines Geräts, ziehen seine Bestandteile mit —
      // und zwar alle, nicht nur die erste Ebene. Im Rack steckt ein Empfänger,
      // darin ein Sender, darin eine Kapsel; die gehören alle zusammen.
      $eqTree = eq_descendants((int) $m[1], rows('SELECT id, parent_id FROM equipment'));
      if ($eqTree) {
        $eqIn = implode(',', array_fill(0, count($eqTree), '?'));
        q("UPDATE equipment SET owner_id = ?, location = '' WHERE id IN ($eqIn)", [$ownerId, ...$eqTree]);
      }
      flash(t('fl_eq_saved'));
    }
    redirect('/intern/equipment');
  }
  if (preg_match('~^/intern/equipment/(\d+)/frist$~', $path, $m) && $method === 'POST') {
    if (($_POST['title'] ?? '') !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $_POST['due_date'] ?? '')) {
      q('INSERT INTO equipment_deadlines (equipment_id, title, due_date, interval_months, notes) VALUES (?,?,?,?,?)', [
        $m[1], trim($_POST['title']), $_POST['due_date'],
        in_array((int) ($_POST['interval_months'] ?? 0), [0, 6, 12, 24], true) ? (int) $_POST['interval_months'] : 0,
        trim($_POST['notes'] ?? ''),
      ]);
      flash(t('fl_deadline_saved'));
    }
    redirect('/intern/equipment');
  }
  if (preg_match('~^/intern/equipment/frist/(\d+)/(erledigt|delete)$~', $path, $m) && $method === 'POST') {
    $dl = row('SELECT * FROM equipment_deadlines WHERE id = ?', [$m[1]]);
    if ($dl && $m[2] === 'erledigt') {
      if ((int) $dl['interval_months'] > 0) {
        // Vom Fälligkeitsdatum aus weiterschieben, bis der Termin in der Zukunft liegt
        $next = $dl['due_date'];
        while ($next <= $today) $next = date('Y-m-d', strtotime($next . ' +' . $dl['interval_months'] . ' months'));
        q('UPDATE equipment_deadlines SET due_date = ? WHERE id = ?', [$next, $dl['id']]);
        flash(t('fl_deadline_done'));
      } else {
        q('DELETE FROM equipment_deadlines WHERE id = ?', [$dl['id']]);
        flash(t('fl_deadline_done_once'));
      }
    } elseif ($dl) {
      q('DELETE FROM equipment_deadlines WHERE id = ?', [$dl['id']]);
      flash(t('fl_deadline_deleted'));
    }
    redirect('/intern/equipment');
  }

  // ---------- Stagerider ----------
  if ($path === '/intern/stagerider' && $method === 'GET') {
    view('intern/stagerider', [
      'title' => t('rider_title'),
      'channels' => rows('SELECT * FROM channels ORDER BY number'),
      'stageItems' => rows('SELECT * FROM stage_items ORDER BY position, id'),
      // Für die Zuordnung eines Eintrags zu einem Menschen — daran hängen
      // Figur und Foto im Plan. Das Instrument muss mit: An ihm errät
      // rider_tech_guess(), wer die Technik ist. Ohne die Spalte fand es nie
      // jemanden, und die Vorauswahl blieb still leer.
      'stageMembers' => rows('SELECT id, name, instrument, on_stage FROM users ORDER BY name'),
    ]);
  }
  if ($path === '/intern/stagerider' && $method === 'POST') {
    require_admin();
    foreach (['rider_stage', 'rider_power', 'rider_pa', 'rider_monitor', 'rider_light',
              'rider_getin', 'rider_extras', 'rider_positions',
              'rider_contact_tech', 'rider_contact_booking'] as $key) {
      if (isset($_POST[$key])) set_setting($key, trim($_POST[$key]));
    }
    // Die Ansprechpartner als Mitglied. 0 heißt „niemand" und lässt den
    // Freitext gelten; eine Kennung, die kein Konto trifft, wird verworfen.
    foreach (['tech', 'booking'] as $art) {
      $wer = (int) ($_POST['rider_contact_' . $art . '_user'] ?? 0);
      if ($wer > 0 && !row('SELECT id FROM users WHERE id = ?', [$wer])) $wer = 0;
      set_setting('rider_contact_' . $art . '_user', (string) $wer);
    }
    flash(t('fl_rider_saved'));
    redirect('/intern/stagerider');
  }
  if ($path === '/intern/stagerider/print' && $method === 'GET') {
    view('intern/stagerider_print', [
      'title' => t('rider_title'),
      'channels' => rows('SELECT * FROM channels ORDER BY number'),
      'stageItems' => rows('SELECT * FROM stage_items ORDER BY position, id'),
    ]);
  }

  // ---------- Kanalbelegung ----------
  if ($path === '/intern/kanaele' && $method === 'GET') {
    view('intern/kanaele', [
      'title' => t('ch_title'),
      'channels' => rows('SELECT * FROM channels ORDER BY number'),
    ]);
  }
  if ($path === '/intern/kanaele/import' && $method === 'POST') {
    $tmp = $_FILES['scene']['tmp_name'] ?? '';
    if (upload_rejected((int) ($_FILES['scene']['error'] ?? UPLOAD_ERR_NO_FILE)) || !is_uploaded_file($tmp)) {
      redirect('/intern/kanaele');
    }
    $found = mixer_channels((string) file_get_contents($tmp));
    if (!$found) {
      flash(t('fl_ch_none_found'));
      redirect('/intern/kanaele');
    }
    if (isset($_POST['replace'])) q('DELETE FROM channels');
    foreach ($found as $number => $channel) {
      // Den Eingang nur setzen, wenn die Datei ihn nennt — eine X32-Szene tut
      // das nicht, und dann soll der bisherige stehen bleiben. Das Mikrofon
      // fasst kein Import an: das weiß kein Pult, das weiß nur die Band.
      if ($channel['patch'] !== '') {
        q('INSERT INTO channels (number, name, patch) VALUES (?,?,?)
           ON DUPLICATE KEY UPDATE name = VALUES(name), patch = VALUES(patch)',
          [$number, $channel['name'], $channel['patch']]);
      } else {
        q('INSERT INTO channels (number, name) VALUES (?,?)
           ON DUPLICATE KEY UPDATE name = VALUES(name)', [$number, $channel['name']]);
      }
    }
    flash(t('fl_ch_imported') . ' ' . count($found));
    redirect('/intern/kanaele');
  }
  if ($path === '/intern/kanaele/neu' && $method === 'POST') {
    $number = (int) ($_POST['number'] ?? 0);
    if ($number > 0) {
      q('INSERT INTO channels (number, patch, name, source, notes) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE patch = VALUES(patch), name = VALUES(name),
                                 source = VALUES(source), notes = VALUES(notes)',
        [$number, trim($_POST['patch'] ?? ''), trim($_POST['name'] ?? ''),
         trim($_POST['source'] ?? ''), trim($_POST['notes'] ?? '')]);
      flash(t('fl_ch_saved'));
    }
    redirect('/intern/kanaele');
  }
  if (preg_match('~^/intern/kanaele/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'delete') {
      q('DELETE FROM channels WHERE id = ?', [$m[1]]);
      flash(t('fl_ch_deleted'));
    } else {
      q('UPDATE channels SET patch = ?, name = ?, source = ?, notes = ? WHERE id = ?',
        [trim($_POST['patch'] ?? ''), trim($_POST['name'] ?? ''),
         trim($_POST['source'] ?? ''), trim($_POST['notes'] ?? ''), $m[1]]);
      flash(t('fl_ch_saved'));
    }
    redirect('/intern/kanaele');
  }
  if ($path === '/intern/kanaele/export' && $method === 'GET') {
    require_once BASE_DIR . '/app/export.php';
    $rows = [];
    foreach (rows('SELECT * FROM channels ORDER BY number') as $c) {
      $rows[] = [$c['number'], $c['patch'], $c['name'], $c['source'], $c['notes']];
    }
    export_send('kanalbelegung-' . date('Y-m-d'),
      [t('ch_number'), t('ch_patch'), t('ch_name'), t('ch_source'), t('notes')], $rows);
  }

  // ---------- Diskussionsthemen ----------
  if ($path === '/intern/themen' && $method === 'GET') {
    view('intern/themen', [
      'title' => t('inav_themen'),
      'topics' => rows('SELECT t.*, u.name AS author,
                               (SELECT COUNT(*) FROM topic_posts p WHERE p.topic_id = t.id) AS posts,
                               (SELECT MAX(p.created_at) FROM topic_posts p WHERE p.topic_id = t.id) AS last_post
                        FROM topics t LEFT JOIN users u ON u.id = t.created_by
                        ORDER BY t.closed, COALESCE((SELECT MAX(p.created_at) FROM topic_posts p WHERE p.topic_id = t.id), t.created_at) DESC'),
    ]);
  }
  if ($path === '/intern/themen' && $method === 'POST') {
    $title = trim($_POST['title'] ?? '');
    if ($title !== '') {
      q('INSERT INTO topics (title, created_by) VALUES (?,?)', [$title, $me['id']]);
      $topicId = (int) $db->lastInsertId();
      if (trim($_POST['text'] ?? '') !== '') {
        q('INSERT INTO topic_posts (topic_id, user_id, text) VALUES (?,?,?)', [$topicId, $me['id'], trim($_POST['text'])]);
      }
      flash(t('fl_topic_created'));
      redirect('/intern/themen/' . $topicId);
    }
    redirect('/intern/themen');
  }
  if (preg_match('~^/intern/themen/(\d+)$~', $path, $m) && $method === 'GET') {
    $topic = row('SELECT t.*, u.name AS author FROM topics t LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?', [$m[1]]);
    if (!$topic) { http_response_code(404); view('404', ['title' => t('inav_themen')]); }
    view('intern/thema', [
      'title' => $topic['title'],
      'topic' => $topic,
      'posts' => rows('SELECT p.*, u.name AS author FROM topic_posts p LEFT JOIN users u ON u.id = p.user_id
                       WHERE p.topic_id = ? ORDER BY p.created_at', [$m[1]]),
    ]);
  }
  if (preg_match('~^/intern/themen/(\d+)/(antwort|schliessen|delete)$~', $path, $m) && $method === 'POST') {
    [$_, $topicId, $action] = $m;
    $topic = row('SELECT * FROM topics WHERE id = ?', [$topicId]);
    if (!$topic) redirect('/intern/themen');
    if ($action === 'antwort' && !$topic['closed'] && trim($_POST['text'] ?? '') !== '') {
      q('INSERT INTO topic_posts (topic_id, user_id, text) VALUES (?,?,?)', [$topicId, $me['id'], trim($_POST['text'])]);
    }
    if ($action === 'schliessen') {
      q('UPDATE topics SET closed = 1 - closed WHERE id = ?', [$topicId]);
    }
    if ($action === 'delete' && ((int) $topic['created_by'] === (int) $me['id'] || $me['role'] === 'admin')) {
      q('DELETE FROM topic_posts WHERE topic_id = ?', [$topicId]);
      q('DELETE FROM topics WHERE id = ?', [$topicId]);
      flash(t('fl_topic_deleted'));
      redirect('/intern/themen');
    }
    redirect('/intern/themen/' . $topicId);
  }
  if (preg_match('~^/intern/beitrag/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    $post = row('SELECT * FROM topic_posts WHERE id = ?', [$m[1]]);
    if ($post && ((int) $post['user_id'] === (int) $me['id'] || $me['role'] === 'admin')) {
      q('DELETE FROM topic_posts WHERE id = ?', [$post['id']]);
      flash(t('fl_post_deleted'));
      back('/intern/themen/' . ($post['topic_id'] ?? ''));
    }
    back('/intern/themen');
  }

  // ---------- Setlist per Ziehen umsortieren ----------
  if (preg_match('~^/intern/setlists/(\d+)/reorder$~', $path, $m) && $method === 'POST') {
    $setlistId = (int) $m[1];
    if (setlist_locked($setlistId)) {
      http_response_code(409);
      exit(t('fl_setlist_locked'));
    }
    // Nur Einträge dieser Setlist annehmen, Reihenfolge kommt aus dem Browser
    $valid = array_column(rows('SELECT id FROM setlist_songs WHERE setlist_id = ?', [$setlistId]), 'id');
    $pos = 0;
    foreach ((array) ($_POST['order'] ?? []) as $itemId) {
      if (!in_array((int) $itemId, array_map('intval', $valid), true)) continue;
      q('UPDATE setlist_songs SET position = ? WHERE id = ? AND setlist_id = ?', [++$pos, (int) $itemId, $setlistId]);
    }
    header('Content-Type: application/json');
    exit(json_encode(['ok' => true, 'count' => $pos]));
  }

  // ---------- Termine als Tabelle exportieren ----------
  if ($path === '/intern/termine/export' && $method === 'GET') {
    require_once BASE_DIR . '/app/export.php';
    $rows = [];
    // Der Export zeigt genau die Termine, die auch die Liste zeigt
    [$expWhere, $expParams] = visible_clause(visible_event_ids($me), 'e.id');
    $allEvents = rows("SELECT e.*, v.name AS venue_name, u.name AS responsible_name FROM events e
                       LEFT JOIN venues v ON v.id = e.venue_id
                       LEFT JOIN users u ON u.id = e.responsible_id
                       WHERE 1 = 1$expWhere ORDER BY e.date", $expParams);
    $exportGear = event_gear_map(array_column($allEvents, 'id'));
    foreach ($allEvents as $ev) {
      $rows[] = [
        $ev['date'], event_type_label($ev['type']), event_status_label($ev['status']), $ev['title'],
        $ev['venue_name'] ?: $ev['location'], $ev['time_meet'], $ev['time'], $ev['time_end'],
        $ev['responsible_name'] ?? '', $ev['fee'], $ev['invoice_no'],
        production_label($ev['pa_source'] ?? ''), production_label($ev['light_source'] ?? ''),
        implode(', ', array_column($exportGear[(int) $ev['id']] ?? [], 'name')),
        $ev['is_public'] ? t('ev_public_badge') : '',
        preg_replace('~\s+~u', ' ', (string) $ev['notes']),
      ];
    }
    export_send('termine-' . date('Y-m-d'), [
      t('date'), t('ev_type'), t('status'), t('name'), t('ev_venue'), t('ev_meet'), t('ev_start'), t('ev_end'),
      t('ev_responsible'), t('ev_fee'), t('ev_invoice'), t('prod_pa'), t('prod_light'), t('ev_gear'),
      t('ev_public_display'), t('ev_notes'),
    ], $rows);
  }

  // ---------- Song-Bewertungen ----------
  if (preg_match('~^/intern/songs/(\d+)/bewerten$~', $path, $m) && $method === 'POST') {
    $stars = (int) ($_POST['rating'] ?? 0);
    if ($stars >= 1 && $stars <= 5) {
      q('INSERT INTO song_ratings (song_id, user_id, rating) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)',
        [$m[1], $me['id'], $stars]);
    } else {
      q('DELETE FROM song_ratings WHERE song_id = ? AND user_id = ?', [$m[1], $me['id']]);
    }
    back('/intern/songs');
  }

  // ---------- Bandkasse ----------
  if ($path === '/intern/kasse' && $method === 'GET') {
    $years = array_column(rows('SELECT DISTINCT YEAR(date) AS y FROM finances
                                WHERE private_for IS NULL OR private_for = ? ORDER BY y DESC', [$me['id']]), 'y');
    $year = in_array((int) ($_GET['jahr'] ?? 0), array_map('intval', $years), true) ? (int) $_GET['jahr'] : null;
    // Private Buchungen sieht nur, wem sie gehören — sie stehen zwar im
    // selben Kassenbuch, sind aber kein Bandgeld.
    $where = ' WHERE (f.private_for IS NULL OR f.private_for = ' . (int) $me['id'] . ')';
    if ($year) $where .= ' AND YEAR(f.date) = ' . $year;
    $entries = rows("SELECT f.*, e.title AS event_title, e.date AS event_date, u.name AS member_name,
                            eq.name AS equipment_name
                     FROM finances f LEFT JOIN events e ON e.id = f.event_id
                     LEFT JOIN users u ON u.id = f.member_id
                     LEFT JOIN equipment eq ON eq.id = f.equipment_id
                     $where ORDER BY f.date DESC, f.id DESC");
    $filesByFinance = [];
    foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.entity_type = 'finance'") as $f) {
      $filesByFinance[$f['entity_id']][] = $f;
    }
    // Gigs mit Gage, die noch nicht als Einnahme verbucht sind — erst ab
    // Beginn des Kassenbuchs (ältere Gigs stecken im Übertrag). Wer die Liste
    // abgeschaltet hat, für den wird sie auch nicht erst berechnet.
    $openFees = setting('fin_open_fees') === '1'
      ? rows("SELECT e.* FROM events e WHERE e.type = 'gig' AND e.fee != '' AND e.status != 'abgesagt'
              AND e.date >= COALESCE((SELECT MIN(f2.date) FROM finances f2 WHERE f2.private_for IS NULL), '1000-01-01')
              AND NOT EXISTS (SELECT 1 FROM finances fi WHERE fi.event_id = e.id AND fi.type = 'einnahme')
              ORDER BY e.date DESC")
      : [];
    view('intern/kasse', [
      'title' => t('fin_title'),
      'entries' => $entries,
      'filesByFinance' => $filesByFinance,
      'years' => $years,
      'year' => $year,
      'openFees' => $openFees,
      'orders' => orders_for($me),
      // Der Kontostand ist der der Bandkasse — private Buchungen bleiben außen vor.
      'balance' => (int) (row("SELECT COALESCE(SUM(IF(type='einnahme', amount_cents, -amount_cents)), 0) AS b
                               FROM finances WHERE private_for IS NULL")['b'] ?? 0),
      'members' => rows('SELECT id, name FROM users ORDER BY name'),
      'events' => rows('SELECT id, title, date FROM events ORDER BY date DESC LIMIT 100'),
    ]);
  }
  if ($path === '/intern/kasse' && $method === 'POST') {
    if (!can_finance()) { flash(t('fl_finance_required')); redirect('/intern/kasse'); }
    // price_to_cents() unterscheidet Tausender- von Dezimaltrennzeichen an der
    // Stellenzahl. Die frühere Zeile entfernte erst alle Punkte — „12.50" wurde
    // damit zu 1.250,00 €, und ein Handy-Zahlenfeld liefert nun einmal Punkte.
    $amount = (int) (price_to_cents((string) ($_POST['amount'] ?? '')) ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';
    if ($amount > 0 && $desc !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) {
      q('INSERT INTO finances (date, type, amount_cents, category, description, event_id, member_id, created_by) VALUES (?,?,?,?,?,?,?,?)', [
        $date,
        ($_POST['type'] ?? '') === 'einnahme' ? 'einnahme' : 'ausgabe',
        $amount,
        array_key_exists($_POST['category'] ?? '', FIN_CATEGORIES) ? $_POST['category'] : 'sonstiges',
        $desc,
        (int) ($_POST['event_id'] ?? 0) ?: null,
        (int) ($_POST['member_id'] ?? 0) ?: null,
        $me['id'],
      ]);
      flash(t('fl_fin_saved'));
    } else {
      flash(t('fl_fin_invalid'));
    }
    redirect('/intern/kasse');
  }
  // Gage eines Gigs als Einnahme übernehmen
  if (preg_match('~^/intern/kasse/gage/(\d+)$~', $path, $m) && $method === 'POST') {
    if (!can_finance()) { flash(t('fl_finance_required')); redirect('/intern/kasse'); }
    $ev = row("SELECT * FROM events WHERE id = ? AND fee != ''", [$m[1]]);
    // Das Gage-Feld ist Freitext. Früher wurde die erste Ziffernfolge daraus
    // gegriffen — aus „ab 19 Uhr, 600 €" wurden 19,00 €, aus „2x400" zwei Euro.
    // Übernommen wird jetzt nur, was als Ganzes ein Betrag ist; alles andere
    // trägt die Kassenführung von Hand ein, statt dass wir raten.
    if ($ev) {
      $feeRoh = trim((string) $ev['fee']);
      $amount = preg_match('~^[\d.,\s]+(?:€|EUR)?$~ui', $feeRoh)
        ? (int) (price_to_cents($feeRoh) ?? 0) : 0;
      if ($amount > 0) {
        q('INSERT INTO finances (date, type, amount_cents, category, description, event_id, created_by) VALUES (?,?,?,?,?,?,?)',
          [$ev['date'], 'einnahme', $amount, 'gage', $ev['title'], $ev['id'], $me['id']]);
        flash(t('fl_fin_saved'));
      } else {
        flash(t('fl_fee_unclear'));
      }
    }
    redirect('/intern/kasse');
  }
  if (preg_match('~^/intern/kasse/(\d+)/delete$~', $path, $m) && $method === 'POST') {
    if (!may_edit_finance(row('SELECT * FROM finances WHERE id = ?', [$m[1]]))) {
      flash(t('fl_finance_required'));
      redirect('/intern/kasse');
    }
    q('DELETE FROM finances WHERE id = ?', [$m[1]]);
    flash(t('fl_fin_deleted'));
    redirect('/intern/kasse');
  }

  // Was diese Person offline dabeihaben will, als Liste von Adressen. Die
  // Seite fragt sie im Hintergrund ab und gibt sie an den Service Worker.
  if ($path === '/intern/offline/liste' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['urls' => offline_urls($me)], JSON_UNESCAPED_SLASHES));
  }
  // Bereiche wählen. Steht im eigenen Profil, denn das Telefon ist persönlich.
  if ($path === '/intern/offline/bereiche' && $method === 'POST') {
    $gewaehlt = array_values(array_intersect(OFFLINE_AREAS, (array) ($_POST['areas'] ?? [])));
    // Alles abgewählt wird als solches gespeichert und nicht als „nichts
    // eingestellt" — sonst käme beim nächsten Laden wieder alles zurück.
    q('UPDATE users SET offline_scope = ? WHERE id = ?',
      [$gewaehlt ? implode(',', $gewaehlt) : OFFLINE_NICHTS, $me['id']]);
    flash(t('fl_off_saved'));
    redirect('/intern/profil');
  }

  // Was zu einem Auftritt gehört, als Liste von Adressen. Der Service Worker
  // holt sie in den Zwischenspeicher, damit auf der Bühne nichts fehlt, was
  // niemand vorher zufällig geöffnet hat.
  //
  // Ausgegeben wird, was diese Person auch sehen darf: die Sichtbarkeit des
  // Termins entscheidet, und die Anhänge folgen ihren Gegenständen.
  if (preg_match('~^/intern/termine/(\d+)/offline$~', $path, $m) && $method === 'GET') {
    $offEv = row('SELECT * FROM events WHERE id = ?', [$m[1]]);
    if (!$offEv || !may_see_event($me, (int) $offEv['id'])) { http_response_code(404); exit('{}'); }

    $offUrls = ['/intern', '/intern/termine', '/intern/songs', '/intern/stagerider',
                '/intern/stagerider/print', '/intern/kanaele'];
    $offSongIds = [];
    if ($offEv['setlist_id']) {
      $offUrls[] = '/intern/setlists';
      $offUrls[] = '/intern/setlists/' . (int) $offEv['setlist_id'];
      $offUrls[] = '/intern/setlists/' . (int) $offEv['setlist_id'] . '/print';
      $offSongIds = array_map('intval', array_column(
        rows('SELECT song_id FROM setlist_songs WHERE setlist_id = ? AND song_id IS NOT NULL',
             [$offEv['setlist_id']]), 'song_id'));
      // Jedes Lied der Setlist mit Leseseite und Bühne/Noten — Letztere mit der
      // Setlist im Rücken (?sl), damit auf der Bühne durchgeblättert werden kann.
      // Ohne diese URLs käme der Teleprompter offline gar nicht erst hoch.
      $offSl = (int) $offEv['setlist_id'];
      foreach ($offSongIds as $offSong) {
        $offUrls[] = '/intern/songs/' . $offSong;
        $offUrls[] = '/intern/songs/' . $offSong . '/buehne?sl=' . $offSl;
        $offUrls[] = '/intern/songs/' . $offSong . '/noten?sl=' . $offSl;
      }
    }

    // Anhänge: die des Termins, die der Setlist und die der Songs darin. Das
    // sind die Noten — der Grund, warum das Ganze überhaupt nötig ist.
    $offFiles = files_map('event', [(int) $offEv['id']]);
    if ($offEv['setlist_id']) {
      $offFiles += files_map('setlist', [(int) $offEv['setlist_id']]);
    }
    if ($offSongIds) $offFiles += files_map('song', $offSongIds);
    foreach ($offFiles as $offList) {
      foreach ($offList as $offFile) $offUrls[] = '/intern/datei/' . (int) $offFile['id'];
    }

    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode([
      'title' => $offEv['title'],
      'urls' => array_values(array_unique($offUrls)),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }

  // ---------- Steuerübersicht ----------
  // Die eigenen Zahlen sieht jeder für sich, die der Band sieht die
  // Kassenführung. Ein fremder privater Kauf taucht in keiner der beiden auf.
  if ($path === '/intern/kasse/steuer' && $method === 'GET') {
    require_once BASE_DIR . '/app/steuer.php';
    view('intern/steuer', ['title' => t('taxr_title')] + tax_report_for($me, $_GET));
  }
  if ($path === '/intern/kasse/steuer/druck' && $method === 'GET') {
    require_once BASE_DIR . '/app/steuer.php';
    view('intern/steuer_print', ['title' => t('taxr_title')] + tax_report_for($me, $_GET));
  }
  if ($path === '/intern/kasse/steuer/export' && $method === 'GET') {
    require_once BASE_DIR . '/app/steuer.php';
    require_once BASE_DIR . '/app/export.php';
    $taxView = tax_report_for($me, $_GET);
    [$taxHead, $taxRows] = tax_export_table($taxView['report']);
    export_send('steuer-' . $taxView['year'] . '-' . $taxView['scope'], $taxHead, $taxRows);
  }
  // Das Paket für die Steuerberatung: Tabelle, Belege, Beiblatt. Ohne die
  // ZIP-Erweiterung bleibt es bei der Tabelle — dann fehlt nichts, was
  // vorher da war.
  if ($path === '/intern/kasse/steuer/paket' && $method === 'GET') {
    require_once BASE_DIR . '/app/steuer.php';
    require_once BASE_DIR . '/app/export.php';
    $taxView = tax_report_for($me, $_GET);
    $taxOwner = $taxView['scope'] === 'band' ? setting('band_name') : (string) $me['name'];
    $taxZip = tax_report_package($taxView, $taxOwner);
    if ($taxZip === null) {
      flash(t('fl_taxr_no_zip'));
      redirect('/intern/kasse/steuer?jahr=' . $taxView['year'] . '&umfang=' . $taxView['scope']);
    }
    $taxName = 'steuer-' . $taxView['year'] . '-' . $taxView['scope'] . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $taxName . '"');
    header('Content-Length: ' . (string) filesize($taxZip));
    readfile($taxZip);
    @unlink($taxZip);
    exit;
  }

  // ---------- Einstellungen ----------
  // Anmelde-Anbieter (#97): IDs offen, Geheimnisse nur bei neuer Eingabe —
  // ein leeres Feld heißt "behalten", nie "löschen". Versiegelt abgelegt,
  // wenn ein Schlüssel liegt (wie das FTP-Passwort der Sicherung).
  // Was die Anwendung nach außen tun darf — beides an einer Stelle.
  if ($path === '/intern/einstellungen/extern' && $method === 'POST') {
    require_admin();
    // In der Demo unverändert: dort löste sonst ein Besucher echten Verkehr
    // dieses Servers an Google, Apple, Mozilla oder OpenStreetMap aus.
    deny_in_demo('/intern/einstellungen');
    set_setting('push_enabled', isset($_POST['push_enabled']) ? '1' : '0');
    set_setting('geocoding_enabled', isset($_POST['geocoding_enabled']) ? '1' : '0');
    set_setting('onedrive_enabled', isset($_POST['onedrive_enabled']) ? '1' : '0');
    set_setting('od_auto_refresh', isset($_POST['od_auto_refresh']) ? '1' : '0');
    flash(t('fl_extern_saved'));
    redirect('/intern/einstellungen');
  }
  /**
   * OneDrive: Anwendungsdaten eintragen (#20).
   *
   * Das Geheimnis wird nur bei Eingabe ersetzt. Ein leeres Feld heißt behalten,
   * nie löschen — sonst reicht ein Speichern der Seite, um die Verbindung
   * unbrauchbar zu machen, ohne dass es jemand merkt. Dasselbe Verhalten wie
   * bei den Zugangsdaten der Sicherung.
   */
  if ($path === '/intern/einstellungen/onedrive' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    set_setting('onedrive_client_id', trim((string) ($_POST['onedrive_client_id'] ?? '')));
    set_setting('onedrive_tenant', trim((string) ($_POST['onedrive_tenant'] ?? 'common')));
    $odSecret = trim((string) ($_POST['onedrive_client_secret'] ?? ''));
    if ($odSecret !== '') {
      set_setting('onedrive_client_secret', crypt_available() ? crypt_seal($odSecret) : $odSecret);
    }
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }
  // Hin zu Microsoft. Nur als POST, damit kein fremder Link die Anmeldung
  // auslösen kann.
  if ($path === '/intern/einstellungen/onedrive/start' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    if (!od_enabled()) { flash(od_configured() ? t('od_needs_enable') : t('od_needs_setup')); redirect('/intern/einstellungen'); }
    redirect(od_auth_url());
  }
  // Und zurück. Diese Adresse steht bei Microsoft in der Registrierung.
  if ($path === '/intern/einstellungen/onedrive/zurueck' && $method === 'GET') {
    require_admin();
    $odState = (string) ($_SESSION['od_state'] ?? '');
    $odVerifier = (string) ($_SESSION['od_verifier'] ?? '');
    unset($_SESSION['od_state'], $_SESSION['od_verifier']);
    // Zustandswert vergleichen, bevor irgendetwas mit dem Code passiert: Ohne
    // diesen Vergleich könnte jemand eine eigene Rückleitung in einen
    // angemeldeten Browser schicken und dessen Band mit seinem OneDrive
    // verbinden.
    if ($odState === '' || !hash_equals($odState, (string) ($_GET['state'] ?? ''))) {
      flash(t('od_state_bad'));
      redirect('/intern/einstellungen');
    }
    if (($_GET['error'] ?? '') !== '' || ($_GET['code'] ?? '') === '') {
      flash(($_GET['error'] ?? '') === 'access_denied'
        ? t('od_denied')
        : t('od_denied') . ' ' . mb_substr((string) ($_GET['error_description'] ?? ''), 0, 200));
      redirect('/intern/einstellungen');
    }
    $odRes = od_exchange_code((string) $_GET['code'], $odVerifier);
    flash($odRes['ok'] ? t('od_ok') : $odRes['message']);
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen/onedrive/loesen' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    od_disconnect();
    flash(t('od_gone'));
    redirect('/intern/einstellungen');
  }

  // Aufräumen: tote Verweise finden und entfernen (#193). Zwei Schritte, nie
  // einer — erst zeigen, was gefunden wurde, dann auf Klick löschen. Ein Knopf,
  // der ohne Vorschau löscht, ist bei Dateien der falsche Knopf.
  if ($path === '/intern/einstellungen/aufraeumen' && $method === 'GET') {
    require_admin();
    // Prüfsummen hier nachtragen, nicht beim Hochfahren (#199): Die Wartezeit
    // trägt, wer die Doppelten sehen will — nicht wer gerade etwas anderes tut.
    $nachtrag = checksums_fill();
    view('intern/aufraeumen', ['title' => t('clean_title'), 'fund' => orphan_scan(),
                               'doppelte' => photo_duplicates(), 'nachtrag' => $nachtrag]);
  }
  // Eines aus einer Doppelten-Gruppe entfernen (#199). Einzeln und je Bild:
  // Welches bleibt, ist eine Entscheidung — die trifft keine Schleife.
  if (preg_match('~^/intern/einstellungen/aufraeumen/foto/(\d+)$~', $path, $m) && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    if (photo_remove((int) $m[1])) flash(t('fl_dup_removed'));
    redirect('/intern/einstellungen/aufraeumen');
  }
  if ($path === '/intern/einstellungen/aufraeumen' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    $weg = orphan_clean();
    flash(str_replace(['%1', '%2', '%3'], [$weg['rows'], $weg['photos'], $weg['files']], t('fl_cleaned')));
    redirect('/intern/einstellungen/aufraeumen');
  }

  // Das Laufwerk durchsehen und Ordner verknüpfen (#20). Eine eigene Seite und
  // nicht ein Block in den Einstellungen: Durchsehen heißt klicken, und jeder
  // Klick lädt eine Ebene nach — das gehört nicht zwischen die Formulare.
  if ($path === '/intern/einstellungen/onedrive/ordner' && $method === 'GET') {
    require_admin();
    $odItem = trim((string) ($_GET['id'] ?? ''));
    view('intern/onedrive_ordner', [
      'title'    => t('od_browse_title'),
      'odItem'   => $odItem,
      'odName'   => trim((string) ($_GET['name'] ?? '')),
      // Ohne Verbindung gar nicht erst fragen: Die Seite soll erklären, was
      // fehlt, statt eine leere Liste zu zeigen.
      'odInhalt' => od_connection()['connected'] ? od_children($odItem) : null,
      'odLinked' => od_folders(),
      'odWeg'    => array_values(array_filter(array_map('trim', explode('/', (string) ($_GET['weg'] ?? ''))))),
    ]);
  }
  if ($path === '/intern/einstellungen/onedrive/ordner/verknuepfen' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    $odItem = trim((string) ($_POST['item_id'] ?? ''));
    if ($odItem === '') { flash(t('od_link_failed')); back('/intern/einstellungen'); }
    od_folder_link($odItem, (string) ($_POST['name'] ?? ''), (string) ($_POST['path'] ?? ''), (int) $me['id']);
    // Gleich nachsehen, was drin liegt: Ein verknüpfter Ordner ohne Inhalt sagt
    // nichts darüber, ob die Verknüpfung getroffen hat.
    $odNeu = row('SELECT id FROM od_folders WHERE item_id = ?', [$odItem]);
    if ($odNeu) od_folder_refresh((int) $odNeu['id']);
    flash(t('od_linked'));
    back('/intern/einstellungen/onedrive/ordner');
  }
  // Verknüpfte Bilder in die Galerie holen (#206). Eigene Route, eigener Knopf:
  // Nachsehen und Übernehmen sind zwei Entscheidungen — wer wissen will, was da
  // liegt, will nicht zwangsläufig fünfhundert Bilder herunterladen.
  if (preg_match('~^/intern/einstellungen/onedrive/ordner/(\d+)/uebernehmen$~', $path, $m) && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    $odHol = od_import((int) $m[1]);
    $odText = [str_replace(['%1', '%2'], [(string) $odHol['done'], fmt_bytes($odHol['bytes'])], t('od_imported'))];
    if ($odHol['left']) $odText[] = str_replace('%1', (string) $odHol['left'], t('od_import_left'));
    if ($odHol['failed']) $odText[] = str_replace('%1', (string) $odHol['failed'], t('od_import_failed'));
    flash(implode(' ', $odText));
    back('/intern/einstellungen/onedrive/ordner');
  }
  if (preg_match('~^/intern/einstellungen/onedrive/ordner/(\d+)/(aktualisieren|loesen)$~', $path, $m) && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    if ($m[2] === 'loesen') {
      od_folder_unlink((int) $m[1]);
      flash(t('od_unlinked'));
    } else {
      $odStand = od_folder_refresh((int) $m[1]);
      if (!$odStand['ok']) {
        flash(t('od_unreachable'));
      } else {
        // Was der Durchgang gesehen hat, und was er nicht gesehen hat. Eine
        // Grenze, die niemand nennt, liest sich wie Vollständigkeit (#205).
        $odText = [str_replace(['%1', '%2', '%3', '%4'],
          [$odStand['neu'], $odStand['geaendert'], $odStand['fehlt'], $odStand['folders']], t('od_refreshed'))];
        if ($odStand['capped']) $odText[] = str_replace('%1', (string) OD_MAX_FILES, t('od_capped'));
        if ($odStand['deep']) {
          $odText[] = str_replace(['%1', '%2'], [(string) count($odStand['deep']), (string) OD_MAX_DEPTH], t('od_too_deep'))
            . ' ' . implode(', ', array_slice($odStand['deep'], 0, 3));
        }
        if ($odStand['unreachable']) {
          $odText[] = str_replace('%1', (string) count($odStand['unreachable']), t('od_part_unreachable'));
        }
        flash(implode(' ', $odText));
      }
    }
    back('/intern/einstellungen/onedrive/ordner');
  }

  // Zweiter Faktor: ob es ihn gibt, und ob er für alle gilt (#169).
  if ($path === '/intern/einstellungen/zwei-faktor' && $method === 'POST') {
    require_admin();
    // In der Demo unverändert: „vorgeschrieben" würde jeden Besucher in eine
    // Einrichtung zwingen, die er mit einem geteilten Konto gar nicht führen
    // kann — und der Nächste stünde vor einem Code, den er nie bekommt.
    deny_in_demo('/intern/einstellungen');
    $totpModus = $_POST['totp_mode'] ?? 'optional';
    set_setting('totp_mode', in_array($totpModus, ['off', 'optional', 'required'], true) ? $totpModus : 'optional');
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen' && $method === 'GET') {
    require_admin();
    // Alle Inhalts-Übersetzungen aller Sprachen: [lang][tkey] => value
    $contentAll = [];
    foreach (rows("SELECT lang, tkey, value FROM translations WHERE tkey LIKE 'content_%'") as $r) {
      $contentAll[$r['lang']][$r['tkey']] = $r['value'];
    }
    view('intern/einstellungen', [
      'title' => t('inav_einstellungen'),
      'ical_url' => absolute_url('/kalender/' . setting('ical_token') . '.ics'),
      'contentAll' => $contentAll,
      'backupRuns' => rows('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 12'),
    ]);
  }
  // Serien an oder aus (#212). Beim Einschalten wird sofort neu gruppiert —
  // sonst zeigte die Galerie den Stand von vor dem Abschalten, und der kann
  // Wochen alt sein.
  if ($path === '/intern/einstellungen/serien' && $method === 'POST') {
    require_admin();
    // In der Demo gesperrt wie jede dauerhafte Einstellung: Dort ist jeder
    // Besucher Admin, und der Nächste fände eine Galerie vor, die ein Fremder
    // umgestellt hat (Review 06.08.).
    deny_in_demo('/intern/einstellungen');
    $an = isset($_POST['stacks_enabled']);
    set_setting('stacks_enabled', $an ? '1' : '0');
    if ($an) stacks_rebuild();
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen' && $method === 'POST') {
    require_admin();
    foreach (['band_name', 'contact_email', 'copyright_text', 'facebook_url', 'instagram_url', 'spotify_url', 'youtube_url', 'site_url'] as $k) {
      if (isset($_POST[$k])) set_setting($k, trim($_POST[$k]));
    }
    if (isset($_POST['_termine_form'])) {
      set_setting('public_show_past', isset($_POST['public_show_past']) ? '1' : '0');
      set_setting('public_limit_upcoming', (string) max(0, (int) ($_POST['public_limit_upcoming'] ?? 10)));
      set_setting('public_limit_past', (string) max(0, (int) ($_POST['public_limit_past'] ?? 5)));
      set_setting('public_embed_mode', ($_POST['public_embed_mode'] ?? '') === 'direct' ? 'direct' : 'consent');
      // Nicht in der Demo umschaltbar: die Adress-Suche fragt serverseitig
      // OpenStreetMap, und die Nutzungsrichtlinie trifft die Adresse dieses
      // Servers — nicht die des Besuchers, der den Schalter umlegt.
      // Umleitung und Ziel bleiben in der Demo, wie sie sind: damit ließe sich
      // die öffentliche Demo in einen Umleiter auf eine beliebige Adresse
      // verwandeln — auf der Domain des Projekts, bis zum nächsten Zurücksetzen.
      if (!is_demo()) {
        set_setting('public_mode', ($_POST['public_mode'] ?? '') === 'redirect' ? 'redirect' : 'website');
        if (($_POST['redirect_url'] ?? '') !== '') set_setting('redirect_url', trim($_POST['redirect_url']));
      }
    }
    if (isset($_POST['_tax_form'])) {
      // Die Beträge kommen als Zahl mit Komma oder Punkt herein; price_to_cents
      // kennt beides, gespeichert wird wieder in Euro.
      foreach (['tax_limit_prev_year', 'tax_limit_this_year', 'tax_gwg_limit',
                'tax_commercial_share', 'tax_commercial_abs', 'tax_vat_rate'] as $taxKey) {
        $cents = price_to_cents((string) ($_POST[$taxKey] ?? ''));
        if ($cents !== null && $cents >= 0) set_setting($taxKey, (string) round($cents / 100, 2));
      }
      // Die Nutzungsdauer ist eine Anzahl Jahre, kein Betrag. Null Jahre gibt
      // es nicht — dann bliebe ein Kauf für immer stehen.
      foreach (['tax_afa_years', ...array_map(fn($c) => 'tax_afa_' . $c, array_keys(TAX_AFA_BY_CATEGORY))] as $afaKey) {
        $afaYears = (int) ($_POST[$afaKey] ?? 0);
        if ($afaYears >= 1 && $afaYears <= 50) set_setting($afaKey, (string) $afaYears);
      }
      set_setting('tax_prices_gross', isset($_POST['tax_prices_gross']) ? '1' : '0');
      // Gründungsdatum: leer heißt „gibt es nicht" — dann rechnet die Kasse wie
      // bisher mit Vorjahr und laufendem Jahr.
      $taxStart = trim($_POST['tax_business_start'] ?? '');
      set_setting('tax_business_start', preg_match('~^\d{4}-\d{2}-\d{2}$~', $taxStart) ? $taxStart : '');
      set_setting('tax_small_business', isset($_POST['tax_small_business']) ? '1' : '0');
      $taxDate = trim($_POST['tax_values_checked'] ?? '');
      set_setting('tax_values_checked', preg_match('~^\d{4}-\d{2}-\d{2}$~', $taxDate) ? $taxDate : date('Y-m-d'));
      flash(t('fl_tax_saved'));
    }
    if (isset($_POST['_update_form'])) {
      set_setting('update_check', isset($_POST['update_check']) ? '1' : '0');
      // Beim Einschalten gleich nachsehen, statt bis morgen zu warten.
      if (isset($_POST['update_check'])) set_setting('update_checked_at', '0');
      flash(t('fl_up_saved'));
    }
    if (isset($_POST['_langs_form'])) {
      $chosen = array_values(array_intersect(array_keys(LANGS), (array) ($_POST['langs'] ?? [])));
      // Die Standardsprache ist die Rückfallebene und damit immer aktiv —
      // ihr Häkchen ist gesperrt und wird deshalb gar nicht erst gesendet.
      // Eine neu gewählte Standardsprache schaltet sich selbst ein.
      $newDefault = array_key_exists($_POST['default_lang'] ?? '', LANGS) ? $_POST['default_lang'] : default_lang();
      if (!in_array($newDefault, $chosen, true)) array_unshift($chosen, $newDefault);
      set_setting('default_lang', $newDefault);
      set_setting('enabled_langs', implode(',', $chosen));
      unset($_SESSION['pub_lang']);
    }
    // Mehrsprachige Texte: alle Sprachen in einem Formular (txt[lang][feld]).
    // Deutsch landet in settings (Fallback-Basis), andere Sprachen in translations.
    $textFields = isset($_POST['_texts_form']) ? ['bio', 'tagline', 'booking_text']
      : (isset($_POST['_legal_form']) ? ['impressum_text', 'privacy_text'] : null);
    if ($textFields) {
      foreach (enabled_langs() as $lang) {
        foreach ($textFields as $ckey) {
          if (!isset($_POST['txt'][$lang][$ckey])) continue;
          $value = trim($_POST['txt'][$lang][$ckey]);
          if ($lang === 'de') {
            set_setting($ckey, $value);
          } elseif ($value === '') {
            q('DELETE FROM translations WHERE lang = ? AND tkey = ?', [$lang, 'content_' . $ckey]);
          } else {
            q('INSERT INTO translations (lang, tkey, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$lang, 'content_' . $ckey, $value]);
          }
        }
      }
      flash(t('fl_texts_saved'));
      redirect('/intern/einstellungen');
    }
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }
  // ---------- Daueraufträge ----------
  if ($path === '/intern/kasse/dauerauftrag' && $method === 'POST') {
    if (!perm_allows($me, 'kasse')) { flash(t('fl_no_permission')); redirect('/intern/kasse'); }
    // Drei Fälle: die Bandkasse führt, wer dort schreiben darf. Die eigene
    // Einzahlung und den privaten Auftrag richtet jeder selbst ein, der die
    // Kasse überhaupt sieht — beide tragen seinen Namen.
    $scope = in_array($_POST['scope'] ?? '', ['band', 'einzahlung', 'own'], true) ? $_POST['scope'] : 'own';
    if ($scope === 'band' && !perm_allows($me, 'kasse', 'write')) { flash(t('fl_no_permission')); redirect('/intern/kasse'); }
    $forBand = $scope === 'band';
    $cents = price_to_cents((string) ($_POST['amount'] ?? ''));
    $start = trim($_POST['start_date'] ?? '') ?: date('Y-m-d');
    if ($cents && trim($_POST['description'] ?? '') !== '') {
      // Eine Einzahlung ist per Definition eine Einnahme der Band unter
      // „Einzahlung Mitglieder" — Art und Kategorie stehen damit fest.
      q('INSERT INTO standing_orders (owner_id, private, type, amount_cents, category, description,
                                      interval_kind, start_date, end_date, next_date, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
        $forBand ? null : $me['id'],
        $scope === 'own' ? 1 : 0,
        $scope === 'einzahlung' || ($_POST['type'] ?? '') === 'einnahme' ? 'einnahme' : 'ausgabe',
        $cents,
        $scope === 'einzahlung' ? 'einlage'
          : (array_key_exists($_POST['category'] ?? '', FIN_CATEGORIES) ? $_POST['category'] : 'sonstiges'),
        trim($_POST['description']),
        array_key_exists($_POST['interval_kind'] ?? '', ORDER_INTERVALS) ? $_POST['interval_kind'] : 'monthly',
        $start, trim($_POST['end_date'] ?? '') ?: null, $start, $me['id'],
      ]);
      orders_run();
      flash(t('fl_order_saved'));
    } else {
      flash(t('fl_fin_invalid'));
    }
    redirect('/intern/kasse');
  }
  if (preg_match('~^/intern/kasse/dauerauftrag/(\d+)/(pause|delete)$~', $path, $m) && $method === 'POST') {
    $order = row('SELECT * FROM standing_orders WHERE id = ?', [$m[1]]);
    if (!may_edit_order($me, $order)) { flash(t('fl_no_permission')); redirect('/intern/kasse'); }
    if ($m[2] === 'pause') {
      q('UPDATE standing_orders SET paused = 1 - paused WHERE id = ?', [$m[1]]);
      flash((int) $order['paused'] ? t('fl_order_resumed') : t('fl_order_paused'));
    } else {
      // Die bereits erzeugten Buchungen bleiben stehen — sie sind Geschichte,
      // kein Zubehör des Auftrags. Nur der Verweis wird gelöst.
      q('UPDATE finances SET standing_order_id = NULL WHERE standing_order_id = ?', [$m[1]]);
      q('DELETE FROM standing_orders WHERE id = ?', [$m[1]]);
      flash(t('fl_order_deleted'));
    }
    redirect('/intern/kasse');
  }

  if ($path === '/intern/einstellungen/kasse' && $method === 'POST') {
    require_admin();
    set_setting('fin_open_fees', isset($_POST['fin_open_fees']) ? '1' : '0');
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen/ersatz' && $method === 'POST') {
    require_admin();
    set_setting('substitute_auto', in_array($_POST['substitute_auto'] ?? '', SUB_AUTO_MODES, true) ? $_POST['substitute_auto'] : 'off');
    flash(t('fl_settings_saved'));
    redirect('/intern/einstellungen');
  }

  // ---------- Sicherungen ----------
  if ($path === '/intern/einstellungen/backup' && $method === 'POST') {
    require_admin();
    set_setting('backup_enabled', isset($_POST['backup_enabled']) ? '1' : '0');
    set_setting('backup_interval', array_key_exists($_POST['backup_interval'] ?? '', BACKUP_INTERVALS) ? $_POST['backup_interval'] : 'daily');
    set_setting('backup_keep', (string) max(1, min(365, (int) ($_POST['backup_keep'] ?? 7))));
    flash(t('fl_bk_saved'));
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen/backup-ziele' && $method === 'POST') {
    require_admin();
    // Ein Zweitziel ist eine Adresse, an die dieser Server Dateien schickt.
    // In einer Demo mit öffentlichen Zugangsdaten wäre das eine Einladung,
    // sich ein Archiv auf den eigenen Server legen zu lassen.
    deny_in_demo('/intern/einstellungen');
    set_setting('backup_ftp_enabled', isset($_POST['backup_ftp_enabled']) ? '1' : '0');
    foreach (['backup_ftp_host', 'backup_ftp_user', 'backup_ftp_dir'] as $k) {
      set_setting($k, trim($_POST[$k] ?? ''));
    }
    set_setting('backup_ftp_port', (string) max(1, min(65535, (int) ($_POST['backup_ftp_port'] ?? 21))));
    set_setting('backup_ftp_keep', (string) max(1, min(365, (int) ($_POST['backup_ftp_keep'] ?? 14))));
    set_setting('backup_ftp_tls', isset($_POST['backup_ftp_tls']) ? '1' : '0');
    set_setting('backup_ftp_passive', isset($_POST['backup_ftp_passive']) ? '1' : '0');
    // Ein leeres Passwortfeld heißt „nicht ändern" — das gespeicherte wird
    // nie ins Formular zurückgeschrieben, es gäbe also nichts abzuschicken.
    // Das FTP-Passwort muss im Klartext an den Server gehen — anders meldet
    // sich FTP nicht an. In der Datenbank hat es trotzdem nichts verloren:
    // liegt ein Schlüssel, wird es versiegelt abgelegt.
    if (($_POST['backup_ftp_pass'] ?? '') !== '') {
      set_setting('backup_ftp_pass', crypt_available()
        ? (string) crypt_seal($_POST['backup_ftp_pass'])
        : $_POST['backup_ftp_pass']);
    }
    flash(t('fl_bk_targets_saved'));
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/backup/ftp-test' && $method === 'POST') {
    require_admin();
    // Der Test baut eine Verbindung zu einem frei eingetippten Host auf. In
    // einer Demo, deren Zugangsdaten öffentlich sind, wäre das ein Werkzeug
    // für Fremde und keine Prüfung der eigenen Einrichtung.
    deny_in_demo('/intern/einstellungen');
    $test = backup_ftp_test();
    flash(($test['ok'] ? '✔ ' : '⚠ ') . $test['message']);
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/backup/run' && $method === 'POST') {
    require_admin();
    deny_in_demo('/intern/einstellungen');
    $run = backup_run('manuell');
    flash(($run['status'] ?? '') === 'ok' ? t('fl_bk_done') : t('fl_bk_failed') . ' ' . ($run['message'] ?? ''));
    redirect('/intern/einstellungen');
  }
  // Die Anhänge aus der Zeit vor dem Schlüssel nachträglich versiegeln. Kein
  // Automatismus: bei vielen Dateien dauert es, und es soll jemand zusehen.
  if ($path === '/intern/dateien/versiegeln' && $method === 'POST') {
    require_admin();
    if (!crypt_available()) {
      flash(t('fl_crypt_no_key'));
      redirect('/intern/einstellungen');
    }
    @set_time_limit(0);
    $sealed = files_seal_all();
    flash($sealed['failed'] > 0
      ? sprintf(t('fl_crypt_sealed_some'), $sealed['done'], $sealed['failed'])
      : sprintf(t('fl_crypt_sealed'), $sealed['done']));
    redirect('/intern/einstellungen');
  }
  // Ein Archiv von außen einspielen — für den Fall, dass der Server neu
  // aufgesetzt wurde und hier noch nichts liegt.
  if ($path === '/intern/backup/upload' && $method === 'POST') {
    require_admin();
    // Ein hochgeladenes Archiv wird beim Zurückspielen ausgeführt: sein SQL
    // läuft Anweisung für Anweisung. Zusammen mit öffentlichen Zugangsdaten
    // wäre das fremdes SQL auf dieser Datenbank.
    deny_in_demo('/intern/einstellungen');
    $up = $_FILES['archive'] ?? null;
    $name = basename((string) ($up['name'] ?? ''));
    if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || !preg_match('~\.tar\.gz(\.enc)?$~', $name)) {
      flash(t('fl_bk_upload_invalid'));
      redirect('/intern/einstellungen');
    }
    $safe = preg_replace('~[^A-Za-z0-9._-]~', '_', $name);
    // Kein vorhandenes Archiv überschreiben: sonst zeigten zwei Einträge auf
    // dieselbe Datei, und „Löschen" bei einem nähme sie dem anderen weg.
    $target = backup_dir() . '/' . $safe;
    for ($n = 2; file_exists($target); $n++) {
      $target = backup_dir() . '/' . $n . '-' . $safe;
    }
    // Erst eintragen, wenn die Datei wirklich liegt. Ohne diese Prüfung entstand
    // bei einem gescheiterten Verschieben eine Zeile „ok, 0 Bytes", die einen
    // Platz im Aufbewahrungsfenster belegte und eine echte Sicherung zu früh
    // löschen ließ.
    if (!move_uploaded_file($up['tmp_name'], $target)) {
      flash(t('fl_bk_upload_failed'));
      redirect('/intern/einstellungen');
    }
    @chmod($target, 0600);
    q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
      [basename($target), (int) filesize($target), 'ok', t('bk_uploaded'), 'upload']);
    flash(t('fl_bk_uploaded'));
    redirect('/intern/einstellungen');
  }
  if (preg_match('~^/intern/backup/(\d+)/restore$~', $path, $m) && $method === 'POST') {
    require_admin();
    // Siehe oben: Zurückspielen ersetzt Datenbank und Dateien.
    deny_in_demo('/intern/einstellungen');
    $run = row('SELECT * FROM backup_runs WHERE id = ?', [$m[1]]);
    $file = $run && $run['filename'] !== '' ? backup_dir() . '/' . basename($run['filename']) : '';
    if (!$file || !is_file($file)) {
      flash(t('fl_bk_missing'));
      redirect('/intern/einstellungen');
    }
    $res = backup_restore($file);
    flash(($res['ok'] ? '✔ ' : '⚠ ') . $res['message']
      . ($res['safety'] !== '' ? ' · ' . t('bk_safety_made') . ' ' . $res['safety'] : ''));
    redirect('/intern/einstellungen');
  }
  if (preg_match('~^/intern/backup/(\d+)/(download|delete)$~', $path, $m)) {
    require_admin();
    // Ein Archiv ist die ganze Installation in einer Datei. Aus einer Demo,
    // deren Zugangsdaten öffentlich sind, geht sie nicht hinaus.
    deny_in_demo('/intern/einstellungen');
    $run = row('SELECT * FROM backup_runs WHERE id = ?', [$m[1]]);
    $file = $run && $run['filename'] !== '' ? backup_dir() . '/' . basename($run['filename']) : '';
    if ($m[2] === 'download' && $method === 'GET' && $file && is_file($file)) {
      header('Content-Type: application/gzip');
      header('Content-Length: ' . filesize($file));
      header('Content-Disposition: attachment; filename="' . basename($file) . '"');
      readfile($file);
      exit;
    }
    if ($m[2] === 'delete' && $method === 'POST') {
      if ($file && is_file($file)) @unlink($file);
      q('DELETE FROM backup_runs WHERE id = ?', [$m[1]]);
      flash(t('fl_bk_deleted'));
    }
    redirect('/intern/einstellungen');
  }

  // Demodaten ein-/ausschalten
  if (preg_match('~^/intern/einstellungen/demo/(add|remove)$~', $path, $m) && $method === 'POST') {
    require_admin();
    require_once BASE_DIR . '/app/demo.php';
    if ($m[1] === 'add') {
      // Dass der Knopf verschwindet, ist die Anzeige. Geprüft wird hier.
      if (demo_in_real_use()) {
        flash(t('fl_demo_in_use'));
        redirect('/intern/einstellungen');
      }
      demo_install();
      flash(t('fl_demo_added'));
    } else {
      demo_remove();
      flash(t('fl_demo_removed'));
    }
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/einstellungen/branding' && $method === 'POST') {
    require_admin();
    foreach (['logo' => 'logo_file', 'background' => 'background_file', 'favicon' => 'favicon_file'] as $field => $key) {
      if (upload_rejected((int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE))) continue;
      $tmp = $_FILES[$field]['tmp_name'] ?? '';
      if (!is_uploaded_file($tmp)) continue;
      if (($_FILES[$field]['size'] ?? 0) > 5 * 1024 * 1024) { flash(t('fl_img_too_big')); continue; }
      if (!str_starts_with(mime_content_type($tmp) ?: '', 'image/')) continue;
      $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION) ?: 'png');
      $name = $field . '_' . time() . '.' . preg_replace('~[^a-z0-9]~', '', $ext);
      if (move_uploaded_file($tmp, UPLOADS_DIR . '/' . $name)) {
        // Logo, Hintergrund und Favicon stehen auf der öffentlichen Seite.
        photo_strip_exif(UPLOADS_DIR . '/' . $name);
        $old = setting($key);
        if ($old) @unlink(UPLOADS_DIR . '/' . $old);
        set_setting($key, $name);
      }
    }
    flash(t('fl_branding_saved'));
    redirect('/intern/einstellungen');
  }
  if (preg_match('~^/intern/einstellungen/branding/(logo|background|favicon)/delete$~', $path, $m) && $method === 'POST') {
    require_admin();
    $key = $m[1] . '_file';
    $old = setting($key);
    if ($old) @unlink(UPLOADS_DIR . '/' . $old);
    set_setting($key, '');
    redirect('/intern/einstellungen');
  }
}

// ---------- Formularwerte ----------
/** Bestandteile erben Besitzer und Standort vom übergeordneten Gerät. */
function equipment_inherit(?int $parentId, $ownerId, string $location): array {
  if (!$parentId) return [$ownerId ?: null, $location];
  $parent = row('SELECT owner_id, location FROM equipment WHERE id = ?', [$parentId]);
  return [$parent['owner_id'] ?? null, ''];
}

/** Bewertungen je Song: Schnitt, Anzahl und die eigene Stimme. */
function song_ratings(int $userId): array {
  $out = [];
  foreach (rows('SELECT song_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS votes,
                        MAX(CASE WHEN user_id = ? THEN rating END) AS mine
                 FROM song_ratings GROUP BY song_id', [$userId]) as $r) {
    $out[(int) $r['song_id']] = $r;
  }
  return $out;
}
/**
 * Packliste eines Termins speichern. Steht weder PA noch Licht auf „eigenes
 * Material", bleibt sie leer — auch dann, wenn im ausgeblendeten Formularteil
 * noch Haken gesetzt sind. Ausgeblendete Felder werden nämlich trotzdem
 * mitgeschickt, und eine Packliste ohne eigenes Material wäre nur verwirrend.
 */
function save_event_gear(int $eventId): void {
  q('DELETE FROM event_equipment WHERE event_id = ?', [$eventId]);
  // Nur Terminarten, die eine Packliste haben, bekommen auch eine. Ein
  // ausgeblendetes Feld wird vom Browser trotzdem mitgeschickt — ohne diese
  // Prüfung hinge an einem freien Tag plötzlich die PA.
  $type = array_key_exists($_POST['type'] ?? '', EVENT_TYPES) ? $_POST['type'] : 'sonstiges';
  if (!in_array('gear', EVENT_TYPE_FIELDS[$type] ?? [], true)) return;
  foreach ((array) ($_POST['equipment'] ?? []) as $eqId) {
    if ((int) $eqId > 0) {
      // Nur, was es auch wirklich gibt — sonst zeigt die Packliste ins Leere
      q('INSERT IGNORE INTO event_equipment (event_id, equipment_id)
         SELECT ?, id FROM equipment WHERE id = ?', [$eventId, (int) $eqId]);
    }
  }
}

/** Packlisten mehrerer Termine: je Termin-ID die Geräte mit Name und Bestandteil-Kennung. */
function event_gear_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT ee.event_id, e.id, e.name, e.parent_id
                 FROM event_equipment ee JOIN equipment e ON e.id = ee.equipment_id
                 WHERE ee.event_id IN ($in) ORDER BY e.category, e.name", $eventIds) as $r) {
    $out[(int) $r['event_id']][] = $r;
  }
  return $out;
}

/**
 * Geräte, die an einem Tag bei mehreren Terminen eingeplant sind. Zwei Gigs am
 * selben Samstag teilen sich keine PA — darauf weist die Terminliste hin.
 */
function event_gear_conflicts(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT ee.event_id, e.name FROM event_equipment ee
                 JOIN equipment e ON e.id = ee.equipment_id
                 JOIN events ev ON ev.id = ee.event_id
                 WHERE ee.event_id IN ($in) AND EXISTS (
                   SELECT 1 FROM event_equipment o JOIN events oe ON oe.id = o.event_id
                   WHERE o.equipment_id = ee.equipment_id AND o.event_id <> ee.event_id
                     AND oe.date = ev.date AND oe.status <> 'abgesagt'
                 ) AND ev.status <> 'abgesagt'
                 ORDER BY e.name", $eventIds) as $r) {
    $out[(int) $r['event_id']][] = $r['name'];
  }
  return $out;
}

function event_values(): array {
  $status = array_key_exists($_POST['status'] ?? '', EVENT_STATUS) ? $_POST['status'] : 'bestaetigt';
  $type = array_key_exists($_POST['type'] ?? '', EVENT_TYPES) ? $_POST['type'] : 'sonstiges';
  return [
    $type,
    $_POST['title'] ?? '', $_POST['date'] ?? '', $_POST['time'] ?? '',
    $_POST['location'] ?? '', $_POST['notes'] ?? '',
    isset($_POST['is_public']) ? 1 : 0,
    ($_POST['setlist_id'] ?? '') !== '' ? $_POST['setlist_id'] : null,
    $_POST['time_meet'] ?? '', $_POST['time_end'] ?? '', $status,
    ($_POST['responsible_id'] ?? '') !== '' ? $_POST['responsible_id'] : null,
    $_POST['fee'] ?? '', $_POST['invoice_no'] ?? '',
    $_POST['public_title'] ?? '', $_POST['public_link'] ?? '', $_POST['public_info'] ?? '',
    ($_POST['venue_id'] ?? '') !== '' ? $_POST['venue_id'] : null,
    array_key_exists($_POST['pa_source'] ?? '', PRODUCTION_SOURCES) ? $_POST['pa_source'] : '',
    array_key_exists($_POST['light_source'] ?? '', PRODUCTION_SOURCES) ? $_POST['light_source'] : '',
  ];
}
function song_values(): array {
  $parts = explode(':', $_POST['duration'] ?? '');
  $sec = count($parts) === 2 ? ((int) $parts[0]) * 60 + (int) $parts[1] : (int) ($parts[0] ?? 0) * 60;
  $status = array_key_exists($_POST['status'] ?? '', SONG_STATUS) ? $_POST['status'] : 'vorschlag';
  // Der Liedtext kommt so an, wie er eingetippt wurde — Zeilenumbrüche sind
  // in einem Liedtext die halbe Information.
  return [$_POST['title'] ?? '', $_POST['artist'] ?? '', $_POST['composer'] ?? '', $_POST['gema_werknr'] ?? '',
          $_POST['song_key'] ?? '', $_POST['tempo'] ?? '', $sec, $status, $_POST['notes'] ?? '',
          $_POST['lyrics'] ?? ''];
}
// Vergangene Termine und dabei gespielte Setlists sind fixiert (Historie)
function event_locked(int $eventId): bool {
  return (bool) row('SELECT 1 FROM events WHERE id = ? AND date < ?', [$eventId, date('Y-m-d')]);
}
function setlist_locked(int $setlistId): bool {
  return (bool) row('SELECT 1 FROM events WHERE setlist_id = ? AND date < ? LIMIT 1', [$setlistId, date('Y-m-d')]);
}
function venue_values(): array {
  // Koordinaten kommen aus der Adress-Suche (versteckte Felder). Nur echte
  // Zahlen übernehmen, sonst leer — nichts Getipptes landet ungeprüft in der DB.
  $lat = is_numeric($_POST['lat'] ?? '') ? (string) $_POST['lat'] : null;
  $lng = is_numeric($_POST['lng'] ?? '') ? (string) $_POST['lng'] : null;
  return [
    $_POST['name'] ?? '', $_POST['city'] ?? '', $_POST['address'] ?? '', $_POST['notes'] ?? '',
    $_POST['contact_name'] ?? '', $_POST['contact_email'] ?? '', $_POST['contact_phone'] ?? '',
    $lat, $lng,
  ];
}
function setlist_entries(int $setlistId): array {
  return rows('SELECT ss.id AS item_id, ss.position, ss.is_break, so.*
               FROM setlist_songs ss LEFT JOIN songs so ON so.id = ss.song_id
               WHERE ss.setlist_id = ? ORDER BY ss.position', [$setlistId]);
}
// Ein Song für die Noten-Bühne: alle Musiker-Notizzettel als Auswahl. $song
// braucht id, title, tempo. Der eigene steht vorn (song_chords_all sortiert so).
function noten_stage_entry(array $song, int $meId): array {
  $musicians = [];
  foreach (song_chords_all((int) $song['id'], $meId) as $c) {
    $musicians[] = ['name' => $c['name'], 'me' => (bool) $c['mine'], 'lines' => lyrics_lines($c['content'])];
  }
  return ['id' => (int) $song['id'], 'title' => $song['title'], 'bpm' => song_bpm($song['tempo']), 'musicians' => $musicians];
}
function file_serve(array $f, bool $alsDownload = false): never {
  $abs = FILES_DIR . '/' . $f['filename'];
  $ext = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
  $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
           'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'mp3' => 'audio/mpeg',
           'wav' => 'audio/wav', 'zip' => 'application/zip'][$ext] ?? 'application/octet-stream';
  // Speichern statt anzeigen, wenn die Ansicht darum bittet: Ein Betrachter, der
  // die Datei nicht darstellen kann, soll sie wenigstens herausgeben können.
  $disposition = !$alsDownload
    && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'mp3', 'wav'], true) ? 'inline' : 'attachment';
  header("Content-Type: $mime");
  header("Content-Disposition: $disposition; filename=\"" . rawurlencode($f['original_name']) . '"');
  // Verschlüsselt abgelegte Anhänge werden hier geöffnet — nach der
  // Rechteprüfung, die den Weg hierher freigegeben hat. Auf der Platte bleibt
  // die Datei versiegelt; entschlüsselt existiert sie nur für diese Antwort.
  if (crypt_is_sealed($abs)) {
    // Bei HEAD gar nicht entschlüsseln: Die Antwort besteht aus Kopfzeilen, und
    // die Länge des Klartexts ist nicht die der versiegelten Datei. Eine falsche
    // Content-Length wäre schlechter als keine.
    if (head_only()) exit;
    $plain = tempnam(sys_get_temp_dir(), 'brf');
    // Bricht der Browser während der Auslieferung ab, beendet PHP das Skript —
    // das @unlink unten liefe dann nicht, und die entschlüsselte Fassung genau
    // der Dateien, die auf der Platte versiegelt bleiben sollen, sammelte sich
    // im Temp-Verzeichnis. Der Shutdown-Handler räumt in jedem Fall auf.
    register_shutdown_function(static function () use ($plain): void { @unlink($plain); });
    if (!crypt_open_file($abs, $plain)) {
      @unlink($plain);
      http_response_code(500);
      exit(t('fl_file_sealed'));
    }
    header('Content-Length: ' . filesize($plain));
    readfile($plain);
    @unlink($plain);
    exit;
  }
  header('Content-Length: ' . filesize($abs));
  if (head_only()) exit;
  readfile($abs);
  exit;
}
function files_map(string $type, array $ids): array {
  if (!$ids) return [];
  $in = implode(',', array_map('intval', $ids));
  $map = [];
  foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                 WHERE f.entity_type = ? AND f.entity_id IN ($in) ORDER BY f.created_at", [$type]) as $f) {
    $map[$f['entity_id']][] = $f;
  }
  return $map;
}
function attendance_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_map('intval', $eventIds));
  $map = [];
  foreach (rows("SELECT a.event_id, a.status, a.user_id, u.name FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.event_id IN ($in)") as $r) {
    $map[$r['event_id']][] = $r;
  }
  return $map;
}
function my_attendance(array $eventIds, int $userId): array {
  if (!$eventIds) return [];
  $in = implode(',', array_map('intval', $eventIds));
  $map = [];
  foreach (rows("SELECT event_id, status FROM attendance WHERE user_id = ? AND event_id IN ($in)", [$userId]) as $r) {
    $map[$r['event_id']] = $r['status'];
  }
  return $map;
}

// ---------- 404 ----------
http_response_code(404);
view('404', ['title' => 'Nicht gefunden']);
