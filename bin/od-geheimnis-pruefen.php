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

// ------------------------- 4b. Erstlauf: die Einstellungszeile fehlt ganz
// Genau dafuer steht das INSERT IGNORE in od_secret_claim(). Vorher war dieser
// Fall nie geprueft, weil die Pruefung die Zeile immer vorher anlegte.
q("DELETE FROM settings WHERE `key` = 'od_secret_warned'");
settings_forget();
$pruefe('ohne Einstellungszeile ist faellig', od_secret_warn_due());
$pruefe('und der Erste bekommt den Platz',
    od_secret_claim(od_secret_expires() . '/' . od_secret_stufe(od_secret_days_left())));
$pruefe('die Zeile steht danach da', setting('od_secret_warned') !== '');
set_setting('od_secret_warned', '');
settings_forget();

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
// Nicht "nicht leer": Das ist fast jede Zeichenkette. Geprueft wird, dass die
// Werte wirklich eingesetzt werden - sonst faellt ein vertauschtes %s nie auf.
$pruefe('sys_od_secret_left setzt beide Werte ein', (static function (): bool {
    $t = sprintf(t('sys_od_secret_left'), 5, '01.03.2027');
    return str_contains($t, '5') && str_contains($t, '01.03.2027');
})());
$pruefe('sys_od_secret_over setzt das Datum ein',
    str_contains(sprintf(t('sys_od_secret_over'), '01.03.2027'), '01.03.2027'));
$pruefe('od_secret_body ersetzt beide Marken',
    !str_contains(str_replace(['%1', '%2'], ['5', '01.03.2027'], t('od_secret_body')), '%'));
$pruefe('od_secret_subject ersetzt seine Marke',
    !str_contains(str_replace('%1', '5', t('od_secret_subject')), '%'));

// ----------------------------------------------------- 11. Der Mailtext
// od_secret_warn_run() laesst sich nicht aufrufen, ohne zu senden - die
// Zusammenstellung des Textes schon. Genau dort sitzt der Fehler, der sonst
// erst dem Empfaenger auffaellt: ein stehengebliebenes %2, ein vertauschter
// Zweig, eine Sprache, die es nicht gibt.
// Ohne Empfaenger keine Textpruefung - aber auch kein Absturz. Hier stand
// einmal "?? $admin", und die Variable gab es in dieser Datei nie: Genau im
// Fall ohne Admin starb das Skript, statt den Abschnitt zu ueberspringen.
// Die Sprache wird festgenagelt, sonst prueft der Vergleich unten die
// Voreinstellung des ersten Admins statt den Code.
$empfTest = ($empf[0] ?? []) + ['name' => 'Pruefkonto', 'pref_lang' => 'de'];
$empfTest['pref_lang'] = 'de';
[$betreffHin, $textHin] = od_secret_mail_text($empfTest, 12, '2028-08-03');
$pruefe('Betreff nennt die Tage', str_contains($betreffHin, '12'));
$pruefe('Betreff ohne Platzhalterrest', !str_contains($betreffHin, '%'));
// fmt_date() waere die Sprache des Betrachters - im Skript die Voreinstellung
// der Installation. Der Text entsteht in der Sprache des EMPFAENGERS. Wer das
// verwechselt, baut genau den Fehler nach, gegen den #349 angetreten ist.
$pruefe('Text nennt Tage und Datum',
    str_contains($textHin, '12') && str_contains($textHin, fmt_date_lang('2028-08-03', 'de')));
// Die Linkzeile bleibt aussen vor: rawurlencode() macht aus dem Schraegstrich
// ein %2F, und ein naives Muster liest das als stehengebliebene Marke. Das ist
// hier schon einmal passiert - die Pruefung hat den Code beschuldigt.
$pruefe('Text ohne Platzhalterrest', !preg_match('~%[12](?![0-9A-Fa-f])~', $textHin));
$pruefe('Text nennt den Empfaenger', str_contains($textHin, (string) $empfTest['name']));
$pruefe('Text enthaelt einen Link', str_contains($textHin, '/login?weiter='));

