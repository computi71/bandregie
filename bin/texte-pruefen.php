<?php
// Texte gegen ihre Verwendung halten (#348).
//
// Zwei Fehlerklassen haben sich beide schon einmal bis in eine Auslieferung
// durchgeschlichen, und beide fallen bei keinem Seitenaufruf auf:
//
//   #340  Ein Text mit %1/%2 ging durch sprintf(). PHP 8 wirft darauf eine
//         ValueError — auf der einen Seite, die den Fehler melden sollte, und
//         nur dann, wenn es wirklich etwas zu melden gab.
//   #345  Ein neuer Text bekam keinen Seed. t() und push_t() fallen still auf
//         die deutsche Fassung zurück, also kam die ganze Mahnmail auf Deutsch
//         bei jemandem an, der kein Deutsch liest.
//
// Beides ist mechanisch zu finden. Diese Prüfung liest nur — sie schreibt
// nichts, braucht keine Datenbank und läuft deshalb überall:
//
//   php bin/texte-pruefen.php .
//
// Die Liste bin/texte-ohne-uebersetzung.txt hält den Rückstand fest, der bei
// der Einführung schon bestand. Sie ist keine Ausnahmeregel, sondern eine
// Aufgabenliste: Ein Schlüssel darin darf unübersetzt sein, jeder NEUE nicht.
// Wer einen abarbeitet, streicht ihn dort.
declare(strict_types=1);

$basis = rtrim($argv[1] ?? '.', '/\\');
if (!is_file($basis . '/app/strings/de.php')) {
    fwrite(STDERR, "Aufruf: php bin/texte-pruefen.php <Verzeichnis>\n");
    exit(2);
}

$ok = 0;
$fehler = 0;
$melde = static function (string $was, array $treffer) use (&$ok, &$fehler): void {
    printf("%-52s %s%s", $was, $treffer ? 'FEHLER (' . count($treffer) . ')' : 'ok', PHP_EOL);
    foreach (array_slice($treffer, 0, 12) as $t) printf("    %s%s", $t, PHP_EOL);
    if (count($treffer) > 12) printf("    … und %d weitere%s", count($treffer) - 12, PHP_EOL);
    $treffer ? $fehler++ : $ok++;
};

// ------------------------------------------------------------ Einlesen
//
// de.php wird EINGEBUNDEN, nicht zerlegt. Hier standen zwei Muster, und beide
// lagen daneben: Ein mit `.` zusammengesetzter Text (fl_demo_locked,
// demo_locked_hint) kam nur bis zum ersten Stück, und bei einem doppelt
// vergebenen Schlüssel (stage_empty, od_not_connected) nahm das Muster den
// ersten, während PHP den letzten nimmt — die Prüfung beurteilte also einen
// Text, den die Anwendung nie ausgibt. Die Datei definiert nur eine Konstante,
// braucht keine Datenbank und kein bootstrap; einbinden ist exakt und kürzer.
require $basis . '/app/strings/de.php';
$texte = UI_STRINGS;

// Doppelte Schlüssel fallen beim Einbinden nicht auf — PHP nimmt still den
// letzten. Also getrennt suchen und melden, statt sie nur im Kommentar zu
// erwähnen.
$deQuelle = (string) file_get_contents($basis . '/app/strings/de.php');
preg_match_all("~^\s*'([a-z0-9_]+)'\s*=>~m", $deQuelle, $dm);
$gesehen = [];
$doppelt = [];
foreach ($dm[1] as $k) {
    if (isset($gesehen[$k])) $doppelt[] = "$k — steht mehrfach in de.php, PHP nimmt den letzten";
    $gesehen[$k] = true;
}

// LANGS statt einer eigenen Liste: Käme eine siebte Sprache dazu, liefe diese
// Prüfung sonst weiter grün, während jeder neue Text dort fehlt.
require_once $basis . '/app/lang.php';
$sprachen = array_values(array_diff(array_keys(LANGS), ['de']));

