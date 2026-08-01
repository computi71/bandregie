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

require_once __DIR__ . '/tresor.php';

const BACKUP_INTERVALS = ['daily' => 86400, 'weekly' => 604800];

/**
 * Zugangsdaten des FTP-Ziels. Das Passwort muss der Server im Klartext
 * kennen — anders meldet sich FTP nicht an —, aber in der Datenbank liegt es
 * versiegelt, sobald ein Schlüssel gesetzt ist. Es verlässt den Server nur in
 * Richtung des eingetragenen Hosts und wird nie ins Formular zurückgeschrieben.
 */
function backup_ftp_config(): array {
  return [
    'enabled' => setting('backup_ftp_enabled') === '1',
    'host'    => setting('backup_ftp_host'),
    'port'    => (int) (setting('backup_ftp_port') ?: 21),
    'user'    => setting('backup_ftp_user'),
    'pass'    => crypt_reveal(setting('backup_ftp_pass')),
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
  // Eine Demo wird stündlich verworfen und neu aufgebaut — sie hat nichts zu
  // sichern. Und was nicht geschrieben wird, kann auch niemand herunterladen:
  // die automatische Sicherung läuft im Seitenaufruf und ginge an jeder
  // Sperre auf einer Route vorbei.
  if (is_demo()) return false;
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
  // Jeden Schreibvorgang prüfen. Läuft die Platte mitten im Lauf voll, schreibt
  // fwrite() nur einen Teil und meldet das ausschließlich im Rückgabewert —
  // ohne diese Prüfung entstünde eine halbe Datenbank, die als „ok" verbucht
  // wird und Wochen später beim Zurückspielen auffliegt.
  $schreib = function (string $text) use ($fh, $path): void {
    $n = fwrite($fh, $text);
    if ($n === false || $n !== strlen($text)) {
      fclose($fh);
      @unlink($path);
      throw new RuntimeException('Sicherung abgebrochen: konnte nicht vollständig schreiben (Platte voll?)');
    }
  };
  $schreib("-- Bandregie " . BANDREGIE_VERSION . ", " . date('c') . "\n");
  $schreib("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
  foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1] ?? '';
    $schreib("DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
    $stmt = $db->query("SELECT * FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
      $vals = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string) $v), $row));
      $schreib("INSERT INTO `$table` ($cols) VALUES ($vals);\n");
    }
    $schreib("\n");
  }
  $schreib("SET FOREIGN_KEY_CHECKS = 1;\n");
  if (!fclose($fh)) throw new RuntimeException('Sicherung abgebrochen: Datei nicht sauber geschlossen');
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
  // Zwischen dem Einsammeln der Liste und dem Packen kann eine Datei
  // verschwunden sein — jemand löscht gerade einen Anhang. Das ist ein Grund,
  // sie auszulassen, kein Grund, die ganze Sicherung fallen zu lassen.
  $fh = @fopen($file, 'rb');
  if (!$fh) return false;
  $size = (int) filesize($file);
  $header = backup_tar_header($nameInArchive, $size, (int) filemtime($file));
  if ($header === null) { fclose($fh); return false; }
  // Auch beim Packen zählt jeder Schreibvorgang: eine volle Platte darf kein
  // Archiv ergeben, das später als vollständig gilt.
  $schreib = function (string $bytes) use ($gz): bool {
    if ($bytes === '') return true;
    $n = gzwrite($gz, $bytes);
    return $n !== false && $n === strlen($bytes);
  };
  if (!$schreib($header)) { fclose($fh); throw new RuntimeException('Archiv nicht vollständig schreibbar (Platte voll?)'); }
  while (!feof($fh)) {
    if (!$schreib((string) fread($fh, 262144))) {
      fclose($fh);
      throw new RuntimeException('Archiv nicht vollständig schreibbar (Platte voll?)');
    }
  }
  fclose($fh);
  if ($size % 512 && !$schreib(str_repeat("\0", 512 - ($size % 512)))) {
    throw new RuntimeException('Archiv nicht vollständig schreibbar (Platte voll?)');
  }
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
  // Zwei Läufe in derselben Sekunde dürfen sich nicht denselben Namen teilen.
  // Genau das passiert beim Zurückspielen, wo die Sicherheitskopie sonst das
  // Archiv überschreibt, das gerade eingespielt werden soll.
  $stamp = date('Y-m-d-His');
  // Die Endung sagt, was drin ist. Wer die Datei in die Hand bekommt, soll
  // nicht raten müssen, ob sie sich öffnen lässt.
  $suffix = crypt_available() ? '.tar.gz.enc' : '.tar.gz';
  $name = 'bandregie-' . $stamp . $suffix;
  for ($n = 2; file_exists($dir . '/' . $name); $n++) {
    $name = 'bandregie-' . $stamp . '-' . $n . $suffix;
  }
  $target = $dir . '/' . $name;
  $sqlFile = $dir . '/.dump.sql';
  $skipped = [];
  // Reste eines hart abgebrochenen Laufs (OOM, Zeitlimit, Neustart) wegräumen:
  // der aufräumende catch-Block läuft dann nicht, und in .dump.sql liegt die
  // vollständige Datenbank im Klartext — Adressen, Kasse, Passwort-Hashes.
  // Nur Altes anfassen, damit ein parallel laufender Vorgang unberührt bleibt.
  foreach (glob($dir . '/{.dump.sql,*.part,.sealing-*,.opening-*}', GLOB_BRACE) ?: [] as $rest) {
    if (is_file($rest) && filemtime($rest) < time() - 3600) @unlink($rest);
  }
  try {
    backup_write_sql($sqlFile);
    // Der Dump liegt kurzzeitig unverschlüsselt da; wenigstens nicht lesbar
    // für andere Konten auf demselben Server.
    @chmod($sqlFile, 0600);
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
    @unlink($sqlFile);
    // Versiegeln, bevor die Datei ihren endgültigen Namen bekommt: was unter
    // dem Namen der Sicherung liegt, ist damit nie kurz der Klartext.
    if (crypt_available()) {
      if (!crypt_seal_file($target . '.part', $target)) {
        throw new RuntimeException('Sicherung ließ sich nicht verschlüsseln');
      }
      @unlink($target . '.part');
    } else {
      rename($target . '.part', $target);
    }
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

  // Aufräumen nach der eigenen Zahl dieses Ziels, neueste zuerst behalten.
  //
  // Das Muster muss alles erfassen, was diese Anwendung je geschrieben hat:
  // verschlüsselte Archive (.enc, seit der Verschlüsselung), zwei Läufe in
  // derselben Sekunde (-2) und den früheren Namen des Projekts. Was es nicht
  // erfasst, bleibt auf dem Ziel für immer liegen — und genau das war der
  // Fall, seit die Endung .enc dazukam.
  $remote = @ftp_nlist($conn, '.') ?: [];
  $mine = [];
  foreach ($remote as $entry) {
    $base = basename($entry);
    if (preg_match('~^band(?:regie|roadie)-\d{4}-\d{2}-\d{2}-\d{6}(?:-\d+)?\.tar\.gz(?:\.enc)?$~', $base)) {
      $mine[] = $base;
    }
  }
  // Nach dem Zeitstempel im Namen sortieren, nicht nach dem Namen selbst:
  // „bandroadie…" sortiert wegen des o immer vor „bandregie…". Auf einem Ziel
  // mit Dateien aus der Zeit vor der Umbenennung landete die frisch
  // hochgeladene Sicherung dadurch am Ende der Liste — und wurde als erstes
  // wieder gelöscht, mit grüner Erfolgsmeldung.
  usort($mine, function (string $a, string $b): int {
    preg_match('~(\d{4}-\d{2}-\d{2}-\d{6})~', $a, $ma);
    preg_match('~(\d{4}-\d{2}-\d{2}-\d{6})~', $b, $mb);
    return strcmp($mb[1] ?? '', $ma[1] ?? '');
  });
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

/**
 * Ein tar aus dem Archiv auspacken. Gegenstück zu backup_tar_add(): 512-Byte-
 * Köpfe, Inhalt auf 512 aufgerundet. Pfade, die aus dem Zielverzeichnis
 * herausführen, werden übersprungen — ein Archiv ist eine fremde Datei.
 *
 * @return array<string> die ausgepackten Dateien, relativ zu $into
 */
function backup_tar_extract(string $archive, string $into): array {
  $gz = gzopen($archive, 'rb');
  if (!$gz) throw new RuntimeException('Archiv nicht lesbar');
  $out = [];
  while (!gzeof($gz)) {
    $header = gzread($gz, 512);
    if ($header === false || strlen($header) < 512 || trim($header) === '') break;
    $name   = trim(substr($header, 0, 100), "\0");
    $size   = (int) octdec(trim(substr($header, 124, 12), "\0 ") ?: '0');
    $prefix = trim(substr($header, 345, 155), "\0");
    if ($prefix !== '') $name = $prefix . '/' . $name;
    $padded = $size % 512 ? $size + (512 - $size % 512) : $size;
    // Nichts außerhalb des Zielverzeichnisses anlegen
    if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')) {
      if ($padded) gzread($gz, $padded);
      continue;
    }
    $target = $into . '/' . $name;
    if (!is_dir(dirname($target))) @mkdir(dirname($target), 0700, true);
    $fh = fopen($target, 'wb');
    $left = $size;
    while ($left > 0) {
      $chunk = gzread($gz, min(262144, $left));
      if ($chunk === false || $chunk === '') break;
      fwrite($fh, $chunk);
      $left -= strlen($chunk);
    }
    fclose($fh);
    if ($padded > $size) gzread($gz, $padded - $size);
    $out[] = $name;
  }
  gzclose($gz);
  return $out;
}

