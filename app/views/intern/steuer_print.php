<?php
// Druckfassung für die Steuerberatung: ein Blatt, das ohne die Anwendung
// verständlich ist. Es nennt deshalb oben, wessen Zahlen es zeigt, und unten,
// mit welchen Werten gerechnet wurde — auf dem Papier kann niemand nachsehen.
$taxOwner = $scope === 'band' ? ($settings['band_name'] ?? '') : ($user['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= e(t('taxr_title')) ?> <?= (int) $year ?> · <?= e($taxOwner) ?></title>
  <style>
    @page { size: A4 portrait; margin: 0; }
    body { font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; font-size: 10.5pt; }
    .sheet { box-sizing: border-box; width: 210mm; min-height: 296mm; padding: 14mm 16mm 12mm; }
    .head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm;
                border-bottom: 0.5mm solid #000; padding-bottom: 4mm; }
    h1 { font-size: 17pt; margin: 0 0 1mm; }
    h2 { font-size: 11.5pt; margin: 6mm 0 1.5mm; text-transform: uppercase; letter-spacing: 0.04em; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th, td { border-bottom: 0.2mm solid #bbb; padding: 1.2mm 2mm; text-align: left; vertical-align: top; }
    th { border-bottom: 0.4mm solid #000; }
    .num { text-align: right; white-space: nowrap; }
    .sum td { border-top: 0.4mm solid #000; border-bottom: 0; font-weight: 700; }
    .muted { color: #555; }
    .note { font-size: 9pt; color: #555; margin-top: 6mm; }
    .toolbar { padding: 0.6rem 16mm; }
    @media print { .toolbar { display: none; } }
    @media screen { body { background: #777; padding: 1rem 0; }
                    .sheet { margin: 0 auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.4); } }
  </style>
</head>
<body>
<div class="toolbar"><button data-print>🖨 <?= e(t('sl_print')) ?></button></div>
<div class="sheet">
  <div class="head-row">
    <div>
      <h1><?= e(t('taxr_title')) ?> <?= (int) $year ?></h1>
      <div class="muted"><?= e($taxOwner) ?> · <?= e(fmt_date(date('Y-m-d'))) ?></div>
    </div>
    <div class="muted"><?= e($scope === 'band' ? t('taxr_scope_band') : t('taxr_scope_own')) ?></div>
  </div>

  <h2><?= e(t('fin_by_category')) ?></h2>
  <table>
    <thead><tr><th><?= e(t('fin_category')) ?></th><th class="num"><?= e(t('fin_income')) ?></th><th class="num"><?= e(t('fin_expense')) ?></th></tr></thead>
    <tbody>
      <?php foreach (array_keys($report['income'] + $report['expense']) as $cat): ?>
        <tr>
          <td><?= e(fin_category_label($cat)) ?></td>
          <td class="num"><?= isset($report['income'][$cat]) ? fmt_money($report['income'][$cat]) : '' ?></td>
          <td class="num"><?= isset($report['expense'][$cat]) ? fmt_money($report['expense'][$cat]) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="sum">
        <td><?= e(t('taxr_sum')) ?></td>
        <td class="num"><?= fmt_money($report['sum_income']) ?></td>
        <td class="num"><?= fmt_money($report['sum_expense']) ?></td>
      </tr>
    </tbody>
  </table>

  <?php if ($report['equipment']): ?>
    <h2><?= e(t('taxr_equipment')) ?></h2>
    <table>
      <thead>
        <tr>
          <th><?= e(t('name')) ?></th>
          <th><?= e(t('taxr_purchased')) ?></th>
          <th class="num"><?= e(t('taxr_purchase_price')) ?></th>
          <th><?= e(t('taxr_method')) ?></th>
          <th class="num"><?= e(t('taxr_amount_year')) ?></th>
          <th class="num"><?= e(t('taxr_remaining')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['equipment'] as $eq): ?>
          <tr>
            <td><?= e($eq['name']) ?></td>
            <td><?= e(fmt_date($eq['date'])) ?></td>
            <td class="num"><?= fmt_money($eq['cents']) ?></td>
            <td class="muted"><?= e($eq['kind'] === 'gwg'
                  ? t('taxr_kind_gwg')
                  : sprintf(t('taxr_kind_afa'), $eq['years'], $eq['first_year'], $eq['last_year'])) ?></td>
            <td class="num"><?= fmt_money($eq['this_year']) ?></td>
            <td class="num muted"><?= fmt_money($eq['remaining']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="sum">
          <td colspan="4"><?= e(t('taxr_afa')) ?></td>
          <td class="num"><?= fmt_money($report['sum_afa']) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <h2><?= e(sprintf(t('taxr_result_year'), $year)) ?></h2>
  <table>
    <tbody>
      <tr><td><?= e(t('fin_income')) ?></td><td class="num"><?= fmt_money($report['sum_income']) ?></td></tr>
      <tr><td><?= e(t('fin_expense')) ?></td><td class="num">− <?= fmt_money($report['sum_expense']) ?></td></tr>
      <tr><td><?= e(t('taxr_afa')) ?></td><td class="num">− <?= fmt_money($report['sum_afa']) ?></td></tr>
      <tr class="sum"><td><?= e(t('taxr_sum')) ?></td><td class="num"><?= fmt_money($report['result']) ?></td></tr>
    </tbody>
  </table>

  <h2><?= e(t('taxr_entries')) ?></h2>
  <table>
    <thead>
      <tr><th><?= e(t('date')) ?></th><th><?= e(t('fin_category')) ?></th>
          <th><?= e(t('fin_description')) ?></th><th class="num"><?= e(t('fin_amount')) ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($report['entries'] as $en): ?>
        <tr>
          <td><?= e(fmt_date($en['date'])) ?></td>
          <td><?= e(fin_category_label($en['category'])) ?></td>
          <td><?= e($en['description']) ?></td>
          <td class="num"><?= $en['type'] === 'einnahme' ? '+' : '−' ?><?= fmt_money((int) $en['amount_cents']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p class="note">
    <?= e(t('taxr_applied')) ?>:
    <?= e(t('set_tax_gwg')) ?> <?= fmt_money(tax_gwg_limit_cents()) ?> ·
    <?= e(t('set_tax_afa_years')) ?> <?= (int) tax_afa_years() ?> ·
    <?= e(t('taxr_small')) ?>: <?= e(setting('tax_small_business', '0') === '1' ? t('taxr_small_on') : t('taxr_small_off')) ?>
  </p>
  <p class="note"><?= e(t('taxr_gross_hint')) ?></p>
  <p class="note">⚖ <?= e(t('tax_no_advice')) ?></p>
</div>
<script src="<?= e(asset('/assets/actions.js')) ?>" defer></script>
</body>
</html>
