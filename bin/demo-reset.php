<?php
declare(strict_types=1);

// Setzt die öffentliche Demo auf den Auslieferungszustand zurück: Tabellen weg,
// Uploads weg, Neuinstallation, Demoband, feste Kennwörter.
//
// Aufruf:   php bin/demo-reset.php [kennwort] [-v]
// Per cron: siehe bin/demo-reset.sh
//
// Es setzt ausschließlich die Installation zurück, zu der es gehört — kein
// Pfad als Aufrufwert, damit es gar nicht erst auf eine fremde zeigen kann.
//
// Und selbst dann nur, wenn deren app/config.php ausdrücklich
// 'is_demo' => true enthält; fehlt der Schalter, bricht es ab. Die Anwendung
// liest den Schlüssel nirgends sonst — er steht nur da, um genau diese Frage
// zu beantworten.
//
// Bei Erfolg sagt es nichts. Ein Auftrag, der stündlich meldet, dass alles in
// Ordnung ist, erzieht seinen Empfänger dazu, die Meldung zu übersehen — und
// dann fällt auch die auf, die etwas zu sagen hat, nicht mehr auf. Wer
// zusehen will, ruft es mit -v auf.

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

$target = dirname(__DIR__);

$args = array_slice($argv, 1);
$verbose = in_array('-v', $args, true) || in_array('--verbose', $args, true);
$rest = array_values(array_filter($args, fn(string $a): bool => !str_starts_with($a, '-')));
$password = $rest[0] ?? 'demo';

$configFile = $target . '/app/config.php';
if (!is_file($configFile)) {
  fail("Keine Installation in $target — app/config.php fehlt.");
}

$config = require $configFile;
if (empty($config['is_demo'])) {
  fail("$configFile enthält kein 'is_demo' => true. Abbruch: das sieht nach einer\n"
     . "  echten Installation aus, und die wird hier nicht angefasst.");
}

$dataDir = $target . '/data';

step('Verbinden');
$db = new PDO(
  "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
  $config['db_user'],
  $config['db_pass'],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

step('Tabellen löschen');
// Die Namen kommen vom Server, nicht aus einer Anfrage. Die Fremdschlüssel
// müssen trotzdem aus, sonst hängt die Reihenfolge davon ab, was worauf zeigt.
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
  $db->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
}
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
note(count($tables) . ' Tabellen entfernt');
$db = null;

step('Uploads und Anhänge löschen');
note(remove_contents($dataDir) . ' Dateien entfernt');

step('Neu installieren');
// Schema, Migrationen, Übersetzungen und das Administratorkonto legt die
// Anwendung beim Einbinden selbst an — genau wie beim ersten Seitenaufruf.
require $target . '/app/bootstrap.php';
require $target . '/app/demo.php';

step('Demoband einspielen');
// Die Website-Demo bekommt die volle erfundene Band (mit Mitgliedern) — das ist
// die is_demo-only Variante. Normale Installationen nutzen demo_install() und
// bekommen nur schlanke, mitgliederfreie Beispieldaten.
demo_install_demoband();

step('Kennwörter setzen');
// Die Demodaten vergeben Zufallskennwörter und schreiben sie in eine Datei.
// Für eine öffentliche Demo muss auf der Werbeseite aber ein Kennwort stehen,
// das jeder eintippen kann — also werden alle Konten gleichgesetzt.
$hash = password_hash($password, PASSWORD_DEFAULT);
q('UPDATE users SET password_hash = ?, must_change_pw = 0', [$hash]);
$accounts = (int) (row('SELECT COUNT(*) AS n FROM users')['n'] ?? 0);
// Die geschweiften Klammern sind nicht Zierde: ohne sie zieht PHP das
// schließende Anführungszeichen noch in den Variablennamen hinein.
note("$accounts Konten auf „{$password}“ gesetzt");

step('Kennwortdateien entfernen');
// INITIAL-PASSWORD.txt und DEMO-LOGINS.txt nennen die Zufallskennwörter von
// eben, die jetzt nicht mehr gelten. Stehen lassen hieße: eine Datei mit
// falschen Zugangsdaten auf einem öffentlich erreichbaren Rechner.
foreach (['INITIAL-PASSWORD.txt', 'DEMO-LOGINS.txt'] as $name) {
  if (is_file($dataDir . '/' . $name)) unlink($dataDir . '/' . $name);
}

note('Fertig. Die Demo steht wieder auf Anfang.');

// Fortschritt nur mit -v, und dann auf die Fehlerausgabe statt auf die
// Standardausgabe. Grund für die Fehlerausgabe: sobald etwas auf der
// Standardausgabe steht, hält PHP die Kopfzeilen für gesendet — und die
// Anwendung, die weiter unten eingebunden wird, setzt beim Start ihre Sitzung
// und ihre Schutzkopfzeilen und quittierte das mit einem Schwung Warnungen.
function step(string $what): void
{
  global $verbose;
  if ($verbose) fwrite(STDERR, "== $what\n");
}

function note(string $what): void
{
  global $verbose;
  if ($verbose) fwrite(STDERR, "   $what\n");
}

function fail(string $message): never
{
  fwrite(STDERR, "  $message\n");
  exit(1);
}

/** Löscht den Inhalt eines Verzeichnisses, das Verzeichnis selbst bleibt. */
function remove_contents(string $dir): int
{
  if (!is_dir($dir)) return 0;

  $removed = 0;
  $entries = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
  $walk = new RecursiveIteratorIterator($entries, RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($walk as $entry) {
    /** @var SplFileInfo $entry */
    if ($entry->isDir()) {
      rmdir($entry->getPathname());
      continue;
    }
    unlink($entry->getPathname());
    $removed++;
  }
  return $removed;
}
