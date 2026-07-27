<?php
declare(strict_types=1);

/**
 * Sicherungen: Datenbank und hochgeladene Dateien landen in einem tar.gz
 * neben der Installation, außerhalb des Webverzeichnisses.
 *
 * Das Archiv wird von Hand geschrieben. ZipArchive fehlt auf manchen Servern,
 * und PharData verbietet sich, solange phar.readonly gesetzt ist — was der
 * Standard ist. Ein tar besteht aus 512-Byte-Blöcken und ist damit die
 * Variante, die überall funktioniert, wo PHP zlib kennt.
 *
 * Direkt aufrufbar für echte Cronjobs:  php app/backup.php
 */

const BACKUP_INTERVALS = ['daily' => 86400, 'weekly' => 604800];

/**
 * Zugangsdaten des FTP-Ziels. Das Passwort steht im Klartext in der
 * Datenbank — anders kann sich ein Programm bei FTP nicht anmelden. Es
 * verlässt den Server nur in Richtung des eingetragenen Hosts und wird nie
 * ins Formular zurückgeschrieben.
 */
function backup_ftp_config(): array {
  return [
    'enabled' => setting('backup_ftp_enabled') === '1',
    'host'    => setting('backup_ftp_host'),
    'port'    => (int) (setting('backup_ftp_port') ?: 21),
    'user'    => setting('backup_ftp_user'),
    'pass'    => setting('backup_ftp_pass'),
    'dir'     => setting('backup_ftp_dir'),
    'tls'     => setting('backup_ftp_tls') === '1',
    'passive' => setting('backup_ftp_passive') === '1',
    'keep'    => max(1, (int) (setting('backup_ftp_keep') ?: 14)),
  ];
}

/**
 * Verbindung zum FTP-Ziel prüfen: anmelden, ins Zielverzeichnis wechseln,
 * auflisten, fertig. Es wird nichts geschrieben und nichts gelöscht.
 *
 * @return array{ok:bool,message:string}
 */
function backup_ftp_test(): array {
  $cfg = backup_ftp_config();
  if (!function_exists('ftp_connect')) {
    return ['ok' => false, 'message' => 'Dieser Server kann kein FTP (Erweiterung fehlt).'];
  }
  if ($cfg['host'] === '' || $cfg['user'] === '') {
    return ['ok' => false, 'message' => 'Server und Benutzer fehlen.'];
  }
  $conn = $cfg['tls'] ? @ftp_ssl_connect($cfg['host'], $cfg['port'], 10)
                      : @ftp_connect($cfg['host'], $cfg['port'], 10);
  if (!$conn) return ['ok' => false, 'message' => 'Keine Verbindung zu ' . $cfg['host'] . ':' . $cfg['port']];
  if (!@ftp_login($conn, $cfg['user'], $cfg['pass'])) {
    @ftp_close($conn);
    return ['ok' => false, 'message' => 'Anmeldung abgelehnt.'];
  }
  @ftp_pasv($conn, $cfg['passive']);
  if ($cfg['dir'] !== '' && !@ftp_chdir($conn, $cfg['dir'])) {
    @ftp_close($conn);
    return ['ok' => false, 'message' => 'Verzeichnis nicht gefunden: ' . $cfg['dir']];
  }
  $list = @ftp_nlist($conn, '.');
  @ftp_close($conn);
  return ['ok' => true, 'message' => 'Verbunden. Im Zielverzeichnis liegen ' . count($list ?: []) . ' Einträge.'];
}

function backup_dir(): string {
  $dir = DATA_DIR . '/backups';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  return $dir;
}

/** Eingestellte Anzahl aufzuhebender Sicherungen; mindestens eine. */
function backup_keep(): int {
  return max(1, (int) (setting('backup_keep') ?: 7));
}

/**
 * Läuft die Sicherung automatisch, und ist sie fällig? Nach einem Fehlschlag
 * wird nicht bei jedem Seitenaufruf neu versucht, sondern frühestens nach
 * einer Stunde — sonst dreht ein kaputter Lauf endlos im Kreis.
 */
function backup_due(): bool {
  if (setting('backup_enabled') !== '1') return false;
  $every = BACKUP_INTERVALS[setting('backup_interval') ?: 'daily'] ?? 86400;
  $ok = row("SELECT created_at FROM backup_runs WHERE status = 'ok' ORDER BY id DESC LIMIT 1");
  if ($ok && (time() - strtotime($ok['created_at'])) < $every) return false;
  $any = row('SELECT created_at FROM backup_runs ORDER BY id DESC LIMIT 1');
  return !$any || (time() - strtotime($any['created_at'])) >= 3600;
}

