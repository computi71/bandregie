<?php
// Die Übungsaufgabe für Lieder in Vorbereitung (#439) von allen Seiten.
//
// Hier zählt der Wechsel, nicht der Stand: Eine Aufgabe beginnt, wenn ein Lied
// in die Vorbereitung KOMMT, und endet, wenn es sie VERLÄSST. Liefe das auf den
// Stand hinaus, bekäme am Tag des Updates jedes Lied, das schon geprobt wird,
// eine Aufgabe an alle — und jedes Speichern eine weitere.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/uebung-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/uebung-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

/** Ein Lied anlegen, wie die Route es tut: speichern, dann den Wechsel melden. */
$neu = static function (string $titel, string $status): int {
    q('INSERT INTO songs (title, status) VALUES (?, ?)', [$titel, $status]);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    song_status_changed($nr, '', 1);
    return $nr;
};
/** Den Status ändern, wie die Route es tut: Stand davor merken. */
$stelle = static function (int $nr, string $status): void {
    $vorher = (string) row('SELECT status FROM songs WHERE id = ?', [$nr])['status'];
    q('UPDATE songs SET status = ? WHERE id = ?', [$status, $nr]);
    song_status_changed($nr, $vorher, 1);
};
$stand = static fn(int $aufgabe): string
    => (string) (row('SELECT status FROM tasks WHERE id = ?', [$aufgabe])['status'] ?? '');
$weg = static function (int $nr): void {
    song_tasks_delete($nr);
    q('DELETE FROM song_votes WHERE song_id = ?', [$nr]);
    q('DELETE FROM songs WHERE id = ?', [$nr]);
    item_forget('song', $nr);
};

// ------------------------------------------------ 1. Wer üben muss
$spieler = song_practice_players();
$pruefe('Spielende: mindestens einer', count($spieler) > 0);
$pruefe('Spielende: keine Aushilfe dabei', !array_filter($spieler,
    static fn(array $u): bool => $u['role'] === 'ersatz'));

// ------------------------------------------ 2. Eine Übung beginnt
$l1 = $neu('ZZUE Neu in Vorbereitung', 'in_arbeit');
$pruefe('neu in Vorbereitung: genau eine Übungsaufgabe', count(song_practice_tasks($l1)) === 1);
$a1 = song_practice_tasks($l1)[0];
$pruefe('sie ist offen', $stand($a1) === 'offen');
$pruefe('sie trägt die Marke',
    (int) row('SELECT is_song_practice FROM tasks WHERE id = ?', [$a1])['is_song_practice'] === 1);
$pruefe('und keine Abstimmungsmarke',
    (int) row('SELECT is_song_vote FROM tasks WHERE id = ?', [$a1])['is_song_vote'] === 0);
$pruefe('sie hängt am Lied', (int) row(
    "SELECT item_id FROM task_links WHERE task_id = ? AND kind = 'song'", [$a1])['item_id'] === $l1);
$pruefe('alle Spielenden sind zuständig',
    count(task_assignees_map([$a1])[$a1] ?? []) === count($spieler));
$pruefe('und alle müssen abhaken',
    (int) row('SELECT required_done FROM tasks WHERE id = ?', [$a1])['required_done'] === 0);
$pruefe('sie steht am Lied unter „Noch zu tun"',
    in_array($a1, array_map(static fn(array $z): int => (int) $z['id'],
        tasks_for_item('song', [$l1])[$l1] ?? []), true));

// --------------------------------------- 3. Nichts doppelt anlegen
$stelle($l1, 'in_arbeit');
$pruefe('erneut gespeichert: weiter genau eine', count(song_practice_tasks($l1)) === 1);
song_practice_start($l1, 1);
$pruefe('ein zweiter Start legt nichts Zweites an', count(song_practice_tasks($l1)) === 1);

// ------------------------------------------------ 4. Abhaken
if (count($spieler) > 1) {
    q('UPDATE task_assignees SET done_at = NOW(3) WHERE task_id = ? AND user_id = ?',
      [$a1, (int) $spieler[0]['id']]);
    task_status_apply($a1);
    $pruefe('einer kann es: noch offen', $stand($a1) === 'offen');
} else {
    echo 'nur ein Spielender - Teilabhaken übersprungen', PHP_EOL;
}

// --------------------------- 5. Aktiv: die Übung ist vorbei
$stelle($l1, 'aktiv');
$pruefe('aktiv: die Übungsaufgabe ist erledigt', $stand($a1) === 'erledigt');
$pruefe('und steht nicht mehr unter „Noch zu tun"', !(tasks_for_item('song', [$l1])[$l1] ?? []));
if (count($spieler) > 1) {
    $pruefe('wer abgehakt hatte, bleibt abgehakt', row(
        'SELECT done_at FROM task_assignees WHERE task_id = ? AND user_id = ?',
        [$a1, (int) $spieler[0]['id']])['done_at'] !== null);
}

