<?php
// Die Tagesmail durchspielen (#332) — OHNE zu senden.
//
// Geprüft werden die Teile: Fälligkeit, Sammeln, Sichtbarkeit, Text und die
// Gültigkeit der Weiterleitungsziele. digest_send() selbst wird bewusst nicht
// aufgerufen; eine Prüfung, die Post verschickt, ist keine Prüfung.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver:
//
//   sudo -u www-data php bin/tagesmail-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/tagesmail-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

$leute = rows('SELECT * FROM users ORDER BY id LIMIT 2');
if (count($leute) < 2) { fwrite(STDERR, "Zwei Konten werden gebraucht\n"); exit(2); }
[$A, $B] = $leute;

// ------------------------------------------------- 1. Weiterleitungsziele
$pruefe('eigener Pfad ist erlaubt', login_weiter_gueltig('/intern/termine'));
$pruefe('fremde Adresse abgewiesen', !login_weiter_gueltig('https://example.org/'));
$pruefe('schemaloses //host abgewiesen', !login_weiter_gueltig('//example.org/intern'));
$pruefe('Rücksprung mit .. abgewiesen', !login_weiter_gueltig('/intern/../../etc'));
$pruefe('Zeilenumbruch abgewiesen', !login_weiter_gueltig("/intern\nLocation: x"));
$pruefe('leeres Ziel abgewiesen', !login_weiter_gueltig(''));
$pruefe('Pfad ausserhalb /intern abgewiesen', !login_weiter_gueltig('/logout'));

// ------------------------------------------------------------ 2. Fälligkeit
$morgen = array_merge($A, ['digest_freq' => 'taeglich', 'digest_hour' => 23, 'digest_sent_at' => null]);
$pruefe('vor der eigenen Uhrzeit: nicht fällig', !digest_faellig($morgen) || (int) date('G') >= 23);
$aus = array_merge($A, ['digest_freq' => 'aus', 'digest_hour' => 0, 'digest_sent_at' => null]);
$pruefe('abgeschaltet: nie fällig', !digest_faellig($aus));
$frisch = array_merge($A, ['digest_freq' => 'taeglich', 'digest_hour' => 0, 'digest_sent_at' => null]);
$pruefe('noch nie gesendet: fällig', digest_faellig($frisch));
$heute = array_merge($frisch, ['digest_sent_at' => date('Y-m-d H:i:s')]);
$pruefe('heute schon gesendet: nicht fällig', !digest_faellig($heute));
$gestern = array_merge($frisch, ['digest_sent_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
$pruefe('gestern gesendet, täglich: fällig', digest_faellig($gestern));
$zweitage = array_merge($gestern, ['digest_freq' => '2tage']);
$pruefe('gestern gesendet, alle 2 Tage: nicht fällig', !digest_faellig($zweitage));
$vorgestern = array_merge($zweitage, ['digest_sent_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
$pruefe('vorgestern gesendet, alle 2 Tage: fällig', digest_faellig($vorgestern));

// ------------------------------------------- 3. Sammeln und Sichtbarkeit
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Tagesmail','2027-06-06','bestaetigt')");
$ev = (int) $GLOBALS['db']->lastInsertId();
item_new('event', $ev, (int) $B['id']);

$gefunden = static function (array $abschnitte, string $text): bool {
    foreach ($abschnitte as $a) {
        foreach ($a['eintraege'] as $e) if (str_contains($e['kopf'], $text)) return true;
    }
    return false;
};
$abschnitteA = digest_collect($A);
$pruefe('der andere findet den neuen Termin', $gefunden($abschnitteA, 'ZZ Tagesmail'));
$pruefe('der Urheber selbst nicht', !$gefunden(digest_collect($B), 'ZZ Tagesmail'));

$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
if ($aushilfe) {
    $pruefe('Aushilfe darf den Termin nicht sehen', !may_see_event($aushilfe, $ev));
    $pruefe('und bekommt ihn nicht in die Mail', !$gefunden(digest_collect($aushilfe), 'ZZ Tagesmail'));
} else {
    echo 'keine Aushilfe vorhanden — Sichtbarkeitsprüfung übersprungen', PHP_EOL;
}

// ------------------------------------------------------------------ 4. Text
$text = digest_text($A, $abschnitteA);
$pruefe('Text nennt den Termin', str_contains($text, 'ZZ Tagesmail'));
$pruefe('Text führt über die Anmeldung', str_contains($text, '/login?weiter='));
$pruefe('Link ist vollständig', str_contains($text, 'http'));
$pruefe('Text nennt das Profil zum Abstellen', str_contains($text, '/intern/profil'));
$pruefe('kein HTML im Text', !str_contains($text, '<'));

// ------------------------------- 5. Nichts offen heisst keine Mail
items_mark_seen($A, 'event', [$ev]);
$leer = digest_collect($A);
$pruefe('nach dem Ansehen nicht mehr in der Mail', !$gefunden($leer, 'ZZ Tagesmail'));

item_forget('event', $ev);
q('DELETE FROM events WHERE id = ?', [$ev]);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