/** Datenbank als SQL schreiben — Struktur und Inhalt, ohne mysqldump. */
function backup_write_sql(string $path): void {
  global $db;
  $fh = fopen($path, 'wb');
  if (!$fh) throw new RuntimeException('SQL-Datei nicht schreibbar');
  fwrite($fh, "-- Bandroadie " . BANDROADIE_VERSION . ", " . date('c') . "\n");
  fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
  foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1] ?? '';
    fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
    $stmt = $db->query("SELECT * FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
      $vals = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string) $v), $row));
      fwrite($fh, "INSERT INTO `$table` ($cols) VALUES ($vals);\n");
    }
    fwrite($fh, "\n");
  }
  fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
  fclose($fh);
}

/**
 * Ein tar-Kopfsatz nach ustar. Lange Pfade werden auf Präfix und Namen
 * verteilt; was auch dann nicht passt, lässt sich nicht sichern und wird
 * gemeldet statt stillschweigend weggelassen.
 */
function backup_tar_header(string $name, int $size, int $mtime): ?string {
  $prefix = '';
  if (strlen($name) > 100) {
    $cut = strrpos(substr($name, 0, 156), '/');
    if ($cut === false || strlen($name) - $cut - 1 > 100) return null;
    $prefix = substr($name, 0, $cut);
    $name = substr($name, $cut + 1);
  }
  $header = pack('a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
    $name, sprintf('%07o', 0644), sprintf('%07o', 0), sprintf('%07o', 0),
    sprintf('%011o', $size), sprintf('%011o', $mtime), '        ', '0',
    '', 'ustar', '00', '', '', '', '', $prefix, '');
  $sum = 0;
  for ($i = 0; $i < 512; $i++) $sum += ord($header[$i]);
  return substr_replace($header, sprintf('%06o', $sum) . "\0 ", 148, 8);
}

/** Eine Datei ins Archiv schreiben; gibt false zurück, wenn der Pfad zu lang ist. */
function backup_tar_add($gz, string $nameInArchive, string $file): bool {
  $size = (int) filesize($file);
  $header = backup_tar_header($nameInArchive, $size, (int) filemtime($file));
  if ($header === null) return false;
  gzwrite($gz, $header);
  $fh = fopen($file, 'rb');
  while (!feof($fh)) gzwrite($gz, (string) fread($fh, 262144));
  fclose($fh);
  if ($size % 512) gzwrite($gz, str_repeat("\0", 512 - ($size % 512)));
  return true;
}

/** Alle Dateien eines Verzeichnisses, rekursiv, mit Pfad relativ zu $base. */
function backup_collect(string $dir, string $base): array {
  if (!is_dir($dir)) return [];
  $out = [];
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if ($file->isFile()) $out[$base . '/' . substr($file->getPathname(), strlen($dir) + 1)] = $file->getPathname();
  }
  return $out;
}

/**
 * Eine Sicherung erstellen. Gibt den Datenbank-Eintrag des Laufs zurück.
 * Ein fehlgeschlagener Lauf wird ebenso vermerkt — eine Sicherung, von der
 * niemand weiß, dass sie ausblieb, ist schlimmer als gar keine.
 */
function backup_run(string $trigger = 'auto'): array {
  $dir = backup_dir();
  if (!is_dir($dir) || !is_writable($dir)) {
    // Kein stiller Fehlschlag: ein nicht beschreibbares Verzeichnis ist der
    // häufigste Grund, warum eine Sicherung nie entsteht.
    $msg = 'Verzeichnis nicht beschreibbar: ' . $dir;
    q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
      ['', 0, 'error', $msg, $trigger]);
    return ['status' => 'error', 'message' => $msg];
  }
  $lock = fopen($dir . '/.lock', 'c');
  if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    return ['status' => 'skipped', 'message' => 'Ein Lauf ist bereits unterwegs'];
  }
  @set_time_limit(0);
  $name = 'bandroadie-' . date('Y-m-d-His') . '.tar.gz';
  $target = $dir . '/' . $name;
  $sqlFile = $dir . '/.dump.sql';
  $skipped = [];
  try {
    backup_write_sql($sqlFile);
    $gz = gzopen($target . '.part', 'wb6');
    if (!$gz) throw new RuntimeException('Archiv nicht schreibbar');
    backup_tar_add($gz, 'database.sql', $sqlFile);
    foreach ([UPLOADS_DIR => 'uploads', FILES_DIR => 'files'] as $src => $base) {
      foreach (backup_collect($src, $base) as $inArchive => $path) {
        if (!backup_tar_add($gz, $inArchive, $path)) $skipped[] = $inArchive;
      }
    }
    gzwrite($gz, str_repeat("\0", 1024)); // tar endet mit zwei leeren Blöcken
    gzclose($gz);
    rename($target . '.part', $target);
    @unlink($sqlFile);
    @chmod($target, 0600);
    $notes = [];
    if ($skipped) $notes[] = count($skipped) . ' Datei(en) mit zu langem Pfad ausgelassen';
    // Das Zweitziel wird getrennt vermerkt. Scheitert es, ist die Sicherung
    // hier trotzdem gültig und zählt für die Aufbewahrung — sichtbar bleibt
    // der Fehlschlag über ftp_ok, sonst würde stündlich neu versucht.
    $ftpEnabled = backup_ftp_config()['enabled'];
    $ftp = backup_ftp_upload($target);
    if ($ftp['message'] !== '') $notes[] = $ftp['message'];
    q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind, ftp_ok) VALUES (?,?,?,?,?,?)',
      [$name, (int) filesize($target), 'ok', implode(' · ', $notes), $trigger,
       $ftpEnabled ? ($ftp['ok'] ? 1 : 0) : null]);
    backup_prune();
  } catch (Throwable $e) {
    @unlink($target . '.part');
    @unlink($sqlFile);
    q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
      ['', 0, 'error', mb_substr($e->getMessage(), 0, 400), $trigger]);
  }
  flock($lock, LOCK_UN);
  fclose($lock);
  return row('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 1') ?? [];
}

