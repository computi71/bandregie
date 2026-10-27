<?php
// Eine kleine Zeitverschiebung ist ein Vorschlag (#413).
//
// Der Grund für eine eigene Datei: Hier wird eine Schreibanweisung bewusst
// NICHT ausgeführt. Die Route legt die alten Zeiten zurück in $_POST, damit
// event_values() sie schreibt — und wer das beim nächsten Umbau übersieht,
// bricht nichts, was auffiele. Die Seite sieht richtig aus, die Zeit springt
// nur eben sofort um, und sechs Zusagen stehen dann an einer Zeit, zu der
// niemand ja gesagt hat.
//
// Deshalb prüft diese Datei beide Enden: die Entscheidung (ist das eine
// kleine Verschiebung?) und die Route, die sie umsetzt. Die Entscheidung
// allein wäre erfüllt, ohne dass irgendetwas davon im Kalender ankäme.
//
// SIE SCHREIBT TESTDATEN (ein Termin, Zusagen, Vorschläge) und läuft deshalb
// nur auf dem Entwicklungsserver, nie auf einer Bandinstanz:
//
//   sudo -u www-data php bin/zeitvorschlag-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Mit einem Hostnamen als drittem Wort wird auch die Route wirklich gerufen:
//
//   ... --schreibt-testdaten staging.beatcuisine.de
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/zeitvorschlag-pruefen.php <Verzeichnis> --schreibt-testdaten [Host]\n");
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

// ------------------- 1. Was als kleine Verschiebung gilt
$pruefe('19:30 sind 1170 Minuten', event_time_minutes('19:30') === 1170);
$pruefe('"abends" ist keine Zeit', event_time_minutes('abends') === null);
$pruefe('25:00 ist keine Zeit', event_time_minutes('25:00') === null);

$basiszeile = ['date' => '2027-11-05', 'time' => '19:00', 'time_meet' => '18:30',
               'time_end' => '22:00', 'venue_id' => '3', 'location' => ''];
$mit = static function (array $aenderung) use ($basiszeile): array {
    return array_merge($basiszeile, $aenderung);
};
$pruefe('eine Stunde später: kleine Verschiebung',
    event_time_small_shift($basiszeile, $mit(['time' => '20:00', 'time_meet' => '19:30',
                                              'time_end' => '23:00'])));
$pruefe('eine halbe Stunde nur am Ende: auch',
    event_time_small_shift($basiszeile, $mit(['time_end' => '22:30'])));
$pruefe('genau drei Stunden: noch',
    event_time_small_shift($basiszeile, $mit(['time' => '22:00'])));
$pruefe('drei Stunden und eine Minute: nicht mehr',
    !event_time_small_shift($basiszeile, $mit(['time' => '22:01'])));
$pruefe('das Ende von 22 auf 2 Uhr: eine andere Frage',
    !event_time_small_shift($basiszeile, $mit(['time' => '20:00', 'time_end' => '02:00'])));
$pruefe('ein anderer Tag: keine Verschiebung',
    !event_time_small_shift($basiszeile, $mit(['date' => '2027-11-06', 'time' => '20:00'])));
$pruefe('ein anderer Ort: keine Verschiebung',
    !event_time_small_shift($basiszeile, $mit(['venue_id' => '4', 'time' => '20:00'])));
$pruefe('ein anderer Freitext-Ort: auch nicht',
    !event_time_small_shift($basiszeile, $mit(['location' => 'Woanders', 'time' => '20:00'])));
$pruefe('eine Zeit, die neu dazukommt, ist Auskunft und kein Verschieben',
    !event_time_small_shift($mit(['time_end' => '']), $mit(['time_end' => '23:00'])));
$pruefe('gar nichts geändert: kein Vorschlag',
    !event_time_small_shift($basiszeile, $basiszeile));