// Aus den Seeds: was eingefügt wird, abzüglich dessen, was ein späterer Seed
// wieder löscht. Ein leerer Wert zählt NICHT als übersetzt — t() fällt darauf
// genauso auf Deutsch zurück wie auf eine fehlende Zeile (('it','events_oclock','')
// ist so ein Fall und stand bis eben als „übersetzt" da).
// In der Reihenfolge, in der die Anweisungen dastehen — nicht erst alle
// Einfügungen und dann alle Löschungen. Mehrere Seeds löschen einen Text und
// setzen ihn im selben Atemzug neu (29-help-and-music.sql sagt im Kopf sogar,
// warum es HIER stehen muss); wer die Löschung nachträglich anwendet, erklärt
// genau die für fehlend, die gerade erneuert wurden. Erster Lauf: 23
// Fehlalarme, von denen die Datenbank keinen einzigen bestätigte.
$uebersetzt = [];
$leer = [];
foreach (glob($basis . '/seed/translations/*.sql') ?: [] as $datei) {
    $seed = (string) file_get_contents($datei);
    $schritte = [];
    // Beide Maskierungen: Die Seeds schreiben ein Apostroph mal als '' und mal
    // als \' (MySQL kann beides). Wer nur '' kennt, bricht die Zeile mitten im
    // Wort ab und erklärt „Foto\'s" für nicht vorhanden — sechs Fehlalarme,
    // alle in genau den Sprachen, die Apostrophe benutzen.
    preg_match_all("~\\('([a-z]{2})','([a-z0-9_]+)','((?:[^'\\\\]|\\\\.|'')*)'\\)~", $seed, $sm,
                   PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($sm as $z) $schritte[$z[0][1]] = ['setzen', $z[1][0], $z[2][0], $z[3][0]];
    preg_match_all("~DELETE\s+FROM\s+translations[^;]*~is", $seed, $dl, PREG_OFFSET_CAPTURE);
    foreach ($dl[0] as $d) $schritte[$d[1]] = ['loeschen', $d[0]];
    ksort($schritte);

    foreach ($schritte as $schritt) {
        if ($schritt[0] === 'setzen') {
            $uebersetzt[$schritt[1]][$schritt[2]] = true;
            // Ein leer gesetzter Wert ist eine Zeile, aber keine Uebersetzung:
            // t() faellt darauf auf Deutsch zurueck. Gemeldet wird er unten
            // eigens - als Seed ist er da, seine Wirkung ist es nicht.
            if (trim($schritt[3]) === '') $leer[] = $schritt[1] . '/' . $schritt[2];
            continue;
        }
        // Nur echte Schlüssel entfernen — in der Anweisung stehen auch
        // Sprachkürzel und Vergleichswerte in Anführungszeichen.
        preg_match_all("~'([a-z0-9_]+)'~", $schritt[1], $weg);
        foreach ($weg[1] as $k) {
            if (!isset($texte[$k])) continue;
            foreach ($sprachen as $l) unset($uebersetzt[$l][$k]);
        }
    }
}

$quellen = [];
foreach (['app', 'httpdocs'] as $ordner) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basis . '/' . $ordner));
    foreach ($it as $datei) {
        if ($datei->isFile() && $datei->getExtension() === 'php'
            && !str_ends_with(str_replace('\\', '/', $datei->getPathname()), 'app/strings/de.php')) {
            $quellen[str_replace($basis . DIRECTORY_SEPARATOR, '', $datei->getPathname())]
                = (string) file_get_contents($datei->getPathname());
        }
    }
}

$melde('kein Schluessel doppelt in de.php', $doppelt);
// Kein Fehler, aber auch kein Schweigen: Ein leer geseedeter Text sieht
// uebersetzt aus und kommt beim Leser auf Deutsch an.
if ($leer) printf("%-52s %s%s", 'Hinweis: leer geseedet (wirkt deutsch)', implode(', ', $leer), PHP_EOL);

