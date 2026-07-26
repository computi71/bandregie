<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e($heading) ?></h1>
<div class="card">
  <?php if ($text): ?>
    <p class="prewrap"><?= e($text) ?></p>
  <?php else: ?>
    <p class="muted">Diese Seite wird gerade noch befüllt. Kontakt: <?= $settings['contact_email'] ? '<a href="mailto:' . e($settings['contact_email']) . '">' . e($settings['contact_email']) . '</a>' : e($settings['band_name']) ?></p>
  <?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
