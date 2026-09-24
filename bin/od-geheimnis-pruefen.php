<?php
// Den Ablauf des OneDrive-Geheimnisses durchspielen (#339) — OHNE zu senden.
//
// Geprüft werden die Entscheidungen: ab wann gemahnt wird, dass jede Stufe nur
// einmal mahnt, dass ein neues Datum die Erinnerung wieder spannt, und wer die
// Mail bekäme. od_secret_warn_run() wird bewusst nicht aufgerufen — die Bremse
// davor (od_secret_claim) ist das, worauf es ankommt, und die lässt sich
// einzeln prüfen, ohne dass Post entsteht.
//
// SIE SCHREIBT EINSTELLUNGEN und läuft deshalb nur auf dem Entwicklungsserver:
//
//   sudo -u www-data php bin/od-geheimnis-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '' || !in_array('--schreibt-testdaten', $argv, true)) {
    fwrite(STDERR, "Aufruf: php bin/od-geheimnis-pruefen.php <Verzeichnis> --schreibt-testdaten\n");
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

// Alles, was angefasst wird, kommt zurück — auch wenn die Prüfung mittendrin
// abbricht. Eine halb umgestellte OneDrive-Einrichtung wäre ein teurer Rest.
$vorher = [];
foreach (['onedrive_client_id', 'onedrive_client_secret', 'onedrive_secret_expires',
          'od_secret_warned'] as $s) {
    $vorher[$s] = setting($s);
}
register_shutdown_function(static function () use ($vorher): void {
    foreach ($vorher as $s => $wert) set_setting($s, $wert);
    settings_forget();
});

/** Ein Datum, das in $tage Tagen liegt. */
$inTagen = static fn(int $tage): string =>
    (new DateTimeImmutable('today'))->modify(($tage >= 0 ? '+' : '') . $tage . ' days')->format('Y-m-d');

/** Datum setzen und die Einstellungen neu lesen. */
$setzeDatum = static function (string $datum): void {
    set_setting('onedrive_secret_expires', $datum);
    settings_forget();
};

// Eine eingerichtete Anwendung vortäuschen — ohne sie schweigt alles zu Recht.
set_setting('onedrive_client_id', 'pruef-kennung');
set_setting('onedrive_client_secret', 'pruef-geheimnis');
settings_forget();

// ------------------------------------------------------- 1. Das Datum lesen
$setzeDatum('2027-03-01');
$pruefe('gueltiges Datum wird gelesen', od_secret_expires() === '2027-03-01');
$setzeDatum('01.03.2027');
$pruefe('deutsches Format gilt als keins', od_secret_expires() === '');
$setzeDatum('');
$pruefe('leer bleibt leer', od_secret_expires() === '' && od_secret_days_left() === null);

// --------------------------------------------------------- 2. Tage zaehlen
$setzeDatum($inTagen(40));
$pruefe('40 Tage voraus', od_secret_days_left() === 40);
$setzeDatum($inTagen(0));
$pruefe('heute sind null Tage', od_secret_days_left() === 0);
$setzeDatum($inTagen(-3));
$pruefe('drei Tage darueber sind -3', od_secret_days_left() === -3);

// ------------------------------------------------------------ 3. Die Stufen
$pruefe('ohne Datum keine Stufe', od_secret_stufe(null) === null);
$pruefe('40 Tage: noch nichts', od_secret_stufe(40) === null);
$pruefe('31 Tage: noch nichts', od_secret_stufe(31) === null);
$pruefe('30 Tage: erste Stufe', od_secret_stufe(30) === 30);
$pruefe('8 Tage: immer noch die erste', od_secret_stufe(8) === 30);
$pruefe('7 Tage: zweite Stufe', od_secret_stufe(7) === 7);
$pruefe('1 Tag: immer noch die zweite', od_secret_stufe(1) === 7);
$pruefe('0 Tage: letzte Stufe', od_secret_stufe(0) === 0);
$pruefe('abgelaufen: letzte Stufe', od_secret_stufe(-9) === 0);

