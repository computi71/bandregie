<?php
// Wohin geht die Zeit beim Laden, und ist beim Verschieben etwas
// verlorengegangen? Zwei Fragen, ein Werkzeug.
//
//   php bin/ladeprofil.php /var/www/bandroadie-oss
//   php bin/ladeprofil.php /var/www/bandroadie-oss --fingerabdruck
//
// Der Fingerabdruck ist die Summe über alle Funktions- und Konstantennamen.
// Bleibt er beim Verschieben gleich, ist nichts verschwunden und nichts
// doppelt definiert — genau der Fehler, der beim Auslagern zweimal laufender
// Skripte entsteht.
//
// Seit #328 hängt die Summe zusätzlich vom Tor vor schema.php ab: Bei
// geschlossenem Tor fehlen column_exists() und index_exists() aus der Liste,
// ohne dass etwas kaputt wäre. Ein Fingerabdruck ist deshalb nur zwischen
// zwei Läufen mit demselben Tor-Zustand vergleichbar — die Zeile „Tor"
// in der Standardausgabe zeigt, welcher das war.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !is_file($basis . '/app/bootstrap.php')) {
    fwrite(STDERR, "Aufruf: php bin/ladeprofil.php <Verzeichnis> [--fingerabdruck]\n");
    exit(2);
}
chdir($basis);

$t0 = microtime(true);
require $basis . '/app/bootstrap.php';
$dauer = (microtime(true) - $t0) * 1000;

$namen = array_merge(
    get_defined_functions()['user'],
    array_keys(get_defined_constants(true)['user'] ?? [])
);
sort($namen);

if (in_array('--fingerabdruck', $argv, true)) {
    echo sha1(implode("\n", $namen)), PHP_EOL;
    foreach ($namen as $name) echo $name, PHP_EOL;
    exit(0);
}

// Die Abfragen zählt die Datenbank selbst mit; minus eins für diese Abfrage.
$fragen = (int) row("SHOW SESSION STATUS LIKE 'Questions'")['Value'] - 1;

printf("geladen in         %8.1f ms%s", $dauer, PHP_EOL);
printf("Abfragen dabei     %8d%s", $fragen, PHP_EOL);
printf("Speicher           %8.1f MB%s", memory_get_peak_usage(true) / 1048576, PHP_EOL);
printf("Funktionen         %8d%s", count(get_defined_functions()['user']), PHP_EOL);
printf("Konstanten         %8d%s", count(get_defined_constants(true)['user'] ?? []), PHP_EOL);
printf("Dateien            %8d%s", count(get_included_files()), PHP_EOL);
$torOffen = (bool) array_filter(get_included_files(), fn($f) => str_ends_with($f, '/app/schema.php'));
printf("Tor                %8s%s", $torOffen ? 'offen' : 'zu', PHP_EOL);
printf("Fingerabdruck      %s%s", substr(sha1(implode("\n", $namen)), 0, 12), PHP_EOL);
