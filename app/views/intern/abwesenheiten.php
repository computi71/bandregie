<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('abs_title')) ?></h1>
<p class="muted"><?= e(t('abs_intro')) ?></p>

<div class="card">
  <form method="post" action="/intern/abwesenheiten" class="form-grid">
    <label><?= e(t('abs_from')) ?><input type="date" name="date_from" required></label>
    <label><?= e(t('abs_to')) ?><input type="date" name="date_to"></label>
    <label class="span2"><?= e(t('abs_reason')) ?><input name="note" placeholder="<?= e(t('abs_reason_ph')) ?>"></label>
    <button class="btn btn-primary span2"><?= e(t('abs_add')) ?></button>
  </form>
</div>

<div class="card">
  <h2><?= e(t('abs_upcoming')) ?></h2>
  <ul class="task-list">
    <?php foreach ($absences as $a): ?>
      <li>
        <strong><?= e($a['name']) ?></strong>
        <span><?= fmt_date($a['date_from']) ?><?= $a['date_to'] !== $a['date_from'] ? ' – ' . fmt_date($a['date_to']) : '' ?></span>
        <?php if ($a['note']): ?><span class="muted"><?= e($a['note']) ?></span><?php endif; ?>
        <?php if ((int) $a['user_id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
          <form class="inline" method="post" action="/intern/abwesenheiten/<?= $a['id'] ?>/delete"><button class="btn btn-tiny btn-danger">🗑</button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$absences): ?><p class="muted center"><?= e(t('abs_none')) ?></p><?php endif; ?>
</div>

<?php if ($past): ?>
<details class="card collapsible">
  <summary><?= e(t('abs_past')) ?></summary>
  <ul class="task-list muted">
    <?php foreach ($past as $a): ?>
      <li><?= e($a['name']) ?> · <?= fmt_date($a['date_from']) ?><?= $a['date_to'] !== $a['date_from'] ? ' – ' . fmt_date($a['date_to']) : '' ?><?= $a['note'] ? ' · ' . e($a['note']) : '' ?></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
