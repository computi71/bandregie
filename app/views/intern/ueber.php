<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>Bandroadie <span class="muted">v<?= e(BANDROADIE_VERSION) ?></span></h1>
<p class="muted"><?= e(t('about_tagline')) ?></p>

<div class="card">
  <h2><?= e(t('about_credits')) ?></h2>
  <p><strong><?= e(t('about_by')) ?></strong> Michael Rothe</p>
  <?php if ($contributors): ?>
    <p class="muted small"><?= e(t('about_contributors')) ?>: <?= e($contributors) ?></p>
  <?php endif; ?>
  <p class="muted small"><?= e(t('about_thanks')) ?></p>
</div>

<div class="card">
  <h2><?= e(t('about_project')) ?></h2>
  <ul class="task-list">
    <li><strong><?= e(t('about_license')) ?></strong> <span class="muted">MIT</span></li>
    <li><strong><?= e(t('about_source')) ?></strong>
      <a href="https://github.com/computi71/bandroadie" target="_blank" rel="noopener">github.com/computi71/bandroadie</a></li>
    <li><strong><?= e(t('about_version')) ?></strong> <span class="muted"><?= e(BANDROADIE_VERSION) ?></span>
      · <a href="https://github.com/computi71/bandroadie/releases" target="_blank" rel="noopener"><?= e(t('about_changelog')) ?></a></li>
    <li><strong><?= e(t('about_stack')) ?></strong> <span class="muted">PHP <?= e(PHP_VERSION) ?> · MariaDB/MySQL</span></li>
  </ul>
  <p class="muted small"><?= e(t('about_data_note')) ?></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
