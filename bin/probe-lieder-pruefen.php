<?php
// Lieder, die direkt an einer Probe hängen (#390), und was daran alles hängt.
//
// Der Grund für eine eigene Datei: Ein Lied erreicht einen Termin seit #390 auf
// zwei Wegen — über die Setlist beim Auftritt, direkt bei der Probe. Die
// Sichtbarkeit, das Offline-Paket und der Teleprompter mussten das lernen, und
// wer einen dieser drei beim nächsten Umbau vergisst, merkt es nicht: Die Seite
// sieht richtig aus, nur sind im Proberaum die Texte weg oder eine Aushilfe
// sieht Lieder, die sie nichts angehen.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/probe-lieder-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Mit einem Hostnamen als drittem Wort werden die beiden Vollbildseiten auch
// wirklich abgerufen:
//
//   ... --schreibt-testdaten staging.beatcuisine.de
//
// Das ist kein Beiwerk. #395 lag genau dort: Die Adresse stand im Paket, die
// Seite dahinter lieferte den Liedtext statt der Akkorde, und eine Prüfung,
// die nur Adressen zählt, sah nichts davon.
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/probe-lieder-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$lieder = rows('SELECT id, title FROM songs ORDER BY id LIMIT 3');
if (count($lieder) < 3) { fwrite(STDERR, "Drei Lieder werden gebraucht\n"); exit(2); }
[$a, $b, $c] = array_map(static fn(array $s): int => (int) $s['id'], $lieder);

/** Einen Termin anlegen und wieder loswerden. */
$termin = static function (string $art, array $songIds): int {
    q('INSERT INTO events (type, title, date, status) VALUES (?, "ZZPL Pruefung", "2027-12-01", "bestaetigt")', [$art]);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    foreach ($songIds as $s) q('INSERT IGNORE INTO event_songs (event_id, song_id) VALUES (?,?)', [$nr, $s]);
    return $nr;
};
$weg = static function (int $nr): void {
    q('DELETE FROM event_songs WHERE event_id = ?', [$nr]);
    q('DELETE FROM substitute_requests WHERE event_id = ?', [$nr]);
    q('DELETE FROM events WHERE id = ?', [$nr]);
    item_forget('event', $nr);
};

// ------------------------------------------------ 1. Die Zuordnung selbst
$p1 = $termin('probe', [$a, $b]);
$pruefe('eine Probe traegt ihre Lieder', count(event_songs($p1)) === 2);
$pruefe('nach Titel sortiert, nicht nach Nummer',
    array_column(event_songs($p1), 'title') === (function () use ($a, $b) {
        $t = array_column(rows('SELECT title FROM songs WHERE id IN (?,?) ORDER BY title', [$a, $b]), 'title');
        return $t;
    })());
$pruefe('event_songs_map liefert dieselbe Liste',
    count(event_songs_map([$p1])[$p1] ?? []) === 2);

// ------------------------------- 2. Nur Arten, die Lieder anbieten, bekommen sie
$pruefe('die Probe bietet das Feld an', in_array('songs', EVENT_TYPE_FIELDS['probe'], true));
$pruefe('der Auftritt bietet es nicht an', !in_array('songs', EVENT_TYPE_FIELDS['gig'], true));
$pruefe('der Auftritt hat weiterhin die Setlist', in_array('setlist', EVENT_TYPE_FIELDS['gig'], true));
$pruefe('die Probe hat keine Setlist mehr', !in_array('setlist', EVENT_TYPE_FIELDS['probe'], true));

// --------------------------------------- 3. Sichtbarkeit fuer eine Aushilfe
// Das ist die Stelle, die still bricht: Ohne den zweiten Weg in
// visible_song_ids() koennte eine Aushilfe ausgerechnet die Lieder nicht
// oeffnen, zu deren Probe sie gebeten wurde.
$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
if ($aushilfe) {
    $pruefe('Aushilfe sieht die fremde Probe nicht', !may_see_event($aushilfe, $p1));
    $pruefe('und deren Lieder auch nicht', !may_see_song($aushilfe, $a));

    q('INSERT IGNORE INTO substitute_requests (event_id, user_id) VALUES (?,?)', [$p1, (int) $aushilfe['id']]);
    $pruefe('gebeten: sieht den Termin', may_see_event($aushilfe, $p1));
    $pruefe('gebeten: sieht das erste Lied', may_see_song($aushilfe, $a));
    $pruefe('gebeten: sieht das zweite Lied', may_see_song($aushilfe, $b));
    $pruefe('aber nicht ein Lied, das nicht dranhaengt', !may_see_song($aushilfe, $c));
    q('DELETE FROM substitute_requests WHERE event_id = ? AND user_id = ?', [$p1, (int) $aushilfe['id']]);
    $pruefe('Anfrage zurueckgezogen: sieht es wieder nicht', !may_see_song($aushilfe, $a));
} else {
    echo 'keine Aushilfe vorhanden - Sichtbarkeitspruefung uebersprungen', PHP_EOL;
}

