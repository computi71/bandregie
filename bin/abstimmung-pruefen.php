<?php
// Die Abstimmung über einen Liedvorschlag (#391) von allen Seiten.
//
// Der Grund für eine eigene Datei: Hier hängen drei Dinge aneinander, die
// einzeln harmlos aussehen — wer gefragt ist, wann die Abstimmung vorbei ist,
// und wann überhaupt eine beginnt. Läuft eines davon weg, merkt es niemand:
// Die Seite sieht richtig aus, nur wartet eine Abstimmung für immer auf
// jemanden, der gar nicht gefragt war — oder die sieben Vorschläge, die beide
// Bands mit sich herumtragen, verschicken plötzlich Aufgaben an alle.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/abstimmung-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/abstimmung-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$merkUmfang = (string) setting('song_vote_scope', 'mitglieder');

/** Ein frischer Vorschlag mit laufender Abstimmung. */
$vorschlag = static function (string $titel = 'ZZAB Vorschlag'): int {
    q('INSERT INTO songs (title, status) VALUES (?, ?)', [$titel, 'vorschlag']);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    song_vote_start($nr, 1);
    return $nr;
};
$weg = static function (int $nr): void {
    foreach (song_vote_tasks($nr) as $t) {
        q('DELETE FROM task_links WHERE task_id = ?', [$t]);
        q('DELETE FROM task_assignees WHERE task_id = ?', [$t]);
        q('DELETE FROM tasks WHERE id = ?', [$t]);
        item_forget('task', $t);
    }
    q('DELETE FROM song_votes WHERE song_id = ?', [$nr]);
    q('DELETE FROM songs WHERE id = ?', [$nr]);
    item_forget('song', $nr);
};
$stand = static fn(int $nr): string
    => (string) row('SELECT status FROM songs WHERE id = ?', [$nr])['status'];
$stimmt = static function (int $song, array $u, string $wie): void {
    q('INSERT INTO song_votes (song_id, user_id, status, updated_at) VALUES (?,?,?,NOW(3))
       ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW(3)',
      [$song, (int) $u['id'], $wie]);
};

// ------------------------------------------------ 1. Wer gefragt wird
set_setting('song_vote_scope', 'leitung');
$nurAdmins = song_vote_voters();
$pruefe('leitung: nur Admins', $nurAdmins && !array_filter($nurAdmins,
    static fn(array $u): bool => $u['role'] !== 'admin'));

set_setting('song_vote_scope', 'mitglieder');
$mitglieder = song_vote_voters();
$pruefe('mitglieder: keine Aushilfe dabei', !array_filter($mitglieder,
    static fn(array $u): bool => $u['role'] === 'ersatz'));
$pruefe('mitglieder: mindestens die Leitung', count($mitglieder) >= count($nurAdmins));

set_setting('song_vote_scope', 'alle');
$alle = song_vote_voters();
$pruefe('alle: mindestens so viele wie mitglieder', count($alle) >= count($mitglieder));
$pruefe('alle: nur, wer Lieder lesen darf', !array_filter($alle,
    static fn(array $u): bool => !perm_allows($u, 'songs')));

set_setting('song_vote_scope', 'unfug');
$pruefe('unbekannter Umfang faellt auf mitglieder zurueck',
    count(song_vote_voters()) === count($mitglieder));
set_setting('song_vote_scope', 'mitglieder');

// ------------------------------------------- 2. Eine Abstimmung beginnt
$v1 = $vorschlag();
$pruefe('ein neuer Vorschlag bekommt eine Aufgabe', count(song_vote_tasks($v1)) === 1);
$a1 = song_vote_tasks($v1)[0];
$pruefe('alle Gefragten sind zustaendig',
    count(task_assignees_map([$a1])[$a1] ?? []) === count(song_vote_voters()));
song_vote_start($v1, 1);
$pruefe('ein zweiter Start legt nichts Zweites an', count(song_vote_tasks($v1)) === 1);
$pruefe('die Aufgabe traegt die Marke',
    (int) row('SELECT is_song_vote FROM tasks WHERE id = ?', [$a1])['is_song_vote'] === 1);
$pruefe('und haengt am Lied', (int) row(
    "SELECT item_id FROM task_links WHERE task_id = ? AND kind = 'song'", [$a1])['item_id'] === $v1);

// --------------------------------------------- 3. Vollzaehlig beendet
$wer = song_vote_voters();
foreach (array_slice($wer, 0, count($wer) - 1) as $i => $u) {
    $stimmt($v1, $u, ['yes', 'maybe', 'no'][$i % 3]);
}
song_vote_apply($v1);
$pruefe('einer fehlt: noch Vorschlag', $stand($v1) === 'vorschlag');
$stimmt($v1, $wer[count($wer) - 1], 'yes');
song_vote_apply($v1);
$pruefe('vollzaehlig: abgestimmt', $stand($v1) === 'abgestimmt');
$pruefe('die Aufgabe ist erledigt',
    (string) row('SELECT status FROM tasks WHERE id = ?', [$a1])['status'] === 'erledigt');
song_vote_apply($v1);
$pruefe('ein zweiter Durchlauf aendert nichts', $stand($v1) === 'abgestimmt');
$weg($v1);

// ------------------------------------------------------ 4. Die Frist
$v2 = $vorschlag('ZZAB Frist');
q('UPDATE tasks SET due_date = ? WHERE id = ?',
  [date('Y-m-d', strtotime('+1 day')), song_vote_tasks($v2)[0]]);
