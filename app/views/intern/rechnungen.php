<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php // Die Rechnungen an die Veranstalter (#356). Gerechnet wird nicht hier —
      // die Zahlen kommen aus dem Vertrag; hier steht, was hinausging und was
      // davon bezahlt ist. ?>
<h1>🧾 <?= e(t('inv_out_title')) ?></h1>
<p class="muted"><?= e(t('inv_out_intro')) ?></p>

<?php if (perm_allows($user, 'rechnungen', 'write') && $offeneVertraege): ?>
<details class="card collapsible" <?= $invoices ? '' : 'open' ?>>
  <summary>➕ <?= e(t('inv_from_contract')) ?></summary>
  <form method="post" action="/intern/rechnungen/aus-vertrag" class="form-grid"><?= csrf_field() ?>
    <label class="span2"><?= e(t('inv_contract')) ?>
      <select name="contract_id" required>
        <?php foreach ($offeneVertraege as $oV): ?>
          <option value="<?= (int) $oV['id'] ?>">
            <?= e($oV['contract_no'] ?: '#' . $oV['id']) ?> ·
            <?= e($oV['event_date'] ? fmt_date($oV['event_date']) : '') ?> <?= e($oV['event_title'] ?? '') ?> ·
            <?= e(fmt_money((int) $oV['fee_cents'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="muted small"><?= e(t('inv_from_contract_hint')) ?></span>
    </label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<div class="card">
  <ul class="task-list">
    <?php foreach ($invoices as $r): ?>
      <li>
        <a href="/intern/rechnungen/<?= (int) $r['id'] ?>"><strong><?= e($r['invoice_no']) ?></strong></a>
        <?= item_mark_html($unseen ?? [], (int) $r['id']) ?>
        <span class="badge <?= $r['status'] === 'bezahlt' ? 'public' : '' ?>"><?= e(invoice_status_label((string) $r['status'])) ?></span>
        <?php if ($r['paid_cash']): ?><span class="badge">💶 <?= e(t('inv_paid_cash')) ?></span><?php endif; ?>
        <span class="muted"><?= e(fmt_money((int) $r['total_cents'])) ?></span>
        <?php if ($r['event_title']): ?>
          <span class="muted small">· <?= e(fmt_date($r['event_date'])) ?> <?= e($r['event_title']) ?></span>
        <?php endif; ?>
        <?php if ($r['promoter_name']): ?><span class="muted small">· <?= e($r['promoter_name']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$invoices): ?><p class="muted center"><?= e(t('inv_out_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
