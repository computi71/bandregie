<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="login-box card">
  <h1>🔑 <?= e(t('pwreset_new_title')) ?></h1>
  <form method="post" action="/passwort-reset/<?= e($token) ?>" class="stack">
    <label><?= e(t('pw_new')) ?>
      <input type="password" name="password" minlength="8" required autofocus autocomplete="new-password"
             data-strength data-labels="<?= e(t('pw_weak')) ?>|<?= e(t('pw_medium')) ?>|<?= e(t('pw_strong')) ?>|<?= e(t('pw_very_strong')) ?>">
    </label>
    <label><?= e(t('pw_repeat')) ?>
      <input type="password" name="password2" minlength="8" required autocomplete="new-password">
    </label>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</div>
<script src="/assets/strength.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