song_vote_apply($v2);
$pruefe('Frist morgen, niemand gestimmt: offen', $stand($v2) === 'vorschlag');
q('UPDATE tasks SET due_date = ? WHERE id = ?',
  [date('Y-m-d', strtotime('-1 day')), song_vote_tasks($v2)[0]]);
$stimmt($v2, song_vote_voters()[0], 'yes');
song_vote_apply($v2);
$pruefe('Frist gestern, einer gestimmt: abgestimmt', $stand($v2) === 'abgestimmt');
$pruefe('die eine Stimme steht noch da', count(song_votes($v2)) === 1);
$weg($v2);

$v3 = $vorschlag('ZZAB Ohne Frist');
song_vote_apply($v3);
$pruefe('ohne Frist wartet sie', $stand($v3) === 'vorschlag');
$pruefe('und song_vote_deadline sagt das auch', song_vote_deadline($v3) === '');
$weg($v3);

// ------------------- 5. Wer wegfaellt, darf nichts haengen lassen
// Dieselbe Lehre wie #334: Gezaehlt wird gegen die Gefragten von HEUTE und
// nicht gegen eine Zahl, die beim Anlegen festgeschrieben wurde.
$v4 = $vorschlag('ZZAB Weggefallen');
$wer = song_vote_voters();
foreach (array_slice($wer, 0, count($wer) - 1) as $u) $stimmt($v4, $u, 'yes');
$fehlt = $wer[count($wer) - 1];
song_vote_apply($v4);
$pruefe('einer schweigt: haengt', $stand($v4) === 'vorschlag');
q('UPDATE users SET role = ? WHERE id = ?', ['ersatz', (int) $fehlt['id']]);
song_vote_apply($v4);
$pruefe('er wird Aushilfe: nicht mehr gefragt, also fertig', $stand($v4) === 'abgestimmt');
q('UPDATE users SET role = ? WHERE id = ?', ['member', (int) $fehlt['id']]);
$weg($v4);

// ---------------------------------------- 6. Wer abstimmen darf
$v5 = $vorschlag('ZZAB Rechte');
$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
$admin = row("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
$pruefe('ein Admin darf', song_may_vote($admin, $v5));
if ($aushilfe) {
    $pruefe('eine Aushilfe darf nicht (mitglieder)', !song_may_vote($aushilfe, $v5));
    set_setting('song_vote_scope', 'alle');
    $pruefe('unter "alle" haengt es nur noch an der Sichtbarkeit',
        song_may_vote($aushilfe, $v5) === may_see_song($aushilfe, $v5));
    set_setting('song_vote_scope', 'mitglieder');
} else {
    echo 'keine Aushilfe vorhanden - Rechtepruefung uebersprungen', PHP_EOL;
}
$weg($v5);

// ----------------- 7. Was schon entschieden ist, wird nicht angefasst
$v6 = $vorschlag('ZZAB Fertig');
q("UPDATE songs SET status = 'abgestimmt' WHERE id = ?", [$v6]);
foreach (song_vote_voters() as $u) $stimmt($v6, $u, 'yes');
song_vote_apply($v6);
$pruefe('abgestimmt bleibt abgestimmt', $stand($v6) === 'abgestimmt');
q("UPDATE songs SET status = 'aktiv' WHERE id = ?", [$v6]);
song_vote_apply($v6);
$pruefe('ein aktives Lied wird nicht zurueckgestuft', $stand($v6) === 'aktiv');
$weg($v6);

// --------------------------------------------- 8. Der neue Status
$pruefe("'abgestimmt' steht in SONG_STATUS", array_key_exists('abgestimmt', SONG_STATUS));
$pruefe('und hat eine Beschriftung', song_status_label('abgestimmt') !== 'abgestimmt');
$pruefe('es steht hinter dem Vorschlag',
    array_search('abgestimmt', array_keys(SONG_STATUS), true)
    === array_search('vorschlag', array_keys(SONG_STATUS), true) + 1);

// ------------- 9. Ein geloeschtes Lied laesst nichts Offenes zurueck
// Das ist genau einmal passiert, beim Bauen: Lied weg, Aufgabe blieb - offen,
// an allen acht Mitgliedern, zeigend auf nichts. Erledigen liess sie sich
// nicht, denn es gab nichts mehr, worueber man abstimmen konnte.
$v7 = $vorschlag('ZZAB Zu loeschen');
$a7 = song_vote_tasks($v7)[0];
$weg($v7);
$pruefe('mit dem Lied geht die Abstimmungsaufgabe',
    !row('SELECT id FROM tasks WHERE id = ?', [$a7]));
$pruefe('und ihre Zustaendigen',
    (int) row('SELECT COUNT(*) n FROM task_assignees WHERE task_id = ?', [$a7])['n'] === 0);
$pruefe('keine Abstimmungsaufgabe ohne Lied',
    (int) row("SELECT COUNT(*) n FROM tasks t
               WHERE t.is_song_vote = 1 AND NOT EXISTS (
                 SELECT 1 FROM task_links tl JOIN songs s ON s.id = tl.item_id
                 WHERE tl.task_id = t.id AND tl.kind = 'song')")['n'] === 0);

// Nichts stehen lassen.
set_setting('song_vote_scope', $merkUmfang);
$reste = (int) row('SELECT COUNT(*) n FROM songs WHERE title LIKE ?', ['ZZAB%'])['n'];
$pruefe('keine Testdaten zurueckgelassen', $reste === 0);
$pruefe('die Einstellung steht wieder, wie sie stand',
    (string) setting('song_vote_scope', 'mitglieder') === $merkUmfang);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
