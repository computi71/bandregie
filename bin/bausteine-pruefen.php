<?php
// Die Vertragsbausteine gegen ihr Versprechen halten (#359).
//
// Ein Vertrag, der aus Teilen zusammengesetzt wird, hat zwei Fehlerklassen,
// die man beim Lesen einer einzelnen Seite nicht sieht:
//
//   - Eine Lücke in der Zählung. Wer einen Punkt abwählt und danach § 2, § 4
//     liest, hält den Vertrag für unvollständig, und ein Jurist schaut genau
//     darauf zuerst.
//   - Ein Entweder-oder, das beides durchlässt. Zwei Gagenregelungen in einem
//     Vertrag sind nicht bloß hässlich, sie sind widersprüchlich.
//
// Dazu die Pflichtstücke: Ein Vertrag ohne Kopf, ohne Unterschriftsfeld oder
// ohne die Abgabenklausel darf gar nicht erst entstehen können.
//
//   sudo -u www-data php bin/bausteine-pruefen.php
//
// Die Prüfung fasst einen vorhandenen Entwurf an und stellt seine Auswahl
// danach wieder her. Auf einer Produktionsanlage hat sie deshalb nichts zu
// suchen; gedacht ist sie für Staging und die Demo.

require __DIR__ . '/../app/bootstrap.php';

$fehler = 0;
$pruef = function (string $was, bool $ok, string $mehr = '') use (&$fehler): void {
  if (!$ok) $fehler++;
  echo ($ok ? '  ok   ' : '  FEHL '), $was, $mehr === '' ? '' : "  — $mehr", PHP_EOL;
};

$alle = contract_blocks();
$nach = [];
foreach ($alle as $b) $nach[(string) $b['bkey']] = $b;
$id = static fn(string $bkey): int => (int) ($nach[$bkey]['id'] ?? 0);

echo '— Wortlaut —', PHP_EOL;
$ohneText = array_filter($alle, static fn(array $b): bool => trim((string) $b['body']) === '');
$ohneName = array_filter($alle, static fn(array $b): bool => trim((string) $b['label']) === '');
$pruef('jeder Baustein hat einen Wortlaut', !$ohneText, implode(' ', array_column($ohneText, 'bkey')));
$pruef('jeder Baustein hat einen Kurznamen', !$ohneName, implode(' ', array_column($ohneName, 'bkey')));

$fremd = array_diff(array_unique(array_column($alle, 'gruppe')), array_keys(CONTRACT_BLOCK_GROUPS));
$pruef('jeder Baustein liegt in einem bekannten Abschnitt', !$fremd, implode(' ', $fremd));

$roh = contract_blocks_rohtext(array_map('intval', array_column($alle, 'id')));
// Backslash-n im Text war schon einmal ein ausgelieferter Fehler (#357).
$pruef('kein literales Backslash-n im Wortlaut', !str_contains($roh, chr(92) . 'n'));

preg_match_all('~\{[a-z_]+\}~', $roh, $pt);
$unbekannt = array_diff(array_unique($pt[0]), contract_placeholders());
$pruef('kein Baustein nennt einen Platzhalter, den es nicht gibt', !$unbekannt, implode(' ', $unbekannt));

echo PHP_EOL, '— Zählung —', PHP_EOL;
$satzweise = [
  'Vorauswahl' => contract_blocks_default(),
  'alles an'   => array_map('intval', array_column($alle, 'id')),
  'nur fest'   => [],
];
foreach ($satzweise as $name => $satz) {
  $text = contract_blocks_rohtext($satz);
  preg_match_all('~^§ (\d+) ~m', $text, $tr);
  $nummern = array_map('intval', $tr[1]);
  $pruef("Paragraphen lückenlos ab 1 ($name)", $nummern === range(1, count($nummern)),
    implode(',', $nummern) ?: 'keiner');

  foreach (preg_split('~^(?=§ )~m', $text) as $abschnitt) {
    if (!preg_match('~^§ (\d+) ~', $abschnitt, $kopf)) continue;
    preg_match_all('~^([a-z])\) ~m', $abschnitt, $bt);
    if (!$bt[1]) continue;
    $soll = array_slice(range('a', 'z'), 0, count($bt[1]));
    $pruef("Buchstaben lückenlos in § {$kopf[1]} ($name)", $bt[1] === $soll, implode('', $bt[1]));
  }
}

