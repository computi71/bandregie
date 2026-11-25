<?php
// Eine Absage aus der Band setzt „Findet statt“ zurück und steht im Kopf (#442).
//
// Die Ausnahmen sind genauso wichtig wie die Regel: Ein Gig, der auf
// „Angefragt“ zurückfällt, verschwindet von der öffentlichen Seite. Deshalb
// steht jede Ausnahme als eigener Fall hier.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/absage-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/absage-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-62s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

$zukunft = date('Y-m-d', strtotime('+60 days'));
$gestern = date('Y-m-d', strtotime('-1 day'));
$termine = [];
$konten = [];

$konto = static function (string $name, string $rolle, int $buehne = 1, ?int $fuer = null) use (&$konten): int {
    q('INSERT INTO users (name, email, password_hash, role, on_stage, substitute_for) VALUES (?,?,?,?,?,?)',
      [$name, strtolower(str_replace(' ', '', $name)) . '@example.invalid', '', $rolle, $buehne, $fuer]);
    return $konten[] = (int) $GLOBALS['db']->lastInsertId();
};
$termin = static function (string $art, string $stand = 'bestaetigt', ?string $tag = null) use (&$termine, $zukunft): int {
    q('INSERT INTO events (type, title, date, status, created_by) VALUES (?,?,?,?,1)',
      [$art, 'ZZAB ' . $art, $tag ?? $zukunft, $stand]);
    return $termine[] = (int) $GLOBALS['db']->lastInsertId();
};
$antwortet = static function (int $ev, int $wer, string $wie): void {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status)', [$ev, $wer, $wie]);
};
/** Antwort wie über die Zusage-Route: erst schreiben, bei „nein“ zurücksetzen. */
$sagt = static function (int $ev, int $wer, string $wie) use ($antwortet): void {
    $antwortet($ev, $wer, $wie);
    if ($wie === 'no') event_dropout_reset($ev, $wer);
};
$stand = static fn(int $ev): string
    => (string) (row('SELECT status FROM events WHERE id = ?', [$ev])['status'] ?? '');

$mitglied = $konto('ZZAB Mitglied', 'member');
$aushilfe = $konto('ZZAB Aushilfe', 'ersatz', 1, $mitglied);
$technik = $konto('ZZAB Technik', 'member', 0);

// ------------------------------------------- 1. Die Regel
$party = $termin('party');
$sagt($party, $mitglied, 'no');
$pruefe('Party, Mitglied sagt ab: zurück auf „Angefragt“', $stand($party) === 'angefragt');
$pruefe('und die Absage steht mit Namen da',
    (event_dropouts([$party])[$party][$mitglied] ?? '') === 'ZZAB Mitglied');
$probe = $termin('probe');
$sagt($probe, $mitglied, 'no');
$pruefe('Probe, Mitglied sagt ab: zurück auf „Angefragt“', $stand($probe) === 'angefragt');

// ------------------------------------------- 2. Was bleibt
foreach (['gig', 'aufbau', 'reise'] as $art) {
    $e = $termin($art);
    $sagt($e, $mitglied, 'no');
    $pruefe("$art, Mitglied sagt ab: bleibt „Findet statt“", $stand($e) === 'bestaetigt');
}
$vorbei = $termin('party', 'bestaetigt', $gestern);
$sagt($vorbei, $mitglied, 'no');
$pruefe('vergangene Party: bleibt', $stand($vorbei) === 'bestaetigt');
$reserviert = $termin('party', 'reserviert');
$sagt($reserviert, $mitglied, 'no');
$pruefe('„Reserviert“ bleibt „Reserviert“', $stand($reserviert) === 'reserviert');
$vielleicht = $termin('party');
$sagt($vielleicht, $mitglied, 'maybe');
$pruefe('„vielleicht“ ändert nichts', $stand($vielleicht) === 'bestaetigt');
$ersatz = $termin('party');
$sagt($ersatz, $aushilfe, 'no');
$pruefe('Aushilfe sagt ab: bleibt', $stand($ersatz) === 'bestaetigt');
$pruefe('und steht nicht als Absage der Band da', !isset(event_dropouts([$ersatz])[$ersatz]));
$technikProbe = $termin('probe');
$sagt($technikProbe, $technik, 'no');
$pruefe('nicht auf der Bühne, Probe: bleibt', $stand($technikProbe) === 'bestaetigt');
$technikParty = $termin('party');
$sagt($technikParty, $technik, 'no');
$pruefe('nicht auf der Bühne, Party: zurückgesetzt', $stand($technikParty) === 'angefragt');

