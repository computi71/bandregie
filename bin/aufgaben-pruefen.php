<?php
// Zuständige, Quorum und Verknüpfungen von Aufgaben durchspielen (#334, #335).
//
// Das Projekt hat keine Testsammlung; diese Datei ist die Absicherung für den
// Teil, dessen Fehler man nicht sieht: Eine Aufgabe, die bei „2 von 3" hängen
// bleibt, sieht genauso aus wie eine, die noch niemand gemacht hat.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten, sonst gehört die Sitzungsdatei
// dem falschen Konto:
//
//   sudo -u www-data php bin/aufgaben-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/aufgaben-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$leute = rows('SELECT * FROM users ORDER BY id LIMIT 3');
if (count($leute) < 3) { fwrite(STDERR, "Drei Konten werden gebraucht\n"); exit(2); }
[$A, $B, $C] = $leute;

/** Eine Testaufgabe mit Zuständigen anlegen. */
$aufgabe = static function (array $wer, int $verlangt) use ($A): int {
    q("INSERT INTO tasks (title, notes, due_date, status, created_by, required_done)
       VALUES ('ZZ Pruefaufgabe', '', '', 'offen', ?, ?)", [(int) $A['id'], $verlangt]);
    $id = (int) $GLOBALS['db']->lastInsertId();
    foreach ($wer as $u) {
        q('INSERT INTO task_assignees (task_id, user_id) VALUES (?,?)', [$id, (int) $u['id']]);
    }
    return $id;
};
/** Abhaken für ein Konto. */
$hake = static function (int $id, array $u): void {
    q('UPDATE task_assignees SET done_at = NOW(3) WHERE task_id = ? AND user_id = ?',
      [$id, (int) $u['id']]);
};
$stand = static fn(int $id): string
    => (string) row('SELECT status FROM tasks WHERE id = ?', [$id])['status'];
$weg = static function (int $id): void {
    q('DELETE FROM task_assignees WHERE task_id = ?', [$id]);
    q('DELETE FROM tasks WHERE id = ?', [$id]);
    item_forget('task', $id);
};

// -------------------------------------------- 1. Ein Zuständiger, wie bisher
$t1 = $aufgabe([$A], 0);
$pruefe('einer zustaendig: vor dem Haken offen', task_status_apply($t1) === 'offen');
$hake($t1, $A);
$pruefe('einer zustaendig: ein Haken erledigt', task_status_apply($t1) === 'erledigt');
$weg($t1);

// -------------------------------------------------- 2. Quorum nicht erreicht
$t2 = $aufgabe([$A, $B, $C], 3);
$hake($t2, $A);
$hake($t2, $B);
$pruefe('zwei von drei bei verlangt 3: offen', task_status_apply($t2) === 'offen');
$hake($t2, $C);
$pruefe('drei von drei bei verlangt 3: erledigt', task_status_apply($t2) === 'erledigt');
$weg($t2);

// -------------------------------------------------------- 3. Quorum erreicht
$t3 = $aufgabe([$A, $B, $C], 2);
$hake($t3, $A);
$pruefe('einer von drei bei verlangt 2: offen', task_status_apply($t3) === 'offen');
$hake($t3, $B);
$pruefe('zwei von drei bei verlangt 2: erledigt', task_status_apply($t3) === 'erledigt');
$weg($t3);

// ------------------------------------------------- 4. Null heisst „alle"
$t4 = $aufgabe([$A, $B, $C], 0);
$hake($t4, $A);
$hake($t4, $B);
$pruefe('verlangt 0 mit drei: zwei genuegen nicht', task_status_apply($t4) === 'offen');
$hake($t4, $C);
$pruefe('verlangt 0 mit drei: alle drei erledigen', task_status_apply($t4) === 'erledigt');
$weg($t4);

// ---------------------- 5. Verlangt mehr als Zustaendige: gedeckelt
$t5 = $aufgabe([$A], 3);
$hake($t5, $A);
$pruefe('verlangt 3, nur einer zustaendig: erledigt', task_status_apply($t5) === 'erledigt');
$weg($t5);

// ------------ 6. Neuberechnung darf schliessen, aber nicht wieder oeffnen
$t6 = $aufgabe([$A, $B], 2);
$hake($t6, $A);
$hake($t6, $B);
task_status_apply($t6);
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t6, (int) $B['id']]);
$pruefe('erledigt, Zustaendiger entfernt: bleibt erledigt',
    task_status_apply($t6) === 'erledigt' && $stand($t6) === 'erledigt');
$pruefe('erledigt, ausdruecklich zurueckgenommen: wieder offen',
    task_status_apply($t6, true) === 'offen' && $stand($t6) === 'offen');
$weg($t6);

// ------------------- 7. Entfernen kann eine haengende Aufgabe schliessen
$t7 = $aufgabe([$A, $B, $C], 0);
$hake($t7, $A);
$hake($t7, $B);
task_status_apply($t7);
$pruefe('drei zustaendig, zwei fertig: haengt', $stand($t7) === 'offen');
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t7, (int) $C['id']]);
$pruefe('Dritter entfernt: jetzt erledigt', task_status_apply($t7) === 'erledigt');
$weg($t7);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
