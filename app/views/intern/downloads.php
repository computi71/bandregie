<?php
$fmtSize = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
require BASE_DIR . '/app/views/_header.php';
?>
<h1><?= e(t('dl_title')) ?></h1>
<p class="muted"><?= e(t('dl_intro')) ?></p>

<div class="card">
  <h2><?= e(t('dl_release')) ?></h2>
  <form method="post" action="/intern/downloads/modus" class="form-grid">
    <label><?= e(t('dl_mode')) ?>
      <select name="mode">
        <option value="token" <?= $mode === 'token' ? 'selected' : '' ?>><?= e(t('dl_mode_token')) ?></option>
        <option value="public" <?= $mode === 'public' ? 'selected' : '' ?>><?= e(t('dl_mode_public')) ?></option>
        <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>><?= e(t('dl_mode_off')) ?></option>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="new_token" value="1"> <?= e(t('dl_new_token')) ?>
    </label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
  <?php if ($mode === 'token'): ?>
    <p class="muted small"><?= e(t('dl_current_link')) ?><br><code><?= e($shareUrl) ?></code></p>
  <?php elseif ($mode === 'public'): ?>
    <p class="muted small"><?= e(t('dl_public_note')) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= e(t('files_word')) ?></h2>
  <ul class="task-list">
    <?php foreach ($files as $f): ?>
      <li>
        <a href="/intern/datei/<?= $f['id'] ?>" target="_blank"><?= e($f['original_name']) ?></a>
        <span class="muted small"><?= $fmtSize((int) $f['size']) ?><?= $f['uploader'] ? ' · ' . e($f['uploader']) : '' ?></span>
        <form class="inline" method="post" action="/intern/datei/<?= $f['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><button class="btn btn-tiny btn-danger">🗑</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$files): ?><p class="muted center"><?= e(t('dl_none')) ?></p><?php endif; ?>
  <form method="post" action="/intern/dateien" enctype="multipart/form-data" class="comment-form">
    <input type="hidden" name="entity_type" value="download">
    <input type="hidden" name="entity_id" value="0">
    <input type="file" name="files[]" multiple required>
    <button class="btn btn-primary"><?= e(t('upload')) ?></button>
  </form>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
