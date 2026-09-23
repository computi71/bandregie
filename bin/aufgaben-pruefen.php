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

// ---------- 6a. Entfernen darf eine erledigte Aufgabe nicht aufmachen
// Damit die Zahl wirklich fällt, muss der ABGEHAKTE entfernt werden: Nimmt man
// den anderen heraus, bleibt einer von einem übrig und die Aufgabe ist zu
// Recht weiter erledigt. Genau daran ist diese Prüfung beim ersten Schreiben
// gescheitert - sie hatte den Falschen entfernt und dem Code die Schuld gegeben.
$t6 = $aufgabe([$A, $B], 2);
$hake($t6, $A);
$hake($t6, $B);
task_status_apply($t6);
$pruefe('zwei von zwei: erledigt', $stand($t6) === 'erledigt');
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t6, (int) $A['id']]);
$pruefe('erledigt, abgehakter Zustaendiger entfernt: bleibt erledigt',
    task_status_apply($t6) === 'erledigt' && $stand($t6) === 'erledigt');
$weg($t6);

// -------- 6b. Ein zurückgenommenes Häkchen darf sie wieder aufmachen
$t6b = $aufgabe([$A], 0);
$hake($t6b, $A);
task_status_apply($t6b);
$pruefe('einer, abgehakt: erledigt', $stand($t6b) === 'erledigt');
q('UPDATE task_assignees SET done_at = NULL WHERE task_id = ? AND user_id = ?',
  [$t6b, (int) $A['id']]);
$pruefe('zurueckgenommen ohne Erlaubnis: bleibt erledigt',
    task_status_apply($t6b) === 'erledigt' && $stand($t6b) === 'erledigt');
$pruefe('zurueckgenommen mit Erlaubnis: wieder offen',
    task_status_apply($t6b, true) === 'offen' && $stand($t6b) === 'offen');
$weg($t6b);

// ------------------- 7. Entfernen kann eine haengende Aufgabe schliessen
$t7 = $aufgabe([$A, $B, $C], 0);
$hake($t7, $A);
$hake($t7, $B);
task_status_apply($t7);
$pruefe('drei zustaendig, zwei fertig: haengt', $stand($t7) === 'offen');
q('DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?', [$t7, (int) $C['id']]);
$pruefe('Dritter entfernt: jetzt erledigt', task_status_apply($t7) === 'erledigt');
$weg($t7);

// -------------- 8. Abhaken macht zuständig, Zurücknehmen löst es wieder
// Geprüft werden die Anweisungen, die auch die Route ausführt. Über HTTP zu
// gehen hieße, hier das CSRF-Token nachzubauen - also eine zweite Kopie einer
// Sicherheitsmechanik zu pflegen. Die HTTP-Schicht deckt routen-pruefen.php ab.
q("INSERT INTO tasks (title, notes, due_date, status, created_by, required_done)
   VALUES ('ZZ Routenaufgabe', '', '', 'offen', ?, 0)", [(int) $A['id']]);
$t8 = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO task_assignees (task_id, user_id, done_at) VALUES (?,?,NOW(3))
   ON DUPLICATE KEY UPDATE done_at = NOW(3)', [$t8, (int) $B['id']]);
$pruefe('unzugewiesen abgehakt: erledigt', task_status_apply($t8) === 'erledigt');
$pruefe('unzugewiesen abgehakt: der Haken trägt einen Namen',
    (int) row('SELECT COUNT(*) n FROM task_assignees WHERE task_id = ? AND done_at IS NOT NULL',
              [$t8])['n'] === 1);
q('UPDATE task_assignees SET done_at = NULL WHERE task_id = ? AND user_id = ?', [$t8, (int) $B['id']]);
$pruefe('zurueckgenommen: wieder offen', task_status_apply($t8, true) === 'offen');
$weg($t8);

// ---------------- 9. Zahl am Symbol und das Loeschen eines Kontos
$t9 = $aufgabe([$A, $B], 2);
$hake($t9, $A);
task_status_apply($t9);
$vorher = open_items_count($B);
$pruefe('offene Aufgabe zaehlt beim Zustaendigen', $vorher >= 1);
$hake($t9, $B);
task_status_apply($t9);
$pruefe('erledigte Aufgabe zaehlt nicht mehr', open_items_count($B) === $vorher - 1);
$weg($t9);

// Ein Konto faellt weg, waehrend eine Aufgabe daran haengt.
$t10 = $aufgabe([$A, $B, $C], 0);
$hake($t10, $A);
$hake($t10, $B);
task_status_apply($t10);
$pruefe('drei zustaendig, zwei fertig: haengt', $stand($t10) === 'offen');
// Das, was user_purge() tut: Zeilen weg UND neu rechnen.
$weggefallen = array_column(rows('SELECT task_id FROM task_assignees WHERE user_id = ?', [(int) $C['id']]), 'task_id');
q('DELETE FROM task_assignees WHERE user_id = ?', [(int) $C['id']]);
foreach ($weggefallen as $nr) task_status_apply((int) $nr);
$pruefe('Konto weg: Aufgabe nicht mehr haengend', $stand($t10) === 'erledigt');
$weg($t10);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
