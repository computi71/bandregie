<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('inav_setlists')) ?></h1>

<div class="card">
  <form method="post" action="/intern/setlists" class="comment-form">
    <input name="name" placeholder="<?= e(t('sl_new_ph')) ?>" required>
    <button class="btn btn-primary"><?= e(t('create')) ?></button>
  </form>
</div>

<?php foreach ($setlists as $sl): ?>
  <div class="card setlist-row">
    <div>
      <a class="setlist-name" href="/intern/setlists/<?= $sl['id'] ?>"><?= $sl['locked'] ? '🔒 ' : '' ?><?= e($sl['name']) ?></a>
      <span class="muted"><?= $sl['song_count'] ?> <?= e(t('sl_songs')) ?> · <?= fmt_duration($sl['total_sec']) ?> min<?= $sl['locked'] ? ' · ' . e(t('sl_played_locked')) : '' ?></span>
      <?php if ($sl['notes']): ?><div class="muted small"><?= e($sl['notes']) ?></div><?php endif; ?>
    </div>
    <div class="row-buttons">
      <a class="btn btn-small" href="/intern/setlists/<?= $sl['id'] ?>"><?= e($sl['locked'] ? t('view') : t('edit')) ?></a>
      <a class="btn btn-small btn-ghost" href="/intern/setlists/<?= $sl['id'] ?>/print" target="_blank">🖨 <?= e(t('sl_print')) ?></a>
      <a class="btn btn-small btn-ghost" href="/intern/setlists/<?= $sl['id'] ?>/gema" target="_blank">🏛 GEMA</a>
      <form class="inline" method="post" action="/intern/setlists/<?= $sl['id'] ?>/copy"><button class="btn btn-small btn-ghost"><?= e(t('copy')) ?></button></form>
      <?php if (!$sl['locked']): ?>
        <form class="inline" method="post" action="/intern/setlists/<?= $sl['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><button class="btn btn-small btn-danger"><?= e(t('delete')) ?></button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$setlists): ?><p class="muted center"><?= e(t('sl_none')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
