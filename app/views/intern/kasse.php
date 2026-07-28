<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>💰 <?= e(t('fin_title')) ?></h1>
  <div class="row-buttons">
    <a class="btn <?= $year === null ? 'btn-primary' : 'btn-ghost' ?> btn-small" href="/intern/kasse"><?= e(t('fin_all_years')) ?></a>
    <?php foreach ($years as $y): ?>
      <a class="btn <?= $year === (int) $y ? 'btn-primary' : 'btn-ghost' ?> btn-small" href="/intern/kasse?jahr=<?= $y ?>"><?= $y ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php
// Summen und Kategorien zeigen die Bandkasse. Eigene private Buchungen
// stehen zwar in der Liste, gehören aber nicht in die Bandzahlen — sie
// bekommen ihre eigene Zeile.
$sumIn = 0; $sumOut = 0; $byCat = []; $ownPrivate = 0;
$rentCost = 0; $deposits = 0;
foreach ($entries as $en) {
  if ($en['private_for'] !== null) {
    $ownPrivate += $en['type'] === 'einnahme' ? $en['amount_cents'] : -$en['amount_cents'];
    continue;
  }
  if ($en['type'] === 'einnahme') $sumIn += $en['amount_cents']; else $sumOut += $en['amount_cents'];
  $byCat[$en['category']][$en['type']] = ($byCat[$en['category']][$en['type']] ?? 0) + $en['amount_cents'];
  // Wofür die Einzahlungen gedacht sind, und was tatsächlich hereinkam
  if ($en['type'] === 'ausgabe' && in_array($en['category'], FIN_DEPOSIT_COVERS, true)) $rentCost += $en['amount_cents'];
  if ($en['type'] === 'einnahme' && $en['category'] === 'einlage') $deposits += $en['amount_cents'];
}
?>
<div class="grid-2">
  <div class="card">
    <h2><?= e(t('fin_balance')) ?></h2>
    <p style="font-size:1.8rem; font-weight:bold" class="<?= $balance < 0 ? 'warn' : '' ?>"><?= fmt_money($balance) ?></p>
    <p class="muted small">
      <?= e($year !== null ? (string) $year : t('fin_all_years')) ?>:
      <?= e(t('fin_income')) ?> <strong><?= fmt_money($sumIn) ?></strong> ·
      <?= e(t('fin_expense')) ?> <strong><?= fmt_money($sumOut) ?></strong>
    </p>
    <?php if ($ownPrivate !== 0): ?>
      <p class="muted small">🔒 <?= e(t('fin_private_sum')) ?> <strong><?= fmt_money($ownPrivate) ?></strong></p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2><?= e(t('fin_by_category')) ?></h2>
    <ul class="task-list">
      <?php foreach ($byCat as $cat => $sums): ?>
        <li>
          <strong><?= e(fin_category_label($cat)) ?></strong>
          <span class="muted small">
            <?php if (!empty($sums['einnahme'])): ?>+<?= fmt_money($sums['einnahme']) ?><?php endif; ?>
            <?php if (!empty($sums['ausgabe'])): ?> −<?= fmt_money($sums['ausgabe']) ?><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$byCat): ?><p class="muted"><?= e(t('fin_none')) ?></p><?php endif; ?>
  </div>
</div>

<?php if ($rentCost || $deposits): $gap = $rentCost - $deposits; ?>
<div class="card">
  <h2>🏠 <?= e(t('fin_rent_cover')) ?></h2>
  <p class="muted small"><?= e(t('fin_rent_cover_hint')) ?></p>
  <ul class="task-list">
    <li><strong><?= e(t('fin_rent_cost')) ?></strong> <span class="muted"><?= fmt_money($rentCost) ?></span></li>
    <li><strong><?= e(t('fin_rent_deposits')) ?></strong> <span class="muted">+<?= fmt_money($deposits) ?></span></li>
    <li>
      <strong><?= e($gap > 0 ? t('fin_rent_gap') : t('fin_rent_surplus')) ?></strong>
      <span class="<?= $gap > 0 ? 'warn' : 'chip-yes' ?>"><?= fmt_money(abs($gap)) ?></span>
    </li>
  </ul>
</div>
<?php endif; ?>

<?php require_once BASE_DIR . '/app/steuer.php'; $tax = tax_small_business_status(); ?>
<?php if ($tax): ?>
  <div class="card">
    <h2><?= e(t('tax_title')) ?></h2>
    <ul class="task-list">
      <li>
        <strong><?= e(sprintf(t('tax_turnover_year'), date('Y'))) ?></strong>
        <span class="<?= in_array($tax['state'], ['close', 'over_this'], true) ? 'warn' : 'muted' ?>">
          <?= fmt_money($tax['this_year']) ?> <?= e(t('tax_of')) ?> <?= fmt_money($tax['limit_this']) ?>
        </span>
      </li>
      <li>
        <strong><?= e(sprintf(t('tax_turnover_year'), date('Y') - 1)) ?></strong>
        <span class="<?= $tax['state'] === 'over_prev' ? 'warn' : 'muted' ?>">
          <?= fmt_money($tax['prev_year']) ?> <?= e(t('tax_of')) ?> <?= fmt_money($tax['limit_prev']) ?>
        </span>
      </li>
    </ul>
    <?php if ($tax['state'] !== 'ok'): ?>
      <p class="<?= $tax['state'] === 'close' ? 'muted' : 'warn' ?>">
        <strong><?= e(t('tax_state_' . $tax['state'])) ?></strong>
      </p>
    <?php endif; ?>
    <p class="muted small"><?= e(t('tax_counts_hint')) ?></p>
    <p class="muted small">⚖ <?= e(t('tax_no_advice')) ?></p>
  </div>