// ------------------- Testdaten
q("DELETE FROM events WHERE title LIKE 'ZZZV%'");
$leute = rows("SELECT id, name FROM users ORDER BY id LIMIT 4");
if (count($leute) < 3) { fwrite(STDERR, "Drei Konten werden gebraucht\n"); exit(2); }
[$chef, $a, $b] = array_map(static fn(array $u): int => (int) $u['id'], $leute);
$c = isset($leute[3]) ? (int) $leute[3]['id'] : 0;

$neuerTermin = static function (string $titel) use ($chef): int {
    q("INSERT INTO events (type, title, date, time, time_meet, time_end, status, created_by)
       VALUES ('probe', ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), '19:00', '18:30', '22:00',
               'bestaetigt', ?)", [$titel, $chef]);
    return (int) $GLOBALS['db']->lastInsertId();
};
$sagt = static function (int $ev, int $wer, string $wie): void {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status)', [$ev, $wer, $wie]);
};
$zeitVon = static function (int $ev): string {
    $r = row('SELECT time, time_meet, time_end FROM events WHERE id = ?', [$ev]);
    return $r['time_meet'] . '/' . $r['time'] . '/' . $r['time_end'];
};
$raeumAuf = static function (int $ev): void {
    q('DELETE FROM attendance WHERE event_id = ?', [$ev]);
    q('DELETE FROM event_time_confirms WHERE event_id = ?', [$ev]);
    q('DELETE FROM events WHERE id = ?', [$ev]);
};
$neueZeiten = ['time' => '20:00', 'time_meet' => '19:30', 'time_end' => '23:00'];

// Die Testkonten stehen für diesen Lauf auf der Bühne. Ohne das hängt die
// Prueffung davon ab, welche Häkchen auf der Entwicklungsanlage gerade stehen:
// Wer nicht auf der Bühne steht, muss bei einer Probe seit #420 nicht
// bestätigen — richtig so, aber hier wird etwas anderes geprüft.
$buehneVorher = [];
foreach ([$chef, $a, $b] as $zzU) {
    $buehneVorher[$zzU] = (int) row('SELECT on_stage FROM users WHERE id = ?', [$zzU])['on_stage'];
    q('UPDATE users SET on_stage = 1 WHERE id = ?', [$zzU]);
}
$buehneZurueck = static function () use ($buehneVorher): void {
    foreach ($buehneVorher as $zzU => $zzW) q('UPDATE users SET on_stage = ? WHERE id = ?', [$zzW, $zzU]);
};

// ------------------- 2. Vorschlagen, bestätigen, gilt
$e1 = $neuerTermin('ZZZV Vorschlag');
$sagt($e1, $a, 'yes');
$sagt($e1, $b, 'yes');
if ($c) $sagt($e1, $c, 'no');
$offen = event_time_propose($e1, $neueZeiten, $chef);
$pruefe('zwei müssen bestätigen (die Zusagenden ohne den Vorschlagenden)', $offen === 2);
$pruefe('im Kalender steht weiter die alte Zeit', $zeitVon($e1) === '18:30/19:00/22:00');
$pruefe('der Vorschlag steht daneben',
    event_time_label(event_time_proposal($e1) ?? [], '_proposed') === '19:30/20:00/23:00');
$pruefe('wer abgesagt hat, muss nichts bestätigen',
    !$c || !event_time_awaits($e1, $c));
$pruefe('die Zusagen stehen alle noch',
    (int) row('SELECT COUNT(*) n FROM attendance WHERE event_id = ?', [$e1])['n'] === ($c ? 3 : 2));

$pruefe('wer nicht gefragt ist, kann nicht bestätigen',
    !$c || !event_time_confirm($e1, $c));
$pruefe('der Erste bestätigt', event_time_confirm($e1, $a));
$pruefe('und die Zeit gilt noch nicht', $zeitVon($e1) === '18:30/19:00/22:00');
$pruefe('ein zweites Mal tippen tut nichts', !event_time_confirm($e1, $a));
$pruefe('der Zweite bestätigt', event_time_confirm($e1, $b));
$pruefe('jetzt gilt die neue Zeit', $zeitVon($e1) === '19:30/20:00/23:00');
$pruefe('der Vorschlag ist weg', event_time_proposal($e1) === null);
$pruefe('und die Zusagen stehen immer noch',
    (int) row("SELECT COUNT(*) n FROM attendance WHERE event_id = ? AND status = 'yes'",
              [$e1])['n'] === 2);
