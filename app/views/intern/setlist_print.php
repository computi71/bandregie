<?php
// Druckversion nach Band-Vorlage: pro Set eine A4-Seite.
// Kopf: Titel links (unterstrichen), Logo rechts; darunter die Songs 32 pt fett.
// Passt ein Set nicht auf die Seite, wird die Schrift automatisch verkleinert —
// gerechnet mit knappen Rändern (10/12 mm) und Umbruch langer Titel.

// Sets an den Pausen-Markern trennen (is_break 1 = Pause, 2 = Zugabe-Strich)
$sets = [[]];
foreach ($entries as $entry) {
  if ((int) $entry['is_break'] === 1) {
    $sets[] = [];
  } else {
    $sets[count($sets) - 1][] = $entry;
  }
}
$sets = array_values(array_filter($sets, fn($s) => $s !== []));

// Fürs Papier das Druck-Logo (dunkel auf weiß) bevorzugen, sonst Website-Logo
$logo = ($settings['print_logo_file'] ?? '') ?: ($settings['logo_file'] ?? '');
$watermark = $settings['print_watermark_file'] ?? '';

// Infozeile: ganzer Gig (alle Sets zusammen)
$songCount = count(array_filter($entries, fn($x) => !$x['is_break']));
$totalMin = (int) round(array_sum(array_map(fn($x) => (int) ($x['duration_sec'] ?? 0), $entries)) / 60);
$pauseCount = count(array_filter($entries, fn($x) => (int) $x['is_break'] === 1));
$pauseText = [0 => 'ohne Pause', 1 => 'mit Pause'][$pauseCount] ?? "mit $pauseCount Pausen";

// Schriftgröße pro Set. Bedruckbare Fläche: A4-Höhe 297 mm − 2×10 mm Rand −
// Kopfblock (~38 mm) ≈ 240 mm. Eine Zeile braucht F pt × 0,3528 mm/pt × 1,18
// Zeilenhöhe ≈ 0,42×F mm. Titel über ~28 Zeichen brechen bei 32 pt um und
// zählen doppelt. Daraus: F = 560 / Zeilen, gedeckelt auf 32 pt, min. 14 pt.
$fontFor = function (array $set): int {
  $lines = 0;
  foreach ($set as $entry) {
    $lines += (int) $entry['is_break'] === 2 ? 1 : (mb_strlen($entry['title']) > 28 ? 2 : 1);
  }
  return max(14, min(32, intdiv(560, max(1, $lines))));
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Setlist · <?= e($setlist['name']) ?></title>
  <style>
    /* Ränder fest ins Blatt eingebaut (Padding) statt über @page — so stimmen sie
       unabhängig von der Rand-Einstellung im Druckdialog des Browsers. */
    @page { size: A4 portrait; margin: 0; }
    body { font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }
    .sheet { box-sizing: border-box; width: 210mm; height: 296mm; padding: 12mm 14mm 10mm;
             break-after: page; position: relative; overflow: hidden; }
    .sheet:last-child { break-after: auto; }
    .head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
    .head { font-weight: 700; text-decoration: underline; font-size: 14pt; }
    .sub { font-size: 12pt; margin-top: 4mm; line-height: 1.35; }
    .logo img { max-height: 22mm; max-width: 75mm; }
    .logo .bandname { font-size: 22pt; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .songs { margin-top: 8mm; position: relative; z-index: 1; }
    .song { font-weight: 700; line-height: 1.18; }
    .song .note { font-weight: 400; font-size: 55%; }
    .encore-rule { border: 0; border-top: 0.6mm solid #000; margin: 1mm 0; }
    /* position:fixed wiederholt das Wasserzeichen beim Druck auf jeder Seite */
    /* Wasserzeichen liegt in jedem Blatt; pointer-events: none, damit es keine Klicks schluckt */
    .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 0; pointer-events: none; }
    .watermark img { width: 72%; opacity: 0.07; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .toolbar { margin: 0.6rem 0 1rem; position: relative; z-index: 2; }
    @media print { .toolbar { display: none; } }
    @media screen {
      body { background: #777; padding: 1rem; }
      .sheet { margin: 0 auto 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.4); background: #fff; }
    }
  </style>
</head>
<body>
  <div class="toolbar"><button data-print>🖨 Drucken</button></div>
  <?php foreach ($sets as $set): ?>
    <div class="sheet">
      <?php if ($watermark): ?>
        <div class="watermark"><img src="/uploads/<?= e($watermark) ?>" alt=""></div>
      <?php endif; ?>
      <div class="head-row">
        <div>
          <div class="head">Setlist <?= e($setlist['name']) ?></div>
          <div class="sub"><?= $songCount ?> Lieder<?= $totalMin > 0 ? " = $totalMin Minuten" : '' ?><br><?= e($pauseText) ?></div>
        </div>
        <div class="logo">
          <?php if ($logo): ?>
            <img src="/uploads/<?= e($logo) ?>" alt="<?= e($settings['band_name']) ?>">
          <?php else: ?>
            <span class="bandname"><?= e($settings['band_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="songs" style="font-size: <?= $fontFor($set) ?>pt">
        <?php foreach ($set as $entry): ?>
          <?php if ((int) $entry['is_break'] === 2): ?>
            <hr class="encore-rule">
          <?php else: ?>
            <div class="song">
              <?= e($entry['title']) ?>
              <?php if ($entry['notes'] && mb_strlen($entry['notes']) <= 40): ?><span class="note">(<?= e($entry['notes']) ?>)</span><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<script src="/assets/actions.js" defer></script>
</body>
</html>
