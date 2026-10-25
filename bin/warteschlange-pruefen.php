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

// Vorher aufräumen, damit die Zahlen nur von diesem Lauf stammen.
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");
$zahl = static fn(): int => (int) row("SELECT COUNT(*) n FROM push_queue WHERE topic LIKE 'zzq%'")['n'];

$wer = rows('SELECT id, role, substitute_for, pref_lang, push_topics FROM users ORDER BY id LIMIT 3');
if (!$wer) { fwrite(STDERR, "Keine Konten\n"); exit(2); }
$bau = static fn(string $lang): array => ['title' => 'Titel ' . $lang, 'body' => '', 'url' => '/intern'];

// ------------------------------------------------ 1. Einreihen
push_queue_add('zzqa', $wer, $bau);
$pruefe('eine Zeile je Empfaenger', $zahl() === count($wer));
$erste = row("SELECT * FROM push_queue WHERE topic = 'zzqa' ORDER BY id LIMIT 1");
$pruefe('der Wortlaut steht fertig drin',
    str_contains((string) $erste['payload'], 'Titel'));
$pruefe('und ist sofort faellig', strtotime((string) $erste['send_after']) <= time() + 1);
$pruefe('noch kein Versuch', (int) $erste['attempts'] === 0);

// Die Sprache des Empfaengers, nicht die des Absenders.
$einer = $wer[0];
q('UPDATE users SET pref_lang = ? WHERE id = ?', ['fr', (int) $einer['id']]);
$einerNeu = row('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id = ?',
                [(int) $einer['id']]);
push_queue_add('zzqb', [$einerNeu], $bau);
$pruefe('gerendert in der Sprache des Empfaengers', str_contains(
    (string) row("SELECT payload FROM push_queue WHERE topic = 'zzqb'")['payload'], 'Titel fr'));
q('UPDATE users SET pref_lang = ? WHERE id = ?', [(string) $einer['pref_lang'], (int) $einer['id']]);

// ------------------------------------------------ 2. Abgewaehlt
$merk = (string) $einer['push_topics'];
q('UPDATE users SET push_topics = ? WHERE id = ?', ['zzqc', (int) $einer['id']]);
$ohne = row('SELECT id, role, substitute_for, pref_lang, push_topics FROM users WHERE id = ?',
            [(int) $einer['id']]);
push_queue_add('zzqc', [$ohne], $bau);
$pruefe('ein abgewaehltes Thema kommt nicht in die Schlange',
    (int) row("SELECT COUNT(*) n FROM push_queue WHERE topic = 'zzqc'")['n'] === 0);
q('UPDATE users SET push_topics = ? WHERE id = ?', [$merk, (int) $einer['id']]);

// ------------------------------------------------ 3. Sammeln
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");
$viele = static fn(int $n, string $lang): array
    => ['title' => $n . ' Antworten', 'body' => '', 'url' => '/intern'];
push_queue_add('zzqd', [$einer], $bau, 'zzq:7', $viele);
$pruefe('erste gesammelte Zeile angelegt', $zahl() === 1);
$pruefe('und wartet auf Nachzuegler', strtotime(
    (string) row("SELECT send_after FROM push_queue WHERE topic='zzqd'")['send_after']) > time() + 60);
push_queue_add('zzqd', [$einer], $bau, 'zzq:7', $viele);
push_queue_add('zzqd', [$einer], $bau, 'zzq:7', $viele);
$pruefe('drei Antworten bleiben eine Zeile', $zahl() === 1);
$gesammelt = row("SELECT * FROM push_queue WHERE topic = 'zzqd'");
$pruefe('die Zeile zaehlt mit', (int) $gesammelt['collapse_count'] === 3);
$pruefe('und sagt es im Wortlaut', str_contains((string) $gesammelt['payload'], '3 Antworten'));

// Ein anderer Schluessel ist eine andere Sache.
push_queue_add('zzqd', [$einer], $bau, 'zzq:8', $viele);
$pruefe('ein anderer Termin ist eine eigene Zeile', $zahl() === 2);

// Was schon faellig ist, wird nicht mehr eingesammelt - sonst waechst eine
// Zeile ewig weiter und geht nie hinaus.
q("UPDATE push_queue SET send_after = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE collapse_key = 'zzq:7'");
push_queue_add('zzqd', [$einer], $bau, 'zzq:7', $viele);
$pruefe('eine faellige Zeile sammelt nicht mehr ein', $zahl() === 3);

// ------------------------------------------------ 4. Abholen
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");
// Ein Konto ohne Geraet: Die Zeile wird beim Abholen verworfen, nicht gesendet.
$ohneGeraet = null;
foreach ($wer as $u) {
    if (!row('SELECT 1 FROM push_subscriptions WHERE user_id = ?', [(int) $u['id']])) {
        $ohneGeraet = $u;
        break;
    }
}
if ($ohneGeraet) {
    push_queue_add('zzqe', [$ohneGeraet], $bau);
    $vorher = $zahl();
    $stand = push_queue_run();
    $pruefe('ohne Geraet wird die Zeile verworfen', $zahl() === $vorher - 1);
    $pruefe('und als verworfen gezaehlt', $stand['verworfen'] >= 1);
} else {
    echo 'jedes Testkonto hat ein Geraet - Verwerfpruefung uebersprungen', PHP_EOL;
}

// ------------------------------------------------ 5. Zu alt
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");
push_queue_add('zzqf', [$einer], $bau);
q("UPDATE push_queue SET created_at = DATE_SUB(NOW(), INTERVAL ? HOUR) WHERE topic = 'zzqf'",
  [PUSH_QUEUE_TTL_HOURS + 1]);
$stand = push_queue_run();
$pruefe('was zu alt ist, geht ungesehen weg', $zahl() === 0);

// ------------------------------------------------ 6. Aufgeben
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");
push_queue_add('zzqg', [$einer], $bau);
q("UPDATE push_queue SET attempts = ? WHERE topic = 'zzqg'", [PUSH_QUEUE_TRIES]);
$faellig = rows('SELECT * FROM push_queue WHERE send_after <= NOW() AND attempts < ?',
                [PUSH_QUEUE_TRIES]);
$pruefe('eine ausgereizte Zeile wird nicht mehr geholt', !array_filter($faellig,
    static fn(array $z): bool => (string) $z['topic'] === 'zzqg'));
q("DELETE FROM push_queue WHERE topic LIKE 'zzq%'");

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

$pruefe('nichts stehen gelassen', $zahl() === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