$raeumAuf($e1);

// ------------------- 3. Hatte niemand zugesagt, gibt es nichts zu schützen
$e2 = $neuerTermin('ZZZV Niemand');
$sagt($e2, $a, 'no');
$pruefe('ohne Zusagen gilt die Zeit sofort', event_time_propose($e2, $neueZeiten, $chef) === 0);
$pruefe('und steht auch wirklich im Kalender', $zeitVon($e2) === '19:30/20:00/23:00');
$raeumAuf($e2);

// ------------------- 4. Zurücknehmen und Verfallen
$e3 = $neuerTermin('ZZZV Zuruecknehmen');
$sagt($e3, $a, 'yes');
event_time_propose($e3, $neueZeiten, $chef);
event_time_clear($e3);
$pruefe('zurückgenommen: kein Vorschlag mehr', event_time_proposal($e3) === null);
$pruefe('und die alte Zeit steht', $zeitVon($e3) === '18:30/19:00/22:00');
$pruefe('keine Bestätigungszeile bleibt liegen',
    (int) row('SELECT COUNT(*) n FROM event_time_confirms WHERE event_id = ?', [$e3])['n'] === 0);

event_time_propose($e3, $neueZeiten, $chef);
q('UPDATE events SET time_proposed_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE id = ?',
  [EVENT_TIME_PROPOSAL_HOURS + 1, $e3]);
$pruefe('nach der Frist verfällt er', event_time_proposals_expire() >= 1);
$pruefe('und die alte Zeit steht immer noch', $zeitVon($e3) === '18:30/19:00/22:00');
$pruefe('ein frischer Vorschlag verfällt nicht mit',
    (static function () use ($neuerTermin, $sagt, $a, $chef, $neueZeiten, $raeumAuf): bool {
        $ev = $neuerTermin('ZZZV Frisch');
        $sagt($ev, $a, 'yes');
        event_time_propose($ev, $neueZeiten, $chef);
        event_time_proposals_expire();
        $steht = event_time_proposal($ev) !== null;
        $raeumAuf($ev);
        return $steht;
    })());
$raeumAuf($e3);

// ------------------- 4b. Wer nicht auf der Bühne steht, hält nichts auf (#420)
$e9 = $neuerTermin('ZZZV Technik');
$sagt($e9, $a, 'yes');
q('UPDATE users SET on_stage = 0 WHERE id = ?', [$a]);
$pruefe('bei der Probe muss die Technik nicht bestätigen',
    event_time_propose($e9, $neueZeiten, $chef) === 0);
$pruefe('und die Zeit gilt damit sofort', $zeitVon($e9) === '19:30/20:00/23:00');
$raeumAuf($e9);

// Beim Gig zählt sie wie jeder andere.
$e10 = $neuerTermin('ZZZV Technik Gig');
q("UPDATE events SET type = 'gig' WHERE id = ?", [$e10]);
$sagt($e10, $a, 'yes');
$pruefe('beim Gig muss sie bestätigen', event_time_propose($e10, $neueZeiten, $chef) === 1);
$pruefe('und die alte Zeit steht solange', $zeitVon($e10) === '18:30/19:00/22:00');
$raeumAuf($e10);
q('UPDATE users SET on_stage = 1 WHERE id = ?', [$a]);

