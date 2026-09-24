<?php
// Jede Seite des Bandbereichs einmal aufrufen und den Statuscode nennen.
//
//   sudo -u www-data php bin/routen-pruefen.php /var/www/bandroadie-oss staging.beatcuisine.de 1
//
// ALS WEBNUTZER starten, nicht als deploy: Das Skript legt die Sitzungsdatei
// an, die es danach per curl benutzt. Läuft es unter einem anderen Konto,
// gehört ihm die Datei, php-fpm darf sie nicht lesen — und dann meldet jede
// einzelne Seite 302 statt 200, als wäre die halbe Anwendung kaputt.
//
// Warum ein Skript und keine Stichprobe: Ein Verschieben zerlegt selten alles,
// meistens genau eine Seite in genau einem Bereich. Die findet nur, wer alle
// aufruft.
//
// Geprüft wird der Statuscode UND das Ende der Seite (#342). Der Code allein
// genügt nicht: Der Kopf ist längst ausgeliefert, wenn eine Ansicht mittendrin
// scheitert, also steht die 200 schon in der Leitung und nur der Rumpf bricht
// ab. Eine Seite ohne abschließendes </html> hat es nicht bis zum Fuß
// geschafft — egal, was der Code behauptet.
//
// NUR auf dem Entwicklungsserver. Es legt eine Sitzung für ein beliebiges
// Konto an, und das gehört nicht auf eine Bandinstanz.
declare(strict_types=1);

[$basis, $host, $uid] = [$argv[1] ?? '', $argv[2] ?? '', (int) ($argv[3] ?? 0)];
if ($basis === '' || $host === '' || !$uid) {
    fwrite(STDERR, "Aufruf: php bin/routen-pruefen.php <Verzeichnis> <Host> <Mitgliedsnummer>\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

// bootstrap.php hat die Sitzung beim Einbinden bereits gestartet (wegen der
// Cookie-Absicherung ganz oben dort); ein zweiter session_start() hier würde
// nur eine Warnung erzeugen. Es reicht, die Mitgliedsnummer einzutragen und
// die Sitzung freizugeben, damit curl sie unter der eigenen Kennung nutzt.
$_SESSION['uid'] = $uid;
$sid = session_id();
session_write_close();

// Die Adressen kommen aus der Rechtetabelle: Sie kennt jeden Bereich, und neue
// Bereiche sind damit automatisch dabei.
$pfade = ['/intern', '/intern/hilfe', '/intern/profil', '/intern/einstellungen'];
foreach (PERM_MODULES as $pfadliste) {
    foreach ($pfadliste as $pfad) {
        if ($pfad !== '' && !in_array($pfad, $pfade, true)) $pfade[] = $pfad;
    }
}

// Diese drei stehen in PERM_MODULES, tragen dort aber nur die Rechteprüfung
// und sind selbst keine aufrufbare Seite: /intern/thema und /intern/beitrag
// sind lediglich der gemeinsame Namensteil von Routen mit einer ID dahinter
// (/intern/themen/{id}, /intern/beitrag/{id}/delete), /intern/kommentare hat
// überhaupt keine GET-Route, nur eine POST-Löschaktion. Ein Aufruf genau
// dieser Pfade liefert immer 404 — das prüft die Route, nicht die Seite.
$keine_eigene_seite = ['/intern/thema', '/intern/beitrag', '/intern/kommentare'];
$pfade = array_values(array_diff($pfade, $keine_eigene_seite));

$fehler = 0;
foreach ($pfade as $pfad) {
    // $host steckt zweimal im selben Befehl — einmal in --resolve, einmal in
    // der URL. Beide Stellen gleich behandeln, sonst wird ausgerechnet die
    // unauffällige Stelle irgendwann kopiert, ohne den Schutz mitzunehmen.
    $befehl = sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s -w "
%%{http_code}" https://%s%s',
        escapeshellarg($host), escapeshellarg($sid), escapeshellarg($host), $pfad);
    $antwort = (string) shell_exec($befehl);
    $trenner = strrpos($antwort, "
");
    $code = (int) substr($antwort, $trenner === false ? 0 : $trenner + 1);
    $rumpf = $trenner === false ? '' : substr($antwort, 0, $trenner);
    $ganz = str_ends_with(rtrim($rumpf), '</html>');
    $gut = $code === 200 && $ganz;
    if (!$gut) $fehler++;
    printf("%-34s %d %s%s", $pfad, $code,
        $gut ? 'ok' : ($code === 200 ? 'FEHLER (Seite bricht ab)' : 'FEHLER'), PHP_EOL);
}
printf('%s%d Seiten, %d Fehler%s', PHP_EOL, count($pfade), $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
