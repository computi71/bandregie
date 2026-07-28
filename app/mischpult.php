<?php
declare(strict_types=1);

/**
 * Kanalbelegung aus einer Mischpult-Datei lesen.
 *
 * Zwei Formate, weil die verbreiteten Pulte zwei völlig verschiedene Dateien
 * schreiben: das X32/M32 einen Textabzug seiner OSC-Befehle (.scn), das WING
 * eine JSON-Momentaufnahme (.snap). Erkannt wird am Inhalt, nicht an der
 * Endung — eine umbenannte Datei ist keine andere Datei.
 *
 * Gesucht ist am Ende nur eines: der Port, in den das Kabel gesteckt wird.
 */

/**
 * Quellgruppen des WING. Die Kürzel stehen so in der Datei; ausgeschrieben
 * versteht sie auch jemand, der nicht am Pult steht.
 */
const WING_INPUT_GROUPS = [
  'LCL'  => 'Local', 'AUX' => 'Aux', 'A' => 'A', 'B' => 'B', 'C' => 'C',
  'SC'   => 'StageConnect', 'USB' => 'USB', 'CRD' => 'Card', 'MOD' => 'Module',
  'PLAY' => 'Player',
];

/**
 * Kanäle aus einer Szenen- oder Momentaufnahmedatei.
 *
 * @return array<int, array{name: string, patch: string}> nach Kanalnummer
 */
function mixer_channels(string $raw): array {
  $wing = mixer_channels_wing($raw);
  return $wing !== [] ? $wing : mixer_channels_x32($raw);
}

/**
 * WING-Momentaufnahme (.snap): JSON mit ae_data.ch.<Nr>. Der Name steht am
 * Kanal, der Eingang in in.conn — Gruppe plus Nummer, also „A3" oder
 * „Local 5". „OFF" heißt: nichts gepatcht, dann bleibt der Port leer, statt
 * eine Buchse zu erfinden, die es nicht gibt.
 *
 * Ob ein Eingang mono oder stereo läuft, steht nicht am Kanal, sondern am
 * Eingang selbst (io.in). Ein Stereo-Eingang belegt zwei Buchsen — immer eine
 * ungerade und die darauffolgende gerade —, und beide Kabel müssen auf den
 * Rider.
 */
function mixer_channels_wing(string $raw): array {
  $data = json_decode($raw, true);
  if (!is_array($data) || !isset($data['ae_data']['ch']) || !is_array($data['ae_data']['ch'])) {
    return [];
  }
  $inputs = $data['ae_data']['io']['in'] ?? [];
  $out = [];
  foreach ($data['ae_data']['ch'] as $number => $channel) {
    if (!is_array($channel)) continue;
    $name = trim((string) ($channel['name'] ?? ''));
    if ($name === '' || (int) $number <= 0) continue;
    $conn = $channel['in']['conn'] ?? [];
    $out[(int) $number] = [
      'name'  => $name,
      'patch' => wing_port((string) ($conn['grp'] ?? ''), (int) ($conn['in'] ?? 0), $inputs),
    ];
  }
  ksort($out);
  return $out;
}

/**
 * Ein Eingang des WING als lesbarer Port; stereo ergibt „A9–A10".
 *
 * @param array $inputs der Zweig ae_data.io.in
 */
function wing_port(string $group, int $input, array $inputs): string {
  if ($group === '' || $group === 'OFF' || $input < 1) return '';
  $label = WING_INPUT_GROUPS[$group] ?? $group;
  // Einbuchstabige Gruppen sind die Stageboxen: „A3" liest sich besser als
  // „A 3", bei ausgeschriebenen Gruppen ist das Leerzeichen richtig.
  $port = fn(int $n): string => strlen($label) === 1 ? $label . $n : $label . ' ' . $n;

  if (($inputs[$group][(string) $input]['mode'] ?? 'M') !== 'ST') return $port($input);
  $first = $input % 2 === 1 ? $input : $input - 1;
  // „A9–A10" bei den Stageboxen, „Local 7–8" bei den ausgeschriebenen: das
  // Kürzel zweimal zu wiederholen liest sich dort nur umständlich.
  return strlen($label) === 1
    ? $port($first) . '–' . $port($first + 1)
    : $port($first) . '–' . ($first + 1);
}

/**
 * X32/M32-Szene (.scn): Zeilen der Form
 *   /ch/01/config "BD GF" 2 YE 1
 * also Name, Symbol, Farbe und — hier das Entscheidende — die Eingangsnummer.
 * Eine 0 heißt: kein Eingang zugewiesen. Beim X32 ist die Zuordnung damit
 * geradliniger als beim WING: eine Nummer, keine Gruppen, kein Stereopaar.
 */
function mixer_channels_x32(string $raw): array {
  $out = [];
  foreach (preg_split('~\R~', $raw) ?: [] as $line) {
    if (!preg_match('~^/ch/(\d+)/config\s+"([^"]*)"(?:\s+\S+\s+\S+\s+(\d+))?~', trim($line), $m)) continue;
    $name = trim($m[2]);
    if ($name === '') continue;
    $source = (int) ($m[3] ?? 0);
    $out[(int) $m[1]] = ['name' => $name, 'patch' => $source > 0 ? (string) $source : ''];
  }
  ksort($out);
  return $out;
}