// ------------------- 5. Die Route (nur mit Hostnamen)
$host = $argv[3] ?? '';
if ($host !== '') {
    $e4 = $neuerTermin('ZZZV Route');
    $sagt($e4, $a, 'yes');
    $_SESSION['uid'] = $chef;
    $sid = session_id();
    $token = csrf_token();
    session_write_close();
    $post = static function (string $pfad, array $felder) use ($host, $sid): string {
        $teile = [];
        foreach ($felder as $k => $v) $teile[] = '-d ' . escapeshellarg($k . '=' . $v);
        return (string) shell_exec(sprintf(
            'curl -sk -o /dev/null -w "%%{http_code}" --resolve %s:443:127.0.0.1 -b PHPSESSID=%s %s https://%s%s',
            escapeshellarg($host), escapeshellarg($sid), implode(' ', $teile),
            escapeshellarg($host), $pfad));
    };
    $formular = static function (array $zeiten) use ($token): array {
        return ['_token' => $token, 'type' => 'probe', 'title' => 'ZZZV Route',
                'date' => date('Y-m-d', strtotime('+30 days')), 'status' => 'bestaetigt',
                'time' => $zeiten['time'], 'time_meet' => $zeiten['time_meet'],
                'time_end' => $zeiten['time_end']];
    };

    $code = $post("/intern/termine/$e4/update", $formular($neueZeiten));
    $pruefe('Route: das Formular geht durch', $code === '302');
    $pruefe('Route: die Zeit im Kalender hat sich NICHT bewegt',
        $zeitVon($e4) === '18:30/19:00/22:00');
    $pruefe('Route: stattdessen steht ein Vorschlag', event_time_proposal($e4) !== null);
    $pruefe('Route: und einer muss ihn bestätigen', count(event_time_waiting($e4)) === 1);

    // Die Bestätigung über ihre eigene Route — mit DERSELBEN Sitzung. Ein
    // zweites session_start() ginge hier nicht mehr: Die Prüfzeilen oben sind
    // schon ausgegeben. Also wird der Vorschlag diesmal von $a gemacht, damit
    // der Chef derjenige ist, der bestätigen muss.
    event_time_clear($e4);
    $sagt($e4, $chef, 'yes');
    event_time_propose($e4, $neueZeiten, $a);
    $pruefe('Route: der Chef ist diesmal der Gefragte', event_time_awaits($e4, $chef));
    $pruefe('Route: bestätigen geht durch', $post("/intern/termine/$e4/zeit-ok", ['_token' => $token]) === '302');
    $pruefe('Route: jetzt steht die neue Zeit im Kalender',
        $zeitVon($e4) === '19:30/20:00/23:00');

    // Und ein großer Sprung räumt einen offenen Vorschlag weg. Die Sitzung
    // des Chefs liegt noch, die Route schreibt also weiter als er.
    $sagt($e4, $a, 'yes');
    event_time_propose($e4, ['time' => '21:00', 'time_meet' => '20:30', 'time_end' => '23:30'], $chef);
    $post("/intern/termine/$e4/update",
          $formular(['time' => '09:00', 'time_meet' => '08:30', 'time_end' => '12:00']));
    $pruefe('Route: ein weiter Sprung räumt den Vorschlag weg',
        event_time_proposal($e4) === null);
    $pruefe('Route: und er wird wirklich geschrieben', $zeitVon($e4) === '08:30/09:00/12:00');
    $pruefe('Route: die Antworten sind dabei verworfen',
        (int) row('SELECT COUNT(*) n FROM attendance WHERE event_id = ?', [$e4])['n'] === 0);
    $raeumAuf($e4);
} else {
    echo "  (ohne Hostnamen: die Route selbst wurde nicht gerufen)" . PHP_EOL;
}

// ------------------- 6. Nichts liegengelassen
$buehneZurueck();
$pruefe('keine ZZZV-Termine zurückgelassen',
    (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZZV%'")['n'] === 0);
$pruefe('keine verwaisten Bestätigungszeilen',
    (int) row('SELECT COUNT(*) n FROM event_time_confirms c
               LEFT JOIN events e ON e.id = c.event_id WHERE e.id IS NULL')['n'] === 0);

printf("%s%d ok, %d Fehler%s", PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler > 0 ? 1 : 0);
