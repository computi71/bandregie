<?php
// Abgelaufene Liedaufgaben schließen sich einmal am Tag (#441).
//
// Die Regel ist eng, und das Enge ist der Wert: Was sie schließt, muss
// wirklich vorbei sein. Jede Ausnahme steht deshalb als eigener Fall hier —
// eine Aufgabe, die zu Unrecht verschwindet, merkt niemand, weil man nach
// Erledigtem nicht sucht.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/abgelaufen-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/abgelaufen-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$gestern = date('Y-m-d', strtotime('-1 day'));
$heute = date('Y-m-d');
$lieder = [];
$aufgaben = [];

$lied = static function (string $status) use (&$lieder): int {
    q('INSERT INTO songs (title, status) VALUES (?, ?)', ['ZZAL ' . $status, $status]);
    return $lieder[] = (int) $GLOBALS['db']->lastInsertId();
};
/** Eine Aufgabe von Hand, verknüpft mit [Sorte, Nummer]-Paaren. */
$aufgabe = static function (string $faellig, array $bezuege, int $uebung = 0) use (&$aufgaben): int {
    q("INSERT INTO tasks (title, notes, due_date, status, created_by, is_song_practice)
       VALUES ('ZZAL Aufgabe', '', ?, 'offen', 1, ?)", [$faellig, $uebung]);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    foreach ($bezuege as [$sorte, $id]) {
        q('INSERT INTO task_links (task_id, kind, item_id) VALUES (?, ?, ?)', [$nr, $sorte, $id]);
    }
    return $aufgaben[] = $nr;
};
$stand = static fn(int $nr): string
    => (string) (row('SELECT status FROM tasks WHERE id = ?', [$nr])['status'] ?? '');

// --------------------------------------------------- Aufbau
$aktiv = $lied('aktiv');
$aktiv2 = $lied('aktiv');
$probe = $lied('in_arbeit');
$termin = (int) (row('SELECT id FROM events ORDER BY id LIMIT 1')['id'] ?? 0);

$r3 = $aufgabe($gestern, [['song', $aktiv]]);
$r3zwei = $aufgabe($gestern, [['song', $aktiv], ['song', $aktiv2]]);
$r3heute = $aufgabe($heute, [['song', $aktiv]]);
$r3ohne = $aufgabe('', [['song', $aktiv]]);
$r3probe = $aufgabe($gestern, [['song', $probe]]);
$r3gemischt = $aufgabe($gestern, [['song', $aktiv], ['song', $probe]]);
$r3termin = $termin ? $aufgabe($gestern, [['song', $aktiv], ['event', $termin]]) : 0;
$r3weg = $aufgabe($gestern, [['song', 99999999]]);
$nichts = $aufgabe($gestern, []);

$r2 = $aufgabe($gestern, [['song', $probe]], 1);
$r2heute = $aufgabe($heute, [['song', $probe]], 1);
$r2ohne = $aufgabe('', [['song', $probe]], 1);

q("INSERT INTO songs (title, status) VALUES ('ZZAL Vorschlag', 'vorschlag')");
$vorschlag = $lieder[] = (int) $db->lastInsertId();
song_vote_start($vorschlag, 1);
$r1 = song_vote_tasks($vorschlag)[0] ?? 0;
q('UPDATE tasks SET due_date = ? WHERE id = ?', [$gestern, $r1]);
q("INSERT INTO songs (title, status) VALUES ('ZZAL Vorschlag heute', 'vorschlag')");
$vorschlagHeute = $lieder[] = (int) $db->lastInsertId();
song_vote_start($vorschlagHeute, 1);
$r1heute = song_vote_tasks($vorschlagHeute)[0] ?? 0;
q('UPDATE tasks SET due_date = ? WHERE id = ?', [$heute, $r1heute]);

set_setting('tasks_expired_on', '');
tasks_expire_run();

// ------------------------------------------------- 1. Abstimmung
$pruefe('Abstimmung, Frist gestern: erledigt', $stand($r1) === 'erledigt');
$pruefe('und das Lied steht auf „Abgestimmt"',
    (string) row('SELECT status FROM songs WHERE id = ?', [$vorschlag])['status'] === 'abgestimmt');
$pruefe('Abstimmung, Frist heute: noch offen', $stand($r1heute) === 'offen');

// ----------------------------------------------- 2. Übungsaufgabe
$pruefe('Übung, fällig gestern: erledigt', $stand($r2) === 'erledigt');
$pruefe('Übung, fällig heute: noch offen', $stand($r2heute) === 'offen');
$pruefe('Übung ohne Fälligkeit: offen', $stand($r2ohne) === 'offen');

// ---------------------------------- 3. Nur an aktiven Liedern
$pruefe('von Hand, aktives Lied, gestern: erledigt', $stand($r3) === 'erledigt');
$pruefe('von Hand, zwei aktive Lieder, gestern: erledigt', $stand($r3zwei) === 'erledigt');
$pruefe('von Hand, aktives Lied, heute: noch offen', $stand($r3heute) === 'offen');
$pruefe('von Hand, aktives Lied, ohne Fälligkeit: offen', $stand($r3ohne) === 'offen');
$pruefe('Lied noch in Vorbereitung: offen', $stand($r3probe) === 'offen');
$pruefe('ein aktives und ein geprobtes Lied: offen', $stand($r3gemischt) === 'offen');
if ($r3termin) {
    $pruefe('hängt auch an einem Termin: offen', $stand($r3termin) === 'offen');
} else {
    echo 'kein Termin vorhanden - Terminfall übersprungen', PHP_EOL;
}
$pruefe('Lied gelöscht: offen', $stand($r3weg) === 'offen');
$pruefe('ohne jeden Bezug: offen', $stand($nichts) === 'offen');

// ------------------------------------------------ 4. Tagesmarke
$pruefe('die Tagesmarke steht auf heute', setting('tasks_expired_on') === $heute);
$quelle = (string) file_get_contents($basis . '/httpdocs/index.php');
$pruefe('der Seitenaufruf stößt den Lauf einmal am Tag an',
    (bool) preg_match("~setting\\('tasks_expired_on'\\) !== date\\('Y-m-d'\\)\\)\\s*\\{\\s*"
        . "\\\$hintergrund\\[\\] = fn\\(\\) => tasks_expire_run\\(\\);~", $quelle));

// Nichts stehen lassen.
foreach ($aufgaben as $nr) {
    q('DELETE FROM task_links WHERE task_id = ?', [$nr]);
    q('DELETE FROM tasks WHERE id = ?', [$nr]);
    item_forget('task', $nr);
}
foreach ($lieder as $nr) {
    song_tasks_delete($nr);
    q('DELETE FROM songs WHERE id = ?', [$nr]);
    item_forget('song', $nr);
}
$reste = (int) row("SELECT COUNT(*) n FROM songs WHERE title LIKE 'ZZAL%'")['n']
       + (int) row("SELECT COUNT(*) n FROM tasks WHERE title LIKE 'ZZAL%'")['n'];
$pruefe('keine Testdaten zurückgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