[$betreffWeg, $textWeg] = od_secret_mail_text($empfTest, -4, '2026-09-20');
$pruefe('abgelaufen: anderer Betreff', $betreffWeg !== $betreffHin);
$pruefe('abgelaufen: keine negative Zahl im Betreff', !str_contains($betreffWeg, '-4'));
$pruefe('abgelaufen: keine negative Zahl im Text', !str_contains($textWeg, '-4'));
$pruefe('abgelaufen: nennt das Datum', str_contains($textWeg, fmt_date_lang('2026-09-20', 'de')));
$pruefe('abgelaufen: ohne Platzhalterrest', !preg_match('~%[12](?![0-9A-Fa-f])~', $textWeg));
$pruefe('die beiden Texte sind verschieden', $textWeg !== $textHin);

// Eine unbekannte Sprache darf nicht in einem leeren Text muenden.
$fremd = $empfTest; $fremd['pref_lang'] = 'kl';
[$betreffFremd, $textFremd] = od_secret_mail_text($fremd, 12, '2028-08-03');
$pruefe('unbekannte Sprache faellt auf Deutsch zurueck',
    $betreffFremd === $betreffHin && $textFremd === $textHin);

// ------------------------------------------------- 12. Datumspruefung (#347)
$pruefe('echtes Datum gilt', datum_gueltig('2028-08-03'));
$pruefe('Monat 13 gilt nicht', !datum_gueltig('2027-13-01'));
$pruefe('31. Februar gilt nicht', !datum_gueltig('2027-02-31'));
$pruefe('29.02. im Schaltjahr gilt', datum_gueltig('2028-02-29'));
$pruefe('29.02. sonst nicht', !datum_gueltig('2027-02-29'));
$pruefe('deutsches Format gilt nicht', !datum_gueltig('03.08.2028'));
$pruefe('leer gilt nicht', !datum_gueltig(''));
$setzeDatum('2027-13-45');
$pruefe('unmoegliches Datum kommt nicht durch', od_secret_expires() === '');

// ------------------------- 13. Datum am Geheimnis festgemacht (#354)
$warSeit = setting('onedrive_secret_set_at');
set_setting('onedrive_secret_set_at', '2026-08-03');
$setzeDatum('');
settings_forget();
$pruefe('ohne Datum faellt es aus dem Eintragetag', od_secret_expires() === '2028-08-03');
$pruefe('Grenze sind 24 Monate', od_secret_grenze() === '2028-08-03');
$setzeDatum('2027-03-01');
$pruefe('ein eingetragenes Datum gewinnt', od_secret_expires() === '2027-03-01');
$pruefe('plausibel: innerhalb der Grenze', od_secret_datum_plausibel('2028-08-03'));
$pruefe('unplausibel: zehn Jahre voraus', !od_secret_datum_plausibel('2037-03-01'));
$pruefe('unplausibel: in der Vergangenheit', !od_secret_datum_plausibel('2020-01-01'));
$pruefe('unplausibel: unmoegliches Datum', !od_secret_datum_plausibel('2027-13-45'));
$pruefe('unplausibel: leer', !od_secret_datum_plausibel(''));
set_setting('onedrive_secret_set_at', '');
$setzeDatum('');
settings_forget();
$pruefe('weder Datum noch Eintragetag: leer', od_secret_expires() === '');
set_setting('onedrive_secret_set_at', $warSeit);
settings_forget();

// --------------------------------- 14. Sprachkontext greift auch mittelbar
$pruefe('with_lang schaltet um',
    with_lang('en', static fn(): string => fmt_date('2028-08-03')) !== fmt_date('2028-08-03'));
$vorher = current_lang();
try { with_lang('fr', static function () { throw new RuntimeException('Absicht'); }); }
catch (Throwable $e) { /* gewollt */ }
$pruefe('und stellt sich auch nach einer Ausnahme zurueck', current_lang() === $vorher);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
