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
    // Zugabe-Strich und Blockgrenze sind je eine Zeile; ohne das rechnet die
    // Seite bei sieben Blöcken zu groß und der letzte Song fällt herunter (#241).
    $lines += in_array((int) $entry['is_break'], [2, 3], true)
      ? 1 : (mb_strlen($entry['title']) > 28 ? 2 : 1);
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
    /* Der Titel bleibt auf einer Zeile: „Setlist Mittwochs Konzert Zündstoff –
       19. August 2026" brach neben dem Logo um und stand zweizeilig unter dem
       Bandnamen. Ein Kopf, der über zwei Zeilen läuft, drückt die Songs nach
       unten — und auf einem Blatt, das ohnehin knapp ist, kostet das eine Zeile
       Repertoire. Nur der Titel selbst wird schmaler, wenn der Platz nicht
       reicht; umgebrochen wird er nicht (#240). */
    .head {
      font-weight: 700;
      text-decoration: underline;
      font-size: 14pt;
      white-space: nowrap;
      /* Damit der Flex-Kasten den Titel schrumpfen lässt statt ihn zu brechen */
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    /* Lange Namen: eine Stufe kleiner, statt abgeschnitten zu werden. */
    .head.head-long { font-size: 12pt; }
    .head.head-verylong { font-size: 10.5pt; }
    .sub { font-size: 12pt; margin-top: 4mm; line-height: 1.35; }
    .logo img { max-height: 22mm; max-width: 75mm; }
    .logo .bandname { font-size: 22pt; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .songs { margin-top: 8mm; position: relative; z-index: 1; }
    .song { font-weight: 700; line-height: 1.18; }
    .song .note { font-weight: 400; font-size: 55%; }
    .encore-rule { border: 0; border-top: 0.6mm solid #000; margin: 1mm 0; }
    /* Blockgrenze: gestrichelt und dünner als der Zugabe-Strich — auf einen Blick
       zu unterscheiden, auch aus zwei Metern auf einem Notenpult (#241). */
    .block-line { border: 0; border-top: 0.35mm dashed #000; margin: 1.5mm 0; }
    /* Mit Anweisung: der Text sitzt in der Linie, links ausgerichtet, damit das
       Auge ihn beim Abwärtslesen findet. */
    .block-rule { display: flex; align-items: center; gap: 2mm; margin: 1.5mm 0; }
    .block-rule::after { content: ''; flex: 1; border-top: 0.35mm dashed #000; }
    .block-rule span { font-size: 45%; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    /* Eine Anweisung gilt für den Block UNTER der Linie — deshalb sitzt sie
       optisch näher an ihm als an dem Block darüber (#241). */
    .block-rule { margin: 2mm 0 0.5mm; }
    /* Die Klammer vom Zettel: Titel links, geschweifte Klammer rechts daneben,
       Anweisung dahinter. Gezeichnet aus einem Rahmen mit runden Ecken — das
       kommt der Handschrift näher als eine Glyphe, die je Drucker anders sitzt
       und bei zwei Zeilen zu groß wäre (#242). */
    .braced { display: flex; align-items: stretch; gap: 2mm; }
    .braced-songs { flex: 0 1 auto; }
    .brace {
      flex: 0 0 2.5mm;
      border: 0.5mm solid #000;
      border-left: 0;
      border-radius: 0 3mm 3mm 0;
      margin: 0.8mm 0;
    }
    .brace-note {
      align-self: center;
      font-size: 45%;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      white-space: nowrap;
    }
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
          <?php // Je länger der Name, desto kleiner die Zeile — gerechnet, nicht
              // geraten: 14 pt tragen etwa 46 Zeichen neben dem Logo. ?>
        <?php $headText = 'Setlist ' . $setlist['name']; ?>
        <?php $headClass = mb_strlen($headText) > 62 ? ' head-verylong' : (mb_strlen($headText) > 46 ? ' head-long' : ''); ?>
        <div class="head<?= $headClass ?>"><?= e($headText) ?></div>
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
        <?php
          // Zeilen mit derselben Klammernummer zu Gruppen zusammenfassen — die
          // gezeichnete Klammer vom Zettel (#242). Alles ohne Nummer bleibt für
          // sich; die Reihenfolge bleibt in jedem Fall die der Setliste.
          $gruppen = [];
          foreach ($set as $entry) {
            $nr = $entry['bracket'] !== null ? (int) $entry['bracket'] : 0;
            $letzte = $gruppen ? array_key_last($gruppen) : null;
            if ($nr > 0 && $letzte !== null && $gruppen[$letzte]['nr'] === $nr) {
              $gruppen[$letzte]['zeilen'][] = $entry;
            } else {
              $gruppen[] = ['nr' => $nr, 'zeilen' => [$entry]];
            }
          }
        ?>
        <?php foreach ($gruppen as $gruppe): ?>
        <?php // Eine Klammer nur, wo sie etwas umfasst — über einer Zeile wäre sie Zierrat. ?>
        <?php $geklammert = $gruppe['nr'] > 0 && count($gruppe['zeilen']) > 1; ?>
        <?php $klammerText = $geklammert ? (string) $gruppe['zeilen'][0]['item_note'] : ''; ?>
        <?php if ($geklammert): ?><div class="braced"><div class="braced-songs"><?php endif; ?>
        <?php foreach ($gruppe['zeilen'] as $entry): ?>
          <?php if ((int) $entry['is_break'] === 2): ?>
            <hr class="encore-rule">
          <?php elseif ((int) $entry['is_break'] === 3): ?>
            <?php // Blockgrenze wie der Strich auf dem Papier — gestrichelt, damit
                  // sie sich vom durchgezogenen Zugabe-Strich unterscheidet. Steht
                  // eine Anweisung dabei, steht sie in der Linie (#241). ?>
            <?php if ((string) $entry['item_note'] !== ''): ?>
              <div class="block-rule"><span><?= e($entry['item_note']) ?></span></div>
            <?php else: ?>
              <hr class="block-line">
            <?php endif; ?>
          <?php else: ?>
            <div class="song">
              <?= e($entry['title']) ?>
              <?php // Die Anweisung der Zeile zuerst — sie gilt für diesen Abend.
                    // Die Notiz am Lied gilt immer und tritt dahinter zurück. ?>
              <?php $cue = (string) $entry['item_note'] !== '' ? (string) $entry['item_note']
                            : ((string) $entry['notes'] !== '' && mb_strlen((string) $entry['notes']) <= 40 ? (string) $entry['notes'] : ''); ?>
              <?php if ($cue !== ''): ?><span class="note">(<?= e($cue) ?>)</span><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($geklammert): ?>
          </div>
          <?php // Die Klammer selbst: rechts neben den Titeln, mit der Anweisung
                // daneben — so wie sie auf dem Zettel gezeichnet ist. ?>
          <div class="brace" aria-hidden="true"></div>
          <?php if ($klammerText !== ''): ?><div class="brace-note"><?= e($klammerText) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<script src="<?= e(asset('/assets/actions.js')) ?>" defer></script>
</body>
</html>
