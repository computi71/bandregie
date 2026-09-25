<?php
// Das Angebot auf Papier (#302). Es benutzt den gemeinsamen Druckkopf wie
// Setliste und Rider, damit ein Veranstalter drei Blätter derselben Band
// bekommt und nicht drei Blätter aus drei Programmen.
//
// Die Zeilen kommen fertig aus quote_display_lines(): Ist der Rabatt nicht
// auszuweisen, stehen dort bereits ermäßigte Preise, und dann darf hier auch
// keine Rabattzeile mehr auftauchen.
$printDoc = 'quote';
$zeigtRabatt = !empty($quote['discount_show']) && $sums['discount'] > 0;
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('quote_sheet_title')) ?> · <?= e($quote['title']) ?></title>
  <style>
<?php require BASE_DIR . '/app/views/intern/_print_style.php'; ?>
    body { font-size: 10.5pt; }
    .head-row { border-bottom: 0.5mm solid #000; padding-bottom: 4mm; }
    h1 { font-size: 17pt; margin: 0 0 1mm; }
    .meta { margin-top: 6mm; }
    .meta div { margin: 0.6mm 0; }
    .meta b { display: inline-block; min-width: 34mm; }
    table { width: 100%; border-collapse: collapse; margin-top: 8mm; position: relative; z-index: 1; }
    th, td { border-bottom: 0.2mm solid #bbb; padding: 1.6mm 2mm; text-align: left; vertical-align: top; }
    th { border-bottom: 0.4mm solid #000; }
    .num { text-align: right; white-space: nowrap; }
    .sum td { border-top: 0.4mm solid #000; border-bottom: 0; font-weight: 700; font-size: 12pt; }
    .plain td { border-bottom: 0; }
    .note { font-size: 9pt; color: #555; margin-top: 8mm; }
    .prewrap { white-space: pre-wrap; }
  </style>
</head>
<body>
<?php $zurueckUrl = '/intern/angebote/' . (int) $quote['id']; $sprachwahl = true;
      require BASE_DIR . '/app/views/intern/_printbar.php'; ?>
<div class="sheet">
  <?= print_watermark_html($printDoc) ?>
  <div class="head-row">
    <div>
      <h1><?= e(t('quote_sheet_title')) ?></h1>
      <div class="muted"><?= e(setting('band_name')) ?> · <?= e(fmt_date($quote['quote_date'])) ?></div>
    </div>
    <?= print_logo_html($printDoc) ?>
  </div>

  <div class="meta">
    <?php if ($quote['customer'] !== ''): ?>
      <div><b><?= e(t('quote_for')) ?>:</b> <?= e($quote['customer']) ?></div>
    <?php endif; ?>
    <div><b><?= e(t('title_lbl')) ?>:</b> <?= e($quote['title']) ?></div>
    <?php if ($quote['event_id']): ?>
      <div><b><?= e(t('date')) ?>:</b> <?= e(fmt_date($quote['event_date'])) ?><?php
        if (($quote['event_time'] ?? '') !== ''): ?>, <?= e($quote['event_time']) ?><?php
        if (($quote['event_time_end'] ?? '') !== ''): ?>–<?= e($quote['event_time_end']) ?><?php endif;
        endif; ?></div>
      <?php $ort = event_place($quote); ?>
      <?php if ($ort !== ''): ?><div><b><?= e(t('quote_event')) ?>:</b> <?= e($ort) ?></div><?php endif; ?>
    <?php endif; ?>
    <?php if ((int) $quote['play_minutes'] > 0): ?>
      <div><b><?= e(t('quote_playtime_short')) ?>:</b> <?= e(quote_time_text((int) $quote['play_minutes'])) ?></div>
    <?php endif; ?>
  </div>

  <table>
    <thead><tr><th><?= e(t('quote_extra_label')) ?></th><th class="num"><?= e(t('quote_extra_amount')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($lines as $z): ?>
        <tr><td><?= e($z['label']) ?></td><td class="num"><?= e(fmt_money((int) $z['amount_cents'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <?php if ($zeigtRabatt): ?>
        <tr class="plain"><td><?= e(t('quote_subtotal')) ?></td><td class="num"><?= e(fmt_money($sums['before_discount'])) ?></td></tr>
        <tr class="plain"><td><?= e($quote['discount_label'] !== '' ? $quote['discount_label'] : t('quote_discount')) ?>
            <?= e('(' . $sums['discount_percent'] . ' %)') ?></td>
            <td class="num">− <?= e(fmt_money($sums['discount'])) ?></td></tr>
      <?php endif; ?>
      <?php if ($sums['vat_rate'] > 0): ?>
        <tr class="plain"><td><?= e(t('quote_net')) ?></td><td class="num"><?= e(fmt_money($sums['net'])) ?></td></tr>
        <tr class="plain"><td><?= e(sprintf(t('quote_vat'), $sums['vat_rate'])) ?></td><td class="num"><?= e(fmt_money($sums['vat'])) ?></td></tr>
      <?php endif; ?>
      <tr class="sum"><td><?= e(t('quote_total')) ?></td><td class="num"><?= e(fmt_money($sums['total'])) ?></td></tr>
    </tfoot>
  </table>

  <?php if (trim((string) $quote['notes']) !== ''): ?>
    <p class="prewrap note"><?= e($quote['notes']) ?></p>
  <?php endif; ?>

  <p class="note">
    <?php if ($sums['vat_rate'] === 0): ?><?= e(t('quote_small_business')) ?><br><?php endif; ?>
    <?= e(t('quote_footnote')) ?>
  </p>
</div>
</body>
</html>
