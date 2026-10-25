<?php
// Sichtbarkeit in Schleifen: gleiches Ergebnis, feste Zahl Abfragen (#410).
//
// may_see_song() und seine Geschwister bauen bei jedem Aufruf die Liste der
// sichtbaren Nummern neu aus der Datenbank. In einer Schleife ist das eine
// Abfrage je Zeile — bei Beat Cuisine neunundfünfzig für neunundfünfzig
// Lieder, und niemand sieht es: Die Seite ist richtig, nur langsam, und sie
// wird genau dann langsam, wenn eine Band eine Aushilfe anlegt.
//
// Deshalb prüft diese Datei zwei Dinge zusammen und nicht nacheinander:
//
//   1. visible_rows() lässt dieselben Zeilen durch wie may_see_*() je Zeile.
//      Sonst wäre aus einer Beschleunigung ein Rechteloch geworden.
//   2. Die Zahl der Abfragen wächst nicht mit der Zahl der Zeilen.
//
// Punkt 2 allein wäre erfüllt, wenn alles durchgelassen würde; Punkt 1 allein
// wäre erfüllt von genau dem Code, der hier ersetzt wurde.
//
// SIE SCHREIBT TESTDATEN (ein Aushilfskonto und eine Anfrage) und läuft
// deshalb nur auf dem Entwicklungsserver, nie auf einer Bandinstanz:
//
//   sudo -u www-data php bin/sicht-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/sicht-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

// Wie viele Lesezugriffe die Datenbank seit dem letzten Blick gezählt hat.
// SHOW zählt selbst nicht als Com_select, der Zähler misst sich also nicht
// selbst mit.
$abfragen = static function (): int {
    $z = row("SHOW SESSION STATUS LIKE 'Com_select'");
    return (int) ($z['Value'] ?? 0);
};

// ------------------- Testdaten
q("DELETE FROM users WHERE email = 'zzsicht@example.invalid'");
$mitglied = row("SELECT id FROM users WHERE role <> 'admin' AND substitute_for IS NULL LIMIT 1")
    ?? row('SELECT id FROM users ORDER BY id LIMIT 1');
q("INSERT INTO users (name, email, password_hash, role, substitute_for)
   VALUES ('ZZ Sicht', 'zzsicht@example.invalid', '', 'member', ?)",
  [(int) $mitglied['id']]);
$aushilfe = row("SELECT * FROM users WHERE email = 'zzsicht@example.invalid'");

$termine = rows('SELECT id FROM events ORDER BY id DESC LIMIT 2');
if (count($termine) < 2) { fwrite(STDERR, "Zwei Termine werden gebraucht\n"); exit(2); }
$gefragt = (int) $termine[0]['id'];
$nichtGefragt = (int) $termine[1]['id'];
q('DELETE FROM substitute_requests WHERE user_id = ?', [(int) $aushilfe['id']]);
q('INSERT INTO substitute_requests (event_id, user_id) VALUES (?,?)',
  [$gefragt, (int) $aushilfe['id']]);

$aufraeumen = static function () use ($aushilfe): void {
    q('DELETE FROM substitute_requests WHERE user_id = ?', [(int) $aushilfe['id']]);
    q('DELETE FROM users WHERE id = ?', [(int) $aushilfe['id']]);
};

$pruefe('Aushilfe angelegt und zu einem Termin gebeten', is_substitute($aushilfe));

// ------------------- 1. Gleiches Ergebnis wie vorher
$vergleiche = static function (string $was, array $zeilen, ?array $ids, callable $einzeln) use ($pruefe): void {
    $neu = array_column(visible_rows($zeilen, $ids), 'id');
    $alt = array_column(array_filter($zeilen, static fn(array $z): bool => $einzeln((int) $z['id'])), 'id');
    $pruefe($was, array_values(array_map('intval', $neu)) === array_values(array_map('intval', $alt)));
};

foreach ([['Aushilfe', $aushilfe], ['Mitglied', $mitglied]] as [$wer, $konto]) {
    $konto = row('SELECT * FROM users WHERE id = ?', [(int) $konto['id']]);
    $vergleiche("Termine, $wer: dieselbe Auswahl wie may_see_event()",
        rows('SELECT id FROM events ORDER BY id'), visible_event_ids($konto),
        static fn(int $id): bool => may_see_event($konto, $id));
    $vergleiche("Lieder, $wer: dieselbe Auswahl wie may_see_song()",
        rows('SELECT id FROM songs ORDER BY id'), visible_song_ids($konto),
        static fn(int $id): bool => may_see_song($konto, $id));
    $vergleiche("Setlists, $wer: dieselbe Auswahl wie may_see_setlist()",
        rows('SELECT id FROM setlists ORDER BY id'), visible_setlist_ids($konto),
        static fn(int $id): bool => may_see_setlist($konto, $id));
}

// Und das Ergebnis ist wirklich eingeschränkt — sonst prüfte alles oben nur,
// dass zwei Wege dasselbe Nichts tun.
$pruefe('die Aushilfe sieht den Termin, zu dem sie gebeten wurde',
    may_see_event($aushilfe, $gefragt));
$pruefe('und den anderen nicht', !may_see_event($aushilfe, $nichtGefragt));

// ------------------- 2. Die Zahl der Abfragen hängt nicht an der Zahl der Zeilen
$lieder = rows('SELECT id, title FROM songs ORDER BY title');
$pruefe('es sind genug Lieder da, um einen Unterschied zu sehen', count($lieder) >= 5);

$vorher = $abfragen();
$sichtbar = visible_rows($lieder, visible_song_ids($aushilfe));
$mitHelfer = $abfragen() - $vorher;

$vorher = $abfragen();
array_filter($lieder, static fn(array $s): bool => may_see_song($aushilfe, (int) $s['id']));
$jeZeile = $abfragen() - $vorher;

printf("  %d Lieder: %d Abfragen mit visible_rows(), %d je Zeile%s",
    count($lieder), $mitHelfer, $jeZeile, PHP_EOL);
$pruefe('visible_rows() fragt höchstens dreimal, egal wie viele Lieder', $mitHelfer <= 3);
$pruefe('der Weg je Zeile war teurer (sonst misst diese Prüfung nichts)',
    $jeZeile > $mitHelfer);

// ------------------- 3. Die beiden Aufrufstellen aus #410
$vorher = $abfragen();
$verlinkbar = task_linkable($aushilfe);
$kostenLinkbar = $abfragen() - $vorher;
printf("  task_linkable(): %d Abfragen%s", $kostenLinkbar, PHP_EOL);
// Drei Bereiche, je eine Liste und eine Sichtbarkeitsfrage; dazu die Rechte.
$pruefe('task_linkable() bleibt unter zwanzig Abfragen', $kostenLinkbar < 20);
$pruefe('und liefert nur den Termin, zu dem gebeten wurde',
    array_column($verlinkbar['event'] ?? [], 'id') === [$gefragt]);

$vorher = $abfragen();
$daten = event_view_data($aushilfe);
$kostenTermine = $abfragen() - $vorher;
printf("  event_view_data(): %d Abfragen bei %d Liedern%s",
    $kostenTermine, count($lieder), PHP_EOL);
$pruefe('event_view_data() wächst nicht mit der Zahl der Lieder',
    $kostenTermine < 40 + count($daten['events'] ?? []));

$aufraeumen();
$pruefe('Testkonto wieder weg',
    row("SELECT id FROM users WHERE email = 'zzsicht@example.invalid'") === null);

printf("%s%d ok, %d Fehler%s", PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler > 0 ? 1 : 0);
