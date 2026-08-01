<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Was hier steht, geht an eine Steuerberatung — deshalb sagt die Seite an
// jeder Zahl, wie sie zustande kommt, und am Ende, mit welchen Einstellungen.
$taxQuery = fn(array $over = []): string => '?' . http_build_query(
  array_merge(['jahr' => $year, 'umfang' => $scope], $over));
?>
<div class="page-head">
  <h1>⚖ <?= e(t('taxr_title')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost btn-small" href="/intern/kasse/steuer/druck<?= e($taxQuery()) ?>" target="_blank">🖨 <?= e(t('sl_print')) ?></a>
    <a class="btn btn-ghost btn-small" href="/intern/kasse/steuer/export<?= e($taxQuery()) ?>">⭳ <?= e(t('ev_export')) ?></a>
    <?php if (class_exists('ZipArchive')): ?>
      <a class="btn btn-ghost btn-small" href="/intern/kasse/steuer/paket<?= e($taxQuery()) ?>">📦 <?= e(t('taxr_package')) ?></a>
    <?php endif; ?>
  </div>
</div>
<p class="muted"><?= e(t('taxr_intro')) ?></p>
<p class="muted small">📦 <?= e(t('taxr_package_hint')) ?></p>

<div class="row-buttons">
  <?php if (can_finance()): ?>
    <a class="btn <?= $scope === 'eigen' ? 'btn-primary' : 'btn-ghost' ?> btn-small"
       href="/intern/kasse/steuer<?= e($taxQuery(['umfang' => 'eigen'])) ?>"><?= e(t('taxr_scope_own')) ?></a>
    <a class="btn <?= $scope === 'band' ? 'btn-primary' : 'btn-ghost' ?> btn-small"
       href="/intern/kasse/steuer<?= e($taxQuery(['umfang' => 'band'])) ?>"><?= e(t('taxr_scope_band')) ?></a>
  <?php endif; ?>
  <?php foreach ($years as $y): ?>
    <a class="btn <?= $year === (int) $y ? 'btn-primary' : 'btn-ghost' ?> btn-small"
       href="/intern/kasse/steuer<?= e($taxQuery(['jahr' => $y])) ?>"><?= (int) $y ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$report['entries'] && !$report['equipment']): ?>
  <div class="card"><p class="muted"><?= e(t('taxr_empty')) ?></p></div>
<?php else: ?>

<div class="grid-2">
  <div class="card">
    <h2><?= e(sprintf(t('taxr_result_year'), $year)) ?></h2>
    <p style="font-size:1.8rem; font-weight:bold" class="<?= $report['result'] < 0 ? 'warn' : '' ?>"><?= fmt_money($report['result']) ?></p>
    <ul class="task-list">
      <li><strong><?= e(t('fin_income')) ?></strong><span class="muted">+ <?= fmt_money($report['sum_income']) ?></span></li>
      <li><strong><?= e(t('fin_expense')) ?></strong><span class="muted">− <?= fmt_money($report['sum_expense']) ?></span></li>
      <li><strong><?= e(t('taxr_afa')) ?></strong><span class="muted">− <?= fmt_money($report['sum_afa']) ?></span></li>
      <?php // Einlagen und Ausschüttungen stehen in der Liste unten, zählen aber
            // nicht ins Ergebnis. Ohne diese Zeile sähe es aus, als fehlte Geld. ?>
      <?php $neutral = $report['sum_neutral_in'] + $report['sum_neutral_out']; ?>
      <?php if ($neutral > 0): ?>
        <li><strong><?= e(t('taxr_neutral')) ?></strong><span class="muted"><?= fmt_money($neutral) ?></span></li>
      <?php endif; ?>
    </ul>
    <?php if ($neutral > 0): ?><p class="muted small"><?= e(t('taxr_neutral_hint')) ?></p><?php endif; ?>
    <p class="muted small"><?= e($scope === 'band' ? t('taxr_scope_band_hint') : t('taxr_scope_own_hint')) ?></p>
  </div>

  <div class="card">
    <h2><?= e(t('fin_by_category')) ?></h2>
    <ul class="task-list">
      <?php foreach ($report['income'] as $cat => $cents): ?>
        <li><strong><?= e(fin_category_label($cat)) ?></strong><span class="muted">+ <?= fmt_money($cents) ?></span></li>
      <?php endforeach; ?>
      <?php foreach ($report['expense'] as $cat => $cents): ?>
        <li><strong><?= e(fin_category_label($cat)) ?></strong><span class="muted">− <?= fmt_money($cents) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$report['income'] && !$report['expense']): ?><p class="muted"><?= e(t('fin_none')) ?></p><?php endif; ?>
  </div>
