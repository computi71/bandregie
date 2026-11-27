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
$schicke = static function (string $formularPfad, string $zielPfad, array $felder, string $sid = '') use ($host): string {
    $topf = tempnam(sys_get_temp_dir(), 'zzga');
    // Mit Sitzungskennung für die Seiten des Bandbereichs: Ohne sie landete
    // der Aufruf auf der Anmeldung, und eine Prüfung „es entsteht kein zweiter
    // Gast" wäre grün, weil überhaupt nichts passiert ist.
    if ($sid !== '') file_put_contents($topf, '');
    $basis = sprintf('curl -sk --resolve %s:443:127.0.0.1 -c %s -b %s%s',
        escapeshellarg($host), escapeshellarg($topf), escapeshellarg($topf),
        $sid !== '' ? ' -b ' . escapeshellarg('PHPSESSID=' . $sid) : '');
    $seite = (string) shell_exec(sprintf('%s https://%s%s', $basis, $host, $formularPfad));
    preg_match('~name="_token" value="([a-f0-9]+)"~', $seite, $t);
    $daten = ['_token' => $t[1] ?? ''] + $felder;
    $args = '';
    foreach ($daten as $k => $v) $args .= ' --data-urlencode ' . escapeshellarg("$k=$v");
    // Die Antwortseite kommt zurück: Manches steht nur dort — der Dank nach
    // der Zusage etwa erscheint kein zweites Mal.
    $antwort = (string) shell_exec(sprintf('%s%s https://%s%s', $basis, $args, $host, $zielPfad));
    @unlink($topf);
    return $antwort;
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
    // Auch die Setlisten: Bricht ein Lauf vor dem Aufräumen ab, blieben sie
    // sonst für immer stehen — und die nächste Runde fände eine Setliste, die
    // schon „gespielt" ist.
    foreach (rows("SELECT id FROM setlists WHERE name LIKE 'ZZGA %'") as $s) {
        q('UPDATE events SET setlist_id = NULL WHERE setlist_id = ?', [(int) $s['id']]);
        q('DELETE FROM setlist_songs WHERE setlist_id = ?', [(int) $s['id']]);
        q('DELETE FROM setlists WHERE id = ?', [(int) $s['id']]);
    }
    q("DELETE FROM permissions WHERE user_id IN (SELECT id FROM users WHERE name = 'ZZGA Konto')");
    q("DELETE FROM users WHERE name = 'ZZGA Konto'");
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

// ------------------------------------------------ 4. Setliste
q("INSERT INTO setlists (name) VALUES ('ZZGA Setliste')");
$setliste = (int) $GLOBALS['db']->lastInsertId();
q('UPDATE events SET setlist_id = ? WHERE id = ?', [$setliste, $termin]);
// Zweiter Termin mit derselben Setliste: Ein Gast, der an beiden gebucht ist,
// darf trotzdem nur einmal im Filter stehen.
q("INSERT INTO events (title, date, type, status, setlist_id) VALUES ('ZZGA Gig 2', ?, 'gig', 'bestaetigt', ?)",
  [date('Y-m-d', strtotime('+40 days')), $setliste]);
$termin2 = (int) $GLOBALS['db']->lastInsertId();
// Der vergangene Termin bekommt eine EIGENE Setliste: Hinge er an derselben,
// wäre die Setliste „bereits live gespielt" und damit fixiert — dann gäbe es
// unten gar keine Auswahl mehr, und die Prüfung des Filters prüfte nichts.
q("INSERT INTO setlists (name) VALUES ('ZZGA Setliste alt')");
$setlisteAlt = (int) $GLOBALS['db']->lastInsertId();
q("INSERT INTO events (title, date, type, status, setlist_id) VALUES ('ZZGA Gig alt', ?, 'gig', 'bestaetigt', ?)",
  [date('Y-m-d', strtotime('-40 days')), $setlisteAlt]);
$terminAlt = (int) $GLOBALS['db']->lastInsertId();

$sl = $buche('ZZGA Setlistengast', ['show_songs' => 1]);
$gastSl = (int) $sl['guest_id'];
q('INSERT INTO guest_bookings (guest_id, event_id, token, access_until, status, show_songs) VALUES (?,?,?,?,?,1)',
  [$gastSl, $termin2, bin2hex(random_bytes(32)), guest_access_until(date('Y-m-d', strtotime('+40 days'))), 'zugesagt']);
guest_song_set($gastSl, $liedA, 'yes');
guest_song_set($gastSl, $liedB, 'no');

$pruefe('Gast der Setliste steht im Filter', array_key_exists($gastSl, setlist_guests($setliste)));
$pruefe('Zweimal gebucht heißt einmal im Filter', count(setlist_guests($setliste)) === 1);
$pruefe('Er bringt seine Antworten mit',
    (setlist_guests($setliste)[$gastSl]['answers'][$liedA] ?? '') === 'yes');

$stumm = $buche('ZZGA Stumm', ['show_songs' => 1]);
$pruefe('Ohne Antwort nicht im Filter', !array_key_exists((int) $stumm['guest_id'], setlist_guests($setliste)));

$storno2 = $buche('ZZGA Storno2', ['show_songs' => 1, 'status' => 'storniert']);
guest_song_set((int) $storno2['guest_id'], $liedA, 'yes');
$pruefe('Stornierte Buchung nicht im Filter',
    !array_key_exists((int) $storno2['guest_id'], setlist_guests($setliste)));

// Nur am vergangenen Termin gebucht: Eine Setliste, die vor zwei Jahren
// gespielt wurde, soll nicht jeden Filter mit alten Gästen füllen.
q("INSERT INTO guests (name, email) VALUES ('ZZGA Gestern', '')");
$gastAlt = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO guest_bookings (guest_id, event_id, token, access_until, status, show_songs) VALUES (?,?,?,?,?,1)',
  [$gastAlt, $terminAlt, bin2hex(random_bytes(32)), guest_access_until(date('Y-m-d', strtotime('-40 days'))), 'zugesagt']);
