<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>📋 <?= e(t('rider_title')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/stagerider/print" target="_blank">🖨 <?= e(t('rider_print')) ?></a>
  </div>
</div>
<p class="muted"><?= e(t('rider_intro')) ?></p>

<div class="card">
  <h2>🎭 <?= e(t('stage_plot')) ?></h2>
  <p class="muted small"><?= e(t('stage_hint')) ?></p>
  <?php $stageEdit = perm_allows($user, 'rider', 'write'); require BASE_DIR . '/app/views/_buehnenplan.php'; ?>

  <?php if ($stageEdit): ?>
    <?php if ($stageItems): ?>
      <form method="post" action="/intern/stagerider/plan/update"><?= csrf_field() ?>
        <p class="muted small"><?= e(t('stage_drag_hint')) ?></p>
        <ul class="task-list stage-list">
          <?php foreach ($stageItems as $si): ?>
            <li data-stagerow="<?= (int) $si['id'] ?>">
              <select name="item[<?= $si['id'] ?>][kind]" aria-label="<?= e(t('stage_kind')) ?>">
                <?php foreach (array_keys(STAGE_KINDS) as $k): ?>
                  <option value="<?= $k ?>" <?= $si['kind'] === $k ? 'selected' : '' ?>><?= e(t('stagekind_' . $k)) ?></option>
                <?php endforeach; ?>
              </select>
              <input name="item[<?= $si['id'] ?>][label]" value="<?= e($si['label']) ?>" aria-label="<?= e(t('stage_label')) ?>">
              <input name="item[<?= $si['id'] ?>][note]" value="<?= e($si['note']) ?>" placeholder="<?= e(t('stage_note')) ?>" aria-label="<?= e(t('stage_note')) ?>">
              <input type="number" name="item[<?= $si['id'] ?>][x]" value="<?= (int) $si['x'] ?>" min="0" max="100" class="stage-num" aria-label="<?= e(t('stage_x')) ?>">
              <input type="number" name="item[<?= $si['id'] ?>][y]" value="<?= (int) $si['y'] ?>" min="0" max="100" class="stage-num" aria-label="<?= e(t('stage_y')) ?>">
              <?php // Ein eigenes Formular je Zeile wäre verschachtelt und damit
                    // ungültig — der Knopf schickt stattdessen seine Kennung mit. ?>
              <button class="btn btn-tiny btn-danger" name="remove" value="<?= $si['id'] ?>"
                      title="<?= e(t('delete')) ?>" formnovalidate
                      onclick="return confirm('<?= e(t('confirm_delete')) ?>')">🗑</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
    <?php endif; ?>

    <details class="subsection">
      <summary>➕ <?= e(t('stage_add')) ?></summary>
      <form method="post" action="/intern/stagerider/plan/add" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('stage_kind')) ?>
          <select name="kind">
            <?php foreach (array_keys(STAGE_KINDS) as $k): ?><option value="<?= $k ?>"><?= e(t('stagekind_' . $k)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(t('stage_label')) ?><input name="label" required></label>
        <label><?= e(t('stage_note')) ?><input name="note"></label>
        <label><?= e(t('stage_x')) ?><input type="number" name="x" min="0" max="100" value="50"></label>
        <label><?= e(t('stage_y')) ?><input type="number" name="y" min="0" max="100" value="50"></label>
        <button class="btn btn-primary span2"><?= e(t('stage_add')) ?></button>
      </form>
    </details>

    <form method="post" action="/intern/stagerider/plan/vorlage" class="inline" onsubmit="return <?= $stageItems ? "confirm('" . e(t('stage_replace_warn')) . "')" : 'true' ?>"><?= csrf_field() ?>
      <button class="btn"><?= e(t('stage_from_members')) ?></button>
    </form>
    <p class="muted small"><?= e(t('stage_from_members_hint')) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= e(t('rider_requirements')) ?></h2>
  <p class="muted small"><?= e(t('rider_empty_hint')) ?></p>
  <form method="post" action="/intern/stagerider" class="form-grid"><?= csrf_field() ?>
    <label class="span2"><?= e(t('rider_stage_lbl')) ?><textarea name="rider_stage" rows="2"><?= e($settings['rider_stage'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_power_lbl')) ?><textarea name="rider_power" rows="2"><?= e($settings['rider_power'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_pa_lbl')) ?><textarea name="rider_pa" rows="2"><?= e($settings['rider_pa'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_monitor_lbl')) ?><textarea name="rider_monitor" rows="2"><?= e($settings['rider_monitor'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_light_lbl')) ?><textarea name="rider_light" rows="2"><?= e($settings['rider_light'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_getin_lbl')) ?><textarea name="rider_getin" rows="2"><?= e($settings['rider_getin'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_extras_lbl')) ?><textarea name="rider_extras" rows="2"><?= e($settings['rider_extras'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_positions_lbl')) ?>
      <textarea name="rider_positions" rows="5" placeholder="<?= e(t('rider_positions_ph')) ?>"><?= e($settings['rider_positions'] ?? '') ?></textarea>
    </label>
    <label><?= e(t('rider_contact_tech_lbl')) ?><input name="rider_contact_tech" value="<?= e($settings['rider_contact_tech'] ?? '') ?>"></label>
    <label><?= e(t('rider_contact_booking_lbl')) ?><input name="rider_contact_booking" value="<?= e($settings['rider_contact_booking'] ?? '') ?>"></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <h2><?= e(t('rider_inputs')) ?></h2>
  <?php if ($channels): ?>
    <table class="table">
      <thead><tr><th style="width:4rem"><?= e(t('ch_number')) ?></th><th><?= e(t('ch_name')) ?></th><th><?= e(t('ch_source')) ?></th><th><?= e(t('notes')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($channels as $c): ?>
          <tr><td><?= (int) $c['number'] ?></td><td><?= e($c['name']) ?></td><td><?= e($c['source']) ?></td><td class="muted"><?= e($c['notes']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted"><?= e(t('rider_inputs_empty')) ?></p>
  <?php endif; ?>
  <a class="btn btn-small btn-ghost" href="/intern/kanaele"><?= e(t('rider_inputs_from')) ?> →</a>
</div>
<script src="/assets/stageplot.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