/**
 * Ein fertiges Archiv auf den FTP-Server legen und dort aufräumen. Übertragen
 * wird erst, wenn die Datei vollständig ist — niemals aus einem laufenden
 * Dump heraus. Geht etwas schief, bleibt die Sicherung auf dem eigenen Server
 * trotzdem gültig; der Fehlschlag wird nur vermerkt.
 *
 * @return array{ok:bool,message:string}
 */
function backup_ftp_upload(string $file): array {
  $cfg = backup_ftp_config();
  if (!$cfg['enabled']) return ['ok' => true, 'message' => ''];
  if (!function_exists('ftp_connect')) return ['ok' => false, 'message' => 'FTP fehlt auf diesem Server'];

  $conn = $cfg['tls'] ? @ftp_ssl_connect($cfg['host'], $cfg['port'], 20)
                      : @ftp_connect($cfg['host'], $cfg['port'], 20);
  if (!$conn) return ['ok' => false, 'message' => 'FTP: keine Verbindung'];
  if (!@ftp_login($conn, $cfg['user'], $cfg['pass'])) {
    @ftp_close($conn);
    return ['ok' => false, 'message' => 'FTP: Anmeldung abgelehnt'];
  }
  @ftp_pasv($conn, $cfg['passive']);
  if ($cfg['dir'] !== '' && !@ftp_chdir($conn, $cfg['dir'])) {
    @ftp_close($conn);
    return ['ok' => false, 'message' => 'FTP: Verzeichnis nicht gefunden'];
  }
  $name = basename($file);
  // Erst unter einem Zwischennamen hochladen und dann umbenennen: bricht die
  // Übertragung ab, liegt dort kein halbes Archiv mit gültigem Namen.
  if (!@ftp_put($conn, $name . '.part', $file, FTP_BINARY) || !@ftp_rename($conn, $name . '.part', $name)) {
    @ftp_delete($conn, $name . '.part');
    @ftp_close($conn);
    return ['ok' => false, 'message' => 'FTP: Übertragung fehlgeschlagen'];
  }

  // Aufräumen nach der eigenen Zahl dieses Ziels, neueste zuerst behalten
  $remote = @ftp_nlist($conn, '.') ?: [];
  $mine = [];
  foreach ($remote as $entry) {
    $base = basename($entry);
    if (preg_match('~^bandroadie-\d{4}-\d{2}-\d{2}-\d{6}\.tar\.gz$~', $base)) $mine[] = $base;
  }
  rsort($mine);
  $dropped = 0;
  foreach (array_slice($mine, $cfg['keep']) as $old) {
    if (@ftp_delete($conn, $old)) $dropped++;
  }
  @ftp_close($conn);
  return ['ok' => true, 'message' => 'FTP: übertragen' . ($dropped ? ", $dropped alte entfernt" : '')];
}

/**
 * Alte Sicherungen entfernen — erst nachdem eine neue vollständig vorliegt,
 * und nur erfolgreiche Läufe zählen mit. Sonst schiebt eine kaputte Nacht
 * die brauchbaren Sicherungen aus dem Fenster.
 */
function backup_prune(): void {
  $keep = backup_keep();
  $old = rows("SELECT * FROM backup_runs WHERE status = 'ok' AND filename <> '' ORDER BY id DESC");
  foreach (array_slice($old, $keep) as $run) {
    @unlink(backup_dir() . '/' . $run['filename']);
    q("UPDATE backup_runs SET filename = '', message = ? WHERE id = ?", ['abgelaufen und gelöscht', $run['id']]);
  }
}

// Direkter Aufruf aus einem Cronjob
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
  require __DIR__ . '/bootstrap.php';
  $run = backup_run('cron');
  echo ($run['status'] ?? '?') . ' ' . ($run['filename'] ?? '') . ' ' . ($run['message'] ?? '') . PHP_EOL;
  exit(($run['status'] ?? '') === 'ok' ? 0 : 1);
}
