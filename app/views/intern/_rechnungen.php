<?php
/**
 * Rechnungen zu Anschaffungen (#180). Erwartet $invoices und $user.
 *
 * Eine Rechnung steht hier einmal, auch wenn zwanzig Geräte darauf stehen. Am
 * Gerät wird sie nur ausgewählt; das PDF hängt an ihr und nicht an jedem Ding.
 *
 * Gezeigt werden nur Belege, die dieser Mensch sehen darf — die Liste kommt
 * bereits gefiltert an (invoice_list($user)).
 */
?>
<details class="card collapsible">
  <summary>🧾 <?= e(t('inv_title')) ?> (<?= count($invoices) ?>)</summary>
  <p class="muted small"><?= e(t('inv_hint')) ?></p>
  <p class="muted small">🔒 <?= e(t('inv_privacy')) ?></p>

  <?php if ($invoices): ?>
    <ul class="task-list">
      <?php foreach ($invoices as $inv): ?>
        <?php $invAnzahl = invoice_item_count((int) $inv['id']); ?>
        <li>
          <strong><?= e(invoice_label($inv)) ?></strong>
          <span class="muted small">
            <?= e($invAnzahl === 1 ? t('inv_items_one') : str_replace('%1', (string) $invAnzahl, t('inv_items'))) ?>
          </span>
          <?php if ($inv['notes'] !== ''): ?><div class="muted small prewrap"><?= e($inv['notes']) ?></div><?php endif; ?>

          <?php // Der Beleg selbst: hängt an der Rechnung, nicht am Gerät —
                // deshalb liegt er genau einmal auf der Platte. ?>
          <?php $attachFiles = $invoicesFiles[$inv['id']] ?? []; $attachType = 'invoice'; $attachId = (int) $inv['id'];
                require BASE_DIR . '/app/views/_dateien.php'; ?>

          <details class="subsection">
            <summary>✏️ <?= e(t('edit')) ?></summary>
            <form method="post" action="/intern/equipment/rechnung" class="form-grid"><?= csrf_field() ?>
              <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
              <label><?= e(t('inv_supplier')) ?><input name="supplier" value="<?= e($inv['supplier']) ?>"></label>
              <label><?= e(t('inv_order_no')) ?><input name="order_no" value="<?= e($inv['order_no']) ?>"></label>
              <label><?= e(t('inv_invoice_no')) ?><input name="invoice_no" value="<?= e($inv['invoice_no']) ?>"></label>
              <label><?= e(t('inv_date')) ?><input type="date" name="invoice_date" value="<?= e($inv['invoice_date'] ?? '') ?>"></label>
              <label><?= e(t('inv_total')) ?><input name="total" inputmode="decimal" placeholder="0,00"
                value="<?= $inv['total_cents'] !== null ? e(number_format((int) $inv['total_cents'] / 100, 2, ',', '.')) : '' ?>"></label>
              <label class="span2"><?= e(t('notes')) ?><input name="notes" value="<?= e($inv['notes']) ?>"></label>
              <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
            </form>
            <p class="muted small"><?= e(t('inv_delete_hint')) ?></p>
            <form method="post" action="/intern/equipment/rechnung/<?= (int) $inv['id'] ?>/delete" class="inline"
                  data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
              <button class="btn btn-tiny btn-danger"><?= e(t('delete')) ?></button>
            </form>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="muted small"><?= e(t('inv_none')) ?></p>
  <?php endif; ?>

  <details class="subsection">
    <summary>➕ <?= e(t('inv_new')) ?></summary>
    <form method="post" action="/intern/equipment/rechnung" class="form-grid"><?= csrf_field() ?>
      <label><?= e(t('inv_supplier')) ?><input name="supplier" placeholder="<?= e(t('inv_supplier_ph')) ?>"></label>
      <label><?= e(t('inv_order_no')) ?><input name="order_no"></label>
      <label><?= e(t('inv_invoice_no')) ?><input name="invoice_no"></label>
      <label><?= e(t('inv_date')) ?><input type="date" name="invoice_date"></label>
      <label><?= e(t('inv_total')) ?><input name="total" inputmode="decimal" placeholder="0,00"></label>
      <label class="span2"><?= e(t('notes')) ?><input name="notes"></label>
      <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
    </form>
  </details>
</details>