<?php endif; ?>

<?php if (!can_finance()): ?><p class="muted small"><?= e(t('fin_readonly_hint')) ?></p><?php endif; ?>

<details class="card acc" name="kasseacc" <?= $orders ? '' : '' ?>>
  <summary>🔁 <?= e(t('ord_title')) ?> (<?= count($orders) ?>)</summary>
  <p class="muted small"><?= e(t('ord_intro')) ?></p>

  <?php if (!$orders): ?><p class="muted small"><?= e(t('ord_none')) ?></p><?php endif; ?>
  <ul class="task-list">
    <?php foreach ($orders as $ord): ?>
      <li class="<?= $ord['paused'] ? 'muted' : '' ?>">
        <span class="badge <?= $ord['type'] === 'einnahme' ? 'public' : '' ?>"><?= e(fmt_money((int) $ord['amount_cents'])) ?></span>
        <strong><?= e($ord['description']) ?></strong>
        <span class="muted small">
          <?= e(t('ord_' . ['monthly' => 'monthly', 'quarterly' => 'quarterly', 'yearly' => 'yearly'][$ord['interval_kind']] ?? 'monthly')) ?>
          · <?= e(fin_category_label($ord['category'])) ?>
          <?php // Bandkasse, Einzahlung eines Mitglieds oder privat ?>
          · <?= e($ord['owner_id'] === null ? t('ord_scope_band')
                : ((int) $ord['private'] ? t('ord_scope_own')
                : t('ord_scope_deposit') . ' · ' . $ord['owner_name'])) ?>
          <?php if ($ord['paused']): ?> · <?= e(t('ord_paused')) ?>
          <?php else: ?> · <?= e(t('ord_next')) ?> <?= fmt_date($ord['next_date']) ?><?php endif; ?>
          <?php if ($ord['end_date']): ?> · <?= e(t('ord_end')) ?>: <?= fmt_date($ord['end_date']) ?><?php endif; ?>
        </span>
        <form class="inline" method="post" action="/intern/kasse/dauerauftrag/<?= $ord['id'] ?>/pause"><?= csrf_field() ?>
          <button class="btn btn-tiny"><?= e($ord['paused'] ? t('ord_resume') : t('ord_pause')) ?></button>
        </form>
        <form class="inline" method="post" action="/intern/kasse/dauerauftrag/<?= $ord['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
          <button class="btn btn-tiny btn-danger">🗑</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>

  <details class="subsection">
    <summary>➕ <?= e(t('ord_new')) ?></summary>
    <form method="post" action="/intern/kasse/dauerauftrag" class="form-grid"><?= csrf_field() ?>
      <label><?= e(t('ord_scope')) ?>
        <select name="scope">
          <option value="einzahlung"><?= e(t('ord_scope_deposit')) ?></option>
          <option value="own"><?= e(t('ord_scope_own')) ?></option>
          <?php if (can_finance()): ?><option value="band"><?= e(t('ord_scope_band')) ?></option><?php endif; ?>
        </select>
        <span class="muted small"><?= e(t('ord_scope_hint')) ?></span>
      </label>
      <label data-orderfield><?= e(t('fin_type_out')) ?> / <?= e(t('fin_type_in')) ?>
        <select name="type">
          <option value="ausgabe"><?= e(t('fin_type_out')) ?></option>
          <option value="einnahme"><?= e(t('fin_type_in')) ?></option>
        </select>
      </label>
      <label><?= e(t('fin_amount')) ?><input name="amount" required placeholder="0,00"></label>
      <label data-orderfield><?= e(t('fin_category')) ?>
        <select name="category">
          <?php foreach (array_keys(FIN_CATEGORIES) as $cat): ?>
            <option value="<?= $cat ?>"><?= e(fin_category_label($cat)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="span2"><?= e(t('fin_description')) ?><input name="description" required placeholder="<?= e(t('ord_desc_ph')) ?>"></label>
      <label><?= e(t('ord_interval')) ?>
        <select name="interval_kind">
          <option value="monthly"><?= e(t('ord_monthly')) ?></option>
          <option value="quarterly"><?= e(t('ord_quarterly')) ?></option>
          <option value="yearly"><?= e(t('ord_yearly')) ?></option>
        </select>
      </label>
      <label><?= e(t('ord_start')) ?><input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
      <label class="span2"><?= e(t('ord_end')) ?><input type="date" name="end_date"></label>
      <button class="btn btn-primary span2"><?= e(t('ord_new')) ?></button>
    </form>
  </details>
</details>

<?php if ($openFees && can_finance()): ?>
<details class="card acc" name="kasseacc">
  <summary><?= e(t('fin_open_fees')) ?> (<?= count($openFees) ?>)</summary>
  <ul class="task-list">
    <?php foreach ($openFees as $ev): ?>
      <li>
        <span class="event-date"><?= fmt_date($ev['date']) ?></span>
        <strong><?= e($ev['title']) ?></strong>
        <span class="muted"><?= e($ev['fee']) ?></span>
        <form class="inline" method="post" action="/intern/kasse/gage/<?= $ev['id'] ?>"><?= csrf_field() ?>
          <button class="btn btn-tiny btn-primary"><?= e(t('fin_import_gage')) ?></button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</details>
<?php endif; ?>

<?php if (can_finance()): ?>
<details class="card collapsible" <?= $entries ? '' : 'open' ?>>
  <summary>➕ <?= e(t('fin_new')) ?></summary>
  <form method="post" action="/intern/kasse" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('date')) ?><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></label>
    <label><?= e(t('ev_type')) ?>
      <select name="type">
        <option value="ausgabe"><?= e(t('fin_type_out')) ?></option>
        <option value="einnahme"><?= e(t('fin_type_in')) ?></option>
      </select>
    </label>
    <label><?= e(t('fin_amount')) ?><input name="amount" required inputmode="decimal" placeholder="<?= e(t('fin_amount_ph')) ?>"></label>
    <label><?= e(t('fin_category')) ?>
      <select name="category">
        <?php foreach (FIN_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(fin_category_label($val)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('fin_description')) ?><input name="description" required></label>
    <label><?= e(t('fin_event')) ?>
      <select name="event_id"><option value="">–</option><?php foreach ($events as $ev): ?><option value="<?= $ev['id'] ?>"><?= e($ev['date']) ?> · <?= e($ev['title']) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('fin_member')) ?>
      <select name="member_id"><option value="">–</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn btn-primary span2"><?= e(t('fin_add')) ?></button>
  </form>
