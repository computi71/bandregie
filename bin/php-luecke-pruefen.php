<?php
// Die Warnung vor PHP-Fassungen mit bekannter Lücke durchspielen (#389).
//
// Der Grund für eine eigene Datei: Diese Prüfung ist die einzige Stelle, an der
// die Anwendung eine Aussage über den Unterbau trifft, auf dem sie steht — und
// eine falsche Aussage wäre hier schlimmer als keine. Ein grünes Häkchen an
// einem offenen Server ist genau das, was am 21.10.2026 passiert ist.
//
// Sie schreibt nichts und darf überall laufen:
//
//   php bin/php-luecke-pruefen.php <Verzeichnis>
//
// Rückgabewert 0, wenn alles stimmt, sonst 1.
declare(strict_types=1);

$basis = $argv[1] ?? '';
if ($basis === '') {
    fwrite(STDERR, "Aufruf: php bin/php-luecke-pruefen.php <Verzeichnis>\n");
    exit(2);
}
chdir($basis);
require $basis . '/app/bootstrap.php';
require $basis . '/app/systemcheck.php';

$ok = 0;
$fehler = 0;
$pruefe = static function (string $was, bool $erfuellt) use (&$ok, &$fehler): void {
    printf("%-44s %s%s", $was, $erfuellt ? 'ok' : 'FEHLER', PHP_EOL);
    $erfuellt ? $ok++ : $fehler++;
};

// Erwartung je Fassung: '' = heil, 'offen' = Lücke, 'eol' = nichts zu sagen.
$faelle = [
    // Genau auf der geflickten Fassung ist heil, eine darunter nicht. Das ist
    // der Punkt, an dem ein Vergleich gegen EINE Zahl scheitern würde: 8.2.35
    // ist geflickt, das höher aussehende 8.3.35 nicht.
    '8.5.12' => '',      '8.5.11' => 'offen',
    '8.4.27' => '',      '8.4.26' => 'offen',
    '8.3.36' => '',      '8.3.35' => 'offen',
    '8.2.35' => '',      '8.2.34' => 'offen',

    // Zweige ohne eigene Veröffentlichung: Hier wird nichts behauptet, weil ein
    // Rückportieren der Distribution der Nummer nicht anzusehen ist.
    '8.1.34' => 'eol',   '8.0.30' => 'eol',   '7.4.33' => 'eol',

    // Und die Zukunft: Diese Liste ist vom Oktober 2026. Ein neuerer Zweig darf
    // NICHT als "nicht mehr gepflegt" gelten — die Warnung stünde sonst eines
    // Tages genau falsch herum.
    '8.6.0' => '',       '8.10.0' => '',      '9.0.0' => '',

    // So, wie Distributionen die Fassung wirklich schreiben.
    '8.3.36-1~deb12u1'             => '',
    '8.2.34-debian.12.260925.1442' => 'offen',
    '8.5.12-1+0~20261021.27+debian13~1.gbp1a3e8a' => '',
];

foreach ($faelle as $fassung => $erwartet) {
    [$ist, $rat] = php_hole_state((string) $fassung);
    $pruefe(sprintf('%-30s -> %s', $fassung, $ist === '' ? 'heil' : $ist), $ist === $erwartet);
    // Wo gewarnt wird, muss auch ein Rat stehen — eine Warnung ohne Ausweg
    // ist nur Beunruhigung.
    if ($ist !== '') {
        $pruefe(sprintf('%-30s    mit Rat im Klartext', $fassung),
            $rat !== '' && !str_contains($rat, '%1'));
    }
}

// Die laufende Fassung muss die Prüfung ebenfalls durchlaufen, ohne dass etwas
// Unerwartetes herauskommt.
[$hier, ] = php_hole_state(PHP_VERSION);
$pruefe('die laufende Fassung liefert eine gültige Antwort',
    in_array($hier, ['', 'offen', 'eol'], true));
printf('   (hier: PHP %s -> %s)%s', PHP_VERSION, $hier === '' ? 'heil' : $hier, PHP_EOL);

printf('%s%d ok, %d Fehler%s', PHP_EOL, $ok, $fehler, PHP_EOL);
exit($fehler ? 1 : 0);