// ------------------------------------------------------- 4. Wann faellig ist
set_setting('od_secret_warned', '');
$setzeDatum('');
$pruefe('ohne Datum keine Mahnung', !od_secret_warn_due());
$setzeDatum($inTagen(40));
$pruefe('40 Tage vorher keine Mahnung', !od_secret_warn_due());
$setzeDatum($inTagen(25));
$pruefe('25 Tage vorher faellig', od_secret_warn_due());

// ---------------------------------------- 5. Jede Stufe mahnt nur einmal
$marke = od_secret_expires() . '/' . od_secret_stufe(od_secret_days_left());
$pruefe('Platz beanspruchen gelingt', od_secret_claim($marke));
$pruefe('ein zweiter bekommt ihn nicht', !od_secret_claim($marke));
$pruefe('danach nicht mehr faellig', !od_secret_warn_due());

// ------------------------------------------- 6. Naechste Stufe mahnt wieder
// Dasselbe Datum, aber nur noch fuenf Tage hin: Stufe 7 liegt unter 30.
$fuenf = $inTagen(5);
$setzeDatum($fuenf);
set_setting('od_secret_warned', $fuenf . '/30');
settings_forget();
$pruefe('bei 5 Tagen mahnt die zweite Stufe', od_secret_warn_due());
set_setting('od_secret_warned', $fuenf . '/7');
settings_forget();
$pruefe('und danach ist Ruhe', !od_secret_warn_due());

// Abgelaufen ist die letzte Stufe und kommt auch nach der zweiten noch.
$weg = $inTagen(-1);
$setzeDatum($weg);
set_setting('od_secret_warned', $weg . '/7');
settings_forget();
$pruefe('abgelaufen mahnt ein letztes Mal', od_secret_warn_due());
set_setting('od_secret_warned', $weg . '/0');
settings_forget();
$pruefe('und dann endgueltig Ruhe', !od_secret_warn_due());

// ------------------------------------------- 7. Ein neues Datum spannt neu
$neu = $inTagen(20);
$setzeDatum($neu);
$pruefe('neues Datum mahnt wieder', od_secret_warn_due());

// ----------------------------------- 8. Ohne Anwendung schweigt die Mahnung
set_setting('onedrive_client_id', '');
settings_forget();
$pruefe('ohne eingetragene Anwendung still', !od_secret_warn_due());
set_setting('onedrive_client_id', 'pruef-kennung');
settings_forget();

// ------------------------------------------------------------ 9. Empfaenger
$empf = od_secret_empfaenger();
$pruefe('es gibt Empfaenger', $empf !== []);
$pruefe('nur die Bandleitung', (static function () use ($empf): bool {
    foreach ($empf as $u) if (($u['role'] ?? '') !== 'admin') return false;
    return true;
})());
$pruefe('niemand ohne Adresse', (static function () use ($empf): bool {
    foreach ($empf as $u) if ((string) $u['email'] === '') return false;
    return true;
})());

// --------------------------------------------------------- 10. Die Texte
foreach (['od_secret_expires', 'od_secret_expires_hint', 'od_secret_subject',
          'od_secret_subject_over', 'od_secret_body', 'od_secret_body_over',
          'od_secret_howto', 'sys_od_secret', 'sys_od_secret_left',
          'sys_od_secret_over', 'sys_od_secret_none', 'sys_od_secret_none_hint',
          'sys_od_secret_hint'] as $schluessel) {
    $pruefe("Text $schluessel vorhanden", t($schluessel) !== $schluessel);
}
// Die Prueftexte gehen durch sprintf, die Mailtexte durch str_replace. Wer das
// vertauscht, bekommt entweder einen Absturz oder eine Mail mit %1 darin.
$pruefe('sys_od_secret_left formatiert', sprintf(t('sys_od_secret_left'), 5, '01.03.2027') !== '');
$pruefe('sys_od_secret_over formatiert', sprintf(t('sys_od_secret_over'), '01.03.2027') !== '');
$pruefe('od_secret_body ersetzt beide Marken',
    !str_contains(str_replace(['%1', '%2'], ['5', '01.03.2027'], t('od_secret_body')), '%'));
$pruefe('od_secret_subject ersetzt seine Marke',
    !str_contains(str_replace('%1', '5', t('od_secret_subject')), '%'));

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
