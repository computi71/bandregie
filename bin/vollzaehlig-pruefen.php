<?php
// Die Meldung „alle haben geantwortet" (#382) und alles, was sie nicht tun darf.
//
// Der Grund für eine eigene Datei: Diese Meldung kann auf zwei Arten falsch
// sein, und beide sieht man ihr nicht an. Sie kann ausbleiben — dann zählt
// der Eintrager weiter von Hand, so wie vorher. Und sie kann zu oft kommen —
// dann klingelt bei jedem Umentscheiden ein Telefon, und der Empfänger
// schaltet irgendwann alle Mitteilungen ab.
//
// Wer antworten soll, beantwortet event_answerers(); dieselbe Frage stellt
// open_votes() je Person andersherum. Laufen die beiden auseinander, wartet
// die Meldung auf jemanden, der den Termin gar nicht auf seiner Liste hat.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/vollzaehlig-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/vollzaehlig-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$termin = static function (string $titel = 'ZZVZ Termin', int $wer = 1): int {
    q('INSERT INTO events (type, title, date, status, created_by) VALUES (?,?,?,?,?)',
      ['gig', $titel, '2027-11-20', 'bestaetigt', $wer]);
    return (int) $GLOBALS['db']->lastInsertId();
};
$weg = static function (int $nr): void {
    q('DELETE FROM attendance WHERE event_id = ?', [$nr]);
    q('DELETE FROM substitute_requests WHERE event_id = ?', [$nr]);
    q('DELETE FROM events WHERE id = ?', [$nr]);
    item_forget('event', $nr);
};
$marke = static fn(int $nr): int
    => (int) row('SELECT answers_complete FROM events WHERE id = ?', [$nr])['answers_complete'];
$antwortet = static function (int $ev, array $u, string $wie): void {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status)', [$ev, (int) $u['id'], $wie]);
};

// ------------------------------------------ 1. Wer antworten soll
$e1 = $termin();
$wer = event_answerers($e1);
$pruefe('es wird ueberhaupt jemand gefragt', count($wer) > 0);
$pruefe('kein Konto von aussen dabei', !array_filter($wer,
    static fn(array $u): bool => is_outsider($u)));
$pruefe('nur wer Termine lesen darf', !array_filter($wer,
    static fn(array $u): bool => !perm_allows($u, 'termine')));

// Die Gegenprobe zu open_votes(): Wer gefragt ist, hat den Termin auch offen.
$offenBei = 0;
foreach ($wer as $u) {
    if (in_array($e1, array_map(static fn(array $o): int => (int) $o['id'], open_votes($u)), true)) {
        $offenBei++;
    }
}
$pruefe('und hat den Termin auch selbst als offen', $offenBei === count($wer));

// --------------------------------- 2. Eine Aushilfe zaehlt erst nach Anfrage
$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
if ($aushilfe) {
    $ohne = count(event_answerers($e1));
    q('INSERT IGNORE INTO substitute_requests (event_id, user_id) VALUES (?,?)',
      [$e1, (int) $aushilfe['id']]);
    $mit = count(event_answerers($e1));
    $pruefe('eine angefragte Aushilfe kommt dazu', $mit === $ohne + 1);
    q('DELETE FROM substitute_requests WHERE event_id = ? AND user_id = ?',
      [$e1, (int) $aushilfe['id']]);
    $pruefe('zurueckgezogen faellt sie wieder heraus',
        count(event_answerers($e1)) === $ohne);
} else {
    echo 'keine Aushilfe vorhanden - Ersatzpruefung uebersprungen', PHP_EOL;
}
$weg($e1);

// ----------------------------- 3. Die Marke und die eine Meldung
$e2 = $termin('ZZVZ Marke');
$wer = event_answerers($e2);
$pruefe('am Anfang nicht vollzaehlig', !event_answers_complete($e2));
$pruefe('und die Marke steht auf null', $marke($e2) === 0);

foreach (array_slice($wer, 0, count($wer) - 1) as $i => $u) {
    $antwortet($e2, $u, ['yes', 'maybe', 'no'][$i % 3]);
}
event_completion_notify($e2);
$pruefe('einer fehlt: keine Meldung', $marke($e2) === 0);

$letzter = $wer[count($wer) - 1];
$antwortet($e2, $letzter, 'yes');
event_completion_notify($e2);
$pruefe('vollzaehlig: Meldung raus', $marke($e2) === 1);

// Umentscheiden laesst die Runde vollzaehlig - eine Antwort bleibt eine Antwort.
$antwortet($e2, $letzter, 'no');
event_completion_notify($e2);
$pruefe('ja zu nein: keine zweite Meldung', $marke($e2) === 1);
$antwortet($e2, $letzter, 'yes');
event_completion_notify($e2);
$pruefe('und zurueck auch nicht', $marke($e2) === 1);

// Eine zurueckgezogene Antwort macht die Runde wieder offen.
q('DELETE FROM attendance WHERE event_id = ? AND user_id = ?', [$e2, (int) $letzter['id']]);
event_completion_notify($e2);
$pruefe('Antwort zurueckgezogen: Marke faellt', $marke($e2) === 0);
$antwortet($e2, $letzter, 'yes');
event_completion_notify($e2);
$pruefe('wieder vollzaehlig: das ist eine Neuigkeit', $marke($e2) === 1);
$weg($e2);

// ------------------- 4. Wer dazukommt, macht die Runde wieder offen
// Sonst meldete ein Termin "alle" und meinte "alle von damals".
if ($aushilfe) {
    $e3 = $termin('ZZVZ Dazu');
    foreach (event_answerers($e3) as $u) $antwortet($e3, $u, 'yes');
    event_completion_notify($e3);
    $pruefe('vollzaehlig gemeldet', $marke($e3) === 1);
    q('INSERT IGNORE INTO substitute_requests (event_id, user_id) VALUES (?,?)',
      [$e3, (int) $aushilfe['id']]);
    event_completion_notify($e3);
    $pruefe('Aushilfe angefragt: wieder offen', $marke($e3) === 0);
    $antwortet($e3, $aushilfe, 'yes');
    event_completion_notify($e3);
    $pruefe('sie antwortet: wieder vollzaehlig', $marke($e3) === 1);
    $weg($e3);
}

// ------------------------- 5. Ein Termin ohne Eintrager faellt nicht um
$e4 = $termin('ZZVZ Ohne Eintrager');
q('UPDATE events SET created_by = NULL WHERE id = ?', [$e4]);
foreach (event_answerers($e4) as $u) $antwortet($e4, $u, 'yes');
event_completion_notify($e4);
$pruefe('ohne Eintrager wird die Marke trotzdem gesetzt', $marke($e4) === 1);
$weg($e4);

// -------------------------------------- 6. Das Mitteilungsthema
$pruefe("'vollzaehlig' steht in PUSH_TOPICS", in_array('vollzaehlig', PUSH_TOPICS, true));
$pruefe('und hat eine Beschriftung', t('push_topic_vollzaehlig') !== 'push_topic_vollzaehlig');
$pruefe('es laesst sich abwaehlen', !in_array('vollzaehlig', push_topics(['push_topics' => 'vollzaehlig']), true));
$pruefe('wer nichts eingestellt hat, hat es an',
    in_array('vollzaehlig', push_topics(['push_topics' => '']), true));
$pruefe('push_to_user gibt es', function_exists('push_to_user'));

// Nichts stehen lassen.
$reste = (int) row('SELECT COUNT(*) n FROM events WHERE title LIKE ?', ['ZZVZ%'])['n'];
$pruefe('keine Testdaten zurueckgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
