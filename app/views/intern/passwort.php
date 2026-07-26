<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🔑 <?= e(t('pw_change_title')) ?></h1>
<?php if ($forced): ?><p class="warn"><?= e(t('pw_forced_hint')) ?></p><?php endif; ?>

<div class="card" style="max-width:480px">
  <form method="post" action="/intern/passwort" class="form-grid" style="grid-template-columns:1fr">
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