// --------------------------------------------- 1. Benutzte Schlüssel gibt es
// Ein Tippfehler in t('termne_titel') gibt keinen Fehler, sondern zeigt den
// Schlüssel selbst an — im Zweifel mitten auf der Seite.
$unbekannt = [];
foreach ($quellen as $pfad => $src) {
    foreach (["~\bt\('([a-z0-9_]+)'\)~", "~push_t\(\s*\\\$[a-zA-Z_]+\s*,\s*'([a-z0-9_]+)'\s*\)~"] as $muster) {
        preg_match_all($muster, $src, $tm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($tm as $treffer) {
            $schluessel = $treffer[1][0];
            if (!isset($texte[$schluessel])) {
                $nr = substr_count(substr($src, 0, (int) $treffer[0][1]), "\n") + 1;
                $unbekannt[] = "$pfad:$nr  t('$schluessel') — steht nicht in de.php";
            }
        }
    }
}
$melde('jeder benutzte Schluessel steht in de.php', $unbekannt);

// ------------------------------------- 2. sprintf bekommt sprintf-Texte (#340)
// Erlaubt sind %s, %d und Verwandte, auch mit Stellenangabe (%1$s). Verboten
// ist %1 ohne Dollarzeichen — das ist die str_replace-Schreibweise, und PHP
// bricht darauf ab.
$falschFormat = [];
foreach ($quellen as $pfad => $src) {
    preg_match_all("~sprintf\(\s*t\('([a-z0-9_]+)'\)~", $src, $fm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    foreach ($fm as $treffer) {
        $schluessel = $treffer[1][0];
        $text = $texte[$schluessel] ?? '';
        if (preg_match('~%\d(?![$0-9])~', $text)) {
            $nr = substr_count(substr($src, 0, (int) $treffer[0][1]), "\n") + 1;
            $falschFormat[] = "$pfad:$nr  sprintf(t('$schluessel')) — Text traegt %1/%2 statt %s/%d";
        }
    }
}
$melde('sprintf-Texte benutzen %s und %d', $falschFormat);

// ------------------------------- 3. str_replace bekommt str_replace-Texte
// Die Gegenrichtung: %s in einem Text, der ersetzt statt formatiert wird,
// bleibt einfach stehen und steht dann so in der Mail.
$falschMarke = [];
foreach ($quellen as $pfad => $src) {
    preg_match_all("~str_replace\(([^;]{0,220}?)(?:t|push_t)\(\s*(?:\\\$[a-zA-Z_]+\s*,\s*)?'([a-z0-9_]+)'\s*\)~s",
                   $src, $rm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    foreach ($rm as $treffer) {
        $schluessel = $treffer[2][0];
        $text = $texte[$schluessel] ?? '';
        if ($text === '') continue;
        if (preg_match('~%(?:\d+\$)?[sdfu]~', $text)) {
            $nr = substr_count(substr($src, 0, (int) $treffer[0][1]), "\n") + 1;
            $falschMarke[] = "$pfad:$nr  str_replace(… t('$schluessel')) — Text traegt %s/%d statt %1/%2";
        }
        // Beide Richtungen, und die zweite ist die gefährliche: Ein %2 im Text,
        // das die Aufrufstelle nie ersetzt, bleibt stehen und geht so in die
        // Mail. Genau dafür wurde od_secret_mail_text() herausgezogen — geprüft
        // wurde bis hierher aber nur die harmlose Richtung, obwohl der
        // Kommentar „und umgekehrt" versprach.
        $ersetzt = substr_count($treffer[1][0], "'%2'");
        $imText = preg_match('~%2(?![0-9$])~', $text);
        $nr = substr_count(substr($src, 0, (int) $treffer[0][1]), "\n") + 1;
        if (!$ersetzt && $imText) {
            $falschMarke[] = "$pfad:$nr  t('$schluessel') — %2 steht im Text, wird hier aber nicht ersetzt";
        }
        if ($ersetzt && !$imText) {
            $falschMarke[] = "$pfad:$nr  t('$schluessel') — %2 wird ersetzt, steht aber nicht im Text";
        }
    }
}
$melde('str_replace-Texte benutzen %1 und %2', $falschMarke);

// ------------------------------------------ 4. Mailtexte sind uebersetzt (#345)
// Hier gibt es keinen Rueckstand und darf keiner entstehen: Eine Mail kann der
// Empfaenger nicht umschalten, und die Sprache der Oberflaeche hilft ihm nicht.
// Auch die zusammengesetzten: push_t($lang, 'itemkind_' . $kind) nennt keinen
// fertigen Schlüssel, aber jeden, der mit „itemkind_" anfängt. Ohne diese
// Zeilen blieben ganze Gruppen unsichtbar — die Abschnittsüberschriften der
// Tagesmail und die Sortennamen waren genau so durchgerutscht.
$mailSchluessel = [];
$mailPraefixe = [];
foreach ($quellen as $src) {
    preg_match_all("~push_t\(\s*\\\$[a-zA-Z_]+\s*,\s*'([a-z0-9_]+)'\s*\)~", $src, $pm);
    foreach ($pm[1] as $k) $mailSchluessel[$k] = true;
    preg_match_all("~push_t\(\s*\\\$[a-zA-Z_]+\s*,\s*'([a-z0-9_]+_)'\s*\.~", $src, $pp);
    foreach ($pp[1] as $p) $mailPraefixe[$p] = true;
}
$praefixListe = array_keys($mailPraefixe);
foreach (array_keys($texte) as $k) {
    foreach ($praefixListe as $p) {
        if (str_starts_with($k, $p)) { $mailSchluessel[$k] = true; break; }
    }
}
$mailLuecken = [];
foreach (array_keys($mailSchluessel) as $k) {
    if (!isset($texte[$k])) continue;              // Abschnitt 1 meldet das
    foreach ($sprachen as $l) {
        if (!isset($uebersetzt[$l][$k])) $mailLuecken[] = "$k fehlt in $l";
    }
}
$melde('Mailtexte sind in allen Sprachen vorhanden', $mailLuecken);

// ------------------------------- 5. Neue Oberflaechentexte bekommen ihren Seed
$listeDatei = $basis . '/bin/texte-ohne-uebersetzung.txt';
$bekannt = [];
foreach (file($listeDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
    $zeile = trim($zeile);
    if ($zeile !== '' && !str_starts_with($zeile, '#')) $bekannt[$zeile] = true;
}
// Über ALLE Schlüssel, nicht nur die wörtlich benutzten. Der Filter „wird
// benutzt" war eine blinde Stelle: Sorten, Abschnitte, Rollen und Zustände
// entstehen zur Laufzeit aus einem Wortstamm, und 48 Texte fehlten deshalb,
// ohne dass sie irgendwo auffielen. Ein Text, den niemand benutzt, gehört
// gelöscht — nicht von dieser Prüfung verschwiegen.
$neueLuecken = [];
$gemeldet = [];
$offen = 0;
foreach (array_keys($texte) as $k) {
    foreach ($sprachen as $l) {
        if (isset($uebersetzt[$l][$k])) continue;
        $offen++;
        if (!isset($bekannt[$k]) && !isset($gemeldet[$k])) {
            $gemeldet[$k] = true;
            $neueLuecken[] = "$k fehlt in $l — Seed nachziehen";
        }
    }
}
$melde('kein NEUER Text ohne Uebersetzung', $neueLuecken);

// --------------------------------------------- 6. Was in der Liste steht, lebt
// Ein abgearbeiteter oder geloeschter Schluessel soll die Liste verlassen,
// sonst deckt sie irgendwann eine echte Luecke mit.
$totInListe = [];
foreach (array_keys($bekannt) as $k) {
    if (!isset($texte[$k])) { $totInListe[] = "$k — steht in der Liste, aber nicht mehr in de.php"; continue; }
    $fehltNoch = false;
    foreach ($sprachen as $l) if (!isset($uebersetzt[$l][$k])) $fehltNoch = true;
    if (!$fehltNoch) $totInListe[] = "$k — inzwischen uebersetzt, gehoert aus der Liste gestrichen";
}
$melde('die Rueckstandsliste ist aktuell', $totInListe);

printf('%s%d ok, %d Fehler   (Rueckstand: %d Luecken ueber %d Schluessel)%s',
       PHP_EOL, $ok, $fehler, $offen, count($bekannt), PHP_EOL);
exit($fehler ? 1 : 0);