/**
 * SQL-Text in einzelne Anweisungen zerlegen. Ein Semikolon in einem Songtitel
 * oder einer Notiz darf nicht als Ende zählen, deshalb wird durch den Text
 * gelaufen statt an ";" zu zerschneiden.
 *
 * @return array<string>
 */
function backup_split_sql(string $sql): array {
  $out = [];
  $cur = '';
  $quote = '';
  $len = strlen($sql);
  for ($i = 0; $i < $len; $i++) {
    $c = $sql[$i];
    if ($quote !== '') {
      $cur .= $c;
      if ($c === '\\' && $i + 1 < $len) { $cur .= $sql[++$i]; continue; }
      if ($c === $quote) $quote = '';
      continue;
    }
    if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $cur .= $c; continue; }
    if ($c === '-' && substr($sql, $i, 3) === '-- ') {         // Kommentarzeile
      $nl = strpos($sql, "\n", $i);
      $i = $nl === false ? $len : $nl;
      continue;
    }
    if ($c === ';') {
      if (trim($cur) !== '') $out[] = trim($cur);
      $cur = '';
      continue;
    }
    $cur .= $c;
  }
  if (trim($cur) !== '') $out[] = trim($cur);
  return $out;
}

/**
 * Eine Sicherung zurückspielen: erst eine Sicherheitskopie des jetzigen
 * Standes, dann Datenbank und Dateien ersetzen. Der bisherige Datenbestand
 * wird zur Seite gelegt statt gelöscht — wer die falsche Datei erwischt,
 * soll nicht alles verloren haben.
 *
 * @return array{ok:bool,message:string,safety:string}
 */
