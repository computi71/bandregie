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
q("DELETE FROM push_queue WHERE payload LIKE ?", ['%' . ZZQ . '%']);
$viele = static fn(int $n, string $lang): array
    => ['title' => ZZQ . ' ' . $n . ' Antworten', 'body' => '', 'url' => '/intern'];
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('erste gesammelte Zeile angelegt', $zahl() === 1);
$pruefe('und wartet auf Nachzuegler', strtotime((string) row(
    "SELECT send_after FROM push_queue WHERE collapse_key = 'zzq:7'")['send_after']) > time() + 60);
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('drei Antworten bleiben eine Zeile', $zahl() === 1);
$gesammelt = row("SELECT * FROM push_queue WHERE collapse_key = 'zzq:7'");
$pruefe('die Zeile zaehlt mit', (int) $gesammelt['collapse_count'] === 3);
$pruefe('und sagt es im Wortlaut', str_contains((string) $gesammelt['payload'], '3 Antworten'));

// Ein anderer Schluessel ist eine andere Sache.
push_queue_add('events', [$einer], $bau, 'zzq:8', $viele);
$pruefe('ein anderer Termin ist eine eigene Zeile', $zahl() === 2);

// Was schon faellig ist, wird nicht mehr eingesammelt - sonst waechst eine
// Zeile ewig weiter und geht nie hinaus.
q("UPDATE push_queue SET send_after = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE collapse_key = 'zzq:7'");
push_queue_add('events', [$einer], $bau, 'zzq:7', $viele);
$pruefe('eine faellige Zeile sammelt nicht mehr ein', $zahl() === 3);

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

$pruefe('nichts stehen gelassen', $zahl() === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