// ------------------------------- 6. Zurück in die Vorbereitung
$stelle($l1, 'in_arbeit');
$pruefe('wieder in Vorbereitung: eine neue, offene Aufgabe',
    count(song_practice_tasks($l1, true)) === 1 && count(song_practice_tasks($l1)) === 2);
$a1b = song_practice_tasks($l1, true)[0];

// ----------------------- 7. Ins Archiv: auch dann ist sie vorbei
$stelle($l1, 'archiv');
$pruefe('aussortiert: die Übungsaufgabe ist erledigt', $stand($a1b) === 'erledigt');
$stelle($l1, 'demo');
$pruefe('ein anderer Wechsel legt nichts an', count(song_practice_tasks($l1)) === 2);

// ------------------- 8. Was schon in Vorbereitung war, bleibt in Ruhe
q('INSERT INTO songs (title, status) VALUES (?, ?)', ['ZZUE Schon da', 'in_arbeit']);
$l2 = (int) $db->lastInsertId();
$stelle($l2, 'in_arbeit');
$pruefe('Bestand in Vorbereitung: keine Aufgabe beim Speichern', !song_practice_tasks($l2));
$stelle($l2, 'aktiv');
$pruefe('und aktiv gestellt: weiterhin keine', !song_practice_tasks($l2));

// --------------- 9. Von Hand angelegte Aufgaben fasst niemand an
q('INSERT INTO songs (title, status) VALUES (?, ?)', ['ZZUE Handarbeit', 'in_arbeit']);
$l3 = (int) $db->lastInsertId();
q("INSERT INTO tasks (title, notes, due_date, status, created_by) VALUES ('ZZUE Noten besorgen', '', '', 'offen', 1)");
$hand = (int) $db->lastInsertId();
q("INSERT INTO task_links (task_id, kind, item_id) VALUES (?, 'song', ?)", [$hand, $l3]);
$stelle($l3, 'aktiv');
$pruefe('eine Aufgabe von Hand bleibt offen, wenn das Lied aktiv wird', $stand($hand) === 'offen');
$weg($l3);
$pruefe('und überlebt das Löschen des Liedes', $stand($hand) === 'offen');
q('DELETE FROM task_links WHERE task_id = ?', [$hand]);
q('DELETE FROM tasks WHERE id = ?', [$hand]);

// ----------------- 10. Die Abstimmung geht denselben Weg wie vorher
$l4 = $neu('ZZUE Vorschlag', 'vorschlag');
$pruefe('ein neuer Vorschlag bekommt seine Abstimmung', count(song_vote_tasks($l4)) === 1);
$pruefe('aber keine Übung', !song_practice_tasks($l4));
$stelle($l4, 'in_arbeit');
$pruefe('Vorschlag -> Vorbereitung: die Übung beginnt', count(song_practice_tasks($l4, true)) === 1);

// ------------- 11. Ein gelöschtes Lied lässt nichts Offenes zurück
$geloescht = [...song_practice_tasks($l1), ...song_practice_tasks($l4), ...song_vote_tasks($l4)];
$weg($l1);
$weg($l4);
$in = implode(',', $geloescht);
$pruefe('mit dem Lied gehen seine Übungs- und Abstimmungsaufgaben',
    (int) row("SELECT COUNT(*) n FROM tasks WHERE id IN ($in)")['n'] === 0);
$pruefe('und ihre Zuständigen',
    (int) row("SELECT COUNT(*) n FROM task_assignees WHERE task_id IN ($in)")['n'] === 0);
$pruefe('keine Übungsaufgabe ohne Lied',
    (int) row("SELECT COUNT(*) n FROM tasks t
               WHERE t.is_song_practice = 1 AND NOT EXISTS (
                 SELECT 1 FROM task_links tl JOIN songs s ON s.id = tl.item_id
                 WHERE tl.task_id = t.id AND tl.kind = 'song')")['n'] === 0);

// -------------------------------------------- 12. Texte und Route
$pruefe('der Aufgabentitel ist übersetzt', t('song_practice_task') !== 'song_practice_task');
$pruefe('der Titel nennt das Lied', str_contains(t('song_practice_task'), '%1'));
$quelle = (string) file_get_contents($basis . '/httpdocs/index.php');
$pruefe('Anlegen meldet den Wechsel',
    str_contains($quelle, "song_status_changed(\$songNeu, '', (int) \$me['id'])"));
$pruefe('Speichern meldet ihn mit dem Stand davor',
    str_contains($quelle, "song_status_changed((int) \$m[1], \$songVorher, (int) \$me['id'])"));
$pruefe('Löschen räumt die Liedaufgaben ab',
    str_contains($quelle, 'song_tasks_delete((int) $m[1])'));

// Nichts stehen lassen.
$weg($l2);
$reste = (int) row('SELECT COUNT(*) n FROM songs WHERE title LIKE ?', ['ZZUE%'])['n']
       + (int) row('SELECT COUNT(*) n FROM tasks WHERE title LIKE ?', ['ZZUE%'])['n'];
$pruefe('keine Testdaten zurückgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