echo PHP_EOL, '— Auswahl —', PHP_EOL;
// Lieber ein eigener Wegwerf-Entwurf als der erstbeste echte: Die Prüfung
// schreibt an der Auswahl herum, und ein halbfertiger Vertrag, an dem gerade
// jemand sitzt, ist der falsche Ort dafür.
q("INSERT INTO contracts (contract_date, status, body) VALUES (CURDATE(), 'entwurf', '')");
$entwurf = (int) $db->lastInsertId();
try {

  $gagenarten = array_filter([$id('gage_fix'), $id('gage_anteil'), $id('gage_mix')]);
  contract_blocks_set($entwurf, $gagenarten);
  $geblieben = array_intersect(contract_block_ids($entwurf), $gagenarten);
  $pruef('von drei Gagenarten bleibt genau eine', count($geblieben) === 1, count($geblieben) . ' geblieben');

  contract_blocks_set($entwurf, []);
  $leer = contract_block_ids($entwurf);
  $feste = array_filter($alle, static fn(array $b): bool => (bool) $b['fest']);
  $pruef('leere Auswahl lässt genau die festen Punkte stehen',
    count($leer) === count($feste), count($leer) . ' statt ' . count($feste));

  // Der karge Fall ist der, der zählt: Was auch dann drinsteht, kann niemand
  // versehentlich aus einem Vertrag werfen.
  $karg = contract_compose(contract_full($entwurf), $leer);
  $pruef('auch der kargste Vertrag hat einen Kopf', str_contains($karg, 'GASTSPIELVERTRAG'));
  $pruef('auch der kargste Vertrag hat ein Unterschriftsfeld', str_contains($karg, 'Ort, Datum'));
  $pruef('die Künstlersozialabgabe lässt sich nicht abwählen', str_contains($karg, 'Künstlersozialabgabe'));
  $pruef('im fertigen Vertrag steht keine geschweifte Klammer mehr', !preg_match('~[{}]~', $karg));

  // Stillgelegt heißt „nicht mehr anbieten", nicht „aus laufenden Verträgen
  // ziehen". Das steht so am Schalter, also muss es auch gelten.
  $probe = $id('gaesteliste');
  if ($probe > 0) {
    contract_blocks_set($entwurf, [$probe]);
    q('UPDATE contract_blocks SET active = 0 WHERE id = ?', [$probe]);
    $nachher = contract_block_ids($entwurf);
    $pruef('ein stillgelegter Punkt bleibt angehakt', in_array($probe, $nachher, true));
    $pruef('und bleibt im Wortlaut stehen',
      str_contains(contract_compose(contract_full($entwurf), $nachher), 'Gästeliste'));
    $pruef('steht aber nicht mehr in der Vorauswahl',
      !in_array($probe, contract_blocks_default(), true));
    $pruef('und taucht in der Häkchenliste des Vertrages auf',
      in_array($probe, array_map('intval', array_column(contract_blocks_fuer_vertrag($nachher), 'id')), true));
    q('UPDATE contract_blocks SET active = 1 WHERE id = ?', [$probe]);
  }

} finally {
  // Auch wenn oben etwas geworfen hat: Der Wegwerf-Entwurf verschwindet.
  q('DELETE FROM contract_block_use WHERE contract_id = ?', [$entwurf]);
  q('DELETE FROM contracts WHERE id = ?', [$entwurf]);
  $pruef('Wegwerf-Entwurf wieder entfernt',
    row('SELECT id FROM contracts WHERE id = ?', [$entwurf]) === null);
}

echo PHP_EOL, $fehler === 0 ? 'Alles grün.' : "$fehler Punkte offen.", PHP_EOL;
exit($fehler === 0 ? 0 : 1);
