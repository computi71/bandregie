<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>Bandregie <span class="muted">v<?= e(BANDREGIE_VERSION) ?></span></h1>
<p class="muted"><?= e(t('about_tagline')) ?></p>

<div class="card">
  <h2><?= e(t('about_credits')) ?></h2>
  <p><strong><?= e(t('about_by')) ?></strong> Michael Rothe</p>
  <?php if ($contributors): ?>
    <p class="muted small"><?= e(t('about_contributors')) ?>: <?= e($contributors) ?></p>
  <?php endif; ?>
  <p class="muted small"><?= e(t('about_thanks')) ?></p>
  <?php // Beim Entwickler und nicht in der Projektliste: wer etwas dalassen
        // mag, meint die Person und nicht das Repository. ?>
  <?php if (DONATE_URL !== ''): ?>
    <p><strong><?= e(t('about_donate')) ?></strong>
      <a href="<?= e(DONATE_URL) ?>" target="_blank" rel="noopener"><?= e(t('about_donate_link')) ?></a></p>
    <p class="muted small"><?= e(t('about_donate_note')) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= e(t('about_project')) ?></h2>
  <ul class="task-list">
    <li><strong><?= e(t('about_license')) ?></strong>
      <a href="https://github.com/computi71/bandregie/blob/main/LICENSE.md" target="_blank" rel="noopener">FSL-1.1-ALv2</a>
      <div class="muted small"><?= e(t('about_license_note')) ?></div></li>
    <li><strong><?= e(t('about_source')) ?></strong>
      <a href="https://github.com/computi71/bandregie" target="_blank" rel="noopener">github.com/computi71/bandregie</a></li>
    <li><strong><?= e(t('about_version')) ?></strong> <span class="muted"><?= e(BANDREGIE_VERSION) ?></span>
      · <a href="https://github.com/computi71/bandregie/releases" target="_blank" rel="noopener"><?= e(t('about_changelog')) ?></a></li>
    <li><strong><?= e(t('about_stack')) ?></strong> <span class="muted">PHP <?= e(PHP_VERSION) ?> · MariaDB/MySQL</span></li>
  </ul>
  <p class="muted small"><?= e(t('about_data_note')) ?></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
