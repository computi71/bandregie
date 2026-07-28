<?php
declare(strict_types=1);

/**
 * Kanalbelegung aus einer Mischpult-Datei lesen.
 *
 * Zwei Formate, weil die verbreiteten Pulte zwei völlig verschiedene Dateien
 * schreiben: das X32/M32 einen Textabzug seiner OSC-Befehle (.scn), das WING
 * eine JSON-Momentaufnahme (.snap). Erkannt wird am Inhalt, nicht an der
 * Endung — eine umbenannte Datei ist keine andere Datei.
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
 * Kanal, die Quelle in in.conn — Gruppe plus Nummer, also „A3" oder „Local 5".
 * „OFF" heißt: nichts gepatcht. Dann bleibt der Eingang leer, statt eine
 * Buchse zu erfinden, die es nicht gibt.
 */
function mixer_channels_wing(string $raw): array {
  $data = json_decode($raw, true);
  if (!is_array($data) || !isset($data['ae_data']['ch']) || !is_array($data['ae_data']['ch'])) {
    return [];
  }
  $out = [];
  foreach ($data['ae_data']['ch'] as $number => $channel) {
    if (!is_array($channel)) continue;
    $name = trim((string) ($channel['name'] ?? ''));
    if ($name === '' || (int) $number <= 0) continue;
    $conn = $channel['in']['conn'] ?? [];
    $group = (string) ($conn['grp'] ?? '');
    $input = (int) ($conn['in'] ?? 0);
    $patch = '';
    if ($group !== '' && $group !== 'OFF' && $input > 0) {
      $label = WING_INPUT_GROUPS[$group] ?? $group;
      // Einbuchstabige Gruppen sind die Stageboxen: „A3" liest sich besser
      // als „A 3", bei ausgeschriebenen Gruppen ist das Leerzeichen richtig.
      $patch = strlen($label) === 1 ? $label . $input : $label . ' ' . $input;
    }
    $out[(int) $number] = ['name' => $name, 'patch' => $patch];
  }
  ksort($out);
  return $out;
}

/**
 * X32/M32-Szene (.scn): Zeilen der Form /ch/01/config "Kick" 1 RD 1.
 * Einen Eingang nennt sie nicht — das Pult schreibt nur die Beschriftung.
 */
function mixer_channels_x32(string $raw): array {
  $out = [];
  foreach (preg_split('~\R~', $raw) ?: [] as $line) {
    if (preg_match('~^/ch/(\d+)/config\s+"([^"]*)"~', trim($line), $m)) {
      $name = trim($m[2]);
      if ($name !== '') $out[(int) $m[1]] = ['name' => $name, 'patch' => ''];
    }
  }
  ksort($out);
  return $out;
}
