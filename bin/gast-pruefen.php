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

// Ein Formular wirklich abschicken: Seite holen, Token herauslesen, mit
// derselben Sitzung senden. Ohne das prüfte ein POST nur den CSRF-Riegel und
// liefe grün, auch wenn die Route dahinter alles durchließe.
$schicke = static function (string $formularPfad, string $zielPfad, array $felder) use ($host): void {
    $topf = tempnam(sys_get_temp_dir(), 'zzga');
    $basis = sprintf('curl -sk --resolve %s:443:127.0.0.1 -c %s -b %s',
        escapeshellarg($host), escapeshellarg($topf), escapeshellarg($topf));
    $seite = (string) shell_exec(sprintf('%s https://%s%s', $basis, $host, $formularPfad));
    preg_match('~name="_token" value="([a-f0-9]+)"~', $seite, $t);
    $daten = ['_token' => $t[1] ?? ''] + $felder;
    $args = '';
    foreach ($daten as $k => $v) $args .= ' --data-urlencode ' . escapeshellarg("$k=$v");
    shell_exec(sprintf('%s -o /dev/null%s https://%s%s', $basis, $args, $host, $zielPfad));
    @unlink($topf);
};

// ---- Testdaten. Reste eines abgebrochenen Laufs zuerst weg, sonst stolpert
// der nächste Lauf über die Buchung von vorgestern.
$weg = static function () use (&$weg): void {
    foreach (rows("SELECT id FROM guests WHERE name LIKE 'ZZGA %'") as $g) {
        q('DELETE FROM guest_bookings WHERE guest_id = ?', [(int) $g['id']]);
        q('DELETE FROM guest_songs WHERE guest_id = ?', [(int) $g['id']]);
        q('DELETE FROM guests WHERE id = ?', [(int) $g['id']]);
        item_forget('guest', (int) $g['id']);
    }
    foreach (rows("SELECT id FROM songs WHERE title LIKE 'ZZGA %'") as $s) song_delete((int) $s['id']);
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

// ------------------------------------------------ 2. Liederliste
// Eigene Lieder, damit der Lauf nicht vom Bestand der Instanz abhängt.
q("INSERT INTO songs (title, status) VALUES ('ZZGA Lied A', 'aktiv')");
$liedA = (int) $GLOBALS['db']->lastInsertId();
q("INSERT INTO songs (title, status) VALUES ('ZZGA Lied B', 'aktiv')");
$liedB = (int) $GLOBALS['db']->lastInsertId();
q("INSERT INTO songs (title, status) VALUES ('ZZGA Vorschlag', 'vorschlag')");
$liedV = (int) $GLOBALS['db']->lastInsertId();
$aktiveLieder = (int) row("SELECT COUNT(*) AS n FROM songs WHERE status IN ('aktiv', 'in_arbeit')")['n'];

$mit = $buche('ZZGA Mit', ['show_songs' => 1]);
$gastMit = (int) $mit['guest_id'];
$pruefe('Antwort landet beim Gast',
    guest_song_set($gastMit, $liedA, 'yes') && (guest_song_answers($gastMit)[$liedA] ?? '') === 'yes');
$pruefe('Erfundener Stand wird abgelehnt', !guest_song_set($gastMit, $liedA, 'vielleicht-schon'));
$pruefe('Vorschlag ist nicht wählbar', !guest_song_set($gastMit, $liedV, 'yes'));
$pruefe('Zahlen zählen nur, was er sieht', guest_song_counts($gastMit)['total'] === $aktiveLieder);
$pruefe('Beantwortet wird gezählt', guest_song_counts($gastMit)['answered'] === 1);

// Archiviert zählt nicht mehr mit, wieder aktiv wieder — die Zeile bleibt.
q("UPDATE songs SET status = 'archiv' WHERE id = ?", [$liedA]);
$pruefe('Archiviertes Lied zählt nicht', guest_song_counts($gastMit)['yes'] === 0);
q("UPDATE songs SET status = 'aktiv' WHERE id = ?", [$liedA]);
$pruefe('Wieder aktiv zählt wieder', guest_song_counts($gastMit)['yes'] === 1);

$seiteMit = $hole('/gast/' . $mit['token']);
$pruefe('Seite mit Haken nennt die Liederliste', str_contains($seiteMit, '/lieder'));
$pruefe('Seite ohne Haken nennt die Liederliste nicht', !str_contains($seiteZu, '/lieder'));
$pruefe('Liste zeigt das aktive Lied', str_contains($hole('/gast/' . $mit['token'] . '/lieder'), 'ZZGA Lied B'));
$pruefe('Liste zeigt den Vorschlag nicht', !str_contains($hole('/gast/' . $mit['token'] . '/lieder'), 'ZZGA Vorschlag'));
$pruefe('Ohne Haken keine Liste', !str_contains($hole('/gast/' . $zu['token'] . '/lieder'), 'ZZGA Lied B'));

// Ein Token, das nicht (mehr) gilt, erreicht nichts — gleich, woran es liegt.
$storniert = $buche('ZZGA Storniert', ['show_songs' => 1, 'status' => 'storniert']);
$abgesagt = $buche('ZZGA Abgesagt', ['show_songs' => 1, 'status' => 'abgesagt']);
$offen = $buche('ZZGA Offen', ['show_songs' => 1, 'status' => 'angefragt']);
$abgelaufen = $buche('ZZGA Abgelaufen', ['show_songs' => 1]);
q("UPDATE guest_bookings SET access_until = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?", [$abgelaufen['id']]);
foreach (['Storniert' => $storniert, 'Abgesagt' => $abgesagt, 'Offen' => $offen, 'Abgelaufen' => $abgelaufen] as $wie => $b) {
    $pruefe("$wie erreicht die Liste nicht", !str_contains($hole('/gast/' . $b['token'] . '/lieder'), 'ZZGA Lied B'));
}

// Über die echte Route, mit Token und Sitzung: Hier hängt das Schreiben an
// einer Spalte, und ein Haken, der nur den Knopf wegnimmt, wäre keiner.
$schicke('/gast/' . $mit['token'] . '/lieder', '/gast/' . $mit['token'] . '/lied/' . $liedB, ['status' => 'maybe']);
$pruefe('Route schreibt die Antwort', (guest_song_answers($gastMit)[$liedB] ?? '') === 'maybe');

q('UPDATE guest_bookings SET show_songs = 0 WHERE id = ?', [$mit['id']]);
$schicke('/gast/' . $mit['token'], '/gast/' . $mit['token'] . '/lied/' . $liedB, ['status' => 'no']);
$pruefe('Ohne Haken schreibt die Route nicht', (guest_song_answers($gastMit)[$liedB] ?? '') === 'maybe');
q('UPDATE guest_bookings SET show_songs = 1 WHERE id = ?', [$mit['id']]);

$schicke('/gast/' . $storniert['token'], '/gast/' . $storniert['token'] . '/lied/' . $liedB, ['status' => 'yes']);
$pruefe('Stornierte Buchung schreibt nicht',
    !row('SELECT 1 FROM guest_songs WHERE guest_id = ?', [(int) $storniert['guest_id']]));

// Die Antwort gehört dem Gast und nicht der Buchung: Läuft die eine ab und
// kommt eine neue, steht alles noch da.
q("UPDATE guest_bookings SET access_until = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE guest_id = ?", [$gastMit]);
q('INSERT INTO guest_bookings (guest_id, event_id, token, access_until, status, show_songs) VALUES (?,?,?,?,?,1)',
  [$gastMit, $termin, bin2hex(random_bytes(32)), guest_access_until(date('Y-m-d', strtotime('+30 days'))), 'zugesagt']);
$pruefe('Antwort überlebt die Buchung', (guest_song_answers($gastMit)[$liedA] ?? '') === 'yes');

// ------------------------------------------------ 3. Was die Band sieht
$pruefe('Zahlen je Gast in einer Abfrage', (guest_songs_map([$gastMit])[$gastMit]['yes'] ?? 0) === 1);
$pruefe('Gast ohne Antwort steht nicht in der Liste',
    !array_key_exists((int) $zu['guest_id'], guest_songs_map([$gastMit, (int) $zu['guest_id']])));
$pruefe('Antworten löschen leert die Liste',
    guest_songs_clear($gastMit) === 2 && guest_song_answers($gastMit) === []);
$pruefe('Antworten löschen lässt die Buchung stehen',
    (bool) row('SELECT 1 FROM guest_bookings WHERE guest_id = ?', [$gastMit]));

// Dieselbe Funktion wie die Löschroute — eine Abschrift hier prüfte nur sich
// selbst (#439).
foreach ([$liedA, $liedB, $liedV] as $l) song_delete($l);
$pruefe('Gelöschtes Lied nimmt die Antworten mit',
    !row('SELECT 1 FROM guest_songs WHERE song_id = ?', [$liedA]));

$weg();
q('DELETE FROM events WHERE id = ?', [$termin]);

printf("%s%d ok, %d Fehler%s", PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
