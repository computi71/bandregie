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
// Mit einem Hostnamen als drittem Wort wird auch das Anlegen über die Route
// geprüft — wer einträgt, ist voreingestellt verantwortlich (#424), und das
// entscheidet sich dort und nirgends sonst:
//
//   ... --schreibt-testdaten staging.beatcuisine.de
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

// ------------- 7. Ein geaenderter Termin fragt neu (#402)
// Die Liste EVENT_FIELDS_INVALIDATE ist der ganze Unterschied zwischen
// "sechs Antworten weg" und "Tippfehler berichtigt". Laeuft sie weg, merkt es
// niemand: Im einen Fall bleiben Antworten auf eine Frage stehen, die es
// nicht mehr gibt, im anderen weckt eine berichtigte Gage sechs Telefone.
$e5 = $termin('ZZVZ Verschoben');
foreach (event_answerers($e5) as $u) $antwortet($e5, $u, 'yes');
event_completion_notify($e5);
$pruefe('vollzaehlig und gemeldet', $marke($e5) === 1);

$abdruck = event_answer_fingerprint($e5);
q('UPDATE events SET title = ?, fee = ?, notes = ? WHERE id = ?',
  ['ZZVZ Anders benannt', '250', 'Hinweis', $e5]);
$pruefe('Titel, Gage, Notiz: dieselbe Frage',
    event_answer_fingerprint($e5) === $abdruck);

q('UPDATE events SET date = ? WHERE id = ?', ['2027-11-21', $e5]);
$pruefe('anderes Datum: andere Frage', event_answer_fingerprint($e5) !== $abdruck);
$abdruck = event_answer_fingerprint($e5);
q('UPDATE events SET time = ? WHERE id = ?', ['21:30', $e5]);
$pruefe('andere Uhrzeit: andere Frage', event_answer_fingerprint($e5) !== $abdruck);
$abdruck = event_answer_fingerprint($e5);
q('UPDATE events SET location = ? WHERE id = ?', ['Eine Stunde weiter', $e5]);
$pruefe('anderer Ort: andere Frage', event_answer_fingerprint($e5) !== $abdruck);

$wieViele = count(event_answerers($e5));
$verworfen = event_answers_reset($e5, 1);
$pruefe('alle Antworten verworfen', $verworfen === $wieViele);
$pruefe('und keine ist uebrig',
    (int) row('SELECT COUNT(*) n FROM attendance WHERE event_id = ?', [$e5])['n'] === 0);
