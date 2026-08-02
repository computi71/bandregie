<?php
declare(strict_types=1);

// Tabellen-Export ohne Fremdbibliotheken: Wo die ZIP-Erweiterung vorhanden ist,
// entsteht eine echte XLSX-Datei; sonst eine CSV, die Excel und LibreOffice
// ebenso öffnen. Beides wird direkt an den Browser gestreamt.

function export_available_format(): string {
  return class_exists('ZipArchive') ? 'xlsx' : 'csv';
}

/** @param string[] $head @param array<int, array<int, string>> $rows */
function export_send(string $basename, array $head, array $rows): never {
  if (export_available_format() === 'xlsx') {
    $data = export_xlsx_bytes($head, $rows);
    // Leer heißt: das Archiv kam nicht zustande. Dann die Tabelle als CSV —
    // besser ein anderes Format als eine Datei, die sich nicht öffnen lässt.
    if ($data !== '') export_send_bytes($basename . '.xlsx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $data);
    error_log('Bandregie: xlsx-Export fehlgeschlagen, CSV ausgeliefert');
  }
  export_send_csv($basename, $head, $rows);
}

/** Fertige Bytes als Download hinausgeben. */
function export_send_bytes(string $filename, string $mime, string $data): never {
  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . (string) strlen($data));
  echo $data;
  exit;
}

/**
 * Eine Zelle so entschärfen, dass die Tabellenkalkulation sie als Text nimmt.
 *
 * Beginnt ein Wert mit =, +, - oder @, hält Excel ihn für eine Formel und
 * rechnet ihn beim Öffnen — und Formeln können mehr, als sie sollten. Was
 * jemand als Beschreibung einer Buchung eingetippt hat, darf beim Empfänger
 * der Datei nichts ausführen. Ein vorangestelltes Hochkomma ist die übliche
 * Antwort darauf; die xlsx-Fassung braucht sie nicht, dort steht der Wert
 * ausdrücklich als Text.
 *
 * Zahlen bleiben unangetastet — ein Minusbetrag ist keine Formel, und als
 * Text wäre er in der Tabelle nicht mehr zu rechnen.
 */
function export_csv_cell(string $value): string {
  if ($value === '' || is_numeric($value)) return $value;
  return preg_match('~^[=+\-@\t\r]~', $value) ? "'" . $value : $value;
}

/** Die Tabelle als Zeichenkette — zum Senden und zum Beilegen in ein Paket. */
function export_csv_bytes(array $head, array $rows): string {
  $out = fopen('php://temp', 'r+');
  fwrite($out, "\xEF\xBB\xBF");            // BOM, damit Excel UTF-8 erkennt
  fputcsv($out, array_map('export_csv_cell', $head), ';', '"', '');
  foreach ($rows as $row) {
    fputcsv($out, array_map(fn($v) => export_csv_cell((string) $v), $row), ';', '"', '');
  }
  rewind($out);
  $data = (string) stream_get_contents($out);
  fclose($out);
  return $data;
}

function export_send_csv(string $basename, array $head, array $rows): never {
  export_send_bytes($basename . '.csv', 'text/csv; charset=utf-8', export_csv_bytes($head, $rows));
}

function export_xlsx_bytes(array $head, array $rows): string {
  $col = function (int $i): string {          // 0 -> A, 26 -> AA
    $s = '';
    for ($n = $i; $n >= 0; $n = intdiv($n, 26) - 1) $s = chr(65 + $n % 26) . $s;
    return $s;
  };
  $cell = function (string $ref, string $value) {
    if ($value === '') return '<c r="' . $ref . '"/>';
    if (preg_match('~^-?\d+(?:\.\d+)?$~', $value)) return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
    return '<c r="' . $ref . '" t="inlineStr" s="0"><is><t xml:space="preserve">'
      . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
  };

  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  $r = 1;
  $sheet .= '<row r="1">';
  foreach (array_values($head) as $i => $v) $sheet .= $cell($col($i) . '1', (string) $v);
  $sheet .= '</row>';
  foreach ($rows as $row) {
    $r++;
    $sheet .= '<row r="' . $r . '">';
    foreach (array_values($row) as $i => $v) $sheet .= $cell($col($i) . $r, (string) $v);
    $sheet .= '</row>';
  }
  $sheet .= '</sheetData></worksheet>';

  $parts = [
    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      . '</Types>',
    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      . '</Relationships>',
    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
      . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
      . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>',
    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      . '</Relationships>',
    'xl/worksheets/sheet1.xml' => $sheet,
  ];

  $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
  // Die Datei trägt den vollständigen Export — bei der Steuertabelle also die
  // Finanzen der Band. Endet die Anfrage zwischen Anlegen und Aufräumen, liefe
  // das @unlink unten nie; der Shutdown-Handler räumt auf jedem Weg.
  register_shutdown_function(static function () use ($tmp): void { @unlink($tmp); });
  $zip = new ZipArchive();
  // Lässt sich das Archiv nicht anlegen (kein Platz, keine Rechte im
  // Temp-Verzeichnis), schlägt anschließend jedes addFromString fehl und
  // zurückbliebe die leere Datei von tempnam() — ausgeliefert als 0-Byte-xlsx
  // mit Erfolgsmeldung. Wer damit zur Steuerberatung geht, merkt es dort.
  // Dann lieber die CSV: sie braucht kein Archiv und trägt dieselben Zahlen.
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) return '';
  foreach ($parts as $name => $content) $zip->addFromString($name, $content);
  if (!$zip->close()) return '';
  $data = (string) file_get_contents($tmp);
  @unlink($tmp);
  return $data;
}


/**
 * Die Tabelle im bestmöglichen Format, als Endung und Inhalt — für alles, was
 * sie nicht selbst ausliefert, sondern weiterreicht.
 *
 * @return array{0: string, 1: string} Endung und Inhalt
 */
function export_table_bytes(array $head, array $rows): array {
  if (export_available_format() === 'xlsx') {
    $data = export_xlsx_bytes($head, $rows);
    // Auch hier gilt: lieber eine CSV im Paket als eine leere Tabelle darin.
    if ($data !== '') return ['xlsx', $data];
    error_log('Bandregie: xlsx-Export fehlgeschlagen, CSV ins Paket gelegt');
  }
  return ['csv', export_csv_bytes($head, $rows)];
}