guest_song_set($gastAlt, $liedA, 'yes');
$pruefe('Vergangener Termin nicht im Filter', setlist_guests($setlisteAlt) === []);

// Die gebaute Seite, nicht nur die Funktion: Filter und Zeichen entscheiden
// sich in der Ansicht, und ein Recht, das nur dort geprüft wird, prüft keine
// Funktion mit.
$sidLetzte = '';
$mitglied = static function (array $rechte) use ($host, &$sidLetzte): callable {
    q("INSERT INTO users (name, email, role, password_hash) VALUES ('ZZGA Konto', ?, 'member', '')",
      ['zzga-' . bin2hex(random_bytes(4)) . '@example.invalid']);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    foreach ($rechte as $modul => $darf) {
        q('INSERT INTO permissions (user_id, module, can_read, can_write) VALUES (?,?,?,?)',
          [$nr, $modul, (int) $darf[0], (int) $darf[1]]);
    }
    // Die Sitzungsdatei selbst schreiben statt session_start() ein zweites Mal
    // zu rufen: Nach der ersten Ausgabe geht das nicht mehr, und dann liefe
    // das zweite Konto still unter der Kennung des ersten — die Prüfung
    // „ohne Gästerecht" sähe die Seite mit Gästerecht und meldete grün.
    $sid = bin2hex(random_bytes(16));
    $ablage = (string) (ini_get('session.save_path') ?: sys_get_temp_dir());
    file_put_contents(rtrim($ablage, '/') . '/sess_' . $sid, 'uid|i:' . $nr . ';');
    $sidLetzte = $sid;
    return static fn(string $pfad): string => (string) shell_exec(sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s https://%s%s',
        escapeshellarg($host), escapeshellarg($sid), $host, $pfad));
};

$alleRechte = ['setlists' => [1, 1], 'songs' => [1, 1], 'gaeste' => [1, 1], 'termine' => [1, 1]];
$sieht = $mitglied($alleRechte);
$slSeite = $sieht('/intern/setlists/' . $setliste);
$pruefe('Setliste nennt den Filter', str_contains($slSeite, 'ZZGA Setlistengast'));
$slGefiltert = $sieht('/intern/setlists/' . $setliste . '?gast=' . $gastSl);
$pruefe('Gefilterte Auswahl zeigt das Kann-Lied', str_contains($slGefiltert, 'ZZGA Lied A'));
$pruefe('Gefilterte Auswahl zeigt das Nein-Lied nicht', !str_contains($slGefiltert, 'ZZGA Lied B'));

// Zeichen am schon eingetragenen Lied: Die Setliste steht, der Gast kam danach.
q('INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?,?,1)', [$setliste, $liedB]);
$slMitLied = $sieht('/intern/setlists/' . $setliste);
$pruefe('Zeichen am Lied, das er nicht kann', str_contains($slMitLied, '✘ ZZGA Setlistengast'));
$pruefe('Ausdruck trägt kein Zeichen',
    !str_contains($sieht('/intern/setlists/' . $setliste . '/print'), 'ZZGA Setlistengast'));

