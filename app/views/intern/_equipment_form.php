<?php
/**
 * Bearbeiten-Formular für ein Gerät. Erwartet $formEq (Datensatz), $members,
 * $items und $user. Wird an zwei Stellen gebraucht: aufgeklappt bei
 * eigenständigen Geräten und im Dialog bei Bestandteilen.
 */
// Fremdes Eigentum bleibt lesbar, aber unveränderlich — die Felder sind
// gesperrt, entscheidend ist aber die Prüfung in der Route.
$eqMayOwn = eq_may_edit_owner_fields($formEq, $user);
$eqLock = $eqMayOwn ? '' : 'disabled';
?>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
  <label><?= e(t('name')) ?><input name="name" value="<?= e($formEq['name']) ?>" required></label>
  <label><?= e(t('eq_cat')) ?>
    <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>" <?= $formEq['category'] === $val ? 'selected' : '' ?>><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
  </label>
  <label data-eqinherit><?= e(t('eq_owner')) ?>
    <select name="owner_id" <?= $eqLock ?>><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $formEq['owner_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
  </label>
  <label data-eqinherit><?= e(t('eq_location')) ?><input name="location" value="<?= e($formEq['location']) ?>"></label>
  <label><?= e(t('eq_parent')) ?>
    <?php
      // Sich selbst oder einen eigenen Bestandteil als übergeordnetes Gerät zu
      // wählen ergäbe eine Schleife — die Liste lässt beides gar nicht erst zu.
      $eqBlocked = [(int) $formEq['id'], ...eq_descendants((int) $formEq['id'], $items)];
    ?>
    <select name="parent_id" <?= $eqLock ?>><option value=""><?= e(t('eq_parent_none')) ?></option>
      <?php foreach ($items as $other): ?>
        <?php if (in_array((int) $other['id'], $eqBlocked, true)) continue; ?>
        <option value="<?= $other['id'] ?>" <?= (int) ($formEq['parent_id'] ?? 0) === (int) $other['id'] ? 'selected' : '' ?>><?= e($other['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('eq_slot')) ?><input name="slot" value="<?= e($formEq['slot'] ?? '') ?>" placeholder="<?= e(t('eq_slot_ph')) ?>"></label>
  <p class="muted span2" data-eqhint hidden><?= e(t('eq_inherit_hint')) ?></p>
  <?php if (!$eqMayOwn): ?>
    <p class="muted small span2">🔒 <?= e(t('eq_owner_locked')) ?></p>
  <?php endif; ?>
  <label><?= e(t('eq_purchased')) ?><input type="date" name="purchased_on" value="<?= e($formEq['purchased_on'] ?? '') ?>" <?= $eqLock ?>></label>
  <label><?= e(t('eq_price')) ?><input type="number" name="price" step="0.01" min="0" value="<?= $formEq['price_cents'] !== null ? e(number_format((int) $formEq['price_cents'] / 100, 2, '.', '')) : '' ?>" <?= $eqLock ?>></label>
  <label class="checkbox span2"><input type="checkbox" name="is_standard" value="1" <?= $formEq['is_standard'] ? 'checked' : '' ?>> 📦 <?= e(t('eq_standard')) ?></label>
  <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($formEq['notes']) ?></textarea></label>
  <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
</form>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>" class="inline"><?= csrf_field() ?>
  <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
</form>
