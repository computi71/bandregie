<?php
// „Wichtig" durchspielen (#333) — OHNE zu senden.
//
// Der Versand wird für die Dauer der Prüfung abgeschaltet und danach wieder
// hergestellt. Geprüft werden die Entscheidungen: wer kennzeichnen darf, wer
// die Mail bekäme, und dass zweimal Drücken keine zweite Mail auslöst.
//
// SIE SCHREIBT TESTDATEN und läuft deshalb nur auf dem Entwicklungsserver:
//
//   sudo -u www-data php bin/wichtig-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/wichtig-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-56s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// Versand aus, Einstellungen merken — am Ende steht alles wieder wie vorher.
$warMail = setting('important_mail', '1');
$warWer = setting('important_who', 'write');
set_setting('important_mail', '0');

$admin = row("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$mitglied = row("SELECT * FROM users WHERE role = 'member' AND email <> '' ORDER BY id LIMIT 1");
$aushilfe = row("SELECT * FROM users WHERE role = 'ersatz' LIMIT 1");
if (!$admin || !$mitglied) { fwrite(STDERR, "Ein Admin und ein Mitglied werden gebraucht\n"); exit(2); }

q("INSERT INTO events (type, title, date, status) VALUES ('probe','ZZ Wichtig','2027-07-07','bestaetigt')");
$ev = (int) $GLOBALS['db']->lastInsertId();

// ------------------------------------------------------- 1. Wer darf was
set_setting('important_who', 'admin');
settings_forget();
$pruefe('nur Bandleitung: Admin darf', wichtig_darf($admin, 'event', $ev));
$pruefe('nur Bandleitung: Mitglied darf nicht', !wichtig_darf($mitglied, 'event', $ev));

set_setting('important_who', 'alle');
settings_forget();
$pruefe('jeder, der es sieht: Mitglied darf', wichtig_darf($mitglied, 'event', $ev));

set_setting('important_who', 'write');
settings_forget();
$pruefe('Schreibrecht: Admin darf', wichtig_darf($admin, 'event', $ev));

if ($aushilfe) {
    $pruefe('Aushilfe sieht den Termin nicht', !may_see_event($aushilfe, $ev));
    set_setting('important_who', 'alle');
    settings_forget();
    $pruefe('und darf ihn auch bei "jeder" nicht kennzeichnen',
        !wichtig_darf($aushilfe, 'event', $ev));
    set_setting('important_who', 'write');
    settings_forget();
} else {
    echo 'keine Aushilfe vorhanden — Sichtbarkeitsprüfung übersprungen', PHP_EOL;
}
$pruefe('unbekannte Sorte darf niemand', !wichtig_darf($admin, 'gibtsnicht', $ev));

// ------------------------------------------------ 2. Kennzeichnen und lösen
$pruefe('kennzeichnen gelingt', wichtig_setzen('event', $ev, 'Termin verlegt', $admin));
$flag = wichtig_of('event', $ev);
$pruefe('Kennzeichnung ist da', $flag !== null);
$pruefe('mit Notiz', ($flag['note'] ?? '') === 'Termin verlegt');
$pruefe('mit Urheber', (int) ($flag['set_by'] ?? 0) === (int) $admin['id']);
$pruefe('zweites Mal loest nichts aus', !wichtig_setzen('event', $ev, 'nochmal', $admin));
$pruefe('Notiz bleibt die erste', (wichtig_of('event', $ev)['note'] ?? '') === 'Termin verlegt');
$pruefe('in der Karte fuer Listen', isset(wichtig_map('event', [$ev])[$ev]));

// ------------------------------------------------------------ 3. Empfaenger
$empf = wichtig_empfaenger('event', $ev, $admin);
$ids = array_map(static fn(array $u): int => (int) $u['id'], $empf);
$pruefe('der Kennzeichnende bekommt nichts', !in_array((int) $admin['id'], $ids, true));
$pruefe('ein sehendes Mitglied bekommt sie', in_array((int) $mitglied['id'], $ids, true));
if ($aushilfe) {
    $pruefe('die Aushilfe nicht', !in_array((int) $aushilfe['id'], $ids, true));
}
$pruefe('niemand ohne Adresse', (static function () use ($empf): bool {
    foreach ($empf as $u) if ((string) $u['email'] === '') return false;
    return true;
})());

wichtig_loesen('event', $ev);
$pruefe('zurueckgenommen', wichtig_of('event', $ev) === null);
$pruefe('danach wieder kennzeichenbar', wichtig_setzen('event', $ev, '', $admin));

// ------------------------------------------------------------- 4. Aufraeumen
wichtig_loesen('event', $ev);
item_forget('event', $ev);
q('DELETE FROM events WHERE id = ?', [$ev]);
set_setting('important_mail', $warMail);
set_setting('important_who', $warWer);
settings_forget();
$pruefe('Einstellungen wiederhergestellt',
    setting('important_mail') === $warMail && setting('important_who') === $warWer);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