</details>
<?php endif; ?>

<div class="card">
  <table class="table">
    <thead><tr><th><?= e(t('date')) ?></th><th><?= e(t('fin_description')) ?></th><th><?= e(t('fin_category')) ?></th><th style="text-align:right"><?= e(t('fin_amount')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($entries as $en): ?>
        <tr>
          <td class="muted"><?= fmt_date($en['date']) ?></td>
          <td>
            <strong><?= e($en['description']) ?></strong>
            <?php if ($en['event_title']): ?><div class="muted small">📅 <?= e($en['event_title']) ?></div><?php endif; ?>
            <?php // Bei einer privaten Buchung ist der Name der eigene — das
                  // sagt das Schloss darunter schon. ?>
            <?php if ($en['member_name'] && $en['private_for'] === null): ?><div class="muted small">👤 <?= e($en['member_name']) ?></div><?php endif; ?>
            <?php // Woher der Betrag stammt — sonst wundert man sich über eine
                  // Buchung, die niemand eingetippt hat ?>
            <?php if (!empty($en['standing_order_id'])): ?><div class="muted small">🔁 <?= e(t('ord_from_order')) ?></div><?php endif; ?>
            <?php if ($en['private_for'] !== null): ?><div class="muted small">🔒 <?= e(t('fin_private')) ?></div><?php endif; ?>
            <?php if (!empty($en['equipment_name'])): ?><div class="muted small">🎛 <a href="/intern/equipment"><?= e($en['equipment_name']) ?></a></div><?php endif; ?>
            <?php foreach ($filesByFinance[$en['id']] ?? [] as $f): ?>
              <div class="small">📎 <a href="/intern/datei/<?= $f['id'] ?>" target="_blank"><?= e($f['original_name']) ?></a></div>
            <?php endforeach; ?>
          </td>
          <td><span class="badge"><?= e(fin_category_label($en['category'])) ?></span></td>
          <td style="text-align:right; white-space:nowrap" class="<?= $en['type'] === 'einnahme' ? 'chip-yes' : '' ?>">
            <?= $en['type'] === 'einnahme' ? '+' : '−' ?><?= fmt_money($en['amount_cents']) ?>
          </td>
          <td class="row-buttons">
            <?php if (may_edit_finance($en)): ?>
              <details class="inline-details">
                <summary class="btn btn-tiny">📎</summary>
                <form method="post" action="/intern/dateien" enctype="multipart/form-data" class="comment-form"><?= csrf_field() ?>
                  <input type="hidden" name="entity_type" value="finance">
                  <input type="hidden" name="entity_id" value="<?= $en['id'] ?>">
                  <input type="file" name="files[]" required>
                  <button class="btn btn-tiny"><?= e(t('upload')) ?></button>
                </form>
              </details>
              <form class="inline" method="post" action="/intern/kasse/<?= $en['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$entries): ?><p class="muted center"><?= e(t('fin_none')) ?></p><?php endif; ?>
</div>
<script src="<?= e(asset('/assets/kasse.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
