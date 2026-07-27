<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>❓ <?= e(t('help_title')) ?></h1>
<p class="muted"><?= e(t('help_intro')) ?></p>

<?php foreach (array_keys(PERM_MODULES) as $helpMod): ?>
  <?php if (!perm_allows($user, $helpMod)) continue; ?>
  <section class="card">
    <strong><?= e(t('inav_' . $helpMod)) ?></strong>
    <p class="muted"><?= e(t('help_' . $helpMod)) ?></p>
  </section>
<?php endforeach; ?>

<p class="muted small"><?= e(t('help_more')) ?> <a href="/intern/ueber"><?= e(t('about_open')) ?> →</a></p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