function backup_restore(string $archive): array {
  global $db;
  if (!is_file($archive)) return ['ok' => false, 'message' => 'Archiv nicht gefunden', 'safety' => ''];

  // Eigene Sperre für die gesamte Dauer. Zwei gleichzeitige Wiederherstellungen
  // — Doppelklick, oder Oberfläche und Kommandozeile zusammen — würden ihre
  // DROP- und INSERT-Folgen ineinander schieben. backup_run() hat seine eigene
  // Sperre, die aber frei ist, sobald die Sicherheitskopie fertig ist.
  $lock = @fopen(backup_dir() . '/.restore-lock', 'c');
  if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    return ['ok' => false, 'safety' => '',
            'message' => 'Eine Wiederherstellung läuft bereits — bitte abwarten'];
  }
  @set_time_limit(0);

  // Das Archiv zuerst beiseitelegen: die Sicherheitskopie schreibt gleich ins
  // selbe Verzeichnis, und was zurückgespielt wird, soll davon unberührt sein.
  $tmp = backup_dir() . '/.restore-' . bin2hex(random_bytes(4));
  @mkdir($tmp, 0700, true);
  $source = $tmp . '/quelle.tar.gz';
  if (!@copy($archive, $source)) {
    @rmdir($tmp);
    return ['ok' => false, 'message' => 'Archiv nicht lesbar', 'safety' => ''];
  }
  // Eine versiegelte Sicherung braucht denselben Schlüssel wie beim Schreiben.
  // Fehlt er oder ist es ein anderer, endet es hier — und nicht mitten im
  // Einspielen mit halb ersetzter Datenbank.
  if (crypt_is_sealed($source)) {
    $opened = $tmp . '/klartext.tar.gz';
    if (!crypt_open_file($source, $opened)) {
      @unlink($source);
      @rmdir($tmp);
      return ['ok' => false, 'safety' => '',
              'message' => crypt_available()
                ? 'Die Sicherung lässt sich mit dem eingetragenen data_key nicht öffnen — ist es der Schlüssel des Servers, auf dem sie entstanden ist?'
                : 'Die Sicherung ist verschlüsselt, aber in app/config.php steht kein data_key'];
    }
    @unlink($source);
    $source = $opened;
  }

  $safety = backup_run('restore');
  $safetyName = $safety['filename'] ?? '';
  if (($safety['status'] ?? '') !== 'ok') {
    @unlink($source);
    @rmdir($tmp);
    return ['ok' => false, 'message' => 'Sicherheitskopie fehlgeschlagen, es wurde nichts verändert', 'safety' => ''];
  }

  try {
    $files = backup_tar_extract($source, $tmp);
    if (!is_file($tmp . '/database.sql')) throw new RuntimeException('Im Archiv fehlt database.sql');

    $statements = backup_split_sql((string) file_get_contents($tmp . '/database.sql'));
    if (count($statements) < 5) throw new RuntimeException('Die Datenbankdatei wirkt unvollständig');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($statements as $stmt) $db->exec($stmt);
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Dateien: den bisherigen Stand danebenlegen, dann den aus dem Archiv.
    // Beide Umbenennungen werden geprüft — scheiterte die zweite unbemerkt,
    // wäre der alte Bestand weggeschoben und kein neuer da, und der
    // finally-Block hätte die ausgepackten Dateien gleich mitgelöscht.
    $stamp = date('Ymd-His');
    $beiseite = [];
    foreach ([UPLOADS_DIR => 'uploads', FILES_DIR => 'files'] as $dir => $base) {
      if (!is_dir($tmp . '/' . $base)) continue;
      $alt = $dir . '.vor-' . $stamp;
      if (is_dir($dir) && !@rename($dir, $alt)) {
        throw new RuntimeException('Bisheriger Dateibestand ließ sich nicht beiseitelegen: ' . basename($dir));
      }
      if (!@rename($tmp . '/' . $base, $dir)) {
        // Zurück auf Anfang für dieses Verzeichnis, damit nicht beides fehlt.
        if (is_dir($alt)) @rename($alt, $dir);
        throw new RuntimeException('Dateien aus dem Archiv ließen sich nicht einsetzen: ' . $base);
      }
      $beiseite[] = basename($alt);
    }
    // Die Datenbank kommt aus dem Archiv — und damit auch backup_runs in dem
    // Stand von damals. Der Eintrag der Sicherheitskopie, die eben entstand,
    // wäre also verschwunden: die Datei läge da, aber niemand fände sie in der
    // Oberfläche. Deshalb hier neu eintragen, zusammen mit einem Vermerk.
    if ($safetyName !== '' && is_file(backup_dir() . '/' . $safetyName)) {
      q('INSERT INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
        [$safetyName, (int) filesize(backup_dir() . '/' . $safetyName), 'ok',
         'Stand vor dem Zurückspielen von ' . basename($archive), 'restore']);
    }
    if (is_file($archive)) {
      q('INSERT IGNORE INTO backup_runs (filename, size_bytes, status, message, trigger_kind) VALUES (?,?,?,?,?)',
        [basename($archive), (int) filesize($archive), 'ok', 'Zurückgespielt', 'restored']);
    }
    $count = count($files);
    $hinweis = $beiseite ? ' Der bisherige Dateibestand liegt als ' . implode(', ', $beiseite) . ' daneben.' : '';
    return ['ok' => true, 'safety' => $safetyName,
            'message' => "Zurückgespielt: $count Dateien aus dem Archiv, "
              . count($statements) . ' SQL-Anweisungen.' . $hinweis];
  } catch (Throwable $e) {
    return ['ok' => false, 'safety' => $safetyName, 'message' => $e->getMessage()];
  } finally {
    // Reste des Auspackens wegräumen, auch die verschachtelten
    if (is_dir($tmp)) {
      $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($it as $leftover) {
        $leftover->isDir() ? @rmdir($leftover->getPathname()) : @unlink($leftover->getPathname());
      }
      @rmdir($tmp);
    }
    // Die Sperre auch im Fehlerfall freigeben, sonst wäre nach einem Abbruch
    // kein zweiter Versuch mehr möglich.
    flock($lock, LOCK_UN);
    fclose($lock);
  }
}

