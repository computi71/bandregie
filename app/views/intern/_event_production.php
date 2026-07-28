<?php
/**
 * Bühnentechnik im Terminformular: Herkunft von PA und Licht, der Hinweis auf
 * Angebote als Dateianhang und die Packliste aus dem Inventar. Erwartet
 * $equipment; $prodEv (Termin) und $gearSel (gewählte Geräte-IDs) sind beim
 * Anlegen leer. Wird im Anlegen- und im Bearbeiten-Formular gebraucht.
 */
$prodEv  = $prodEv ?? null;
$gearSel = $gearSel ?? [];
// Bestandteile hängen unter ihrem Gerät — im Inventar wie in der Packliste
$gearByParent = [];
foreach ($equipment as $gearItem) $gearByParent[(int) $gearItem['parent_id']][] = $gearItem;
?>
<label data-eventfield="production"><?= e(t('prod_pa')) ?>
  <select name="pa_source"><option value="">– <?= e(t('prod_none')) ?> –</option>
    <?php foreach (PRODUCTION_SOURCES as $val => $lbl): ?><option value="<?= $val ?>" <?= ($prodEv['pa_source'] ?? '') === $val ? 'selected' : '' ?>><?= e(production_label($val)) ?></option><?php endforeach; ?>
  </select>
</label>
<label data-eventfield="production"><?= e(t('prod_light')) ?>
  <select name="light_source"><option value="">– <?= e(t('prod_none')) ?> –</option>
    <?php foreach (PRODUCTION_SOURCES as $val => $lbl): ?><option value="<?= $val ?>" <?= ($prodEv['light_source'] ?? '') === $val ? 'selected' : '' ?>><?= e(production_label($val)) ?></option><?php endforeach; ?>
  </select>
</label>
<div class="span2" data-eventfield="production">
  <p class="muted small" data-prodhint hidden>🧾 <?= e(t('prod_hint')) ?></p>
</div>
<?php // Die Packliste hängt an der Terminart, nicht daran, woher die PA kommt:
      // ins Auto geladen wird auch für eine Probe oder eine Aufnahme. ?>
<div class="span2" data-eventfield="gear">
  <fieldset class="gear-picker">
    <legend>🎒 <?= e(t('prod_gear')) ?></legend>
    <?php if (!$equipment): ?>
      <p class="muted small"><?= e(t('prod_gear_none')) ?></p>
    <?php endif; ?>
    <?php $gearCat = null; ?>
    <?php foreach ($gearByParent[0] ?? [] as $gearItem): ?>
      <?php if ($gearItem['category'] !== $gearCat): $gearCat = $gearItem['category']; ?>
        <strong class="muted small gear-cat"><?= e(eq_category_label($gearCat)) ?></strong>
      <?php endif; ?>
      <label class="checkbox"><input type="checkbox" name="equipment[]" value="<?= $gearItem['id'] ?>"
        data-gearparent="<?= $gearItem['id'] ?>" <?= in_array((int) $gearItem['id'], $gearSel, true) ? 'checked' : '' ?>> <?= e($gearItem['name']) ?></label>
      <?php foreach ($gearByParent[(int) $gearItem['id']] ?? [] as $gearPart): ?>
        <label class="checkbox gear-part"><input type="checkbox" name="equipment[]" value="<?= $gearPart['id'] ?>"
          data-gearchild="<?= $gearItem['id'] ?>" <?= in_array((int) $gearPart['id'], $gearSel, true) ? 'checked' : '' ?>> <?= e($gearPart['name']) ?></label>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </fieldset>
</div>
