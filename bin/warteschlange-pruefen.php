<?php
// Die Warteschlange für Mitteilungen (#383) von allen Seiten.
//
// Der Grund für eine eigene Datei: Hier kann alles still falsch werden. Eine
// Mitteilung, die nie hinausgeht, fehlt — und niemand vermisst, was er nicht
// kennt. Eine, die doppelt hinausgeht, nervt. Und eine, die nach drei
// Fehlversuchen ewig weiterprobiert, hält den Minutenlauf für immer fest.
//
// Was hier NICHT geprüft wird: ob eine Mitteilung tatsächlich auf einem
// Telefon ankommt. Das entscheidet ein fremder Dienst, und ein Testversand
// ginge an die Geräte echter Leute.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/warteschlange-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/warteschlange-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';
require_once $basis . '/app/push.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-58s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// Echte Themen, denn push_topics() kennt nur die aus PUSH_TOPICS — ein
// erfundenes Thema landete nie in der Schlange, und dann waere diese ganze
// Datei ein Pruefer, der nichts prueft. Erkannt werden die Testzeilen am
// Wortlaut, nicht am Thema.
const ZZQ = 'ZZQ-Pruefung';
q("DELETE FROM push_queue WHERE payload LIKE '%" . ZZQ . "%'");
$zahl = static fn(): int
    => (int) row("SELECT COUNT(*) n FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%'])['n'];

$wer = rows('SELECT id, role, substitute_for, pref_lang, push_topics FROM users ORDER BY id LIMIT 3');
if (!$wer) { fwrite(STDERR, "Keine Konten\n"); exit(2); }
$bau = static fn(string $lang): array
    => ['title' => ZZQ . ' ' . $lang, 'body' => '', 'url' => '/intern'];

// ------------------------------------------------ 1. Einreihen
// Gezaehlt wird gegen die, die das Thema auch wollen: Ein Konto, das alles
// abgewaehlt hat, bekommt keine Zeile - und genau das soll hier mitgeprueft
// werden, statt es als Fehler zu lesen.
$wollen = array_values(array_filter($wer,
    static fn(array $u): bool => in_array('events', push_topics($u), true)));
push_queue_add('events', $wer, $bau);
$pruefe('eine Zeile je Empfaenger, der das Thema will',
    $zahl() === count($wollen) && count($wollen) > 0);
$pruefe('und keine fuer die uebrigen', count($wollen) < count($wer)
    ? $zahl() < count($wer) : true);
$erste = row("SELECT * FROM push_queue WHERE payload LIKE ? ORDER BY id LIMIT 1", ['%' . ZZQ . '%']);
$pruefe('der Wortlaut steht fertig drin',
    str_contains((string) $erste['payload'], ZZQ));
$pruefe('und ist sofort faellig', strtotime((string) $erste['send_after']) <= time() + 1);
$pruefe('noch kein Versuch', (int) $erste['attempts'] === 0);

// Die Sprache des Empfaengers, nicht die des Absenders.
$einer = $wer[0];
q('UPDATE users SET pref_lang = ? WHERE id = ?', ['fr', (int) $einer['id']]);
$einerNeu = row('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id = ?',
                [(int) $einer['id']]);
push_queue_add('events', [$einerNeu], $bau);
$pruefe('gerendert in der Sprache des Empfaengers', (bool) row(
    "SELECT id FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . ' fr%']));
q('UPDATE users SET pref_lang = ? WHERE id = ?', [(string) $einer['pref_lang'], (int) $einer['id']]);

// ------------------------------------------------ 2. Abgewaehlt
$merk = (string) $einer['push_topics'];
q('UPDATE users SET push_topics = ? WHERE id = ?', ['events', (int) $einer['id']]);
$ohne = row('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id = ?',
            [(int) $einer['id']]);
$vorAbwahl = $zahl();
push_queue_add('events', [$ohne], $bau);
$pruefe('ein abgewaehltes Thema kommt nicht in die Schlange', $zahl() === $vorAbwahl);
q('UPDATE users SET push_topics = ? WHERE id = ?', [$merk, (int) $einer['id']]);

// ------------------------------------------------ 3. Sammeln
// Seit #404: Die ERSTE geht sofort, erst die folgenden werden gesammelt. Das
// Sammeln kostete sonst jede Antwort fuenf Minuten, auch die einzelne, auf
// die nie eine zweite folgt - und das ist der haeufige Fall.
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
q("DELETE FROM push_log WHERE collapse_key = 'zzq:7'");
$viele = static fn(int $n, string $lang): array
    => ['title' => ZZQ . ' ' . $n . ' Antworten', 'body' => '', 'url' => '/intern'];

push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('die erste Zeile ist sofort faellig', strtotime((string) row(
    "SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:7'")['send_after']) <= time() + 2);

// So tun, als waere sie hinausgegangen - das ist der Zustand, in dem gesammelt
// werden soll.
q("DELETE FROM push_queue WHERE collapse_key = 'zzq:7'");
q("INSERT INTO push_log (user_id, topic, collapse_key, outcome)
   VALUES (?, 'events', 'zzq:7', 'gesendet')", [(int) $einer['id']]);

push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('die naechste wartet auf Nachzuegler', strtotime((string) row(
    "SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:7'")['send_after']) > time() + 60);
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('drei weitere bleiben eine Zeile', $zahl() === 1);
$gesammelt = row("SELECT * FROM push_queue WHERE collapse_key = 'zzq:7'");
$pruefe('die Zeile zaehlt mit', (int) $gesammelt['collapse_count'] === 3);
$pruefe('und sagt es im Wortlaut', str_contains((string) $gesammelt['payload'], '3 Antworten'));

// Ein anderer Schluessel ist eine andere Sache - und dessen erste geht wieder
// sofort, weil zu ihm noch nichts hinausging.
push_queue_add('events', [$einer], $bau, 'zzq:8', $viele);
$pruefe('ein anderer Termin ist eine eigene Zeile', $zahl() === 2);
$pruefe('und dessen erste geht wieder sofort', strtotime((string) row(
    "SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:8'")['send_after']) <= time() + 2);

// Auch eine schon faellige Zeile sammelt ein (#406). Die Sorge, sie koenne
// dann ewig weiterwachsen und nie hinausgehen, traegt nicht: Ihr Zeitpunkt
// wird dabei NICHT verschoben, also nimmt der naechste Minutenlauf sie mit.
// Laenger als eine Minute kann sie nicht sammeln.
//
// Vorher war es andersherum, und genau daran ging die erste Mitteilung
// verloren: Die ist nach #404 sofort faellig und konnte deshalb nie
// Sammelziel sein - zwei Antworten vor dem ersten Versand ergaben zwei
// Mitteilungen statt einer.
$vorFaellig = (string) row("SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:7'")['send_after'];
q("UPDATE push_queue SET send_after = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE collapse_key = 'zzq:7'");
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('auch eine faellige Zeile sammelt ein', $zahl() === 2);
$nachher = row("SELECT collapse_count, send_after FROM push_queue WHERE collapse_key = 'zzq:7'");
$pruefe('und zaehlt weiter', (int) $nachher['collapse_count'] === 4);
$pruefe('ihr Zeitpunkt bleibt, sie geht also beim naechsten Lauf hinaus',
    strtotime((string) $nachher['send_after']) <= time());
q("DELETE FROM push_log WHERE collapse_key LIKE 'zzq%'");

// ------------------------------------------------ 4. Abholen
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
// Ein Konto ohne Geraet: Die Zeile wird beim Abholen verworfen, nicht gesendet.
$ohneGeraet = null;
foreach ($wer as $u) {
    if (!row('SELECT 1 FROM push_subscriptions WHERE user_id = ?', [(int) $u['id']])) {
        $ohneGeraet = $u;
        break;
    }
}
if ($ohneGeraet) {
    push_queue_add('events', [$ohneGeraet], $bau);
    $vorher = $zahl();
    $stand = push_queue_run();
    $pruefe('ohne Geraet wird die Zeile verworfen', $zahl() === $vorher - 1);
    $pruefe('und als verworfen gezaehlt', $stand['verworfen'] >= 1);
} else {
    echo 'jedes Testkonto hat ein Geraet - Verwerfpruefung uebersprungen', PHP_EOL;
}

// ------------------------------------------------ 5. Zu alt
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
push_queue_add('events', [$einer], $bau);
q("UPDATE push_queue SET created_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE payload LIKE ?",
  [PUSH_QUEUE_TTL_HOURS + 1, '%' . ZZQ . '%']);
$stand = push_queue_run();
$pruefe('was zu alt ist, geht ungesehen weg', $zahl() === 0);

// ------------------------------------------------ 6. Aufgeben
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
push_queue_add('events', [$einer], $bau);
q("UPDATE push_queue SET attempts = ? WHERE payload LIKE ?", [PUSH_QUEUE_TRIES, '%' . ZZQ . '%']);
$faellig = rows('SELECT * FROM push_queue WHERE send_after <= NOW() AND attempts < ?',
                [PUSH_QUEUE_TRIES]);
$pruefe('eine ausgereizte Zeile wird nicht mehr geholt', !array_filter($faellig,
    static fn(array $z): bool => str_contains((string) $z['payload'], ZZQ)));
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);

// ------------------------------------------------ 7. Die Sperre
// Zwei Laeufe duerfen nicht gleichzeitig nach denselben Zeilen greifen. Das
// ist kein Beiwerk: Im Minutentakt holt der naechste an, sobald ein Dienst
// einmal haengt - und dann ginge dieselbe Mitteilung zweimal hinaus.
$sperrdatei = sys_get_temp_dir() . '/bandregie-push-' . md5($basis) . '.lock';
$a = fopen($sperrdatei, 'c');
$pruefe('die Sperrdatei ist zu bekommen', $a !== false && flock($a, LOCK_EX | LOCK_NB));
$b = fopen($sperrdatei, 'c');
$pruefe('ein zweiter Lauf bekommt sie nicht', $b !== false && !flock($b, LOCK_EX | LOCK_NB));
flock($a, LOCK_UN);
$pruefe('nach dem Loslassen wieder frei', flock($b, LOCK_EX | LOCK_NB));
flock($b, LOCK_UN);
fclose($a);
fclose($b);

// ------------------------------- 8. Niemand sendet mehr in der Anfrage
$quelle = (string) file_get_contents($basis . '/app/push.php');
$pruefe('push.php haelt keinen Arbeiter mehr fest',
    !str_contains($quelle, 'register_shutdown_function'));

// ------------------- 9. Das Versandprotokoll (#405)
// Die Frage "bekommt der ueberhaupt Mitteilungen?" kostete einen Vormittag
// Erschliessungsarbeit. Jetzt steht sie in einer Tabelle - und diese Pruefung
// passt darauf auf, dass auch wirklich jeder Ausgang dort landet.
q("DELETE FROM push_log WHERE topic = 'events' AND collapse_key LIKE 'zzq%'");
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
$protokoll = static fn(): array
    => rows("SELECT * FROM push_log WHERE collapse_key LIKE 'zzq%' ORDER BY id");

// Ohne Geraet: muss als solcher vermerkt werden und nicht spurlos verschwinden.
if ($ohneGeraet) {
    push_queue_add('events', [$ohneGeraet], $bau, 'zzq:log1', $viele);
    q("UPDATE push_queue SET send_after = NOW() WHERE collapse_key = 'zzq:log1'");
    push_queue_run();
    $zeilen = $protokoll();
    $pruefe('ohne Geraet steht im Protokoll', count($zeilen) === 1);
    $pruefe('und zwar als "ohne_geraet"',
        ($zeilen[0]['outcome'] ?? '') === 'ohne_geraet');
    $pruefe('mit Empfaenger und Thema',
        (int) ($zeilen[0]['user_id'] ?? 0) === (int) $ohneGeraet['id']
        && ($zeilen[0]['topic'] ?? '') === 'events');
    $pruefe('aber ohne Wortlaut', !array_key_exists('payload', $zeilen[0] ?? []));
}

// Zu alt: ebenfalls vermerkt.
q("DELETE FROM push_log WHERE collapse_key LIKE 'zzq%'");
push_queue_add('events', [$einer], $bau, 'zzq:log2', $viele);
q("UPDATE push_queue SET created_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE collapse_key = 'zzq:log2'",
  [PUSH_QUEUE_TTL_HOURS + 1]);
push_queue_run();
$zeilen = $protokoll();
$pruefe('eine abgelaufene Zeile wird vermerkt',
    count($zeilen) === 1 && ($zeilen[0]['outcome'] ?? '') === 'abgelaufen');

// ------------------- 10. Erste sofort, der Rest gesammelt (#404)
// Das Sammeln kostete sonst JEDE Antwort fuenf Minuten, auch die einzelne.
q("DELETE FROM push_log WHERE collapse_key LIKE 'zzq%'");
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
push_queue_add('events', [$einer], $bau, 'zzq:sofort', $viele);
$erste = row("SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:sofort'");
$pruefe('ohne frischen Versand geht die erste sofort',
    strtotime((string) $erste['send_after']) <= time() + 2);

// Jetzt so tun, als waere gerade eine hinausgegangen.
q("DELETE FROM push_queue WHERE collapse_key = 'zzq:sofort'");
q("INSERT INTO push_log (user_id, topic, collapse_key, outcome)
   VALUES (?, 'events', 'zzq:sofort', 'gesendet')", [(int) $einer['id']]);
$pruefe('push_recently_sent erkennt das',
    push_recently_sent('zzq:sofort', PUSH_QUEUE_COLLAPSE));
push_queue_add('events', [$einer], $bau, 'zzq:sofort', $viele);
$zweite = row("SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:sofort'");
$pruefe('die naechste wartet dann die Sammelfrist ab',
    strtotime((string) $zweite['send_after']) > time() + 60);

// Ein alter Versand zaehlt nicht mehr.
q("UPDATE push_log SET created_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE collapse_key = 'zzq:sofort'");
$pruefe('ein alter Versand zaehlt nicht mehr',
    !push_recently_sent('zzq:sofort', PUSH_QUEUE_COLLAPSE));
$pruefe('und ohne Sammelschluessel ist nie etwas "frisch"',
    !push_recently_sent('', PUSH_QUEUE_COLLAPSE));

q("DELETE FROM push_log WHERE collapse_key LIKE 'zzq%'");
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);

// ------------------- 11. Erinnerung nach einer Absage (#403)
// Eine Erinnerung, die zu oft kommt, ist schlimmer als keine: Sie geht aufs
// Telefon, der Empfaenger hat nichts falsch gemacht, und abstellen laesst sie
// sich nur, indem man alle abstellt. Deshalb steht hier vor allem, wann sie
// NICHT kommen darf.
q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
q("DELETE FROM push_log WHERE topic = 'erinnerung'");
q("DELETE FROM events WHERE title LIKE 'ZZER%'");

$absage = static function (string $art, string $datum, int $wer, string $wann): int {
    q('INSERT INTO events (type, title, date, status, created_by) VALUES (?,?,?,?,1)',
      [$art, 'ZZER ' . $art, $datum, 'bestaetigt']);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    q('INSERT INTO attendance (event_id, user_id, status, created_at) VALUES (?,?,?,?)',
      [$nr, $wer, 'no', $wann]);
    return $nr;
};
$lange_her = date('Y-m-d H:i:s', time() - (PUSH_REMIND_AFTER_HOURS + 2) * 3600);
$gerade = date('Y-m-d H:i:s');
$inDerSchlange = static fn(): int
    => (int) row("SELECT COUNT(*) n FROM push_queue WHERE topic = 'erinnerung'")['n'];

// Eine Probe, vor zwei Tagen abgesagt, ohne Alternative: Das ist der Fall.
$e1 = $absage('probe', '2027-12-20', (int) $einer['id'], $lange_her);
push_reminders_run();
$pruefe('Probe, lange abgesagt, ohne Alternative: erinnert', $inDerSchlange() === 1);

// Ein zweites Mal wird nicht erinnert.
q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
q("INSERT INTO push_log (user_id, topic, collapse_key, outcome)
   VALUES (?, 'erinnerung', ?, 'gesendet')",
  [(int) $einer['id'], 'erinnerung:' . $e1 . ':' . (int) $einer['id']]);
push_reminders_run();
$pruefe('und kein zweites Mal', $inDerSchlange() === 0);
q("DELETE FROM push_log WHERE topic = 'erinnerung'");
q('DELETE FROM events WHERE id = ?', [$e1]);
q('DELETE FROM attendance WHERE event_id = ?', [$e1]);

// Gerade erst abgesagt: Das Fenster aus #401 steht ja noch auf dem Schirm.
q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
$e2 = $absage('probe', '2027-12-21', (int) $einer['id'], $gerade);
push_reminders_run();
$pruefe('gerade erst abgesagt: noch nicht', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id = ?', [$e2]);
q('DELETE FROM attendance WHERE event_id = ?', [$e2]);

// Ein Gig: Dessen Datum macht der Veranstalter.
$e3 = $absage('gig', '2027-12-22', (int) $einer['id'], $lange_her);
push_reminders_run();
$pruefe('beim Gig gibt es nichts zu verschieben', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id = ?', [$e3]);
q('DELETE FROM attendance WHERE event_id = ?', [$e3]);

// Mit Alternative: erledigt.
$e4 = $absage('probe', '2027-12-23', (int) $einer['id'], $lange_her);
q("INSERT INTO events (type, title, date, status, created_by, alternative_for)
   VALUES ('probe', 'ZZER Ersatz', '2027-12-24', 'bestaetigt', 1, ?)", [$e4]);
$e4b = (int) $GLOBALS['db']->lastInsertId();
push_reminders_run();
$pruefe('mit eingetragener Alternative: erledigt', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id IN (?,?)', [$e4, $e4b]);
q('DELETE FROM attendance WHERE event_id = ?', [$e4]);

// Ein Termin in der Vergangenheit: da ist nichts mehr zu retten.
$e5 = $absage('probe', date('Y-m-d', strtotime('-3 days')), (int) $einer['id'], $lange_her);
push_reminders_run();
$pruefe('ein vergangener Termin erinnert nicht', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id = ?', [$e5]);
q('DELETE FROM attendance WHERE event_id = ?', [$e5]);

// Ein abgesagter Termin: niemand muss eine Alternative dafuer suchen.
$e6 = $absage('probe', '2027-12-25', (int) $einer['id'], $lange_her);
q("UPDATE events SET status = 'abgesagt' WHERE id = ?", [$e6]);
push_reminders_run();
$pruefe('ein abgesagter Termin erinnert nicht', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id = ?', [$e6]);
q('DELETE FROM attendance WHERE event_id = ?', [$e6]);

// Wer zugesagt hat, wird nicht erinnert.
$e7 = $absage('probe', '2027-12-26', (int) $einer['id'], $lange_her);
q("UPDATE attendance SET status = 'yes' WHERE event_id = ?", [$e7]);
push_reminders_run();
$pruefe('wer zugesagt hat, wird nicht erinnert', $inDerSchlange() === 0);
q('DELETE FROM events WHERE id = ?', [$e7]);
q('DELETE FROM attendance WHERE event_id = ?', [$e7]);

// Das Thema laesst sich abstellen.
$pruefe("'erinnerung' steht in PUSH_TOPICS", in_array('erinnerung', PUSH_TOPICS, true));
$pruefe('und hat eine Beschriftung', t('push_topic_erinnerung') !== 'push_topic_erinnerung');
$pruefe('und ist abwaehlbar',
    !in_array('erinnerung', push_topics(['push_topics' => 'erinnerung']), true));

// Der Stundentakt: zweimal hintereinander laeuft die Abfrage nur einmal.
q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
$merkMarke = (string) setting('push_remind_last', '0');
set_setting('push_remind_last', '0');
$e8 = $absage('probe', '2027-12-27', (int) $einer['id'], $lange_her);
push_reminders_hourly();
$nach_erstem = $inDerSchlange();
q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
push_reminders_hourly();
$pruefe('stuendlich: der erste Lauf erinnert', $nach_erstem === 1);
$pruefe('der zweite Lauf gleich danach nicht', $inDerSchlange() === 0);
set_setting('push_remind_last', $merkMarke);
q('DELETE FROM events WHERE id = ?', [$e8]);
q('DELETE FROM attendance WHERE event_id = ?', [$e8]);

q("DELETE FROM push_queue WHERE topic = 'erinnerung'");
q("DELETE FROM push_log WHERE topic = 'erinnerung'");
$pruefe('keine ZZER-Termine zurueckgelassen',
    (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZER%'")['n'] === 0);

// ------------------- 12. Wohin eine Mitteilung führt (#421)
//
// Die Regel: Wer antworten soll, landet in der Übersicht auf seinem Punkt —
// dort stehen die Knöpfe, und die Seite geht auch ohne Bereichsrecht auf. Wer
// nur unterrichtet wird, landet bei der Sache selbst.
//
// Geprüft werden beide Hälften. Nur die erste wäre auch erfüllt, wenn jede
// Mitteilung in die Übersicht führte — und dann fände niemand mehr den
// Kommentar, über den er benachrichtigt wurde.
q("DELETE FROM push_queue WHERE topic IN ('events', 'aufgaben')");
$zielVon = static function (): string {
    $z = row("SELECT payload FROM push_queue ORDER BY id DESC LIMIT 1");
    $d = $z ? json_decode((string) $z['payload'], true) : null;
    return (string) ($d['url'] ?? ($d['de']['url'] ?? ''));
};

$pruefe("answer_url() zeigt in die Übersicht",
    answer_url('aufgabe', 17) === '/intern#offen-aufgabe17');
$pruefe('item_url() zeigt weiter auf die Sache selbst',
    str_starts_with(item_url('task', 17), '/intern/aufgaben'));

// Eine Aufgabe, die jemandem zugewiesen wird.
q("DELETE FROM tasks WHERE title LIKE 'ZZZIEL%'");
q("INSERT INTO tasks (title, status, created_by, required_done) VALUES ('ZZZIEL Aufgabe', 'offen', 1, 0)");
$zielAufgabe = (int) $GLOBALS['db']->lastInsertId();
// Ein Konto, das Mitteilungen überhaupt will: $wer[1] war auf der
// Entwicklungsanlage eines mit abbestellten Themen, und dann prüfte das hier
// nur, dass eine Abbestellung wirkt.
$zielKonto = null;
foreach (rows('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id <> 1') as $zu) {
    if (in_array('aufgaben', push_topics($zu), true)) { $zielKonto = $zu; break; }
}
$pruefe('ein Konto gefunden, das Aufgaben-Mitteilungen will', $zielKonto !== null);
$zielWer = (int) ($zielKonto['id'] ?? 0);
q('INSERT INTO task_assignees (task_id, user_id) VALUES (?,?)', [$zielAufgabe, $zielWer]);
q("DELETE FROM push_queue WHERE topic = 'aufgaben'");
task_assigned_notify($zielAufgabe, [$zielWer], 1);
$pruefe('eine zugewiesene Aufgabe meldet sich überhaupt',
    (int) row("SELECT COUNT(*) n FROM push_queue WHERE topic = 'aufgaben'")['n'] === 1);
$pruefe('und führt in die Übersicht auf die Zeile',
    $zielVon() === '/intern#offen-aufgabe' . $zielAufgabe);

// Wer selbst zuweist, bekommt keine Mitteilung von sich.
q("DELETE FROM push_queue WHERE topic = 'aufgaben'");
task_assigned_notify($zielAufgabe, [$zielWer], $zielWer);
$pruefe('wer sich selbst zuständig macht, bekommt nichts',
    (int) row("SELECT COUNT(*) n FROM push_queue WHERE topic = 'aufgaben'")['n'] === 0);

// Abwählbar wie jedes andere Thema.
$pruefe("'aufgaben' steht in PUSH_TOPICS", in_array('aufgaben', PUSH_TOPICS, true));
$pruefe('und hat eine Beschriftung', t('push_topic_aufgaben') !== 'push_topic_aufgaben');
$pruefe('und ist abwählbar',
    !in_array('aufgaben', push_topics(['push_topics' => 'aufgaben']), true));

q('DELETE FROM task_assignees WHERE task_id = ?', [$zielAufgabe]);
q('DELETE FROM tasks WHERE id = ?', [$zielAufgabe]);
q("DELETE FROM push_queue WHERE topic = 'aufgaben'");

// Und die Gegenrichtung: Ein Zeitvorschlag will eine Antwort, die neue Zeit
// unterrichtet nur.
q("DELETE FROM events WHERE title LIKE 'ZZZIEL%'");
q("INSERT INTO events (type, title, date, time, time_meet, time_end, status, created_by)
   VALUES ('probe', 'ZZZIEL Termin', DATE_ADD(CURDATE(), INTERVAL 25 DAY), '19:00', '18:30', '22:00', 'bestaetigt', 1)");
$zielEv = (int) $GLOBALS['db']->lastInsertId();
q("INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,'yes')", [$zielEv, $zielWer]);
q("DELETE FROM push_queue WHERE topic = 'events'");
event_time_propose($zielEv, ['time' => '20:00', 'time_meet' => '19:30', 'time_end' => '23:00'], 1);
$pruefe('der Zeitvorschlag führt in die Übersicht',
    $zielVon() === '/intern#offen-zeit' . $zielEv);
q("DELETE FROM push_queue WHERE topic = 'events'");
event_time_apply($zielEv);
$pruefe('die geltende neue Zeit führt zum Termin',
    str_starts_with($zielVon(), '/intern/termine'));

q("DELETE FROM push_queue WHERE topic = 'events'");
q("DELETE FROM attendance WHERE event_id = ?", [$zielEv]);
q("DELETE FROM event_time_confirms WHERE event_id = ?", [$zielEv]);
q("DELETE FROM events WHERE id = ?", [$zielEv]);
$pruefe('keine ZZZIEL-Daten zurückgelassen',
    (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZZIEL%'")['n'] === 0
    && (int) row("SELECT COUNT(*) n FROM tasks WHERE title LIKE 'ZZZIEL%'")['n'] === 0);

$pruefe('nichts stehen gelassen', $zahl() === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
