<?php
// Nach einem Release kommen mehrere Anfragen gleichzeitig (#438).
//
// Setzt Schema-Version und Seed-Stempel zurück, wie sie nach einem Update
// stehen, und startet dann mehrere Prozesse im selben Augenblick. Ohne das
// Schloss um den Schema-Lauf verklemmen sie sich beim Einspielen der Texte
// (1213 Deadlock), und der Verlierer schließt das Tor hinter einer Saat, die
// nie fertig wurde.
//
// SIE SCHREIBT EINSTELLUNGEN und läuft deshalb nur auf dem Entwicklungsserver.
// Als Webnutzer starten:
//
//   sudo -u www-data php bin/schema-schloss-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/schema-schloss-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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
$frisch = static fn(string $k): string
    => (string) (row('SELECT value FROM settings WHERE `key` = ?', [$k])['value'] ?? '');

// Was ein vollständiger Lauf hinterlässt — daran wird hinterher gemessen.
$stempel = $frisch('translations_seed');
$texte = (int) row('SELECT COUNT(*) n FROM translations')['n'];
$pruefe('Ausgangslage: Stempel gesetzt', strlen($stempel) === 40);

$prozesse = 4;
for ($runde = 1; $runde <= 3; $runde++) {
    // Der Stand direkt nach einem Update.
    q("UPDATE settings SET value = '0' WHERE `key` = 'schema_version'");
    q("UPDATE settings SET value = 'veraltet' WHERE `key` = 'translations_seed'");

    $code = sprintf('chdir(%s); require %s;', var_export($basis, true),
        var_export($basis . '/app/bootstrap.php', true));
    $laeufe = [];
    for ($i = 0; $i < $prozesse; $i++) {
        $rohre = [];
        $p = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $rohre);
        $laeufe[] = [$p, $rohre];
    }
    $meldungen = '';
    foreach ($laeufe as [$p, $rohre]) {
        $meldungen .= stream_get_contents($rohre[1]) . stream_get_contents($rohre[2]);
        proc_close($p);
    }

    $pruefe("Runde $runde: kein Deadlock bei $prozesse gleichzeitigen Läufen",
        !str_contains($meldungen, 'Deadlock'));
    $pruefe("Runde $runde: kein Seed abgebrochen", !str_contains($meldungen, 'abgebrochen'));
    $pruefe("Runde $runde: kein Seed-Fehler vermerkt", $frisch('seed_fehler') === '');
    $pruefe("Runde $runde: Stempel steht wieder", $frisch('translations_seed') === $stempel);
    $pruefe("Runde $runde: Schema-Version steht auf " . BANDREGIE_VERSION,
        $frisch('schema_version') === BANDREGIE_VERSION);
    $pruefe("Runde $runde: alle Texte da",
        (int) row('SELECT COUNT(*) n FROM translations')['n'] === $texte);
    if ($meldungen !== '') echo '  ', str_replace("\n", "\n  ", trim(mb_substr($meldungen, 0, 600))), PHP_EOL;
}

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
