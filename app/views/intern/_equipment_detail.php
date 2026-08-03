<?php
/**
 * Alles, was ein Gerät zum Bearbeiten braucht: Fristen anlegen, Anhänge,
 * Formular. Erwartet $detailEq und den üblichen Zusammenhang ($members,
 * $items, $filesByEq, $user).
 *
 * Der Block steht nicht in der Liste, sondern wird nachgeladen, sobald jemand
 * ein Gerät aufklappt. In der Liste stand er hundertfach im Quelltext — allein
 * die Auswahl des übergeordneten Geräts führt jedes Gerät noch einmal auf, und
 * das machte zwei Drittel der Seite aus, die niemand zu sehen bekam.
 */
?>
<details class="subsection">
  <summary>⏰ <?= e(t('eq_deadline_new')) ?></summary>
  <p class="muted small"><?= e(t('eq_done_hint')) ?></p>
  <form method="post" action="/intern/equipment/<?= (int) $detailEq['id'] ?>/frist" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('title_lbl')) ?><input name="title" required placeholder="<?= e(t('eq_deadline_title_ph')) ?>"></label>
    <label><?= e(t('eq_due')) ?><input type="date" name="due_date" required></label>
    <label><?= e(t('eq_interval')) ?>
      <select name="interval_months">
        <option value="0"><?= e(t('eq_interval_0')) ?></option>
        <option value="6"><?= e(t('eq_interval_6')) ?></option>
        <option value="12" selected><?= e(t('eq_interval_12')) ?></option>
        <option value="24"><?= e(t('eq_interval_24')) ?></option>
      </select>
    </label>
    <label><?= e(t('notes')) ?><input name="notes"></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<?php // Der Beleg zum Gerät. Angezeigt nur, wenn dieser Mensch ihn sehen darf —
      // die Zuordnung allein macht eine fremde Privatrechnung nicht öffentlich.
      $detailInv = !empty($detailEq['invoice_id']) && may_see_invoice($user, (int) $detailEq['invoice_id'])
        ? row('SELECT * FROM invoices WHERE id = ?', [(int) $detailEq['invoice_id']])
        : null; ?>
<?php if ($detailInv): ?>
  <p class="muted small">🧾 <?= e(t('inv_pick')) ?>: <strong><?= e(invoice_label($detailInv)) ?></strong>
    <?php $detailInvAnzahl = invoice_item_count((int) $detailInv['id']); ?>
    <?= e($detailInvAnzahl === 1 ? t('inv_items_one') : str_replace('%1', (string) $detailInvAnzahl, t('inv_items'))) ?>
    <?php foreach (rows("SELECT * FROM files WHERE entity_type = 'invoice' AND entity_id = ?", [(int) $detailInv['id']]) as $detailInvFile): ?>
      · <a href="/intern/datei/<?= (int) $detailInvFile['id'] ?>" target="_blank">📄 <?= e($detailInvFile['original_name']) ?></a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>
<?php if (($detailEq['article_no'] ?? '') !== ''): ?>
  <p class="muted small"><?= e(t('inv_article_no')) ?>: <code><?= e($detailEq['article_no']) ?></code></p>
<?php endif; ?>

<?php $attachFiles = $filesByEq[$detailEq['id']] ?? []; $attachType = 'equipment'; $attachId = $detailEq['id'];
      require BASE_DIR . '/app/views/_dateien.php'; ?>

<details class="subsection" open>
  <summary>✏️ <?= e(t('edit')) ?></summary>
  <?php $formEq = $detailEq; require BASE_DIR . '/app/views/intern/_equipment_form.php'; ?>
</details>
