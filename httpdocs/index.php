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

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
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
    . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: same-origin');
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
if ($method === 'POST' && !csrf_valid()) {
  flash(t('fl_csrf'));
  back('/');
}

// ============================================================
// Öffentliche Seiten
// ============================================================

// ---------- App: Manifest und Symbole ----------
// Das Manifest macht die Seite installierbar. Es trägt den Bandnamen, damit
// auf dem Startbildschirm nicht „Bandroadie" steht, sondern die Band.
if ($path === '/manifest.webmanifest' && $method === 'GET') {
  header('Content-Type: application/manifest+json; charset=utf-8');
  header('Cache-Control: public, max-age=3600');
  $band = setting('band_name') ?: 'Bandroadie';
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
  $keepPrefixes = ['/intern', '/login', '/logout', '/passwort-vergessen', '/passwort-reset/', '/kalender/', '/uploads/', '/impressum', '/datenschutz', '/assets/', '/downloads', '/download/'];
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
    'photos' => rows('SELECT * FROM photos WHERE is_public=1 ORDER BY created_at DESC LIMIT 6'),
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
  view('public/fotos', ['title' => t('nav_fotos'), 'photos' => rows('SELECT * FROM photos WHERE is_public=1 ORDER BY created_at DESC')]);
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
    $uid = "event-{$ev['id']}@" . ($_SERVER['HTTP_HOST'] ?? 'bandroadie.local');
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

if ($path === '/logout' && $method === 'POST') {
  session_destroy();
  redirect('/');
}

// Passwort vergessen: Link per E-Mail anfordern (ohne Konto-Enumeration)
if ($path === '/passwort-vergessen') {
  if ($method === 'POST') {
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

  // Fällige Sicherung nebenbei anstoßen. Ohne Cronjob gibt es keinen anderen
  // Zeitpunkt; die Sperre in backup_run() verhindert doppelte Läufe. Wo der
  // Server es kann, ist die Seite vorher ausgeliefert und niemand wartet.
  if ($method === 'GET' && backup_due()) {
    register_shutdown_function(function () {
      if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
      backup_run('auto');
    });
  }
  // Versionsabfrage hinter der Fußzeile. Ein Admin loest damit eine frische
  // Nachfrage aus; fuer alle anderen bleibt es bei dem, was zuletzt bekannt
  // war — sie koennen ohnehin nichts aktualisieren.
  if ($path === '/intern/version' && $method === 'GET') {
    require_once BASE_DIR . '/app/update.php';
    if (($me['role'] ?? '') === 'admin') set_setting('update_checked_at', '0');
    $latest = update_latest_version();
    $available = $latest !== null && version_compare($latest, BANDROADIE_VERSION, '>');
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode([
      'installedLabel' => t('up_installed') . ' ' . BANDROADIE_VERSION,
      'latestLabel'    => t('up_latest') . ' ' . ($latest ?? t('up_unknown')),
      'verdict'        => $available ? sprintf(t('up_available'), $latest) : t('up_current'),
      'available'      => $available,
    ], JSON_UNESCAPED_UNICODE));
  }
  if ($path === '/intern/passwort') {
    if ($method === 'POST') {
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
      save_event_gear((int) $db->lastInsertId());
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
      redirect('/intern/termine');
    }
    if ($action === 'zusage') {
      $status = in_array($_POST['status'] ?? '', ['yes', 'no', 'maybe'], true) ? $_POST['status'] : 'maybe';
      q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)', [$id, $me['id'], $status]);
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
      if ($text !== '') q('INSERT INTO comments (event_id, user_id, text) VALUES (?,?,?)', [$id, $me['id'], $text]);
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
  if (preg_match('~^/intern/songs/(\d+)/edit$~', $path, $m) && $method === 'GET') {
    $edit = row('SELECT * FROM songs WHERE id = ?', [$m[1]]);
    if (!$edit) redirect('/intern/songs');
    view('intern/songs', [
      'title' => t('inav_songs'),
      'ratings' => song_ratings($me['id']),
      'songs' => $songList(),
      'edit' => $edit,
      'songFiles' => files_map('song', [(int) $m[1]])[(int) $m[1]] ?? [],
    ]);
  }
  if ($path === '/intern/songs' && $method === 'POST') {
    if (($_POST['title'] ?? '') !== '') {
      q('INSERT INTO songs (title, artist, composer, gema_werknr, song_key, tempo, duration_sec, status, notes) VALUES (?,?,?,?,?,?,?,?,?)', song_values());
    }
    redirect('/intern/songs');
  }
  if (preg_match('~^/intern/songs/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'update') {
      q('UPDATE songs SET title=?, artist=?, composer=?, gema_werknr=?, song_key=?, tempo=?, duration_sec=?, status=?, notes=? WHERE id=?', [...song_values(), $m[1]]);
    } else {
      // Songs in bereits gespielten Setlists sind Teil der Historie und bleiben erhalten
      $played = row('SELECT 1 FROM setlist_songs ss JOIN events e ON e.setlist_id = ss.setlist_id
                     WHERE ss.song_id = ? AND e.date < ? LIMIT 1', [$m[1], $today]);
      if ($played) {
        flash(t('fl_song_played'));
      } else {
        q('DELETE FROM songs WHERE id = ?', [$m[1]]);
        q('DELETE FROM setlist_songs WHERE song_id = ?', [$m[1]]);
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
    view('intern/fotos', ['title' => t('inav_fotos'), 'photos' => rows(
      'SELECT p.*, u.name AS uploader FROM photos p LEFT JOIN users u ON u.id = p.uploaded_by ORDER BY p.created_at DESC')]);
  }
  if ($path === '/intern/fotos' && $method === 'POST') {
    foreach ($_FILES['photos']['tmp_name'] ?? [] as $i => $tmp) {
      if (upload_rejected((int) ($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_OK))) continue;
      if (!is_uploaded_file($tmp)) continue;
      if (($_FILES['photos']['size'][$i] ?? 0) > 10 * 1024 * 1024) continue;
      $mime = mime_content_type($tmp) ?: '';
      if (!str_starts_with($mime, 'image/')) continue;
      // Der Name sagt nichts: die Zugriffsprüfung ist der Schutz, aber ein
      // sprechender Name wäre eine zweite Tür, falls sie je umgangen wird.
      // Wie die Datei ursprünglich hieß, steht in der Bildunterschrift.
      $photoExt = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION) ?: 'jpg'));
      $safe = 'foto_' . bin2hex(random_bytes(16)) . '.' . $photoExt;
      if (move_uploaded_file($tmp, UPLOADS_DIR . '/' . $safe)) {
        q('INSERT INTO photos (filename, caption, is_public, uploaded_by) VALUES (?,?,?,?)',
          [$safe, $_POST['caption'] ?? '', isset($_POST['is_public']) ? 1 : 0, $me['id']]);
      }
    }
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
      $p = row('SELECT * FROM photos WHERE id = ?', [$m[1]]);
      if ($p) {
        q('DELETE FROM photos WHERE id = ?', [$p['id']]);
        @unlink(UPLOADS_DIR . '/' . $p['filename']);
      }
    }
    redirect('/intern/fotos');
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
      q('INSERT INTO venues (name, city, address, notes, contact_name, contact_email, contact_phone) VALUES (?,?,?,?,?,?,?)', venue_values());
    }
    redirect('/intern/orte');
  }
  if (preg_match('~^/intern/orte/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'update') {
      q('UPDATE venues SET name=?, city=?, address=?, notes=?, contact_name=?, contact_email=?, contact_phone=? WHERE id=?', [...venue_values(), $m[1]]);
    } else {
      q('DELETE FROM venues WHERE id = ?', [$m[1]]);
      q('UPDATE events SET venue_id = NULL WHERE venue_id = ?', [$m[1]]);
    }
    redirect('/intern/orte');
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
  if (preg_match('~^/intern/datei/(\d+)$~', $path, $m) && $method === 'GET') {
    $f = row('SELECT * FROM files WHERE id = ?', [$m[1]]);
    // Der Anhang erbt die Sichtbarkeit seines Gegenstands; unbekannt und
    // gesperrt antworten gleich, damit die Antwort nichts verrät.
    if (!$f || !may_see_file($me, $f) || !is_file(FILES_DIR . '/' . $f['filename'])) {
      http_response_code(404);
      exit('Datei nicht gefunden');
    }
    file_serve($f);
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
    view('intern/profil', ['title' => t('mem_my_profile'), 'profile' => row('SELECT * FROM users WHERE id = ?', [$me['id']])]);
  }
  if ($path === '/intern/profil' && $method === 'POST') {
    if (display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $me['name']) !== '' && ($_POST['email'] ?? '') !== '') {
      try {
        $prefLang = array_key_exists($_POST['pref_lang'] ?? '', LANGS) ? $_POST['pref_lang'] : 'de';
        q('UPDATE users SET name=?, stage_name=?, instrument=?, email=?, pref_lang=?,
                            first_name=?, last_name=?, phone=?, mobile=? WHERE id=?', [
          display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $me['name']),
          $_POST['stage_name'] ?? '', $_POST['instrument'] ?? '',
          strtolower(trim($_POST['email'])), $prefLang,
          $_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $_POST['phone'] ?? '', $_POST['mobile'] ?? '',
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
    if ($m[1] === 'vorlage') {
      q('DELETE FROM stage_items');
      $pos = 0;
      foreach (stage_default_items(rows('SELECT name, stage_name, instrument FROM users ORDER BY name')) as $it) {
        q('INSERT INTO stage_items (kind, label, x, y, note, position) VALUES (?,?,?,?,?,?)',
          [$it['kind'], $it['label'], $it['x'], $it['y'], $it['note'], $pos++]);
      }
    } elseif ($m[1] === 'add') {
      q('INSERT INTO stage_items (kind, label, x, y, note, position) VALUES (?,?,?,?,?,?)', [
        array_key_exists($_POST['kind'] ?? '', STAGE_KINDS) ? $_POST['kind'] : 'sonstiges',
        trim($_POST['label'] ?? ''),
        max(0, min(100, (int) ($_POST['x'] ?? 50))), max(0, min(100, (int) ($_POST['y'] ?? 50))),
        trim($_POST['note'] ?? ''),
        (int) (row('SELECT COALESCE(MAX(position), 0) + 1 AS p FROM stage_items')['p'] ?? 1),
      ]);
    } elseif ($m[1] === 'update' && ($_POST['remove'] ?? '') !== '') {
      // Der Löschknopf steckt im selben Formular; ein eigenes wäre verschachtelt
      q('DELETE FROM stage_items WHERE id = ?', [(int) $_POST['remove']]);
      flash(t('fl_stage_deleted'));
      redirect('/intern/stagerider');
    } elseif ($m[1] === 'update') {
      // Alle Einträge auf einmal — beim Ziehen im Plan ändern sich mehrere
      foreach ((array) ($_POST['item'] ?? []) as $id => $vals) {
        q('UPDATE stage_items SET kind = ?, label = ?, x = ?, y = ?, note = ? WHERE id = ?', [
          array_key_exists($vals['kind'] ?? '', STAGE_KINDS) ? $vals['kind'] : 'sonstiges',
          trim($vals['label'] ?? ''),
          max(0, min(100, (int) ($vals['x'] ?? 50))), max(0, min(100, (int) ($vals['y'] ?? 50))),
          trim($vals['note'] ?? ''), (int) $id,
        ]);
      }
    }
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
        q('UPDATE users SET name=?, stage_name=?, instrument=?, email=?,
                            first_name=?, last_name=?, phone=?, mobile=?, substitute_for=?,
                            substitute_rank=? WHERE id=?', [
          display_name($_POST['first_name'] ?? '', $_POST['last_name'] ?? '',
                       row('SELECT name FROM users WHERE id = ?', [$m[1]])['name'] ?? ''),
          $_POST['stage_name'] ?? '', $_POST['instrument'] ?? '',
          strtolower(trim($_POST['email'])),
          $_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $_POST['phone'] ?? '', $_POST['mobile'] ?? '',
          ((int) ($_POST['substitute_for'] ?? 0) ?: null),
          max(0, min(99, (int) ($_POST['substitute_rank'] ?? 0))),
          $m[1],
        ]);
        // Rolle: nur Admin, und nicht die eigene (sonst sperrt man sich aus)
        if ((int) $m[1] !== (int) $me['id'] && in_array($_POST['role'] ?? '', ['admin', 'member', 'ersatz'], true)) {
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
  if (preg_match('~^/intern/mitglieder/(\d+)/(delete|passwort)$~', $path, $m) && $method === 'POST') {
    [$_, $id, $action] = $m;
    if ($action === 'delete') {
      require_admin();
      if ((int) $id === (int) $me['id']) {
        flash(t('fl_no_self_delete'));
      } else {
        q('DELETE FROM users WHERE id = ?', [$id]);
      }
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

  // ---------- Über Bandroadie ----------
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
        q('INSERT INTO equipment (name, category, owner_id, location, is_standard, notes, parent_id, slot, purchased_on, price_cents) VALUES (?,?,?,?,?,?,?,?,?,?)', [
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
    // Die Stückzahl verschwindet aus Name und Steckplatz — sie steht jetzt
    // in der Zahl der Zeilen. Der Preis war der eines Geräts und bleibt es.
    $baseName = eq_strip_quantity((string) $eq['name']);
    $baseSlot = eq_strip_quantity((string) $eq['slot']);
    q('UPDATE equipment SET name = ?, slot = ? WHERE id = ?', [$baseName . ' #1', $baseSlot, $m[1]]);
    for ($i = 2; $i <= $count; $i++) {
      q('INSERT INTO equipment (name, category, owner_id, location, is_standard, notes, parent_id, slot, purchased_on, price_cents)
         VALUES (?,?,?,?,?,?,?,?,?,?)', [
        $baseName . ' #' . $i, $eq['category'], $eq['owner_id'], $eq['location'],
        $eq['is_standard'], $eq['notes'], $eq['parent_id'], $baseSlot,
        $eq['purchased_on'], $eq['price_cents'],
      ]);
    }
    flash(sprintf(t('fl_eq_split'), $count));
    redirect('/intern/equipment');
  }
  if (preg_match('~^/intern/equipment/(\d+)/(update|delete)$~', $path, $m) && $method === 'POST') {
    if ($m[2] === 'delete') {
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
      q('UPDATE equipment SET name=?, category=?, owner_id=?, location=?, is_standard=?, notes=?, parent_id=?, slot=?, purchased_on=?, price_cents=? WHERE id=?', [
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
    ]);
  }
  if ($path === '/intern/stagerider' && $method === 'POST') {
    require_admin();
    foreach (['rider_stage', 'rider_power', 'rider_pa', 'rider_monitor', 'rider_light',
              'rider_getin', 'rider_extras', 'rider_positions',
              'rider_contact_tech', 'rider_contact_booking'] as $key) {
      if (isset($_POST[$key])) set_setting($key, trim($_POST[$key]));
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
    $amount = (int) round(((float) str_replace(',', '.', str_replace('.', '', trim($_POST['amount'] ?? '')))) * 100);
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
    if ($ev && preg_match('~(\d+(?:[.,]\d{1,2})?)~', str_replace('.', '', $ev['fee']), $fm)) {
      $amount = (int) round(((float) str_replace(',', '.', $fm[1])) * 100);
      if ($amount > 0) {
        q('INSERT INTO finances (date, type, amount_cents, category, description, event_id, created_by) VALUES (?,?,?,?,?,?,?)',
          [$ev['date'], 'einnahme', $amount, 'gage', $ev['title'], $ev['id'], $me['id']]);
        flash(t('fl_fin_saved'));
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
      set_setting('public_mode', ($_POST['public_mode'] ?? '') === 'redirect' ? 'redirect' : 'website');
      if (($_POST['redirect_url'] ?? '') !== '') set_setting('redirect_url', trim($_POST['redirect_url']));
    }
    if (isset($_POST['_tax_form'])) {
      // Die Beträge kommen als Zahl mit Komma oder Punkt herein; price_to_cents
      // kennt beides, gespeichert wird wieder in Euro.
      foreach (['tax_limit_prev_year', 'tax_limit_this_year', 'tax_gwg_limit',
                'tax_commercial_share', 'tax_commercial_abs'] as $taxKey) {
        $cents = price_to_cents((string) ($_POST[$taxKey] ?? ''));
        if ($cents !== null && $cents >= 0) set_setting($taxKey, (string) round($cents / 100, 2));
      }
      // Die Nutzungsdauer ist eine Anzahl Jahre, kein Betrag. Null Jahre gibt
      // es nicht — dann bliebe ein Kauf für immer stehen.
      $afaYears = (int) ($_POST['tax_afa_years'] ?? 0);
      if ($afaYears >= 1 && $afaYears <= 50) set_setting('tax_afa_years', (string) $afaYears);
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
    $test = backup_ftp_test();
    flash(($test['ok'] ? '✔ ' : '⚠ ') . $test['message']);
    redirect('/intern/einstellungen');
  }
  if ($path === '/intern/backup/run' && $method === 'POST') {
    require_admin();
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
    $up = $_FILES['archive'] ?? null;
    $name = basename((string) ($up['name'] ?? ''));
    if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || !preg_match('~\.tar\.gz(\.enc)?$~', $name)) {
      flash(t('fl_bk_upload_invalid'));
      redirect('/intern/einstellungen');
    }
    $target = backup_dir() . '/' . preg_replace('~[^A-Za-z0-9._-]~', '_', $name);
    move_uploaded_file($up['tmp_name'], $target);
    @chmod($target, 0600);
    q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
      [basename($target), (int) filesize($target), 'ok', t('bk_uploaded'), 'upload']);
    flash(t('fl_bk_uploaded'));
    redirect('/intern/einstellungen');
  }
  if (preg_match('~^/intern/backup/(\d+)/restore$~', $path, $m) && $method === 'POST') {
    require_admin();
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
  return [$_POST['title'] ?? '', $_POST['artist'] ?? '', $_POST['composer'] ?? '', $_POST['gema_werknr'] ?? '',
          $_POST['song_key'] ?? '', $_POST['tempo'] ?? '', $sec, $status, $_POST['notes'] ?? ''];
}
// Vergangene Termine und dabei gespielte Setlists sind fixiert (Historie)
function event_locked(int $eventId): bool {
  return (bool) row('SELECT 1 FROM events WHERE id = ? AND date < ?', [$eventId, date('Y-m-d')]);
}
function setlist_locked(int $setlistId): bool {
  return (bool) row('SELECT 1 FROM events WHERE setlist_id = ? AND date < ? LIMIT 1', [$setlistId, date('Y-m-d')]);
}
function venue_values(): array {
  return [
    $_POST['name'] ?? '', $_POST['city'] ?? '', $_POST['address'] ?? '', $_POST['notes'] ?? '',
    $_POST['contact_name'] ?? '', $_POST['contact_email'] ?? '', $_POST['contact_phone'] ?? '',
  ];
}
function setlist_entries(int $setlistId): array {
  return rows('SELECT ss.id AS item_id, ss.position, ss.is_break, so.*
               FROM setlist_songs ss LEFT JOIN songs so ON so.id = ss.song_id
               WHERE ss.setlist_id = ? ORDER BY ss.position', [$setlistId]);
}
function file_serve(array $f): never {
  $abs = FILES_DIR . '/' . $f['filename'];
  $ext = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
  $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
           'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain', 'mp3' => 'audio/mpeg',
           'wav' => 'audio/wav', 'zip' => 'application/zip'][$ext] ?? 'application/octet-stream';
  $disposition = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'mp3', 'wav'], true) ? 'inline' : 'attachment';
  header("Content-Type: $mime");
  header("Content-Disposition: $disposition; filename=\"" . rawurlencode($f['original_name']) . '"');
  // Verschlüsselt abgelegte Anhänge werden hier geöffnet — nach der
  // Rechteprüfung, die den Weg hierher freigegeben hat. Auf der Platte bleibt
  // die Datei versiegelt; entschlüsselt existiert sie nur für diese Antwort.
  if (crypt_is_sealed($abs)) {
    $plain = tempnam(sys_get_temp_dir(), 'brf');
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
