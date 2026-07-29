<?php
// Wiederverwendbarer Datei-Anhang-Block.
// Erwartet: $attachFiles (Array), $attachType ('event'|'song'|'venue'), $attachId (int)
$fmtSize = function (int $b): string {
  if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
  if ($b >= 1024) return round($b / 1024) . ' KB';
  return $b . ' B';
};
?>
<details class="subsection">
  <summary>📎 <?= e(t('files_word')) ?> (<?= count($attachFiles) ?>)</summary>
  <ul class="task-list">
    <?php foreach ($attachFiles as $f): ?>
      <?php $isImage = in_array(strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'], true); ?>
      <li>
        <?php if ($isImage): ?>
          <a href="/intern/datei/<?= $f['id'] ?>" target="_blank">
            <img class="file-thumb" src="/intern/datei/<?= $f['id'] ?>" alt="<?= e($f['original_name']) ?>" loading="lazy">
          </a>
        <?php endif; ?>
        <a href="/intern/datei/<?= $f['id'] ?>" target="_blank"><?= e($f['original_name']) ?></a>
        <span class="muted small"><?= $fmtSize((int) $f['size']) ?><?= $f['uploader'] ? ' · ' . e($f['uploader']) : '' ?></span>
        <?php if ((int) $f['uploaded_by'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
          <form class="inline" method="post" action="/intern/datei/<?= $f['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$attachFiles): ?><p class="muted small"><?= e(t('files_none')) ?></p><?php endif; ?>
  <form method="post" action="/intern/dateien" enctype="multipart/form-data" class="comment-form"><?= csrf_field() ?>
    <input type="hidden" name="entity_type" value="<?= e($attachType) ?>">
    <input type="hidden" name="entity_id" value="<?= (int) $attachId ?>">
    <input type="file" name="files[]" multiple required>
    <button class="btn btn-small"><?= e(t('upload')) ?></button>
  </form>
</details>
