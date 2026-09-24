<?php
// Die Tagesmail verschicken (#332).
//
// Dieselbe Arbeit, die auch ein Seitenaufruf auslöst — für Instanzen, deren
// öffentliche Seite keine Besucher hat. Ein geschlossener Bandbereich, in den
// tagelang niemand hineinsieht, bekäme sonst nie eine Mail, und das ist genau
// der Fall, für den sie gedacht ist.
//
// Stündlich als der Webnutzer, damit die Uhrzeit je Mitglied auch zählt:
//
//   5 * * * *  sudo -u <webnutzer> php /pfad/bin/tagesmail.php /pfad
//
// Mehrfache Aufrufe sind gefahrlos: Jedes Mitglied wird vor dem Senden
// beansprucht, und wer heute schon seine Mail hat, ist nicht mehr fällig.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !is_file($basis . '/app/bootstrap.php')) {
    fwrite(STDERR, "Aufruf: php bin/tagesmail.php <Verzeichnis der Installation>\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$gesendet = digest_run();

// Nur melden, wenn etwas geschah. Cron verschickt jede Ausgabe als Mail, und
// eine stündliche Nachricht "nichts zu tun" liest nach drei Tagen niemand mehr
// — dann geht auch die echte Meldung unter.
if ($gesendet > 0) {
    printf("%d Tagesmail(s) verschickt%s", $gesendet, PHP_EOL);
}
exit(0);
