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
  <?php // Passkey: versteckt, bis das Skript weiß, dass der Browser es kann.
        // Wer keinen hat, sieht damit auch keinen Knopf, der ins Leere führt. ?>
  <?php if (passkey_supported()): ?>
    <div data-passkey data-token="<?= e(csrf_token()) ?>" hidden style="margin-top:0.8rem">
      <button type="button" class="btn btn-ghost" id="pk-login" style="width:100%"
              data-failed="<?= e(t('fl_pk_failed')) ?>"
              data-cancelled="<?= e(t('pk_cancelled')) ?>"
              data-unsupported="<?= e(t('pk_unsupported')) ?>">🔐 <?= e(t('pk_login')) ?></button>
      <p class="muted small" id="pk-msg"></p>
    </div>
  <?php endif; ?>
  <?php // In der Demo führt der Weg nirgendwohin: die Anwendung verschickt
        // dort keine Post, und die Zugangsdaten stehen ohnehin öffentlich. ?>
  <?php if (!is_demo()): ?>
    <p class="muted small" style="margin-top:0.8rem"><a href="/passwort-vergessen"><?= e(t('pwreset_link')) ?></a></p>
  <?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
