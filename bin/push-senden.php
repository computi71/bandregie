<?php
// Die Warteschlange für Mitteilungen abarbeiten (#383).
//
// Gehört in den Minutentakt:
//
//   * * * * * /usr/bin/php /pfad/zur/app/bin/push-senden.php
//
// Jede Minute, weil eine Mitteilung, die eine Stunde wartet, keine Mitteilung
// mehr ist. Der stündliche Lauf, den es schon gibt, reicht dafür nicht.
//
// WARUM ES DIESE DATEI GIBT: Vorher verschickte der Webarbeiter selbst.
// fastcgi_finish_request() gibt zwar die Antwort frei, aber nicht den Prozess
// — jeder Versand ist ein blockierender HTTPS-Aufruf je Gerät, einer nach dem
// anderen, und bei pm.max_children = 5 sind fünf davon der ganze Pool. Genau
// daran stand die Seite am 20.10.2026 siebzehn Minuten (#374).
//
// DIE SPERRE ist kein Beiwerk. Im Minutentakt holt der nächste Lauf an, bevor
// der vorige fertig ist, sobald ein Dienst einmal hängt — und dann greifen
// zwei Läufe nach denselben Zeilen und verschicken dieselbe Mitteilung
// zweimal. flock() auf eine eigene Datei: Wer sie nicht bekommt, geht wieder,
// und zwar still. Ein Lauf, der „gerade läuft schon einer" ins Protokoll
// schriebe, füllte es im Minutentakt.
//
// Bei Erfolg bleibt es still, damit cron nichts verschickt. Mit --laut sagt
// es, was es getan hat.
declare(strict_types=1);

$laut = in_array('--laut', $argv, true);
$basis = dirname(__DIR__);

// Erst die Sperre, dann die Anwendung: Ein zweiter Lauf soll nicht einmal die
// Datenbank aufmachen.
$sperre = fopen(sys_get_temp_dir() . '/bandregie-push-' . md5($basis) . '.lock', 'c');
if ($sperre === false) {
    fwrite(STDERR, "Sperrdatei nicht zu öffnen\n");
    exit(1);
}
if (!flock($sperre, LOCK_EX | LOCK_NB)) {
    if ($laut) echo "Es läuft schon einer.\n";
    exit(0);
}

require $basis . '/app/bootstrap.php';
require_once $basis . '/app/push.php';

if (!push_available()) {
    if ($laut) echo "Keine Schlüssel hinterlegt — nichts zu tun.\n";
    exit(0);
}

$stand = push_queue_run();
if ($laut) {
    printf("gesendet %d, verworfen %d, später noch einmal %d%s",
        $stand['gesendet'], $stand['verworfen'], $stand['spaeter'], PHP_EOL);
}
// Die Sperre fällt mit dem Prozess; explizit, damit es jeder sieht.
flock($sperre, LOCK_UN);
fclose($sperre);
exit(0);
