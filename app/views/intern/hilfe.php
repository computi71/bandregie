<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>❓ <?= e(t('help_title')) ?></h1>
<p class="muted"><?= e(t('help_intro')) ?></p>

<?php $helpFirst = true; ?>
<?php foreach (array_keys(PERM_MODULES) as $helpMod): ?>
  <?php if (!perm_allows($user, $helpMod)) continue; ?>
  <details class="card acc" name="helpacc" <?= $helpFirst ? 'open' : '' ?>>
    <summary><?= e(t('inav_' . $helpMod)) ?></summary>
    <p class="muted"><?= e(t('help_' . $helpMod)) ?></p>
  </details>
  <?php $helpFirst = false; ?>
<?php endforeach; ?>

<details class="card acc" name="helpacc">
  <summary>📱 <?= e(t('app_install')) ?></summary>
  <p class="muted"><?= e(t('app_install_hint')) ?></p>
</details>

<p class="muted small"><?= e(t('help_more')) ?> <a href="/intern/ueber"><?= e(t('about_open')) ?> →</a></p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
