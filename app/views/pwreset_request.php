<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="login-box card">
  <h1>🔑 <?= e(t('pwreset_title')) ?></h1>
  <p class="muted"><?= e(t('pwreset_intro')) ?></p>
  <form method="post" action="/passwort-vergessen" class="stack">
    <label><?= e(t('login_email')) ?><input type="email" name="email" required autofocus></label>
    <button class="btn btn-primary"><?= e(t('pwreset_send')) ?></button>
  </form>
  <p class="muted small" style="margin-top:0.8rem"><a href="/login">← <?= e(t('nav_bandbereich')) ?></a></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
