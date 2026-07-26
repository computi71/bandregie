<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🎛 <?= e(t('inav_equipment')) ?></h1>

<details class="card collapsible" <?= $items ? '' : 'open' ?>>
  <summary>➕ <?= e(t('eq_new')) ?></summary>
  <form method="post" action="/intern/equipment" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('name')) ?><input name="name" required placeholder="z. B. Bandanhänger, PA-Topteile, Funkstrecke"></label>
    <label><?= e(t('eq_cat')) ?>
      <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('eq_owner')) ?>
      <select name="owner_id"><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('eq_location')) ?><input name="location" placeholder="z. B. Proberaum, Anhänger, bei Andi"></label>
    <label class="checkbox span2"><input type="checkbox" name="is_standard" value="1"> 📦 <?= e(t('eq_standard')) ?></label>
    <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>

<?php $lastCat = null; ?>
<?php foreach ($items as $eq): ?>
  <?php if ($eq['category'] !== $lastCat): $lastCat = $eq['category']; ?>
    <h2 style="margin:1rem 0 0.4rem"><?= e(eq_category_label($lastCat)) ?></h2>
  <?php endif; ?>
  <section class="card">
    <div class="event-head">
      <strong><?= e($eq['name']) ?></strong>
      <?php if ($eq['is_standard']): ?><span class="badge public">📦 <?= e(t('eq_standard_badge')) ?></span><?php endif; ?>
      <span class="muted"><?= e(t('eq_owner')) ?>: <?= e($eq['owner_name'] ?: t('eq_owner_band')) ?></span>
      <?php if ($eq['location']): ?><span class="muted">📍 <?= e($eq['location']) ?></span><?php endif; ?>
    </div>
    <?php if ($eq['notes']): ?><p class="prewrap muted"><?= e($eq['notes']) ?></p><?php endif; ?>

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
            <form class="inline" method="post" action="/intern/equipment/frist/<?= $dl['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
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
      <form method="post" action="/intern/equipment/<?= $eq['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('name')) ?><input name="name" value="<?= e($eq['name']) ?>" required></label>
        <label><?= e(t('eq_cat')) ?>
          <select name="category"><?php foreach (EQ_CATEGORIES as $val => $lbl): ?><option value="<?= $val ?>" <?= $eq['category'] === $val ? 'selected' : '' ?>><?= e(eq_category_label($val)) ?></option><?php endforeach; ?></select>
        </label>
        <label><?= e(t('eq_owner')) ?>
          <select name="owner_id"><option value=""><?= e(t('eq_owner_band')) ?></option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $eq['owner_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select>
        </label>
        <label><?= e(t('eq_location')) ?><input name="location" value="<?= e($eq['location']) ?>"></label>
        <label class="checkbox span2"><input type="checkbox" name="is_standard" value="1" <?= $eq['is_standard'] ? 'checked' : '' ?>> 📦 <?= e(t('eq_standard')) ?></label>
        <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e($eq['notes']) ?></textarea></label>
        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
      <form method="post" action="/intern/equipment/<?= $eq['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')" class="inline"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
      </form>
    </details>
  </section>
<?php endforeach; ?>
<?php if (!$items): ?><p class="muted center"><?= e(t('eq_none')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