</div>

<?php if ($report['equipment']): ?>
  <div class="card">
    <h2>🎛 <?= e(t('taxr_equipment')) ?></h2>
    <p class="muted small"><?= e(t('taxr_equipment_hint')) ?></p>
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('name')) ?></th>
          <th><?= e(t('taxr_purchased')) ?></th>
          <th style="text-align:right"><?= e(t('taxr_purchase_price')) ?></th>
          <th><?= e(t('taxr_method')) ?></th>
          <th style="text-align:right"><?= e(t('taxr_amount_year')) ?></th>
          <th style="text-align:right"><?= e(t('taxr_remaining')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['equipment'] as $eq): ?>
          <tr>
            <td><?= e($eq['name']) ?></td>
            <td class="muted"><?= e(fmt_date($eq['date'])) ?></td>
            <td style="text-align:right; white-space:nowrap"><?= fmt_money($eq['cents']) ?></td>
            <td class="muted small">
              <?= e($eq['kind'] === 'gwg'
                    ? t('taxr_kind_gwg')
                    : sprintf(t('taxr_kind_afa'), $eq['years'], $eq['first_year'], $eq['last_year'])) ?>
              <?php if ($eq['disposed_year'] !== null && $eq['disposed_year'] <= $year): ?>
                <div><?= e(sprintf(t('taxr_disposed'), $eq['disposed_year'])) ?></div>
              <?php endif; ?>
            </td>
            <td style="text-align:right; white-space:nowrap"><strong><?= fmt_money($eq['this_year']) ?></strong></td>
            <td style="text-align:right; white-space:nowrap" class="muted"><?= fmt_money($eq['remaining']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<details class="card acc">
  <summary><?= e(t('taxr_entries')) ?> (<?= count($report['entries']) ?>)</summary>
  <table class="table">
    <thead>
      <tr>
        <th><?= e(t('date')) ?></th>
        <th><?= e(t('fin_category')) ?></th>
        <th><?= e(t('fin_description')) ?></th>
        <th style="text-align:right"><?= e(t('fin_amount')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($report['entries'] as $en): ?>
        <tr>
          <td class="muted"><?= e(fmt_date($en['date'])) ?></td>
          <td><span class="badge"><?= e(fin_category_label($en['category'])) ?></span></td>
          <td><?= e($en['description']) ?>
            <?php if ($en['event_title']): ?><div class="muted small">📅 <?= e($en['event_title']) ?></div><?php endif; ?>
          </td>
          <td style="text-align:right; white-space:nowrap" class="<?= $en['type'] === 'einnahme' ? 'chip-yes' : '' ?>">
            <?= $en['type'] === 'einnahme' ? '+' : '−' ?><?= fmt_money((int) $en['amount_cents']) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</details>

<?php endif; ?>

<div class="card">
  <h2><?= e(t('taxr_applied')) ?></h2>
  <ul class="task-list">
    <li><strong><?= e(t('set_tax_gwg')) ?></strong><span class="muted"><?= fmt_money(tax_gwg_limit_cents()) ?></span></li>
    <li><strong><?= e(t('set_tax_afa_years')) ?></strong><span class="muted"><?= (int) tax_afa_years() ?></span></li>
    <li><strong><?= e(t('taxr_small')) ?></strong>
      <span class="muted"><?= e(setting('tax_small_business', '0') === '1' ? t('taxr_small_on') : t('taxr_small_off')) ?></span></li>
  </ul>
  <p class="muted small"><?= e(t('taxr_gross_hint')) ?></p>
  <p class="muted small">⚖ <?= e(t('tax_no_advice')) ?></p>
</div>

<p class="muted small"><a href="/intern/kasse">← <?= e(t('fin_title')) ?></a></p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
