<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🎛 <?= e(t('inav_equipment')) ?></h1>

<details class="card collapsible" <?= $items ? '' : 'open' ?>>
  <summary>➕ <?= e(t('eq_new')) ?></summary>
  <form method="post" action="/intern/equipment" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('name')) ?><input name="name" required placeholder="z. B. Bandanhänger, PA-Topteile, Funkstrecke"></label>
    <label><?= e(t('eq_cat')) ?>
      <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eqinherit><?= e(t('eq_owner')) ?>
      <select name="owner_id"><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eqinherit><?= e(t('eq_location')) ?><input name="location" placeholder="z. B. Proberaum, Anhänger, bei Andi"></label>
    <label><?= e(t('eq_parent')) ?>
      <select name="parent_id"><option value=""><?= e(t('eq_parent_none')) ?></option>
        <?php foreach ($items as $other): ?><option value="<?= $other['id'] ?>"><?= e($other['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('eq_slot')) ?><input name="slot" placeholder="<?= e(t('eq_slot_ph')) ?>"></label>
    <p class="muted span2" data-eqhint hidden><?= e(t('eq_inherit_hint')) ?></p>
    <label><?= e(t('eq_purchased')) ?><input type="date" name="purchased_on"></label>
    <label><?= e(t('eq_price_each')) ?><input type="number" name="price" step="0.01" min="0" placeholder="0,00"></label>
    <label><?= e(t('eq_count')) ?><input type="number" name="count" value="1" min="1" max="99"></label>
    <label class="checkbox"><input type="checkbox" name="is_standard" value="1"> 📦 <?= e(t('eq_standard')) ?></label>
    <p class="muted span2"><?= e(t('eq_count_hint')) ?></p>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>

<?php $eqValue = 0.0; foreach ($items as $it) $eqValue += (int) ($it['price_cents'] ?? 0); ?>
<?php if ($eqValue > 0): ?>
  <p class="muted"><?= e(t('eq_value_sum')) ?>: <strong><?= e(fmt_money($eqValue)) ?></strong></p>
<?php endif; ?>

<?php $lastCat = null; ?>
<?php
// Zuerst die eigenständigen Geräte; Bestandteile erscheinen unter ihrem Gerät
// — über beliebig viele Ebenen, vom Rack bis zur Kapsel im Mikrofon.
$childrenOf = eq_by_parent($items);
$eqCtx = ['childrenOf' => $childrenOf, 'items' => $items, 'members' => $members,
          'filesByEq' => $filesByEq, 'user' => $user];
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
      <strong><?= e($eq['name']) ?></strong>
      <?php if ($eq['is_standard']): ?><span class="badge public">📦 <?= e(t('eq_standard_badge')) ?></span><?php endif; ?>
      <span class="muted"><?= e(t('eq_owner')) ?>: <?= e($eq['owner_name'] ?: t('eq_owner_band')) ?></span>
      <?php if ($eq['location']): ?><span class="muted">📍 <?= e($eq['location']) ?></span><?php endif; ?>
      <?php if ($eq['price_cents'] !== null || !empty($eq['purchased_on'])): ?>
        <span class="muted">🧾 <?= e(eq_purchase_label($eq)) ?></span>
      <?php endif; ?>
      <?php if (!empty($childrenOf[(int) $eq['id']])): ?>
        <?php [$eqSum, $eqMissing] = eq_tree_value($eq, $items); ?>
        <?php if ($eqSum > 0): ?>
          <span class="muted">Σ <?= e(t('eq_total')) ?>: <strong><?= e(fmt_money($eqSum)) ?></strong><?= $eqMissing ? ' <span class="small">(' . e(t('eq_total_partial')) . ')</span>' : '' ?></span>
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

    <details class="subsection">
      <summary>⏰ <?= e(t('eq_deadline_new')) ?></summary>
      <p class="muted small"><?= e(t('eq_done_hint')) ?></p>
      <form method="post" action="/intern/equipment/<?= $eq['id'] ?>/frist" class="form-grid"><?= csrf_field() ?>
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

    <?php $attachFiles = $filesByEq[$eq['id']] ?? []; $attachType = 'equipment'; $attachId = $eq['id']; require BASE_DIR . '/app/views/_dateien.php'; ?>

    <details class="subsection">
      <summary>✏️ <?= e(t('edit')) ?></summary>
      <?php $formEq = $eq; require BASE_DIR . '/app/views/intern/_equipment_form.php'; ?>
    </details>
  </details>
<?php endforeach; ?>
<?php if (!$items): ?><p class="muted center"><?= e(t('eq_none')) ?></p><?php endif; ?>
<script src="/assets/equipment.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
