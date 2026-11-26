<?php
// Ein Termin mit mehreren möglichen Daten (#443).
//
// Jedes Datum ist ein gewöhnlicher Termin, verknüpft über events.choice_of.
// Was hier geprüft wird, kann auf zwei Arten schiefgehen, und beide sieht man
// der Liste nicht an: Ein Datum gehört nicht mehr zur Auswahl, obwohl es soll
// — dann wählt jemand und ein Termin bleibt verwaist stehen. Oder beim Wählen
// geht etwas verloren, das an einem der anderen Daten hing: der Vertrag, die
// Mail des Veranstalters, ein Kommentar.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver, nie
// auf einer Bandinstanz. Als Webnutzer starten:
//
//   sudo -u www-data php bin/terminwahl-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/terminwahl-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-66s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

/** Termine der Testläufe weg, samt allem, was an ihnen hängt. */
$aufraeumen = static function (): void {
    foreach (rows("SELECT id FROM events WHERE title LIKE 'ZZTW %'") as $r) {
        $nr = (int) $r['id'];
        foreach (['attendance', 'comments', 'event_equipment', 'event_songs', 'substitute_requests', 'contracts',
                  'quotes', 'sales_invoices', 'photos', 'post_messages', 'finances', 'od_folders',
                  'guest_bookings', 'event_time_confirms'] as $tab) {
            q("DELETE FROM `$tab` WHERE event_id = ?", [$nr]);
        }
        q("DELETE FROM files WHERE entity_type = 'event' AND entity_id = ?", [$nr]);
        q('DELETE FROM events WHERE id = ?', [$nr]);
        item_forget('event', $nr);
    }
    foreach (rows("SELECT id FROM users WHERE name LIKE 'ZZTW %' AND email LIKE '%@example.invalid'") as $u) {
        q('DELETE FROM push_subscriptions WHERE user_id = ?', [$u['id']]);
        q('DELETE FROM push_queue WHERE user_id = ?', [$u['id']]);
        q('DELETE FROM push_log WHERE user_id = ?', [$u['id']]);
    }
    q("DELETE FROM users WHERE name LIKE 'ZZTW %' AND email LIKE '%@example.invalid'");
};
$aufraeumen();

