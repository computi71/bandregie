<?php
$fmtSize = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
require BASE_DIR . '/app/views/_header.php';
?>
<h1><?= e(t('downloads_title')) ?></h1>
<div class="card">
  <p class="muted"><?= e(t('downloads_intro')) ?> <?= e($settings['band_name']) ?>.
  <?php if ($settings['contact_email']): ?><?= e(t('downloads_questions')) ?> <a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a><?php endif; ?></p>
  <ul class="task-list">
    <?php foreach ($files as $f): ?>
      <li>
        <a class="btn btn-small" href="/download/<?= $f['id'] ?><?= $dlToken ? '?t=' . e($dlToken) : '' ?>">⬇ <?= e($f['original_name']) ?></a>
        <span class="muted small"><?= $fmtSize((int) $f['size']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$files): ?><p class="muted"><?= e(t('downloads_soon')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