$pruefe('die Marke faellt zurueck', $marke($e5) === 0);
$pruefe('keine verwaiste Antwortmarke', (int) row(
    "SELECT COUNT(*) n FROM seen_marks s WHERE s.kind = 'attendance'
     AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.id = s.item_id)")['n'] === 0);
$pruefe('und der Termin steht bei allen wieder offen', count(array_filter(
    event_answerers($e5),
    static fn(array $u): bool => in_array($e5, array_map(
        static fn(array $o): int => (int) $o['id'], open_votes($u)), true))) === $wieViele);

// Eine Ersatzanfrage ueberlebt: Wer gebeten wurde, ist weiter gebeten.
if ($aushilfe) {
    q('INSERT IGNORE INTO substitute_requests (event_id, user_id) VALUES (?,?)',
      [$e5, (int) $aushilfe['id']]);
    $antwortet($e5, $aushilfe, 'yes');
    event_answers_reset($e5, 1);
    $pruefe('die Ersatzanfrage bleibt stehen', (bool) row(
        'SELECT 1 FROM substitute_requests WHERE event_id = ? AND user_id = ?',
        [$e5, (int) $aushilfe['id']]));
    $pruefe('nur ihre Antwort ist weg', !row(
        'SELECT 1 FROM attendance WHERE event_id = ? AND user_id = ?',
        [$e5, (int) $aushilfe['id']]));
}

// Ohne Antworten darf es nicht stolpern.
$e6 = $termin('ZZVZ Unbeantwortet');
$pruefe('ohne Antworten verwirft es nichts', event_answers_reset($e6, 1) === 0);
$weg($e6);
$weg($e5);

// ------------------- Wer nicht auf der Bühne steht (#420)
//
// Eingeladen ja, gebraucht nein — und zwar nur bei den Arten, bei denen
// gespielt oder abgelichtet wird. Geprüft wird beides zusammen: dass die Band
// nicht auf ihn wartet UND dass er die Mitteilung trotzdem bekommt. Das erste
// allein wäre auch erfüllt, wenn man ihn ganz aus dem Termin wirft.
$zzBuehne = row("SELECT * FROM users WHERE email = 'zzbuehne@example.invalid'");
if (!$zzBuehne) {
    $zzMit = row("SELECT id FROM users WHERE role <> 'admin' AND substitute_for IS NULL LIMIT 1")
        ?? row('SELECT id FROM users ORDER BY id LIMIT 1');
    q("INSERT INTO users (name, email, password_hash, role, on_stage)
       VALUES ('ZZ Technik', 'zzbuehne@example.invalid', '', 'member', 0)");
    $zzBuehne = row("SELECT * FROM users WHERE email = 'zzbuehne@example.invalid'");
}
$zzNr = (int) $zzBuehne['id'];
$artTermin = static function (string $art) use (&$zzNr): int {
    q('INSERT INTO events (type, title, date, status, created_by) VALUES (?,?,?,?,1)',
      [$art, 'ZZVZ ' . $art, '2027-11-21', 'bestaetigt']);
    return (int) $GLOBALS['db']->lastInsertId();
};
$gefragtBei = static function (int $ev) use ($zzNr): bool {
    return in_array($zzNr, array_map(static fn(array $u): int => (int) $u['id'],
                                     event_answerers($ev)), true);
};
$eingeladenBei = static function (int $ev) use ($zzNr): bool {
    return in_array($zzNr, array_map(static fn(array $u): int => (int) $u['id'],
                                     event_invited($ev)), true);
};

foreach (['probe', 'aufnahme', 'fotoshooting'] as $art) {
    $ea = $artTermin($art);
    $pruefe("bei der Art $art wartet die Band nicht auf ihn", !$gefragtBei($ea));
    $pruefe("bei der Art $art ist er trotzdem eingeladen", $eingeladenBei($ea));
    $weg($ea);
}
foreach (['gig', 'besprechung'] as $art) {
    $eb = $artTermin($art);
    $pruefe("bei der Art $art zählt seine Antwort wie jede andere", $gefragtBei($eb));
    $weg($eb);
}

// Und der Haken ist es wirklich, der das macht — nicht das Konto.
q('UPDATE users SET on_stage = 1 WHERE id = ?', [$zzNr]);
$ec = $artTermin('probe');
$pruefe('mit gesetztem Haken wartet die Band auch bei der Probe', $gefragtBei($ec));
$weg($ec);
q('UPDATE users SET on_stage = 0 WHERE id = ?', [$zzNr]);

// Vollzähligkeit: Seine fehlende Antwort hält die Probe nicht auf.
$ed = $artTermin('probe');
foreach (event_answerers($ed) as $u) {
    q("INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,'yes')",
      [$ed, (int) $u['id']]);
}
$pruefe('die Probe ist vollzählig, obwohl er nicht geantwortet hat',
    event_answers_complete($ed));
$weg($ed);

q('DELETE FROM users WHERE id = ?', [$zzNr]);
$pruefe('Testkonto wieder weg',
    row("SELECT id FROM users WHERE email = 'zzbuehne@example.invalid'") === null);

// ------------------- Wer einträgt, ist verantwortlich (#424)
$host = $argv[3] ?? '';
if ($host !== '') {
    $_SESSION['uid'] = 1;
    $sid = session_id();
    $token = csrf_token();
    session_write_close();
    $anlegen = static function (array $felder) use ($host, $sid, $token): int {
        $teile = ['-d ' . escapeshellarg('_token=' . $token)];
        foreach ($felder as $k => $v) $teile[] = '-d ' . escapeshellarg($k . '=' . $v);
        shell_exec(sprintf(
            'curl -sk -o /dev/null --resolve %s:443:127.0.0.1 -b PHPSESSID=%s %s https://%s/intern/termine',
            escapeshellarg($host), escapeshellarg($sid), implode(' ', $teile), escapeshellarg($host)));
        $z = row("SELECT id FROM events WHERE title LIKE 'ZZVR%' ORDER BY id DESC LIMIT 1");
        return $z ? (int) $z['id'] : 0;
    };
    $verantwortlich = static fn(int $ev): ?int
        => ($r = row('SELECT responsible_id FROM events WHERE id = ?', [$ev]))
            && $r['responsible_id'] !== null ? (int) $r['responsible_id'] : null;
    $grund = ['type' => 'probe', 'date' => date('Y-m-d', strtotime('+40 days')), 'status' => 'bestaetigt'];

    q("DELETE FROM events WHERE title LIKE 'ZZVR%'");
    $r1 = $anlegen($grund + ['title' => 'ZZVR leer', 'responsible_id' => '']);
    $pruefe('leer gelassen: der Eintragende steht drin', $r1 && $verantwortlich($r1) === 1);

    // Und eine gewählte Person bleibt stehen — sonst prüfte das oben nur,
    // dass irgendetwas in das Feld geschrieben wird.
    $anderer = row('SELECT id FROM users WHERE id <> 1 ORDER BY id LIMIT 1');
    if ($anderer) {
        $r2 = $anlegen($grund + ['title' => 'ZZVR gewaehlt',
                                 'responsible_id' => (string) $anderer['id']]);
        $pruefe('jemand anderes gewählt: der bleibt stehen',
            $r2 && $verantwortlich($r2) === (int) $anderer['id']);
    }

    // Beim Ändern greift es nicht: Ein Speichern darf die Verantwortung nicht
    // stillschweigend auf den verschieben, der gerade etwas korrigiert hat.
    if ($r1) {
        q('UPDATE events SET responsible_id = NULL WHERE id = ?', [$r1]);
        shell_exec(sprintf(
            'curl -sk -o /dev/null --resolve %s:443:127.0.0.1 -b PHPSESSID=%s -d %s -d %s -d %s -d %s -d %s https://%s/intern/termine/%d/update',
            escapeshellarg($host), escapeshellarg($sid),
            escapeshellarg('_token=' . $token), escapeshellarg('title=ZZVR leer'),
            escapeshellarg('date=' . $grund['date']), escapeshellarg('type=probe'),
            escapeshellarg('status=bestaetigt'), escapeshellarg($host), $r1));
        $pruefe('beim Ändern bleibt das Feld leer', $verantwortlich($r1) === null);
    }
    // Und was auf der Karte davon steht (#425): „Eingetragen von“ genau dort,
    // wo es etwas hinzufügt. Zwei Plaketten mit demselben Namen wären
    // dasselbe zweimal — und ein Gig vom Bookingagenten, der gar keinen
    // Verantwortlichen hat, wäre ohne sie von niemandem.
    $eigen = row('SELECT id, name FROM users WHERE id = 1');
    $seite = (string) shell_exec(sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s https://%s/intern/termine',
        escapeshellarg($host), escapeshellarg($sid), escapeshellarg($host)));
    $stueckZu = static function (string $titel) use ($seite): string {
        $i = strpos($seite, $titel);
        return $i === false ? '' : (string) preg_replace('~\s+~u', ' ',
            trim(strip_tags(substr($seite, $i, 600))));
    };
    $ohneZust = $anlegen($grund + ['title' => 'ZZVR ohne', 'responsible_id' => '']);
    q('UPDATE events SET responsible_id = NULL WHERE id = ?', [$ohneZust]);
    $seite = (string) shell_exec(sprintf(
        'curl -sk --resolve %s:443:127.0.0.1 -b PHPSESSID=%s https://%s/intern/termine',
        escapeshellarg($host), escapeshellarg($sid), escapeshellarg($host)));
    $ohneText = $stueckZu('ZZVR ohne');
    $pruefe('ohne Verantwortlichen steht da, wer eingetragen hat',
        str_contains($ohneText, t('ev_created_by')));
    $gleichText = $stueckZu('ZZVR leer');
    $pruefe('ist er selbst verantwortlich, steht es nur einmal',
        $gleichText !== '' && !str_contains($gleichText, t('ev_created_by')));
    if (!empty($r2)) {
        $andersText = $stueckZu('ZZVR gewaehlt');
        $pruefe('bei jemand anderem stehen beide',
            str_contains($andersText, t('ev_responsible'))
            && str_contains($andersText, t('ev_created_by')));
    }

    foreach (rows("SELECT id FROM events WHERE title LIKE 'ZZVR%'") as $zz) $weg((int) $zz['id']);
    $pruefe('keine ZZVR-Termine zurückgelassen',
        (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZVR%'")['n'] === 0);
} else {
    echo '  (ohne Hostnamen: das Anlegen ueber die Route wurde nicht geprueft)' . PHP_EOL;
}

// Nichts stehen lassen.
$reste = (int) row('SELECT COUNT(*) n FROM events WHERE title LIKE ?', ['ZZVZ%'])['n'];
$pruefe('keine Testdaten zurueckgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
