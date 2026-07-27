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
$sumIn = 0; $sumOut = 0; $byCat = [];
foreach ($entries as $en) {
  if ($en['type'] === 'einnahme') $sumIn += $en['amount_cents']; else $sumOut += $en['amount_cents'];
  $byCat[$en['category']][$en['type']] = ($byCat[$en['category']][$en['type']] ?? 0) + $en['amount_cents'];
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

<?php if (!can_finance()): ?><p class="muted small"><?= e(t('fin_readonly_hint')) ?></p><?php endif; ?>

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
    <label><?= e(t('fin_amount')) ?><input name="amount" required inputmode="decimal" placeholder="z. B. 49,90"></label>
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
            <?php if ($en['member_name']): ?><div class="muted small">👤 <?= e($en['member_name']) ?></div><?php endif; ?>
            <?php foreach ($filesByFinance[$en['id']] ?? [] as $f): ?>
              <div class="small">📎 <a href="/intern/datei/<?= $f['id'] ?>" target="_blank"><?= e($f['original_name']) ?></a></div>
            <?php endforeach; ?>
          </td>
          <td><span class="badge"><?= e(fin_category_label($en['category'])) ?></span></td>
          <td style="text-align:right; white-space:nowrap" class="<?= $en['type'] === 'einnahme' ? 'chip-yes' : '' ?>">
            <?= $en['type'] === 'einnahme' ? '+' : '−' ?><?= fmt_money($en['amount_cents']) ?>
          </td>
          <td class="row-buttons">
            <?php if (can_finance()): ?>
              <details class="inline-details">
                <summary class="btn btn-tiny">📎</summary>
                <form method="post" action="/intern/dateien" enctype="multipart/form-data" class="comment-form"><?= csrf_field() ?>
                  <input type="hidden" name="entity_type" value="finance">
                  <input type="hidden" name="entity_id" value="<?= $en['id'] ?>">
                  <input type="file" name="files[]" required>
                  <button class="btn btn-tiny"><?= e(t('upload')) ?></button>
                </form>
              </details>
              <form class="inline" method="post" action="/intern/kasse/<?= $en['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$entries): ?><p class="muted center"><?= e(t('fin_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