// -------------------------------------------------- 4. Das Offline-Paket
$ev = row('SELECT * FROM events WHERE id = ?', [$p1]);
$urls = event_offline_urls($ev);
foreach ([$a, $b] as $s) {
    $pruefe("Offline: Lied $s ist dabei", in_array('/intern/songs/' . $s, $urls, true));
    $pruefe("Offline: Buehne von $s mit ?ev", in_array("/intern/songs/$s/buehne?ev=$p1", $urls, true));
    $pruefe("Offline: Noten von $s mit ?ev", in_array("/intern/songs/$s/noten?ev=$p1", $urls, true));
}
$pruefe('Offline: keine Adresse doppelt', count($urls) === count(array_unique($urls)));

// ------------------------------- 4b. Was hinter diesen Adressen steht
// Bühne und Noten sehen im Quelltext gleich aus und sind es nicht: Die eine
// zeigt den Text, die andere die Notizzettel. Abgeschrieben wurde der falsche
// Zweig, und niemandem fiel es auf, weil die Adresse ja stimmte (#395).
$host = $argv[3] ?? '';
if ($host !== '') {
    $_SESSION['uid'] = (int) row('SELECT id FROM users ORDER BY id LIMIT 1')['id'];
    $sid = session_id();
    session_write_close();
    $hol = static function (string $pfad) use ($host, $sid): string {
        return (string) shell_exec(sprintf(
            'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s https://%s%s',
            escapeshellarg($host), escapeshellarg($sid), escapeshellarg($host), $pfad));
    };
    $buehne = $hol("/intern/songs/$a/buehne?ev=$p1");
    $noten  = $hol("/intern/songs/$a/noten?ev=$p1");
    $pruefe('Buehne ueber die Probe: Seite kommt ganz', str_ends_with(rtrim($buehne), '</html>'));
    $pruefe('Noten ueber die Probe: Seite kommt ganz', str_ends_with(rtrim($noten), '</html>'));
    $pruefe('Buehne ueber die Probe traegt den Text', str_contains($buehne, '&quot;lines&quot;'));
    $pruefe('Noten ueber die Probe tragen die Notizzettel', str_contains($noten, '&quot;musicians&quot;'));
    $pruefe('und nicht den Text an deren Stelle',
        !preg_match('~data-songs="\[\{[^"]*&quot;lines&quot;~', $noten));
} else {
    echo 'kein Host angegeben - die beiden Vollbildseiten wurden nicht abgerufen', PHP_EOL;
}

$weg($p1);

// ------------------- 5. Ein Auftritt mit Setlist bleibt, wie er war
$sl = null;
q('INSERT INTO setlists (name, notes) VALUES ("ZZPL Setlist", "")');
$sl = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?,?,1)', [$sl, $c]);
q('INSERT INTO events (type, title, date, status, setlist_id) VALUES ("gig", "ZZPL Gig", "2027-12-02", "bestaetigt", ?)', [$sl]);
$g = (int) $GLOBALS['db']->lastInsertId();
$gUrls = event_offline_urls(row('SELECT * FROM events WHERE id = ?', [$g]));
$pruefe('Auftritt: die Setlist ist im Paket', in_array('/intern/setlists/' . $sl, $gUrls, true));
$pruefe('Auftritt: Buehne weiterhin mit ?sl', in_array("/intern/songs/$c/buehne?sl=$sl", $gUrls, true));
$pruefe('Auftritt: kein ?ev dazwischen',
    !array_filter($gUrls, static fn(string $u): bool => str_contains($u, 'ev=' . $g)));
q('DELETE FROM events WHERE id = ?', [$g]);
q('DELETE FROM setlist_songs WHERE setlist_id = ?', [$sl]);
q('DELETE FROM setlists WHERE id = ?', [$sl]);
item_forget('event', $g);

// ------------------------------- 6. Ein Termin, an dem beides haengt
// Eine Terminart bietet nur einen Weg an, aber ein Artwechsel laesst stehen,
// was dranhing. Dann muessen beide Seiten im Paket landen - sonst fehlte
// ausgerechnet das, was jemand vorher eingetragen hatte.
q('INSERT INTO setlists (name, notes) VALUES ("ZZPL Beides", "")');
$sl2 = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?,?,1)', [$sl2, $c]);
q('INSERT INTO events (type, title, date, status, setlist_id) VALUES ("probe", "ZZPL Beides", "2027-12-03", "bestaetigt", ?)', [$sl2]);
$p2 = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO event_songs (event_id, song_id) VALUES (?,?)', [$p2, $a]);
$bUrls = event_offline_urls(row('SELECT * FROM events WHERE id = ?', [$p2]));
$pruefe('beides dran: das Lied der Probe ist da', in_array('/intern/songs/' . $a, $bUrls, true));
$pruefe('beides dran: das Lied der Setlist auch', in_array('/intern/songs/' . $c, $bUrls, true));
$pruefe('beides dran: keine Adresse doppelt', count($bUrls) === count(array_unique($bUrls)));
q('DELETE FROM event_songs WHERE event_id = ?', [$p2]);
q('DELETE FROM events WHERE id = ?', [$p2]);
q('DELETE FROM setlist_songs WHERE setlist_id = ?', [$sl2]);
q('DELETE FROM setlists WHERE id = ?', [$sl2]);
item_forget('event', $p2);

// Nichts stehen lassen.
$reste = (int) row('SELECT COUNT(*) n FROM events WHERE title LIKE ?', ['ZZPL%'])['n']
       + (int) row('SELECT COUNT(*) n FROM setlists WHERE name LIKE ?', ['ZZPL%'])['n'];
$pruefe('keine Testdaten zurueckgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
