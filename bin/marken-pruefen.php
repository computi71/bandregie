<?php
// Die Marken „neu" und „geändert" durchspielen (#321).
//
// Das Projekt hat keine Testsammlung; diese Datei ist die billigste Absicherung
// für den einen Bereich, dessen Fehler man sonst erst Wochen später bemerkt —
// eine Marke, die bleibt, oder eine, die nie erscheint, sieht niemand sofort.
//
// SIE SCHREIBT TESTDATEN. Deshalb nur auf dem Entwicklungsserver, nie auf einer
// Bandinstanz: Sie legt einen Termin an, ändert ihn mehrfach und löscht ihn
// wieder, und sie setzt Lesestände für zwei vorhandene Konten.
//
//   php bin/marken-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1 — damit sie sich auch anhängen
// lässt, ohne dass jemand die Ausgabe liest.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/marken-pruefen.php <Verzeichnis> --schreibt-testdaten
");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-52s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// Zwei beliebige Konten: eines ändert, das andere sieht die Marke.
$beide = rows('SELECT * FROM users ORDER BY id LIMIT 2');
if (count($beide) < 2) { fwrite(STDERR, "Zwei Konten werden gebraucht
"); exit(2); }
[$ICH, $ANDERER] = $beide;

// ---------------------------------------------------------------- 1. anlegen
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Pruefung','2027-03-03','bestaetigt')");
$ev = (int) $GLOBALS['db']->lastInsertId();
item_new('event', $ev, (int) $ANDERER['id']);

$pruefe('neu angelegt: der andere sieht die Marke', isset(items_unseen($ICH, 'event')[$ev]));
$pruefe('neu angelegt: steht als „neu", nicht „geändert"', items_unseen($ICH, 'event')[$ev]['neu'] === true);
$pruefe('neu angelegt: der Verfasser selbst sieht nichts', !isset(items_unseen($ANDERER, 'event')[$ev]));

// ---------------------------------------------------------------- 2. ansehen
item_mark_seen($ICH, 'event', $ev);
$pruefe('angesehen: Marke weg', !isset(items_unseen($ICH, 'event')[$ev]));

// ------------------------------------------------- 3. Wichtiges ändern
q('UPDATE events SET created_at = created_at - INTERVAL 10 MINUTE WHERE id = ?', [$ev]);
$geaendert = item_update('event', $ev, static fn() => q("UPDATE events SET time = '20:00' WHERE id = ?", [$ev]),
                         (int) $ANDERER['id']);
$pruefe('Uhrzeit geändert: markiert', $geaendert);
$marke = items_unseen($ICH, 'event')[$ev] ?? null;
$pruefe('Uhrzeit geändert: steht als „geändert"', $marke && $marke['neu'] === false);
$pruefe('Uhrzeit geändert: mit Namen', ($marke['wer'] ?? '') === $ANDERER['name']);

// ------------------------------------------- 4. Nebensächliches ändern
item_mark_seen($ICH, 'event', $ev);
$egal = item_update('event', $ev, static fn() => q("UPDATE events SET invoice_no = 'RE-1' WHERE id = ?", [$ev]),
                    (int) $ANDERER['id']);
$pruefe('Rechnungsnummer geändert: keine Marke', !$egal && !isset(items_unseen($ICH, 'event')[$ev]));

// ------------------------------------------------- 5. eigene Änderung
item_update('event', $ev, static fn() => q("UPDATE events SET time = '21:00' WHERE id = ?", [$ev]),
            (int) $ICH['id']);
$pruefe('selbst geändert: für einen selbst keine Marke', !isset(items_unseen($ICH, 'event')[$ev]));
$pruefe('selbst geändert: die anderen sehen sie', isset(items_unseen($ANDERER, 'event')[$ev]));

// ------------------------------------------------ 6. gesammelt ansehen
items_mark_seen($ANDERER, 'event', [$ev]);
$pruefe('gesammelt angesehen: Marke weg', !isset(items_unseen($ANDERER, 'event')[$ev]));

// ------------------------------------------------ 7. löschen räumt auf
item_forget('event', $ev);
q('DELETE FROM events WHERE id = ?', [$ev]);
$pruefe('gelöscht: keine Marke bleibt zurück',
    (int) row('SELECT COUNT(*) n FROM seen_marks WHERE kind = ? AND item_id = ?', ['event', $ev])['n'] === 0);

// --------------------------------------- 8. Der Chat erreicht keine Aushilfe
$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
if ($aushilfe) {
    $thema = (int) (row('SELECT id FROM topics LIMIT 1')['id'] ?? 0);
    $pruefe('Aushilfe darf das Thema nicht sehen', $thema > 0 && !may_see_topic($aushilfe, $thema));
    $pruefe('Aushilfe zählt keine Beiträge', topic_unread($aushilfe) === []);
} else {
    echo 'keine Aushilfe vorhanden — Prüfung 8 übersprungen', PHP_EOL;
}

// ------------------------- 9. Jede Sorte ist vollständig verdrahtet
// Der Fehler, den man sonst erst Wochen später bemerkt (#331): Eine Sorte
// steht in ITEM_KINDS, aber ihre Tabelle hat die Spalten nicht, oder
// /intern/gesehen kennt sie nicht. Dann erscheint entweder nie eine Marke
// oder sie lässt sich nie wegklicken — und beides sieht von außen aus wie
// „in diesem Bereich passiert eben nichts".
//
// attendance ist der Grund, warum die id eigens geprüft wird: Die Tabelle
// hatte nur einen zusammengesetzten Schlüssel, und die Marken sprechen jede
// Zeile über i.id an.
$quelle = (string) file_get_contents($basis . '/httpdocs/index.php');
foreach (ITEM_KINDS as $sorte => $art) {
    $spalten = array_column(rows('SHOW COLUMNS FROM `' . $art['tabelle'] . '`'), 'Field');
    $pruefe("$sorte: Tabelle hat id", in_array('id', $spalten, true));
    $pruefe("$sorte: Spalte " . $art['wann'] . " vorhanden", in_array($art['wann'], $spalten, true));
    $pruefe("$sorte: Spalte " . $art['wer'] . " vorhanden", in_array($art['wer'], $spalten, true));
    $pruefe("$sorte: verglichene Felder vorhanden", array_diff($art['felder'], $spalten) === []);
    $pruefe("$sorte: /intern/gesehen kennt sie", str_contains($quelle, "'$sorte' => fn(int \$nr)"));
}

// ----------------- 10. Kommentare: neu, gesehen, und mit dem Termin weg
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Kommentar','2027-03-04','bestaetigt')");
$evK = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO comments (event_id, user_id, text) VALUES (?,?,?)',
  [$evK, (int) $ANDERER['id'], 'ZZ Pruefkommentar']);
$kom = (int) $GLOBALS['db']->lastInsertId();
$pruefe('Kommentar: der andere sieht ihn als „neu"',
    (items_unseen($ICH, 'comment')[$kom]['neu'] ?? null) === true);
$pruefe('Kommentar: der Verfasser selbst sieht nichts',
    !isset(items_unseen($ANDERER, 'comment')[$kom]));
items_mark_seen($ICH, 'comment', [$kom]);
$pruefe('Kommentar: angesehen, Marke weg', !isset(items_unseen($ICH, 'comment')[$kom]));
item_forget('comment', $kom);
q('DELETE FROM comments WHERE id = ?', [$kom]);
q('DELETE FROM events WHERE id = ?', [$evK]);
$pruefe('Kommentar: gelöscht, keine Marke bleibt zurück',
    (int) row('SELECT COUNT(*) n FROM seen_marks WHERE kind = ? AND item_id = ?', ['comment', $kom])['n'] === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