// Der Filter muss sich im Browser bedienen lassen. Die Seite schickt
// script-src 'self' ohne 'unsafe-inline', also führt der Browser kein
// onchange= im Dokument aus — ein Filter, dessen einziger Auslöser so ein
// Attribut ist, sieht fertig aus und tut nichts (gefunden in der Durchsicht).
$pruefe('Filter hat einen Knopf', preg_match('~name="gast".*?</form>~s', $slSeite, $f) === 1
    && str_contains($f[0], '<button'));
$pruefe('Filter braucht kein Skript im Dokument', !preg_match('~\son(change|click|submit)=~', $slSeite));
// Nach dem Hinzufügen eines Lieds muss die Auswahl stehen bleiben, sonst
// fängt man bei jedem Lied von vorn an.
$pruefe('Gefilterte Auswahl trägt den Gast mit',
    preg_match('~action="/intern/setlists/\d+/add".*?</form>~s', $slGefiltert, $a) === 1
    && str_contains($a[0], 'name="gast"'));

$ohneGaeste = $mitglied(['setlists' => [1, 1], 'songs' => [1, 1], 'gaeste' => [0, 0], 'termine' => [1, 1]]);
$slOhne = $ohneGaeste('/intern/setlists/' . $setliste);
$pruefe('Ohne Gästerecht kein Filter und kein Zeichen', !str_contains($slOhne, 'ZZGA Setlistengast'));
$pruefe('Ohne Gästerecht steht die Setliste trotzdem', str_contains($slOhne, 'ZZGA Lied B'));

q("DELETE FROM permissions WHERE user_id IN (SELECT id FROM users WHERE name = 'ZZGA Konto')");
q("DELETE FROM users WHERE name = 'ZZGA Konto'");
q('DELETE FROM setlist_songs WHERE setlist_id = ?', [$setliste]);
q('DELETE FROM setlists WHERE id IN (?, ?)', [$setliste, $setlisteAlt]);
q('DELETE FROM events WHERE id IN (?, ?)', [$termin2, $terminAlt]);

// ------------------------------------------------ 4b. Was beim Löschen bleibt
// Der Gast wird gelöscht, seine Antworten blieben als Waisen liegen — und die
// Datenschutzerklärung verspricht im selben Commit, dass sie mitgehen.
q("INSERT INTO guests (name, email) VALUES ('ZZGA Zu Loeschen', '')");
$gastWeg = (int) $GLOBALS['db']->lastInsertId();
guest_song_set($gastWeg, $liedA, 'yes');
$sieht2 = $mitglied($alleRechte);
$schicke('/intern/gaeste', '/intern/gaeste/' . $gastWeg . '/delete', [], $sidLetzte);
$pruefe('Gelöschter Gast nimmt seine Antworten mit',
    !row('SELECT 1 FROM guest_songs WHERE guest_id = ?', [$gastWeg]));

// „Antworten löschen" muss da sein, solange Zeilen da sind — auch wenn die
// beantworteten Lieder inzwischen alle im Archiv liegen.
$archiv = $buche('ZZGA Archivgast', ['show_songs' => 1]);
guest_song_set((int) $archiv['guest_id'], $liedA, 'yes');
q("UPDATE songs SET status = 'archiv' WHERE id = ?", [$liedA]);
$pruefe('Löschknopf bleibt bei archivierten Liedern',
    str_contains($sieht2('/intern/gaeste'), '/intern/gaeste/' . (int) $archiv['guest_id'] . '/antworten-loeschen'));
q("UPDATE songs SET status = 'aktiv' WHERE id = ?", [$liedA]);

// Was der Gast nach der Zusage liest, muss zu den Haken passen.
// Die Antwortseite selbst, nicht der spätere Aufruf: Der Dank steht nur
// direkt nach dem Zusagen, und genau dort zählte er auf, was es gar nicht
// mehr geben muss.
$ohneAlles = $buche('ZZGA Ohne Alles', ['show_rider' => 0, 'show_setlist' => 0, 'status' => 'angefragt']);
$dank = $schicke('/gast/' . $ohneAlles['token'], '/gast/' . $ohneAlles['token'] . '/antwort', ['antwort' => 'ja']);
$pruefe('Dank verspricht keinen Rider ohne Haken', !str_contains($dank, 'Rider'));
$pruefe('Dank verspricht keine Setliste ohne Haken', !str_contains($dank, 'Setliste'));

