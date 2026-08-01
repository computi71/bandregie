<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="login-box card">
  <h1><?= e(t('nav_bandbereich')) ?></h1>
  <p class="muted"><?= e(t('login_only_members')) ?> <?= e($settings['band_name']) ?>.</p>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/login" class="stack"><?= csrf_field() ?>
    <label><?= e(t('login_email')) ?><input type="email" name="email" required autofocus></label>
    <label><?= e(t('login_password')) ?><input type="password" name="password" required></label>
    <button class="btn btn-primary"><?= e(t('login_submit')) ?></button>
  </form>
  <?php // In der Demo führt der Weg nirgendwohin: die Anwendung verschickt
        // dort keine Post, und die Zugangsdaten stehen ohnehin öffentlich. ?>
  <?php if (!is_demo()): ?>
    <p class="muted small" style="margin-top:0.8rem"><a href="/passwort-vergessen"><?= e(t('pwreset_link')) ?></a></p>
  <?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
