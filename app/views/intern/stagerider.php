<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>📋 <?= e(t('rider_title')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/stagerider/print" target="_blank">🖨 <?= e(t('rider_print')) ?></a>
  </div>
</div>
<p class="muted"><?= e(t('rider_intro')) ?></p>

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
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
