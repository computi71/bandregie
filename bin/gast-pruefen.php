<?php
// Gäste von allen Seiten (#449, #450, #451, #452).
//
// Der Grund für eine eigene Datei: Hier hängt eine Seite nach außen an einer
// Datenbankspalte. Ein Haken, der nicht greift, sieht im Bandbereich völlig
// richtig aus — nur sieht ein Gast dann etwas, das die Band ihm nicht zeigen
// wollte, und das merkt niemand, der nur die eigene Seite ansieht. Deshalb
// wird hier nicht nur die Funktion gefragt, sondern die echte Seite geholt.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/gast-pruefen.php <Verzeichnis> --schreibt-testdaten [Host]\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-58s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// Der Host steckt zweimal im Befehl: einmal in --resolve, einmal in der
// Adresse. Ohne --resolve ginge die Anfrage über den echten Namen hinaus.
$host = $argv[3] ?? (string) (parse_url(absolute_url('/'), PHP_URL_HOST) ?: 'localhost');
$hole = static function (string $pfad) use ($host): string {
    return (string) shell_exec(sprintf('curl -sk --resolve %s:443:127.0.0.1 https://%s%s',
        escapeshellarg($host), $host, $pfad));
};

// ---- Testdaten. Reste eines abgebrochenen Laufs zuerst weg, sonst stolpert
// der nächste Lauf über die Buchung von vorgestern.
$weg = static function () use (&$weg): void {
    foreach (rows("SELECT id FROM guests WHERE name LIKE 'ZZGA %'") as $g) {
        q('DELETE FROM guest_bookings WHERE guest_id = ?', [(int) $g['id']]);
        q('DELETE FROM guests WHERE id = ?', [(int) $g['id']]);
        item_forget('guest', (int) $g['id']);
    }
    foreach (rows("SELECT id FROM events WHERE title LIKE 'ZZGA %'") as $e) {
        event_delete((int) $e['id']);
    }
};
$weg();

q("INSERT INTO events (title, date, type, status) VALUES ('ZZGA Gig', ?, 'gig', 'bestaetigt')",
  [date('Y-m-d', strtotime('+30 days'))]);
$termin = (int) $GLOBALS['db']->lastInsertId();

/** Eine Buchung mit den Haken, die der Aufrufer nennt; ohne Angabe die Vorgabe. */
$buche = static function (string $name, array $haken = []) use ($termin): array {
    q("INSERT INTO guests (name, email) VALUES (?, '')", [$name]);
    $gast = (int) $GLOBALS['db']->lastInsertId();
    q('INSERT INTO guest_bookings (guest_id, event_id, token, access_until, status) VALUES (?,?,?,?,?)',
      [$gast, $termin, bin2hex(random_bytes(32)), guest_access_until(date('Y-m-d', strtotime('+30 days'))),
       $haken['status'] ?? 'zugesagt']);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    foreach (['show_rider', 'show_setlist', 'show_songs'] as $spalte) {
        if (array_key_exists($spalte, $haken)) {
            q("UPDATE guest_bookings SET `$spalte` = ? WHERE id = ?", [(int) $haken[$spalte], $nr]);
        }
    }
    return row('SELECT b.*, g.name AS guest_name FROM guest_bookings b JOIN guests g ON g.id = b.guest_id WHERE b.id = ?', [$nr]);
};

// ------------------------------------------------ 1. Was der Link zeigt
$alt = $buche('ZZGA Alt');
$pruefe('Neue Buchung zeigt den Rider', guest_may_see($alt, 'rider'));
$pruefe('Neue Buchung zeigt die Setliste', guest_may_see($alt, 'setlist'));
$pruefe('Neue Buchung zeigt keine Liederliste', !guest_may_see($alt, 'songs'));
$pruefe('Unbekannter Teil ist zu', !guest_may_see($alt, 'kasse'));

$zu = $buche('ZZGA Zu', ['show_rider' => 0, 'show_setlist' => 0]);
$pruefe('Abgewählter Rider ist zu', !guest_may_see($zu, 'rider'));
$pruefe('Abgewählte Setliste ist zu', !guest_may_see($zu, 'setlist'));

$seiteAlt = $hole('/gast/' . $alt['token']);
$seiteZu = $hole('/gast/' . $zu['token']);
$pruefe('Seite mit Haken nennt den Rider', str_contains($seiteAlt, '/rider'));
$pruefe('Seite ohne Haken nennt den Rider nicht', !str_contains($seiteZu, '/rider'));
$pruefe('Seite ohne Haken nennt die Setliste nicht', !str_contains($seiteZu, '/setliste'));
// Der Termin selbst bleibt immer stehen — dafür lädt die Band ja ein.
$pruefe('Seite ohne Haken nennt den Termin', str_contains($seiteZu, 'ZZGA Gig'));
// Geraten hilft nicht: Die Unterseite muss selbst prüfen, nicht nur der Knopf.
$pruefe('Geratene Rider-Adresse führt zurück',
    !str_contains($hole('/gast/' . $zu['token'] . '/rider'), 'stage'));

$weg();
q('DELETE FROM events WHERE id = ?', [$termin]);

printf("%s%d ok, %d Fehler%s", PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