// Direkter Aufruf: ohne Argument sichern, mit „restore <Datei>" zurückspielen.
// Der Weg über die Kommandozeile bleibt der, der auch dann noch funktioniert,
// wenn die Seite selbst nicht mehr startet.
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
  require __DIR__ . '/bootstrap.php';
  // Einen Schlüssel erzeugen. Er wird nur ausgegeben und nirgends abgelegt —
  // eintragen muss ihn ein Mensch, und dabei merkt er sich, dass es ihn gibt.
  if (($argv[1] ?? '') === 'key' || ($argv[1] ?? '') === 'schluessel') {
    echo "Diese Zeile in app/config.php eintragen:\n\n";
    echo "  'data_key' => '" . crypt_new_key() . "',\n\n";
    echo "Danach werden Sicherungen und Anhänge verschlüsselt abgelegt.\n";
    echo "Ohne diesen Schlüssel sind sie nicht mehr zu öffnen — aufbewahren\n";
    echo "wie das Datenbankpasswort, und nicht in derselben Sicherung.\n";
    exit(0);
  }
  if (($argv[1] ?? '') === 'selftest' || ($argv[1] ?? '') === 'pruefen') {
    $res = crypt_selftest();
    echo ($res['ok'] ? 'ok' : 'fehler') . ' ' . $res['message'] . PHP_EOL;
    exit($res['ok'] ? 0 : 1);
  }
  if (($argv[1] ?? '') === 'restore') {
    $file = $argv[2] ?? '';
    if ($file === '') { fwrite(STDERR, "Aufruf: php app/backup.php restore <archiv.tar.gz>\n"); exit(2); }
    if (!str_contains($file, '/')) $file = backup_dir() . '/' . $file;
    $res = backup_restore($file);
    echo ($res['ok'] ? 'ok' : 'fehler') . ' ' . $res['message']
       . ($res['safety'] !== '' ? ' (Sicherheitskopie: ' . $res['safety'] . ')' : '') . PHP_EOL;
    exit($res['ok'] ? 0 : 1);
  }
  $run = backup_run('cron');
  echo ($run['status'] ?? '?') . ' ' . ($run['filename'] ?? '') . ' ' . ($run['message'] ?? '') . PHP_EOL;
  exit(($run['status'] ?? '') === 'ok' ? 0 : 1);
}
