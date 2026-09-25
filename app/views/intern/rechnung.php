<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php // Eine einzelne Rechnung (#356). Die Nummer steht fest, sobald sie
      // vergeben ist — sie ist das einzige Feld ohne Eingabefeld. ?>
<h1>🧾 <?= e($invoice['invoice_no']) ?></h1>
<p class="muted">
  <?= e(fmt_date($invoice['invoice_date'])) ?>
  <?php if ($invoice['promoter_name']): ?> · <?= e($invoice['promoter_name']) ?><?php endif; ?>
  <?php if ($invoice['event_title']): ?> · <?= e(fmt_date($invoice['event_date'])) ?> <?= e($invoice['event_title']) ?><?php endif; ?>
</p>

<p class="event-links">
  <span class="badge <?= $invoice['status'] === 'bezahlt' ? 'public' : '' ?>"><?= e(invoice_status_label((string) $invoice['status'])) ?></span>
  <?php if ($invoice['paid_cash']): ?><span class="badge">💶 <?= e(t('inv_paid_cash')) ?></span><?php endif; ?>
  <a class="badge link" href="/intern/rechnungen/<?= (int) $invoice['id'] ?>/druck" target="_blank" rel="noopener">🖨 <?= e(t('inv_print')) ?></a>
  <?php if ($invoice['contract_id']): ?>
    <a class="badge link" href="/intern/vertraege/<?= (int) $invoice['contract_id'] ?>">✍ <?= e(t('itemkind_contract')) ?></a>
  <?php endif; ?>
</p>

<?php if (perm_allows($user, 'rechnungen', 'write')): ?>
<div class="card">
  <h2><?= e(t('inv_out_items')) ?></h2>
  <form method="post" action="/intern/rechnungen/<?= (int) $invoice['id'] ?>/posten"><?= csrf_field() ?>
    <?php // Eine Leerzeile mehr, als es Posten gibt: So lässt sich einer
          // nachtragen, ohne vorher auf „hinzufügen" zu drücken. ?>
    <?php foreach (array_merge($items, [['label' => '', 'amount_cents' => 0]]) as $i => $p): ?>
      <div class="row-buttons">
        <input name="label[]" value="<?= e($p['label']) ?>" maxlength="190"
               placeholder="<?= e(t('inv_item_label_ph')) ?>" aria-label="<?= e(t('inv_item_label_ph')) ?>">
        <input name="amount[]" value="<?= $p['label'] === '' ? '' : e(number_format((int) $p['amount_cents'] / 100, 2, ',', '')) ?>"
               inputmode="decimal" placeholder="0,00" aria-label="<?= e(t('inv_item_amount')) ?>">
      </div>
    <?php endforeach; ?>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($invoice['notes'] ?? '') ?></textarea></label>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <p><?= e(t('inv_net')) ?>: <strong><?= e(fmt_money($totals['net'])) ?></strong></p>
  <?php if ($invoice['small_business']): ?>
    <?php // Pflichtangabe, kein Hinweis: Ohne diesen Satz ist die Rechnung
          // eines Kleinunternehmers unvollständig. ?>
    <p class="muted small"><?= e(t('inv_small_business_note')) ?></p>
  <?php else: ?>
    <p><?= e(str_replace('%1', (string) (int) $invoice['vat_percent'], t('inv_vat'))) ?>: <?= e(fmt_money($totals['vat'])) ?></p>
  <?php endif; ?>
  <p><?= e(t('inv_out_total')) ?>: <strong><?= e(fmt_money($totals['total'])) ?></strong></p>
</div>

<?php if (perm_allows($user, 'rechnungen', 'write')): ?>
<div class="card">
  <h2><?= e(t('inv_status')) ?></h2>
  <form method="post" action="/intern/rechnungen/<?= (int) $invoice['id'] ?>/stand" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('inv_status')) ?>
      <select name="status">
        <?php foreach (INVOICE_STATUSES as $sW): ?>
          <option value="<?= e($sW) ?>" <?= $invoice['status'] === $sW ? 'selected' : '' ?>><?= e(invoice_status_label($sW)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox span2">
      <input type="checkbox" name="paid_cash" value="1" <?= $invoice['paid_cash'] ? 'checked' : '' ?>>
      💶 <?= e(t('inv_paid_cash_set')) ?>
    </label>
    <span class="muted small span2"><?= e(t('inv_paid_cash_hint')) ?></span>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