// ------------------------------------------- 3. Der Kopf
$kopf = static function (int $nr, array $daten): string {
    $ev = row('SELECT * FROM events WHERE id = ?', [$nr]);
    $venue = null;
    extract($daten);
    ob_start();
    require BASE_DIR . '/app/views/intern/_event_kopf.php';
    return (string) ob_get_clean();
};
$admin = row("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$sicht = event_view_data([row('SELECT * FROM events WHERE id = ?', [$party])], $admin);
$html = $kopf($party, $sicht);
$pruefe('der Kopf zeigt die Absage', str_contains($html, 'ev-dropout')
    && str_contains($html, 'ZZAB Mitglied'));
$gig = $termine[2];
$html = $kopf($gig, event_view_data([row('SELECT * FROM events WHERE id = ?', [$gig])], $admin));
$pruefe('auch beim Gig, der „Findet statt“ behält',
    str_contains($html, 'ev-dropout') && str_contains($html, 'ev-bestaetigt'));
q("UPDATE events SET status = 'abgesagt' WHERE id = ?", [$party]);
$html = $kopf($party, event_view_data([row('SELECT * FROM events WHERE id = ?', [$party])], $admin));
$pruefe('beim abgesagten Termin keine Marke', !str_contains($html, 'ev-dropout'));
$html = $kopf($vielleicht, event_view_data([row('SELECT * FROM events WHERE id = ?', [$vielleicht])], $admin));
$pruefe('ohne Absage keine Marke', !str_contains($html, 'ev-dropout'));

// ------------------------------------------- 4. Verdrahtung
$quelle = (string) file_get_contents($basis . '/httpdocs/index.php');
$pruefe('die Zusage-Route setzt bei „nein“ zurück', (bool) preg_match(
    "~if \\(\\\$status === 'no'\\) \\{[^}]*event_dropout_reset\\(\\(int\\) \\\$id, \\(int\\) \\\$me\\['id'\\]\\);~s",
    $quelle));
$pruefe('das Mitteilungsthema ist abwählbar', in_array('wackelt', PUSH_TOPICS, true));

// ------------------------------------------- 5. Bestand (Migration)
$alt = $termin('party');
$antwortet($alt, $mitglied, 'no');
$altGig = $termin('gig');
$antwortet($altGig, $mitglied, 'no');
$altVorbei = $termin('party', 'bestaetigt', $gestern);
$antwortet($altVorbei, $mitglied, 'no');
// Der Schema-Lauf in einem eigenen Prozess, wie nach einem Update: Hier ist
// schema.php schon geladen, und ein zweites Laden scheiterte an seinen
// Funktionen.
set_setting('migr_dropout_reset', '');
q("UPDATE settings SET value = '0' WHERE `key` = 'schema_version'");
$lauf = sprintf('chdir(%s); require %s;', var_export($basis, true),
    var_export($basis . '/app/bootstrap.php', true));
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($lauf) . ' 2>&1', $ausgabe, $rc);
$pruefe('Migration: der Schema-Lauf geht durch', $rc === 0);
settings_forget();
$pruefe('Migration: alte Absage setzt die Party zurück', $stand($alt) === 'angefragt');
$pruefe('Migration: der Gig bleibt', $stand($altGig) === 'bestaetigt');
$pruefe('Migration: Vergangenes bleibt', $stand($altVorbei) === 'bestaetigt');
$pruefe('Migration: die Marke ist gesetzt', setting('migr_dropout_reset') !== '');

// Nichts stehen lassen.
foreach ($termine as $nr) {
    q('DELETE FROM attendance WHERE event_id = ?', [$nr]);
    q('DELETE FROM events WHERE id = ?', [$nr]);
    item_forget('event', $nr);
}
foreach ($konten as $nr) q('DELETE FROM users WHERE id = ?', [$nr]);
$reste = (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZAB%'")['n']
       + (int) row("SELECT COUNT(*) n FROM users WHERE name LIKE 'ZZAB%'")['n'];
$pruefe('keine Testdaten zurückgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
