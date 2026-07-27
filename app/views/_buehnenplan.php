<?php
/**
 * Zeichnet den Bühnenplan als SVG. Erwartet $stageItems; optional $stageEdit
 * (Ziehen erlauben) und $stagePrint (Schwarzweiß für den Ausdruck).
 *
 * Die Fläche ist 1000 × 620 Einheiten; x und y der Einträge sind Prozent,
 * damit derselbe Plan auf jeder Bühne stimmt. Vorne ist unten — so schaut
 * der Veranstalter auf die Bühne wie das Publikum.
 */
$stageEdit  = $stageEdit ?? false;
$stagePrint = $stagePrint ?? false;
$stroke = $stagePrint ? '#333' : 'currentColor';
?>
<svg class="stage-plot<?= $stagePrint ? ' stage-print' : '' ?>" viewBox="0 0 1000 620" role="img"
     aria-label="<?= e(t('stage_plot')) ?>"<?= $stageEdit ? ' data-stageedit' : '' ?>>
  <rect x="10" y="10" width="980" height="600" rx="10" fill="none" stroke="<?= $stroke ?>" stroke-width="3" opacity="0.5"/>
  <text x="500" y="40" text-anchor="middle" font-size="26" fill="<?= $stroke ?>" opacity="0.55"><?= e(t('stage_back')) ?></text>
  <text x="500" y="596" text-anchor="middle" font-size="26" fill="<?= $stroke ?>" opacity="0.55"><?= e(t('stage_front')) ?></text>

  <?php foreach ($stageItems as $it): ?>
    <?php
      // Prozent auf die Zeichenfläche legen; y gedreht, weil vorne unten ist
      $px = 60 + ((int) $it['x'] / 100) * 880;
      $py = 560 - ((int) $it['y'] / 100) * 480;
      $sym = STAGE_KINDS[$it['kind']] ?? '▫';
    ?>
    <g class="stage-item" data-id="<?= (int) $it['id'] ?>" transform="translate(<?= round($px) ?>,<?= round($py) ?>)">
      <circle r="30" fill="none" stroke="<?= $stroke ?>" stroke-width="2" opacity="0.7"/>
      <text text-anchor="middle" y="10" font-size="30"><?= $sym ?></text>
      <text text-anchor="middle" y="52" font-size="22" fill="<?= $stroke ?>"><?= e($it['label']) ?></text>
      <?php if ($it['note'] !== ''): ?>
        <text text-anchor="middle" y="74" font-size="18" fill="<?= $stroke ?>" opacity="0.65"><?= e($it['note']) ?></text>
      <?php endif; ?>
    </g>
  <?php endforeach; ?>

  <?php if (!$stageItems): ?>
    <text x="500" y="320" text-anchor="middle" font-size="24" fill="<?= $stroke ?>" opacity="0.6"><?= e(t('stage_empty')) ?></text>
  <?php endif; ?>
</svg>
