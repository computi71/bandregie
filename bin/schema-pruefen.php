<?php
declare(strict_types=1);

/**
 * Wächter gegen den einen Fehler, der zum Tor vor schema.php geführt hat
 * (#328): Anfrage-Logik, die beim Verschieben in app/schema.php landet und
 * bei geschlossenem Tor lautlos nicht mehr läuft. Eigenes Skript statt eines
 * dritten Modus in bin/ladeprofil.php, weil es keine Datenbank und kein
 * geladenes bootstrap.php braucht — reiner Text-Scan, soll also auch dann
 * laufen, wenn die Datei gerade deswegen kaputt ist.
 *
 *   php bin/schema-pruefen.php [Verzeichnis]
 *
 * Prüft app/schema.php auf:
 *   - Zugriffe auf $_SESSION, $_COOKIE, $_GET, $_POST, $_SERVER, $_FILES
 *   - session_*, setcookie, header(, ini_set, date_default_timezone_set,
 *     setlocale, putenv
 *   - jede weitere Funktion/Konstante auf oberster Ebene außer den beiden
 *     bekannten Helfern column_exists() und index_exists()
 *
 * Falle: Der Kopfkommentar der Datei beschreibt genau diesen Vorfall in
 * Prosa und nennt darin einige der gesuchten Wörter — ein blindes grep über
 * die ganze Datei schlägt also immer an. Deshalb wird der einleitende
 * Blockkommentar (die erste /** ... *\/-Gruppe direkt nach dem Dateikopf)
 * vorher entfernt und nur der Code danach geprüft.
 */

$basis = rtrim($argv[1] ?? '.', '/');
$datei = $basis . '/app/schema.php';
if (!is_file($datei)) {
    fwrite(STDERR, "nicht gefunden: $datei\n");
    exit(2);
}

$zeilen = file($datei, FILE_IGNORE_NEW_LINES);
if ($zeilen === false) {
    fwrite(STDERR, "nicht lesbar: $datei\n");
    exit(2);
}

// Den einleitenden Blockkommentar aus der Prüfung nehmen (siehe Falle oben).
// Nicht einfach jeden /** ... *\/-Block überall in der Datei überspringen —
// das würde auch über echtem Code stehende Erklärungen verschlucken und den
// Wächter blind machen. Nur der allererste Block, vor der ersten Codezeile,
// zählt als Kopfkommentar.
$geprueft = $zeilen;
$inKopf = false;
foreach ($zeilen as $i => $zeile) {
    $getrimmt = trim($zeile);
    if ($i === 0 && str_starts_with($getrimmt, '<?php')) { $geprueft[$i] = ''; continue; }
    if ($getrimmt === '' || str_starts_with($getrimmt, 'declare(')) { $geprueft[$i] = ''; continue; }
    if (!$inKopf && str_starts_with($getrimmt, '/**')) {
        $inKopf = true;
        $geprueft[$i] = '';
        if (str_contains($getrimmt, '*/')) $inKopf = false; // einzeiliger Kopfkommentar
        continue;
    }
    if ($inKopf) {
        $geprueft[$i] = '';
        if (str_contains($getrimmt, '*/')) $inKopf = false;
        continue;
    }
    break; // erste echte Codezeile erreicht — ab hier wird scharf geprüft
}

$verboteneAusdruecke = [
    '$_SESSION', '$_COOKIE', '$_GET', '$_POST', '$_SERVER', '$_FILES',
    'session_', 'setcookie', 'header(', 'ini_set', 'date_default_timezone_set',
    'setlocale', 'putenv',
];
$bekannteHelfer = ['column_exists', 'index_exists'];

$treffer = [];
foreach ($geprueft as $i => $zeile) {
    $nr = $i + 1;
    foreach ($verboteneAusdruecke as $ausdruck) {
        if (str_contains($zeile, $ausdruck)) {
            $treffer[] = "Zeile $nr: verbotener Ausdruck '$ausdruck' — $zeile";
        }
    }
    if (preg_match('/^\s*(function|const)\s+([A-Za-z_][A-Za-z0-9_]*)/', $zeile, $m)) {
        $name = $m[2];
        if (!in_array($name, $bekannteHelfer, true)) {
            $treffer[] = "Zeile $nr: unbekannte(r) $m[1] auf oberster Ebene '$name' — $zeile";
        }
    }
}

if ($treffer) {
    fwrite(STDERR, "schema-pruefen: Anfrage-Logik in app/schema.php gefunden:\n");
    foreach ($treffer as $t) fwrite(STDERR, "  $t\n");
    exit(1);
}

echo "schema-pruefen: sauber — keine Anfrage-Logik in app/schema.php\n";
exit(0);