// ------------------------------------------------ 5. Doppelte Gäste
$pruefe('Drei Zeichen finden den Gast', (guests_by_name('ZZG')[0]['id'] ?? 0) > 0);
$pruefe('Mitten im Namen wird gefunden', (guests_by_name('etlisten')[0]['name'] ?? '') === 'ZZGA Setlistengast');
$pruefe('Groß und klein ist gleich', guests_by_name('zzga s') !== []);
$pruefe('Zwei Zeichen finden nichts', guests_by_name('ZZ') === []);
$pruefe('Leerzeichen zählen nicht als Zeichen', guests_by_name(' Z ') === []);
$pruefe('Prozentzeichen ist kein Platzhalter', guests_by_name('%%%') === []);
$pruefe('Unterstrich ist kein Platzhalter', guests_by_name('ZZG_') === []);
$pruefe('Höchstens fünf', count(guests_by_name('ZZGA', 5)) <= 5);

// Über die echte Route: Ein getippter Name, den es schon gibt, legt keinen
// zweiten Gast an.
$bucht = $mitglied($alleRechte);
$sidBucht = $sidLetzte;
$pruefe('Suchroute antwortet dem Berechtigten', str_contains($bucht('/intern/gaeste/suche?q=ZZGA'), 'ZZGA'));
$pruefe('Suchroute schweigt unter drei Zeichen', !str_contains($bucht('/intern/gaeste/suche?q=ZZ'), 'ZZGA'));
$ohneRecht = $mitglied(['setlists' => [1, 1], 'gaeste' => [0, 0], 'termine' => [1, 1]]);
$pruefe('Suchroute schweigt ohne Gästerecht', !str_contains($ohneRecht('/intern/gaeste/suche?q=ZZGA'), 'ZZGA'));

$vorher = (int) row("SELECT COUNT(*) AS n FROM guests WHERE name = 'ZZGA Setlistengast'")['n'];
$schicke('/intern/termine', '/intern/gaeste/buchen',
    ['event_id' => (string) $termin, 'new_name' => 'ZZGA Setlistengast', 'new_email' => 'zzga@example.invalid'],
    $sidBucht);
$pruefe('Getippter Name legt keinen zweiten Gast an',
    (int) row("SELECT COUNT(*) AS n FROM guests WHERE name = 'ZZGA Setlistengast'")['n'] === $vorher);
// Der Riegel darf nicht den gewöhnlichen Weg zumachen: Wer wirklich neu ist,
// wird weiter durch Tippen gebucht.
$schicke('/intern/termine', '/intern/gaeste/buchen',
    ['event_id' => (string) $termin, 'new_name' => 'ZZGA Ganz Neu', 'new_email' => 'zzga2@example.invalid'],
    $sidBucht);
$pruefe('Ein neuer Name legt sehr wohl an',
    (bool) row("SELECT 1 FROM guests WHERE name = 'ZZGA Ganz Neu'"));

// Zwei Leute dürfen gleich heißen: Wer bestätigt, dass es jemand anderes ist,
// bekommt die zweite Zeile — sonst wäre die Prüfung eine Mauer und die Band
// müsste den Namen falsch schreiben.
$schicke('/intern/termine', '/intern/gaeste/buchen',
    ['event_id' => (string) $termin, 'new_name' => 'ZZGA Setlistengast',
     'new_email' => 'zzga3@example.invalid', 'name_force' => '1'],
    $sidBucht);
$pruefe('Bestätigt legt den Namensvetter doch an',
    (int) row("SELECT COUNT(*) AS n FROM guests WHERE name = 'ZZGA Setlistengast'")['n'] === $vorher + 1);
$pruefe('Buchungsformular steht auch auf der Übersicht',
    str_contains($bucht('/intern'), 'gastsuche.js'));

q("DELETE FROM permissions WHERE user_id IN (SELECT id FROM users WHERE name = 'ZZGA Konto')");
q("DELETE FROM users WHERE name = 'ZZGA Konto'");

// Dieselbe Funktion wie die Löschroute — eine Abschrift hier prüfte nur sich
// selbst (#439).
foreach ([$liedA, $liedB, $liedV] as $l) song_delete($l);
$pruefe('Gelöschtes Lied nimmt die Antworten mit',
    !row('SELECT 1 FROM guest_songs WHERE song_id = ?', [$liedA]));

$weg();
q('DELETE FROM events WHERE id = ?', [$termin]);

printf("%s%d ok, %d Fehler%s", PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
