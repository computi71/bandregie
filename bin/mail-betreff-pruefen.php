<?php
// Betreffzeilen durchspielen (#341) — OHNE zu senden.
//
// Geprüft wird, was mail_subject() aus einem Betreff macht: ASCII bleibt
// lesbar, ein Umlaut wird kodiert und kommt beim Entschlüsseln unverändert
// zurück, und eine eingeschmuggelte Zeilenschaltung erzeugt keine zweite
// Kopfzeile.
//
// Sie schreibt nichts und läuft deshalb überall:
//
//   php bin/mail-betreff-pruefen.php /var/www/bandroadie-oss
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '') {
    fwrite(STDERR, "Aufruf: php bin/mail-betreff-pruefen.php <Verzeichnis>\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-56s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// ----------------------------------------------------- 1. ASCII bleibt ASCII
$pruefe('reines ASCII bleibt unveraendert',
    mail_subject('Rehearsal moved to Sunday') === 'Rehearsal moved to Sunday');
$pruefe('leer bleibt leer', mail_subject('') === '');
$pruefe('Satzzeichen zaehlen als ASCII',
    mail_subject('Gig: 19:00 (Halle 2) - bring gear!') === 'Gig: 19:00 (Halle 2) - bring gear!');

// ------------------------------------------------ 2. Umlaute werden kodiert
$mitUmlaut = 'OneDrive: Das Geheimnis läuft in 12 Tagen ab';
$kodiert = mail_subject($mitUmlaut);
$pruefe('Umlaut wird kodiert', str_contains($kodiert, '=?UTF-8?'));
$pruefe('kodiert ist reines ASCII', preg_match('~^[\x20-\x7E\r\n]*$~', $kodiert) === 1);
$pruefe('und kommt unveraendert zurueck', mb_decode_mimeheader($kodiert) === $mitUmlaut);

foreach (['Änderung am Ablauf', 'Grüße aus dem Proberaum', 'Straße & Gasse',
          'Probe fällt aus — kurzfristig', 'Setliste für Sonntag'] as $betreff) {
    $pruefe('hin und zurueck: ' . mb_substr($betreff, 0, 26),
        mb_decode_mimeheader(mail_subject($betreff)) === $betreff);
}

// ------------------------------------------- 3. Keine zweite Kopfzeile
// Der klassische Einschleusversuch: eine Zeilenschaltung und danach ein
// eigener Kopf. Nach mail_subject() darf davon nichts uebrig sein, was ein
// Mailserver als neue Zeile lesen koennte.
$boese = "Probe\r\nBcc: fremder@example.com";
$gezaehmt = mail_subject($boese);
$pruefe('Zeilenschaltung verschwindet', !str_contains($gezaehmt, "\r") && !str_contains($gezaehmt, "\n"));
$pruefe('kein eigener Kopf mehr moeglich', !preg_match('~^\s*Bcc:~mi', $gezaehmt));
$pruefe('der Text bleibt erhalten', str_contains($gezaehmt, 'Probe'));

$boeseUmlaut = "Probe fällt aus\r\nBcc: fremder@example.com";
$gezaehmtU = mail_subject($boeseUmlaut);
// Kodiert steht die Zeilenschaltung als Faltung drin: CRLF + Leerzeichen, und
// das ist die einzige erlaubte Form. Eine Zeile, die mit etwas anderem als
// Leerraum beginnt, waere ein eigener Kopf.
$pruefe('auch kodiert keine eigene Kopfzeile', (static function () use ($gezaehmtU): bool {
    foreach (explode("\n", str_replace("\r\n", "\n", $gezaehmtU)) as $i => $zeile) {
        if ($i > 0 && $zeile !== '' && !str_starts_with($zeile, ' ') && !str_starts_with($zeile, "\t")) return false;
    }
    return true;
})());
$pruefe('und der Einschleusversuch wird sichtbarer Text',
    str_contains(mb_decode_mimeheader($gezaehmtU), 'Bcc: fremder@example.com'));

// --------------------------------------------------------- 4. Laenge deckeln
$lang = str_repeat('a', 500);
$pruefe('ASCII wird bei 200 abgeschnitten', mb_strlen(mail_subject($lang)) === 200);
$pruefe('eigenes Hoechstmass gilt', mb_strlen(mail_subject($lang, 40)) === 40);
$langUmlaut = str_repeat('ä', 500);
$pruefe('auch mit Umlauten wird vorher gedeckelt',
    mb_strlen(mb_decode_mimeheader(mail_subject($langUmlaut))) === 200);

// ------------------------------------------------- 5. Faltung langer Zeilen
$langerBetreff = 'Änderung: ' . str_repeat('Probe am Donnerstag verschoben ', 6);
$gefaltet = mail_subject($langerBetreff);
$pruefe('eine lange Zeile wird gefaltet', str_contains($gefaltet, "\r\n"));
$pruefe('jede Zeile bleibt unter 78 Zeichen', (static function () use ($gefaltet): bool {
    foreach (explode("\r\n", $gefaltet) as $zeile) if (strlen($zeile) > 78) return false;
    return true;
})());
$pruefe('gefaltet kommt der Text trotzdem zurueck',
    mb_decode_mimeheader($gefaltet) === mail_header_value($langerBetreff, 200));

// --------------------------------------- 6. mail_header_value bleibt roh
// Adressen duerfen NICHT kodiert werden — ein =?UTF-8?B?…?= in einer
// Reply-To-Zeile ist keine Adresse mehr.
$pruefe('mail_header_value kodiert nicht',
    mail_header_value('kontakt@beispiel.de') === 'kontakt@beispiel.de');
$pruefe('mail_header_value laesst Umlaute roh',
    mail_header_value('Grüße') === 'Grüße');

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
