<?php
declare(strict_types=1);

/**
 * Prüft, was diese Installation kann und was ihr fehlt.
 *
 * Der Sinn ist nicht eine Liste roter Kreuze, sondern eine Antwort auf die
 * Frage „was geht deswegen nicht". Eine fehlende Bilderweiterung klingt
 * harmlos; dass die Galerie dann Originalbilder in Briefmarken lädt, ist die
 * Auskunft, die jemand beim Einrichten wirklich braucht.
 */

/** Ein Prüfpunkt: 'ok' | 'warn' | 'fail', mit Folge und Rat im Klartext. */
function check_row(string $name, string $state, string $detail, string $consequence = ''): array {
  return ['name' => $name, 'state' => $state, 'detail' => $detail, 'consequence' => $consequence];
}

/** @return array<string, array<int, array>> Gruppenname => Prüfpunkte */
function system_checks(): array {
  $groups = [];

  // --- Grundlagen, ohne die nichts läuft
  $php = PHP_VERSION;
  $groups[t('sys_required')][] = check_row(
    'PHP ' . $php,
    version_compare($php, '8.1', '>=') ? 'ok' : 'fail',
    version_compare($php, '8.1', '>=') ? t('sys_ok') : t('sys_php_old'),
    version_compare($php, '8.1', '>=') ? '' : t('sys_php_old_hint')
  );
  foreach (['pdo_mysql' => t('sys_ext_db'), 'mbstring' => t('sys_ext_text'),
            'fileinfo' => t('sys_ext_files'), 'zlib' => t('sys_ext_zlib')] as $ext => $why) {
    $has = extension_loaded($ext);
    $groups[t('sys_required')][] = check_row($ext, $has ? 'ok' : 'fail', $has ? t('sys_ok') : t('sys_missing'), $why);
  }

  foreach ([DATA_DIR => 'data', UPLOADS_DIR => 'data/uploads', FILES_DIR => 'data/files'] as $dir => $label) {
    $writable = is_dir($dir) && is_writable($dir);
    $groups[t('sys_required')][] = check_row(
      $label, $writable ? 'ok' : 'fail',
      $writable ? t('sys_writable') : t('sys_not_writable'),
      $writable ? '' : t('sys_not_writable_hint')
    );
  }

  // --- Nützlich, aber verzichtbar: jeweils mit dem Preis des Verzichts
  foreach ([
    'gd'    => [t('sys_opt_gd'), function_exists('imagecreatetruecolor')],
    'curl'  => [t('sys_opt_curl'), function_exists('curl_init')],
    'ftp'   => [t('sys_opt_ftp'), function_exists('ftp_connect')],
    'zip'   => [t('sys_opt_zip'), class_exists('ZipArchive')],
    'openssl' => [t('sys_opt_openssl'), extension_loaded('openssl')],
    // Beide Funktionen verschwinden lautlos, wenn ihre Voraussetzung fehlt —
    // deshalb gehören sie hierher, mit Klartext, was dann nicht geht.
    'exif'  => [t('sys_opt_exif'), function_exists('exif_read_data')],
    'push'  => [t('sys_opt_push'), push_supported()],
  ] as $ext => [$why, $has]) {
    $groups[t('sys_optional')][] = check_row($ext, $has ? 'ok' : 'warn', $has ? t('sys_ok') : t('sys_missing'), $why);
  }

  // --- Betrieb: Zustand dieser Installation, nicht der Software
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  $groups[t('sys_operation')][] = check_row(
    t('sys_https'), $https ? 'ok' : 'fail',
    $https ? t('sys_ok') : t('sys_no_https'), $https ? '' : t('sys_no_https_hint')
  );

  $site = setting('site_url');
  $groups[t('sys_operation')][] = check_row(
    t('set_site_url'), $site !== '' ? 'ok' : 'warn',
    $site !== '' ? $site : t('sys_site_url_empty'), $site !== '' ? '' : t('sys_site_url_hint')
  );

  // Steuerliche Grenzen altern still: der Gesetzgeber ändert sie, die
  // Installation merkt davon nichts. Nur hier fällt es auf.
  require_once BASE_DIR . '/app/steuer.php';
  require_once BASE_DIR . '/app/update.php';   // für update_is_plesk()
  if (setting('tax_small_business', '0') === '1') {
    $taxStale = tax_values_stale();
    $groups[t('sys_operation')][] = check_row(
      t('sys_tax_stale'), $taxStale ? 'warn' : 'ok',
      $taxStale ? t('sys_tax_stale_detail') : fmt_date(setting('tax_values_checked')),
      $taxStale ? t('sys_tax_stale_conseq') : ''
    );
  }

  $backup = row("SELECT created_at, status FROM backup_runs WHERE status = 'ok' ORDER BY id DESC LIMIT 1");
  $age = $backup ? (time() - strtotime($backup['created_at'])) : null;
  $groups[t('sys_operation')][] = check_row(
    t('bk_title'),
    $age === null ? 'warn' : ($age < 8 * 86400 ? 'ok' : 'warn'),
    $backup ? fmt_date(substr($backup['created_at'], 0, 10)) : t('sys_no_backup'),
    $age === null || $age >= 8 * 86400 ? t('sys_no_backup_hint') : ''
  );

  // Verschlüsselung ruhender Daten — und nicht nur, ob sie eingeschaltet ist,
  // sondern ob sie trägt. Art. 32 Abs. 1 Buchst. d DSGVO verlangt die
  // Überprüfung der Wirksamkeit, nicht die Absicht.
  $cryptOn = crypt_available();
  $cryptTest = $cryptOn ? crypt_selftest() : ['ok' => false, 'message' => ''];
  $groups[t('sys_operation')][] = check_row(
    t('set_crypt'),
    $cryptOn ? ($cryptTest['ok'] ? 'ok' : 'fail') : 'warn',
    $cryptOn ? $cryptTest['message'] : t('sys_crypt_off'),
    $cryptOn ? ($cryptTest['ok'] ? '' : t('sys_crypt_broken')) : t('sys_crypt_off_hint')
  );
  if ($cryptOn) {
    $plainFiles = count(array_filter(glob(FILES_DIR . '/*') ?: [],
      fn($p) => is_file($p) && !crypt_is_sealed($p)));
    $groups[t('sys_operation')][] = check_row(
      t('sys_crypt_files'),
      $plainFiles === 0 ? 'ok' : 'warn',
      $plainFiles === 0 ? t('sys_ok') : sprintf(t('set_crypt_plain_files'), $plainFiles),
      $plainFiles === 0 ? '' : t('sys_crypt_files_hint')
    );
  }

  // Plesk legt die Besucherstatistik unter /plesk-stat ab. Steht sie offen,
  // liest jeder die IP-Adressen der Besucher mit — gemeldet wurde genau das
  // an einer laufenden Installation.
  if (update_is_plesk()) {
    $statCode = system_probe_status('/plesk-stat/');
    $statOffen = $statCode === 200;
    $groups[t('sys_operation')][] = check_row(
      t('sys_webstat'),
      $statCode === null ? 'warn' : ($statOffen ? 'fail' : 'ok'),
      $statCode === null ? t('sys_webstat_unknown') : ($statOffen ? t('sys_webstat_open') : t('sys_webstat_closed')),
      $statOffen ? t('sys_webstat_hint') : ''
    );
  }

  $cache = system_cache_header();
  $groups[t('sys_operation')][] = check_row(
    t('sys_cache'),
    $cache === null ? 'warn' : ($cache !== '' ? 'ok' : 'warn'),
    $cache === null ? t('sys_cache_unknown') : ($cache !== '' ? $cache : t('sys_cache_none')),
    $cache === '' ? t('sys_cache_hint') : ''
  );

  return $groups;
}

