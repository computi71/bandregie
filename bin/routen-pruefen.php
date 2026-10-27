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

// ---------------- Listenfelder, die als Array ankommen (#396)
//
// Ein Feld aus einer Anfrage kann immer ein Array sein; das entscheidet der
// Absender und nicht das Formular. Wer den rohen Wert als Array-Schluessel
// benutzt, bekommt dann keinen abgelehnten Wert, sondern einen TypeError:
// /intern/termine?art[]=1 antwortete mit 500 und einer leeren Seite.
//
// Geprueft wird beides - dass die Seiten es aushalten, und dass die
// gefaehrliche Schreibweise nicht zurueckkommt.
$mitArray = [
    '/intern/termine?art[]=1',
    '/intern/termine/export?art[]=1',
    '/intern/songs?status[]=1',
    '/intern/uebersetzungen?sprache[]=1',
];
foreach ($mitArray as $pfad) {
    $befehl = sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s -o /dev/null -w "%%{http_code}" %s',
        escapeshellarg($host), escapeshellarg($sid),
        escapeshellarg('https://' . $host . $pfad));
    $code = (int) shell_exec($befehl);
    $gut = $code !== 500;
    if (!$gut) $fehler++;
    printf("%-38s %d %s%s", $pfad, $code, $gut ? 'ok' : 'FEHLER (bricht ab)', PHP_EOL);
}

// ---------------- Akkordeon: gruppiert oder nicht (#422)
//
// Drei Seiten zeigen Listen aus Faltkarten, und sie verhielten sich drei
// verschiedene Weisen. Was sie gruppiert, ist das name-Attribut - der Browser
// macht es selbst, accordion.js nur dort, wo er es nicht kann. Es wegzulassen
// fällt nicht auf: Die Seite sieht richtig aus, es klappen nur beliebig viele
// Karten auf einmal auf, und das merkt erst, wer eine lange Liste hat.
$akkordeon = [
    '/intern'          => ['listev' => 0, 'dashev' => 1],
    '/intern/termine'  => ['listev' => 1, 'dashev' => 0],
    '/intern/aufgaben' => ['taskedit' => 1],
];
foreach ($akkordeon as $pfad => $erwartet) {
    $seite = (string) shell_exec(sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s https://%s%s',
        escapeshellarg($host), escapeshellarg($sid), escapeshellarg($host), $pfad));
    preg_match_all('~<details[^>]*>~s', $seite, $treffer);
    $gefunden = [];
    foreach ($treffer[0] as $d) {
        if (preg_match('~\sname="([^"]+)"~', $d, $g)) {
            $gefunden[$g[1]] = ($gefunden[$g[1]] ?? 0) + 1;
        }
    }
    foreach ($erwartet as $gruppe => $mindestens) {
        $da = $gefunden[$gruppe] ?? 0;
        $gut = $mindestens > 0 ? $da >= $mindestens : $da === 0;
        if (!$gut) $fehler++;
        printf("%-30s %-9s %s%s", $pfad, $gruppe,
            $gut ? "ok ($da)" : "FEHLER ($da, erwartet " . ($mindestens > 0 ? ">=1" : "0") . ")",
            PHP_EOL);
    }
}

$roh = 0;
foreach ([$basis . '/httpdocs/index.php', $basis . '/app/bootstrap.php'] as $datei) {
    $inhalt = (string) file_get_contents($datei);
    // pick() selbst darf es, sonst niemand.
    $inhalt = str_replace('is_string($wert) && array_key_exists($wert, $erlaubt)', '', $inhalt);
    $roh += preg_match_all('~array_key_exists\(\s*\$_(GET|POST|REQUEST|COOKIE)~', $inhalt);
}
if ($roh) $fehler++;
printf("%-38s %s%s", 'rohe Weissliste auf Anfragewerten',
    $roh ? "FEHLER ($roh Stellen)" : 'ok', PHP_EOL);

printf('%s%d Seiten, %d Fehler%s', PHP_EOL, count($pfade), $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
