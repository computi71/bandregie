<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🎛 <?= e(t('inav_equipment')) ?></h1>

<?php // Einmal für die ganze Seite: alle Formulare verweisen darauf. ?>
<datalist id="eq-locations">
  <?php foreach (eq_locations($items) as $loc): ?><option value="<?= e($loc) ?>"><?php endforeach; ?>
</datalist>
<datalist id="eq-slots">
  <?php foreach (eq_slots($items) as $slot): ?><option value="<?= e($slot) ?>"><?php endforeach; ?>
</datalist>

<details class="card collapsible" <?= $items ? '' : 'open' ?>>
  <summary>➕ <?= e(t('eq_new')) ?></summary>
  <form method="post" action="/intern/equipment" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('name')) ?><input name="name" required placeholder="<?= e(t('eq_name_ph')) ?>"></label>
    <label><?= e(t('eq_cat')) ?>
      <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eqinherit><?= e(t('eq_owner')) ?>
      <select name="owner_id"><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eqinherit><?= e(t('eq_location')) ?><input name="location" list="eq-locations" placeholder="<?= e(t('eq_location_ph')) ?>"></label>
    <label><?= e(t('eq_parent')) ?>
      <select name="parent_id"><option value=""><?= e(t('eq_parent_none')) ?></option>
        <?php foreach ($items as $other): ?><option value="<?= $other['id'] ?>"><?= e($other['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('eq_slot')) ?><input name="slot" list="eq-slots" placeholder="<?= e(t('eq_slot_ph')) ?>"></label>
    <p class="muted span2" data-eqhint hidden><?= e(t('eq_inherit_hint')) ?></p>
    <label><?= e(t('eq_purchased')) ?><input type="date" name="purchased_on"></label>
    <?php // Kein type="number": das Feld wirft ein Komma stillschweigend weg,
          // aus 231,27 wird 23127. Als Textfeld kommt die Eingabe heil an. ?>
    <label><?= e(t('eq_price_each')) ?><input name="price" inputmode="decimal" placeholder="0,00"></label>
    <?php // Auch hier und nicht nur beim Bearbeiten: Wer ein Gerät einträgt,
          // weiß in dem Moment, wie er es gekauft hat — später nicht mehr. ?>
    <label><?= e(t('eq_acquired')) ?>
      <select name="acquired_as">
        <option value=""><?= e(t('eq_acquired_unknown')) ?></option>
        <?php foreach (array_keys(EQ_ACQUIRED) as $eqAcq): ?>
          <option value="<?= e($eqAcq) ?>"><?= e(eq_acquired_label($eqAcq)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('eq_count')) ?><input type="number" name="count" value="1" min="1" max="99"></label>
    <label class="checkbox"><input type="checkbox" name="is_standard" value="1"> 📦 <?= e(t('eq_standard')) ?></label>
    <p class="muted span2"><?= e(t('eq_count_hint')) ?></p>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>

<?php // Eine Rechnung aus dem Musikhaus zählt selten ein Gerät auf. Sie steht
      // deshalb einmal für die ganze Seite und nicht in jedem Geräteformular:
      // dort stünde die Geräteliste so oft, wie es Geräte gibt. ?>
<?php if ($items): ?>
  <details class="card collapsible">
    <summary>🧾 <?= e(t('files_multi')) ?></summary>
    <form method="post" action="/intern/dateien" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="hidden" name="entity_type" value="equipment">
      <p class="muted small"><?= e(t('files_multi_hint')) ?></p>
      <input type="file" name="files[]" multiple required>
      <fieldset class="gear-picker">
        <legend><?= e(t('files_multi_pick')) ?></legend>
        <?php foreach (eq_other_names($items, 0) as $eqId => $eqName): ?>
          <label class="checkbox"><input type="checkbox" name="also[]" value="<?= (int) $eqId ?>"> <?= e($eqName) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <button class="btn btn-small"><?= e(t('upload')) ?></button>
    </form>
  </details>
<?php endif; ?>

<?php
// Die Summe zählt nur, was der Betrachter auch sehen darf — fremde Preise
// bleiben außen vor und die Summe gibt sich als Teilsumme zu erkennen,
// statt sich als Gesamtwert auszugeben.
$eqValue = 0; $eqHidden = 0;
foreach ($items as $it) {
  // Was abgegeben ist, gehört nicht mehr zum Bestand.
  if (!empty($it['disposed_on'])) continue;
  if (!eq_may_see_price($it, $user)) { $eqHidden++; continue; }
  $eqValue += (int) ($it['price_cents'] ?? 0);
}
?>
<?php if ($eqValue > 0): ?>
  <p class="muted">
    <?= e(t('eq_value_sum')) ?>: <strong><?= e(fmt_money($eqValue)) ?></strong>
    <?php if ($eqHidden): ?><span class="small">(<?= e(t('eq_value_own_only')) ?>)</span><?php endif; ?>
  </p>
<?php endif; ?>

<?php $lastCat = null; ?>
<?php
// Zuerst die eigenständigen Geräte; Bestandteile erscheinen unter ihrem Gerät
// — über beliebig viele Ebenen, vom Rack bis zur Kapsel im Mikrofon.
$childrenOf = eq_by_parent($items);
$eqCtx = ['childrenOf' => $childrenOf, 'items' => $items, 'members' => $members,
          'filesByEq' => $filesByEq, 'user' => $user, 'bookingsByEq' => $bookingsByEq];
?>
<?php $eqFirst = true; ?>
<?php foreach ($items as $eq): ?>
  <?php if ($eq['parent_id']) continue; ?>
  <?php if ($eq['category'] !== $lastCat): $lastCat = $eq['category']; ?>
    <h2 style="margin:1rem 0 0.4rem"><?= e(eq_category_label($lastCat)) ?></h2>
  <?php endif; ?>
  <details class="card acc" name="eqacc" <?= $eqFirst ? 'open' : '' ?>>
    <?php $eqFirst = false; ?>
    <summary class="eq-summary">
      <?php // Ein Bild sagt in einer Liste aus hundert Typenbezeichnungen mehr
            // als der Name. loading="lazy", damit eine lange Liste nicht
            // hundert Anfragen auf einmal auslöst. ?>
      <?php if ($eqThumb = eq_thumb($filesByEq[$eq['id']] ?? [])): ?>
        <img class="eq-thumb" src="/intern/datei/<?= (int) $eqThumb['id'] ?>" alt="" loading="lazy">
      <?php endif; ?>
      <strong><?= e($eq['name']) ?></strong>
      <?php if (!empty($eq['disposed_on'])): ?><span class="badge">📦 <?= e(sprintf(t('eqb_disposed_on'), fmt_date($eq['disposed_on']))) ?></span><?php endif; ?>
      <?php if ($eq['is_standard'] && empty($eq['disposed_on'])): ?><span class="badge public">📦 <?= e(t('eq_standard_badge')) ?></span><?php endif; ?>
      <span class="muted"><?= e(t('eq_owner')) ?>: <?= e($eq['owner_name'] ?: t('eq_owner_band')) ?></span>
      <?php if ($eq['location']): ?><span class="muted">📍 <?= e($eq['location']) ?></span><?php endif; ?>
      <?php if (eq_may_see_price($eq, $user) && ($eq['price_cents'] !== null || !empty($eq['purchased_on']))): ?>
        <span class="muted">🧾 <?= e(eq_purchase_label($eq)) ?></span>
      <?php endif; ?>
      <?php // Nur wenn erfasst: „nicht erfasst" an hundert Zeilen zu schreiben
            // wäre Lärm, der nichts sagt. Und nur „neu" wegzulassen ginge auch
            // nicht — dann wüsste man bei einem Gerät ohne Zeichen nie, ob es
            // neu war oder ob es niemand eingetragen hat. ?>
      <?php if (($eq['acquired_as'] ?? '') !== '' && eq_may_see_price($eq, $user)): ?>
        <span class="badge"><?= e(eq_acquired_label($eq['acquired_as'])) ?></span>
      <?php endif; ?>
      <?php if (!empty($childrenOf[(int) $eq['id']])): ?>
        <?php $eqSum = eq_tree_value($eq, $items, $user); ?>
        <?php if ($eqSum > 0): ?>
          <span class="muted">Σ <?= e(t('eq_total')) ?>: <strong><?= e(fmt_money($eqSum)) ?></strong></span>
        <?php endif; ?>
      <?php endif; ?>
    </summary>
    <?php if ($eq['notes']): ?><p class="prewrap muted"><?= e($eq['notes']) ?></p><?php endif; ?>

    <?php if (!empty($childrenOf[(int) $eq['id']])): ?>
      <div class="subsection">
        <strong class="muted small"><?= e(t('eq_parts')) ?></strong>
        <?php eq_render_parts($childrenOf[(int) $eq['id']], $eqCtx); ?>
      </div>
    <?php endif; ?>

    <?php $dls = $deadlinesByEq[$eq['id']] ?? []; ?>
    <?php if ($dls): ?>
      <ul class="task-list">
        <?php foreach ($dls as $dl): ?>
          <?php
            $days = (int) ((strtotime($dl['due_date']) - strtotime(date('Y-m-d'))) / 86400);
            $cls = $days < 0 ? 'ev-abgesagt' : ($days <= 30 ? 'ev-angefragt' : '');
          ?>
          <li>
            <span class="badge <?= $cls ?>">⏰ <?= fmt_date($dl['due_date']) ?></span>
            <strong><?= e($dl['title']) ?></strong>
            <span class="muted small">
              <?= e(t('eq_interval_' . $dl['interval_months']) !== 'eq_interval_' . $dl['interval_months'] ? t('eq_interval_' . $dl['interval_months']) : t('eq_interval_0')) ?>
              <?= $days < 0 ? ' · ⚠ ' . e(t('eq_overdue')) : ($days <= 30 ? ' · ' . e(t('eq_due_soon')) . ' ' . $days . ' ' . e(t('eq_days')) : '') ?>
              <?= $dl['notes'] ? ' · ' . e($dl['notes']) : '' ?>
            </span>
            <form class="inline" method="post" action="/intern/equipment/frist/<?= $dl['id'] ?>/erledigt"><?= csrf_field() ?><button class="btn btn-tiny"><?= e(t('eq_done')) ?></button></form>
            <form class="inline" method="post" action="/intern/equipment/frist/<?= $dl['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php // Fristenformular, Anhänge und das Bearbeiten-Formular kommen erst,
          // wenn jemand das Gerät aufklappt. Ohne JavaScript führt der Link auf
          // dieselbe Seite — dort steht alles davon. ?>
    <div class="eq-detail" data-eqdetail="/intern/equipment/<?= $eq['id'] ?>/detail?teil=1">
      <a class="btn btn-small" href="/intern/equipment/<?= $eq['id'] ?>/detail">✏️ <?= e(t('edit')) ?></a>
    </div>
  </details>
<?php endforeach; ?>
<?php if (!$items): ?><p class="muted center"><?= e(t('eq_none')) ?></p><?php endif; ?>
<script src="<?= e(asset('/assets/equipment.js')) ?>" defer></script>
<script src="<?= e(asset('/assets/equipmentlazy.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