/**
 * Womit antwortet die eigene Seite auf einen Pfad? Für Stellen, die der
 * Webserver ausliefert und von denen die Anwendung sonst nichts wüsste.
 *
 * @return int|null HTTP-Status oder null, wenn sich nichts abfragen ließ
 */
function system_probe_status(string $path): ?int {
  if (!function_exists('curl_init')) return null;
  $ch = curl_init(absolute_url($path));
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
  ]);
  $ok = curl_exec($ch) !== false;
  $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  return $ok && $code > 0 ? $code : null;
}

/**
 * Holt die eigene Stylesheet-Adresse und schaut, ob eine Zwischenspeicher-
 * Vorgabe zurückkommt. Ohne curl lässt sich das nicht feststellen — dann
 * wird das ehrlich gesagt, statt etwas zu behaupten.
 */
function system_cache_header(): ?string {
  if (!function_exists('curl_init')) return null;
  $ch = curl_init(absolute_url('/assets/style.css'));
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => true, CURLOPT_HEADER => true, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
  ]);
  $head = curl_exec($ch);
  $failed = $head === false || curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200;
  curl_close($ch);
  if ($failed) return null;
  return preg_match('~^cache-control:\s*(.+)$~im', (string) $head, $m) ? trim($m[1]) : '';
}