$heute = date('Y-m-d');
$tag = static fn(int $tage): string => date('Y-m-d', strtotime("+$tage days"));
$konten = [];
$konto = static function (string $name, string $rolle = 'member', ?int $fuer = null) use (&$konten): int {
    q('INSERT INTO users (name, email, password_hash, role, substitute_for) VALUES (?,?,?,?,?)',
      [$name, strtolower(str_replace(' ', '', $name)) . '@example.invalid', '', $rolle, $fuer]);
    return $konten[] = (int) $GLOBALS['db']->lastInsertId();
};
$termin = static function (string $datum, string $stand = 'angefragt', string $art = 'gig'): int {
    q("INSERT INTO events (type, title, date, status, created_by, time, notes, venue_id)
       VALUES (?, 'ZZTW Anfrage', ?, ?, 1, '20:00', 'Notiz', NULL)", [$art, $datum, $stand]);
    return (int) $GLOBALS['db']->lastInsertId();
};
$zeile = static fn(int $nr): array => row('SELECT * FROM events WHERE id = ?', [$nr]) ?? [];
$antwortet = static function (int $ev, int $wer, string $wie): void {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE status = VALUES(status)', [$ev, $wer, $wie]);
};
$alleJa = static function (int $ev) use ($antwortet): void {
    foreach (event_answerers($ev) as $u) $antwortet($ev, (int) $u['id'], 'yes');
};

$mitglied = $konto('ZZTW Mitglied');

// ------------------------------------------- 1. Anlegen
$a = $termin($tag(130), 'bestaetigt');
$geraet = row('SELECT id FROM equipment ORDER BY id LIMIT 1');
$lied = row('SELECT id FROM songs ORDER BY id LIMIT 1');
if ($geraet) q('INSERT INTO event_equipment (event_id, equipment_id) VALUES (?,?)', [$a, $geraet['id']]);
if ($lied) q('INSERT INTO event_songs (event_id, song_id) VALUES (?,?)', [$a, $lied['id']]);

$neu = choice_add_dates($a, [$tag(130), $tag(137), $tag(137), '2020-01-01', '2027-02-30', 'x'], 1);
$pruefe('genau ein neues Datum (doppelt, vergangen, kaputt verworfen)', count($neu) === 1);
$b = (int) ($neu[0] ?? 0);
$pruefe('das neue Datum ist das gewünschte', ($zeile($b)['date'] ?? '') === $tag(137));
$pruefe('beide tragen die Auswahl des ersten',
    (int) $zeile($a)['choice_of'] === $a && (int) ($zeile($b)['choice_of'] ?? 0) === $a);
$pruefe('beide stehen auf „Angefragt“, auch das vorher bestätigte',
    $zeile($a)['status'] === 'angefragt' && ($zeile($b)['status'] ?? '') === 'angefragt');
$pruefe('Titel, Art, Zeit und Notiz sind übernommen',
    ($zeile($b)['title'] ?? '') === 'ZZTW Anfrage' && ($zeile($b)['type'] ?? '') === 'gig'
    && ($zeile($b)['time'] ?? '') === '20:00' && ($zeile($b)['notes'] ?? '') === 'Notiz');
$pruefe('Technik und Lieder sind übernommen',
    (!$geraet || row('SELECT 1 FROM event_equipment WHERE event_id = ?', [$b]))
    && (!$lied || row('SELECT 1 FROM event_songs WHERE event_id = ?', [$b])));
$einzeln = $termin($tag(150));
$pruefe('choice_group nennt die Auswahl, beim Einzeltermin 0',
    choice_group($b) === $a && choice_group($einzeln) === 0 && choice_dates($einzeln) === []);
$pruefe('choice_dates listet beide nach Datum',
    array_map('intval', array_column(choice_dates($b), 'id')) === [$a, $b]);

$karte = choice_map([$a, $b]);
$pruefe('choice_map: eine Auswahl mit zwei Daten', count($karte) === 1 && count($karte[$a]['dates'] ?? []) === 2);
$alleJa($b);
$karte = choice_map([$a, $b]);
$daten = array_column($karte[$a]['dates'] ?? [], null, 'id');
$pruefe('wo alle ja sagen, ist das Datum hervorgehoben',
    ($daten[$b]['all_yes'] ?? false) === true && ($daten[$a]['all_yes'] ?? true) === false);
$pruefe('und die Zahlen stimmen', ($daten[$b]['yes'] ?? 0) === count(event_answerers($b))
    && ($daten[$b]['open'] ?? 1) === 0 && ($daten[$a]['open'] ?? 0) === count(event_answerers($a)));

// Ein drittes Datum dazu, dann verstreicht eines.
$c = (int) (choice_add_dates($b, [$tag(144)], 1)[0] ?? 0);
$pruefe('ein weiteres Datum landet in derselben Auswahl', choice_group($c) === $a);
q('UPDATE events SET date = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $c]);
choice_prune($a);
$pruefe('ein verstrichenes Datum fällt heraus', choice_group($c) === 0 && choice_group($b) === $a);
q('UPDATE events SET date = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $b]);
choice_prune($a);
$pruefe('bleibt eines übrig, ist es ein gewöhnlicher Termin', choice_group($a) === 0);

// ------------------------------------------- 2. Formular und Route
$quelle = (string) file_get_contents($basis . '/httpdocs/index.php');
$pruefe('Anlegen hängt die weiteren Daten an',
    (bool) preg_match('~choice_add_dates\(\$newEventId, \(array\) \(\$_POST\[\'extra_dates\'\]~', $quelle));
$pruefe('Ändern hängt sie nur nach der Statusprüfung an',
    (bool) preg_match('~if \(choice_may_add_dates\([^;]*\{[^}]*choice_add_dates\(\(int\) \$id~s', $quelle));
$pruefe('beim Ändern erzwingt die Route den Stand der Auswahl',
    str_contains($quelle, "\$_POST['status'] = choice_forced_status((int) \$id"));

$d = $termin($tag(160));
$pruefe('Daten nachtragen: bei „Angefragt“ ja', choice_may_add_dates($zeile($d)));
q("UPDATE events SET status = 'reserviert' WHERE id = ?", [$d]);
$pruefe('bei „Reserviert“ ja', choice_may_add_dates($zeile($d)));
foreach (['bestaetigt', 'abgesagt', 'blockiert'] as $stand) {
    q('UPDATE events SET status = ? WHERE id = ?', [$stand, $d]);
    $pruefe("bei „{$stand}“ nein", !choice_may_add_dates($zeile($d)));
}
q("UPDATE events SET status = 'angefragt', date = ? WHERE id = ?", [date('Y-m-d', strtotime('-2 days')), $d]);
$pruefe('bei einem vergangenen Termin nein', !choice_may_add_dates($zeile($d)));

$e = $termin($tag(170));
$f = (int) (choice_add_dates($e, [$tag(171)], 1)[0] ?? 0);
$pruefe('in der Auswahl bleibt der Stand „Angefragt“', choice_forced_status($f, 'bestaetigt') === 'angefragt');
$pruefe('außerhalb gilt, was gewählt wurde', choice_forced_status($einzeln, 'bestaetigt') === 'bestaetigt');

$admin = row("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$karteHtml = static function (int $nr) use ($admin): string {
    extract(event_view_data([row('SELECT * FROM events WHERE id = ?', [$nr])], $admin));
    $ev = $events[0];
    $user = $me = $admin;
    $evGruppe = 'pruef';
    $evOpen = true;
    ob_start();
    require BASE_DIR . '/app/views/intern/_event_card.php';
    return (string) ob_get_clean();
};
$html = $karteHtml($f);
$pruefe('die Karte zeigt „Zur Wahl“ statt der Statusauswahl',
    str_contains($html, 'ev-choice') && !str_contains($html, '<select name="status"'));
$pruefe('und bietet weitere Daten an', str_contains($html, 'name="extra_dates[]"'));
q("UPDATE events SET status = 'bestaetigt' WHERE id = ?", [$einzeln]);
$html = $karteHtml($einzeln);
$pruefe('beim bestätigten Einzeltermin: Statusauswahl, keine weiteren Daten',
    str_contains($html, '<select name="status"') && !str_contains($html, 'name="extra_dates[]"'));

// ------------------------------------------- 3. Bearbeiten
// Ein Konto mit Gerät, damit Mitteilungen überhaupt in der Warteschlange
// landen; gezählt wird nur, was diesen Testtermin nennt.
$hoerer = $konto('ZZTW Hoerer');
q("INSERT INTO push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth) VALUES (?,?,?,?,?)",
  [$hoerer, hash('sha256', 'zztw' . $hoerer), 'https://fcm.googleapis.com/fcm/send/zztw' . $hoerer, 'x', 'x']);
$post = static fn(): int => (int) row("SELECT COUNT(*) n FROM push_queue WHERE user_id = ? AND payload LIKE '%ZZTW%'",
                                     [$hoerer])['n'];
$g = $termin($tag(180));
$h = (int) (choice_add_dates($g, [$tag(181)], 1)[0] ?? 0);
$antwortet($h, $mitglied, 'yes');
$antwortet($h, $hoerer, 'yes');
if ($geraet) q('INSERT INTO event_equipment (event_id, equipment_id) VALUES (?,?)', [$g, $geraet['id']]);
q("UPDATE events SET title = 'ZZTW Anfrage neu', location = 'ZZTW Ort', notes = 'Neu' WHERE id = ?", [$g]);
$vorherPost = $post();
choice_sync($g, 1);
$pruefe('Titel, Ort und Notiz gehen auf das andere Datum über',
    ($zeile($h)['title'] ?? '') === 'ZZTW Anfrage neu' && ($zeile($h)['location'] ?? '') === 'ZZTW Ort'
    && ($zeile($h)['notes'] ?? '') === 'Neu');
$pruefe('sein Datum bleibt', ($zeile($h)['date'] ?? '') === $tag(181));
$pruefe('der neue Ort fragt dort neu (Zusagen verworfen)',
    !row('SELECT 1 FROM attendance WHERE event_id = ?', [$h]));
$pruefe('ohne eine Mitteilung je Datum', $post() === $vorherPost);
$pruefe('die Technik zieht mit', !$geraet
    || row('SELECT 1 FROM event_equipment WHERE event_id = ? AND equipment_id = ?', [$h, $geraet['id']]));

// Eine kleine Zeitverschiebung am ersten Datum: Auch das andere bekommt den
// Vorschlag, still.
$antwortet($h, $hoerer, 'yes');
q("UPDATE events SET time_proposed = '20:30', time_meet_proposed = '', time_end_proposed = '',
                     time_proposed_by = 1, time_proposed_at = NOW() WHERE id = ?", [$g]);
$vorherPost = $post();
choice_sync($g, 1);
$pruefe('ein offener Zeitvorschlag geht als Vorschlag mit',
    ($zeile($h)['time_proposed'] ?? '') === '20:30' && ($zeile($h)['time'] ?? '') === '20:00');
$pruefe('und auch der ohne Mitteilung', $post() === $vorherPost);

$i = (int) (choice_add_dates($g, [$tag(182)], 1)[0] ?? 0);
q("UPDATE events SET date = ?, title = 'ZZTW alt' WHERE id = ?", [date('Y-m-d', strtotime('-1 day')), $i]);
choice_sync($g, 1);
$pruefe('ein verstrichenes Datum bleibt unberührt', ($zeile($i)['title'] ?? '') === 'ZZTW alt');

// ------------------------------------------- 4. Wählen
$aushilfe = $konto('ZZTW Aushilfe', 'ersatz', $mitglied);
$w1 = $termin($tag(200));
$w2 = (int) (choice_add_dates($w1, [$tag(207)], 1)[0] ?? 0);
$antwortet($w1, $mitglied, 'yes');
// Alles, was an einem Datum hängen kann, liegt am ersten.
q("INSERT INTO comments (event_id, user_id, text) VALUES (?, 1, 'ZZTW Kommentar')", [$w1]);
q('INSERT INTO contracts (event_id, contract_date) VALUES (?, CURDATE())', [$w1]);
q('INSERT INTO quotes (event_id, quote_date) VALUES (?, CURDATE())', [$w1]);
q("INSERT INTO sales_invoices (event_id, invoice_no, invoice_date) VALUES (?, ?, CURDATE())", [$w1, 'ZZTW-' . $w1]);
q("INSERT INTO photos (event_id, filename) VALUES (?, ?)", [$w1, 'zztw-' . $w1 . '.jpg']);
q("INSERT INTO files (entity_type, entity_id, filename, original_name, size) VALUES ('event', ?, ?, 'x.pdf', 1)",
  [$w1, 'zztw-' . $w1 . '.pdf']);
q("INSERT INTO post_messages (event_id, uid) VALUES (?, ?)", [$w1, 'zztw-' . $w1]);
q("INSERT INTO finances (event_id, date, amount_cents, description) VALUES (?, CURDATE(), 100, 'ZZTW Gage')", [$w1]);
q("INSERT INTO guest_bookings (guest_id, event_id, token, access_until) VALUES (0, ?, ?, NOW())",
  [$w1, hash('sha256', 'zztw' . $w1)]);
q('INSERT INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,1)',
  [$w1, $aushilfe, $mitglied]);
$haengt = static function (int $nr): array {
    $n = static fn(string $sql): int => (int) row($sql, [$nr])['n'];
    return [
        'comments' => $n('SELECT COUNT(*) n FROM comments WHERE event_id = ?'),
        'contracts' => $n('SELECT COUNT(*) n FROM contracts WHERE event_id = ?'),
        'quotes' => $n('SELECT COUNT(*) n FROM quotes WHERE event_id = ?'),
        'sales_invoices' => $n('SELECT COUNT(*) n FROM sales_invoices WHERE event_id = ?'),
        'photos' => $n('SELECT COUNT(*) n FROM photos WHERE event_id = ?'),
        'files' => $n("SELECT COUNT(*) n FROM files WHERE entity_type = 'event' AND entity_id = ?"),
        'post_messages' => $n('SELECT COUNT(*) n FROM post_messages WHERE event_id = ?'),
        'finances' => $n('SELECT COUNT(*) n FROM finances WHERE event_id = ?'),
        'guest_bookings' => $n('SELECT COUNT(*) n FROM guest_bookings WHERE event_id = ?'),
        'substitute_requests' => $n('SELECT COUNT(*) n FROM substitute_requests WHERE event_id = ?'),
    ];
};
$pruefe('Aushilfe nur am ersten Datum: gezählt nur dort', (static function () use ($w1, $w2, $aushilfe): bool {
    $gefragt = static fn(int $ev): array => array_map('intval', array_column(event_answerers($ev), 'id'));
    return in_array($aushilfe, $gefragt($w1), true) && !in_array($aushilfe, $gefragt($w2), true);
})());
$vorherPost = $post();
$pruefe('Wählen gelingt', choice_choose($w2, 1) === true);
$pruefe('alles vom anderen Datum hängt jetzt am gewählten',
    !array_filter($haengt($w2), static fn(int $n): bool => $n !== 1));
$pruefe('das andere Datum ist weg, samt Zusagen',
    !$zeile($w1) && !row('SELECT 1 FROM attendance WHERE event_id = ?', [$w1]));
$pruefe('das gewählte ist ein gewöhnlicher angefragter Termin',
    choice_group($w2) === 0 && ($zeile($w2)['status'] ?? '') === 'angefragt');
$pruefe('die Band erfährt es mit einer Mitteilung', $post() === $vorherPost + 1);
$pruefe('zweimal wählen ändert nichts', choice_choose($w2, 1) === false && $zeile($w2));

// Dieselbe Aushilfe an beiden Daten: Am Ende bleibt eine Anfrage.
$x1 = $termin($tag(210));
$x2 = (int) (choice_add_dates($x1, [$tag(211)], 1)[0] ?? 0);
foreach ([$x1, $x2] as $nr) {
    q('INSERT INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,1)',
      [$nr, $aushilfe, $mitglied]);
}
choice_choose($x2, 1);
$pruefe('dieselbe Aushilfe an beiden Daten: eine Anfrage bleibt',
    (int) row('SELECT COUNT(*) n FROM substitute_requests WHERE user_id = ? AND event_id IN (?,?)',
              [$aushilfe, $x1, $x2])['n'] === 1
    && row('SELECT 1 FROM substitute_requests WHERE event_id = ? AND user_id = ?', [$x2, $aushilfe]));

// Das erste Datum löschen: Die anderen bleiben eine Auswahl.
$y1 = $termin($tag(220));
[$y2, $y3] = array_map('intval', choice_add_dates($y1, [$tag(221), $tag(222)], 1));
event_delete($y1);
choice_prune($y1);
$pruefe('erstes Datum gelöscht: die anderen bleiben eine Auswahl',
    !$zeile($y1) && choice_group($y2) === $y1 && choice_group($y3) === $y1);
$pruefe('und lassen sich wählen', choice_choose($y3, 1) && !$zeile($y2));

// Ein Datum ist verstrichen, bevor jemand wählt: Es bleibt stehen.
$z1 = $termin($tag(230));
[$z2, $z3] = array_map('intval', choice_add_dates($z1, [$tag(231), $tag(232)], 1));
q('UPDATE events SET date = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $z1]);
choice_choose($z3, 1);
$pruefe('ein verstrichenes Datum wird beim Wählen nicht gelöscht', (bool) $zeile($z1) && !$zeile($z2));

$pruefe('die Route kennt „waehlen“ und prüft das Schreibrecht',
    str_contains($quelle, '|waehlen)$~')
    && (bool) preg_match('~if \(\$action === \'waehlen\'\) \{\s*if \(!perm_allows\(\$me, \'termine\', \'write\'\)\)~', $quelle));
$pruefe('Löschen räumt die Auswahl auf',
    (bool) preg_match('~\$evWahlGruppe = choice_group\(\(int\) \$id\);\s*event_delete\(\(int\) \$id\);\s*if \(\$evWahlGruppe\) choice_prune\(\$evWahlGruppe\);~', $quelle));

// ------------------------------------------- 5. Ansicht
$pruefe('choice_order holt die Daten einer Auswahl zusammen', array_column(choice_order([
    ['id' => 1, 'choice_of' => 1], ['id' => 2, 'choice_of' => null], ['id' => 3, 'choice_of' => 1],
    ['id' => 4, 'choice_of' => 4], ['id' => 5, 'choice_of' => null], ['id' => 6, 'choice_of' => 4],
]), 'id') === [1, 3, 2, 4, 6, 5]);

$v1 = $termin($tag(240));
$v2 = (int) (choice_add_dates($v1, [$tag(247)], 1)[0] ?? 0);
$alleJa($v2);
$antwortet($v1, $mitglied, 'no');
q('INSERT INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,1)',
  [$v1, $aushilfe, $mitglied]);
$blockHtml = static function (array $wer) use ($v1, $v2): string {
    extract(event_view_data([row('SELECT * FROM events WHERE id = ?', [$v1]),
                             row('SELECT * FROM events WHERE id = ?', [$v2])], $wer));
    $user = $wer;
    $ev = $events[0];
    $wahl = $choiceByEvent[(int) $ev['choice_of']] ?? null;
    ob_start();
    if ($wahl) require BASE_DIR . '/app/views/intern/_event_choice.php';
    return (string) ob_get_clean();
};
$html = $blockHtml($admin);
$pruefe('der Block nennt beide Daten mit Wahlknopf',
    str_contains($html, "/intern/termine/$v1/waehlen") && str_contains($html, "/intern/termine/$v2/waehlen"));
$pruefe('und hebt das Datum hervor, an dem alle können',
    (bool) preg_match('~class="[^"]*ev-choice-all[^"]*"[^>]*data-ev="' . $v2 . '"~', $html)
    && !preg_match('~class="[^"]*ev-choice-all[^"]*"[^>]*data-ev="' . $v1 . '"~', $html));
$pruefe('die Absage steht in der Zeile', str_contains($html, '✘ 1'));
$html = $blockHtml(row('SELECT * FROM users WHERE id = ?', [$aushilfe]));
$pruefe('eine Aushilfe sieht keinen Wahlknopf und nur ihr Datum',
    !str_contains($html, '/waehlen') && !str_contains($html, 'data-ev="' . $v2 . '"'));

q('UPDATE events SET is_public = 1, type = ? WHERE id IN (?,?)', ['gig', $v1, $v2]);
$oeffentlich = array_map('intval', array_column(rows("SELECT e.id FROM events e WHERE e.type='gig' AND e.is_public=1
    AND e.status='bestaetigt' AND e.date >= ?", [$heute]), 'id'));
$pruefe('ein Datum zur Wahl steht nie auf der öffentlichen Seite',
    !in_array($v1, $oeffentlich, true) && !in_array($v2, $oeffentlich, true));

// ------------------------------------------- Ende
$aufraeumen();
$reste = (int) row("SELECT COUNT(*) n FROM events WHERE title LIKE 'ZZTW%'")['n']
       + (int) row("SELECT COUNT(*) n FROM users WHERE name LIKE 'ZZTW%'")['n'];
$pruefe('keine Testdaten zurückgelassen', $reste === 0);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
