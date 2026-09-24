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
foreach (ITEM_KINDS as $sorte => $art) {
    $spalten = array_column(rows('SHOW COLUMNS FROM `' . $art['tabelle'] . '`'), 'Field');
    $pruefe("$sorte: Tabelle hat id", in_array('id', $spalten, true));
    $pruefe("$sorte: Spalte " . $art['wann'] . " vorhanden", in_array($art['wann'], $spalten, true));
    $pruefe("$sorte: Spalte " . $art['wer'] . " vorhanden", in_array($art['wer'], $spalten, true));
    $pruefe("$sorte: verglichene Felder vorhanden", array_diff($art['felder'], $spalten) === []);
    // Millisekunden, nicht Sekunden (#338). Wer eine Karte liest, waehrend
    // jemand anderes sie speichert, bekaeme die Aenderung sonst nie zu sehen -
    // einmal im Jahr, nicht nachstellbar, und deshalb am teuersten zu suchen.
    // Die zehn mit #331 hinzugekommenen Tabellen trugen genau deshalb monatelang
    // eine Ungenauigkeit, die niemand bemerkt haette.
    $typ = (string) (row('SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                         [$art['tabelle'], $art['wann']])['t'] ?? '');
    $pruefe("$sorte: " . $art['wann'] . " in Millisekunden ($typ)", str_contains($typ, '(3)'));
}

// Jede Sorte muss in item_visible() einen Arm haben (#335). Vorher stand hier
// eine Suche im Quelltext nach der alten Prüfkarte in /intern/gesehen; die gibt
// es nicht mehr, und Text zu durchsuchen war ohnehin schwächer als zu fragen.
//
// Die Falle: Ein fehlender match-Arm landet im default und heißt „nein" - und
// eine erfundene Nummer ergibt bei einer verdrahteten Sorte genauso „nein".
// Eine ausgedachte Nummer beweist also gar nichts. Was beweist: eine ECHTE
// Zeile und ein Admin-Konto, das jedes Recht hat. Sorten ohne Zeile in dieser
// Datenbank lassen sich so nicht prüfen und werden als übersprungen gemeldet,
// statt still durchzugehen.
//
// Eine Ausnahme, und sie ist keine Nachlässigkeit: Eine private Auslage in der
// Kasse gehört dem Mitglied, nicht der Bandleitung - auch ein Admin sieht sie
// nicht. Deshalb wird dort eine Buchung der Band genommen, und die private
// bekommt gleich ihre eigene Prüfung weiter unten.
$adminKonto = row("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
foreach (array_merge(array_keys(ITEM_KINDS), ['topic']) as $sorte) {
    $tab = $sorte === 'topic' ? 'topics' : ITEM_KINDS[$sorte]['tabelle'];
    $eine = $sorte === 'finance'
        ? row('SELECT id FROM finances WHERE private_for IS NULL ORDER BY id LIMIT 1')
        : row("SELECT id FROM `$tab` ORDER BY id LIMIT 1");
    if (!$eine) {
        printf("%-52s %s%s", "$sorte: item_visible() (keine Zeile vorhanden)", 'uebersprungen', PHP_EOL);
        continue;
    }
    $pruefe("$sorte: item_visible() sagt dem Admin ja",
        item_visible($adminKonto, $sorte, (int) $eine['id']) === true);
}
$pruefe('item_visible() lehnt eine unbekannte Sorte ab',
    item_visible($adminKonto, 'gibtsnicht', 1) === false);
$pruefe('item_visible() lehnt Nummer 0 ab',
    item_visible($adminKonto, 'event', 0) === false);
// Die private Auslage eines anderen bleibt auch vor der Bandleitung zu. Das
// ist der Fall, an dem diese Prüfung beim ersten Lauf gescheitert ist - sie
// hatte angenommen, ein Admin dürfe alles sehen.
// Der Eigner muss es noch geben: Eine Buchung, deren private_for auf ein
// gelöschtes Konto zeigt, sieht niemand mehr - richtig beantwortet, aber als
// Prüfung unbrauchbar. Gefunden am 23.09.2026 auf Staging, siehe #337.
$privat = row('SELECT f.id, f.private_for FROM finances f
               JOIN users u ON u.id = f.private_for
               WHERE f.private_for <> ? ORDER BY f.id LIMIT 1', [(int) $adminKonto['id']]);
if ($privat) {
    $pruefe('private Auslage bleibt auch für den Admin zu',
        item_visible($adminKonto, 'finance', (int) $privat['id']) === false);
    $eigner = row('SELECT * FROM users WHERE id = ?', [(int) $privat['private_for']]);
    $pruefe('ihr Eigner sieht sie',
        $eigner && item_visible($eigner, 'finance', (int) $privat['id']) === true);
} else {
    echo 'keine fremde Privatbuchung vorhanden - Prüfung übersprungen', PHP_EOL;
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

// ------------- 11. Zusagen: erste Zusage "neu", geänderte "geändert"
q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Zusage','2027-03-05','bestaetigt')");
$evZ = (int) $GLOBALS['db']->lastInsertId();
q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)', [$evZ, (int) $ANDERER['id'], 'yes']);
$zNr = (int) $GLOBALS['db']->lastInsertId();
item_new('attendance', $zNr, (int) $ANDERER['id']);
$pruefe('Zusage: steht als „neu"', (items_unseen($ICH, 'attendance')[$zNr]['neu'] ?? null) === true);
$pruefe('Zusage: wer selbst zusagt, sieht nichts', !isset(items_unseen($ANDERER, 'attendance')[$zNr]));

items_mark_seen($ICH, 'attendance', [$zNr]);
// Zurückdatieren, sonst fällt updated_at mit created_at zusammen und die
// geänderte Zusage gälte weiter als „neu".
q('UPDATE attendance SET created_at = created_at - INTERVAL 10 MINUTE WHERE id = ?', [$zNr]);
$zAnders = item_update('attendance', $zNr,
    static fn() => q("UPDATE attendance SET status = 'no' WHERE id = ?", [$zNr]), (int) $ANDERER['id']);
$pruefe('Zusage geändert: markiert', $zAnders);
$pruefe('Zusage geändert: steht als „geändert"',
    (items_unseen($ICH, 'attendance')[$zNr]['neu'] ?? null) === false);

// Zweimal dasselbe geklickt ist keine Nachricht wert.
items_mark_seen($ICH, 'attendance', [$zNr]);
$zGleich = item_update('attendance', $zNr,
    static fn() => q("UPDATE attendance SET status = 'no' WHERE id = ?", [$zNr]), (int) $ANDERER['id']);
$pruefe('Zusage unverändert: keine Marke',
    !$zGleich && !isset(items_unseen($ICH, 'attendance')[$zNr]));

item_forget('attendance', $zNr);
q('DELETE FROM attendance WHERE id = ?', [$zNr]);
q('DELETE FROM events WHERE id = ?', [$evZ]);
$pruefe('Zusage: gelöscht, keine Marke bleibt zurück',
    (int) row('SELECT COUNT(*) n FROM seen_marks WHERE kind = ? AND item_id = ?', ['attendance', $zNr])['n'] === 0);

// ---------------- 12. Verwaiste Privatbuchungen finden (#337)
// Eine private Auslage, deren Konto verschwunden ist, sieht niemand mehr und
// sie fehlt im Kontostand. finances_orphaned() ist die Abfrage, die das sagt.
$vorher = count(finances_orphaned());
$fremd = (int) row('SELECT COALESCE(MAX(id), 0) + 99 n FROM users')['n'];   // sicher kein Konto
q("INSERT INTO finances (date, type, amount_cents, category, description, created_by, private_for)
   VALUES (CURDATE(), 'ausgabe', 1234, 'sonstiges', 'ZZ verwaiste Auslage', ?, ?)",
  [(int) $ICH['id'], $fremd]);
$zz = (int) $GLOBALS['db']->lastInsertId();
$gefunden = finances_orphaned();
$pruefe('verwaiste Buchung wird gefunden', count($gefunden) === $vorher + 1);
$pruefe('und zwar genau diese',
    in_array($zz, array_map(static fn(array $f): int => (int) $f['id'], $gefunden), true));

// Eine private Auslage MIT vorhandenem Eigner ist keine Waise.
q("INSERT INTO finances (date, type, amount_cents, category, description, created_by, private_for)
   VALUES (CURDATE(), 'ausgabe', 500, 'sonstiges', 'ZZ echte Auslage', ?, ?)",
  [(int) $ICH['id'], (int) $ICH['id']]);
$echt = (int) $GLOBALS['db']->lastInsertId();
$pruefe('Auslage mit lebendem Eigner ist keine Waise',
    !in_array($echt, array_map(static fn(array $f): int => (int) $f['id'], finances_orphaned()), true));

// Freigeben macht daraus Bandgeld.
q('UPDATE finances SET private_for = NULL WHERE id = ?', [$zz]);
$pruefe('freigegeben: nicht mehr verwaist', count(finances_orphaned()) === $vorher);
q('DELETE FROM finances WHERE id IN (?, ?)', [$zz, $echt]);
item_forget('finance', $zz);
item_forget('finance', $echt);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
