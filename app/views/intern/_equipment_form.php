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
// Was jemand für sein Gerät bezahlt hat, steht auch nicht in einem gesperrten
// Feld — ein gesperrtes Feld verbirgt nichts, es zeigt nur grau an.
$eqSeePrice = eq_may_see_price($formEq, $user);
?>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
  <label><?= e(t('name')) ?><input name="name" value="<?= e($formEq['name']) ?>" required></label>
  <label><?= e(t('eq_cat')) ?>
    <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>" <?= $formEq['category'] === $val ? 'selected' : '' ?>><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
  </label>
  <label data-eqinherit><?= e(t('eq_owner')) ?>
    <select name="owner_id" <?= $eqLock ?>><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $formEq['owner_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
  </label>
  <?php // Die Vorschlagsliste steht einmal oben auf der Seite. ?>
  <label data-eqinherit><?= e(t('eq_location')) ?><input name="location" list="eq-locations" value="<?= e($formEq['location']) ?>"></label>
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
  <label><?= e(t('eq_slot')) ?><input name="slot" list="eq-slots" value="<?= e($formEq['slot'] ?? '') ?>" placeholder="<?= e(t('eq_slot_ph')) ?>"></label>
  <p class="muted span2" data-eqhint hidden><?= e(t('eq_inherit_hint')) ?></p>
  <?php if (!$eqMayOwn): ?>
    <p class="muted small span2">🔒 <?= e(t('eq_owner_locked')) ?></p>
  <?php endif; ?>
  <?php if ($eqSeePrice): ?>
    <label><?= e(t('eq_purchased')) ?><input type="date" name="purchased_on" value="<?= e($formEq['purchased_on'] ?? '') ?>" <?= $eqLock ?>></label>
    <?php // Textfeld statt type="number", sonst geht das Komma verloren.
          // Angezeigt wird deshalb auch in der Schreibweise des Landes. ?>
    <label><?= e(t('eq_price')) ?><input name="price" inputmode="decimal" placeholder="0,00" value="<?= $formEq['price_cents'] !== null ? e(number_format((int) $formEq['price_cents'] / 100, 2, ',', '.')) : '' ?>" <?= $eqLock ?>></label>
  <?php else: ?>
    <p class="muted small span2">🔒 <?= e(t('eq_price_hidden')) ?></p>
  <?php endif; ?>
  <label class="checkbox span2"><input type="checkbox" name="is_standard" value="1" <?= $formEq['is_standard'] ? 'checked' : '' ?>> 📦 <?= e(t('eq_standard')) ?></label>
  <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($formEq['notes']) ?></textarea></label>
  <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
</form>
<?php
// Aufteilen bietet sich an, wenn eine Zeile für mehrere gleiche Geräte steht.
// Bei Geräten mit Bestandteilen geht es nicht — siehe Route.
$eqHasParts = (bool) array_filter($items, fn($i) => (int) ($i['parent_id'] ?? 0) === (int) $formEq['id']);
$eqQtyHint = eq_quantity_hint($formEq);
?>
<?php if ($eqMayOwn && !$eqHasParts): ?>
  <details class="subsection">
    <summary><?= e(t('eq_split')) ?><?= $eqQtyHint ? ' ' . e(sprintf(t('eq_split_found'), $eqQtyHint)) : '' ?></summary>
    <p class="muted small"><?= e(t('eq_split_hint')) ?></p>
    <form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/aufteilen" class="inline"><?= csrf_field() ?>
      <label><?= e(t('eq_count')) ?><input type="number" name="count" value="<?= $eqQtyHint ?: 2 ?>" min="2" max="99"></label>
      <button class="btn btn-small"><?= e(t('eq_split')) ?></button>
    </form>
  </details>
<?php endif; ?>
<form method="post" action="/intern/equipment/<?= $formEq['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>" class="inline"><?= csrf_field() ?>
  <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
</form>
