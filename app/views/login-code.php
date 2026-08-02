<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="login-box card">
  <h1>🔑 <?= e(t('totp_step_title')) ?></h1>
  <p class="muted"><?= e(t('totp_step_hint')) ?></p>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <?php // inputmode="numeric" holt auf dem Handy die Zifferntastatur, aber
        // type="text" bleibt: Im selben Feld darf auch ein Rückweg stehen,
        // und der hat Buchstaben. autocomplete="one-time-code" lässt iOS und
        // Android den Code aus der Zwischenablage anbieten. ?>
  <form method="post" action="/login/code" class="stack"><?= csrf_field() ?>
    <label><?= e(t('totp_code_label')) ?>
      <input name="code" required autofocus inputmode="numeric" autocomplete="one-time-code"
             maxlength="20" spellcheck="false" autocapitalize="off">
    </label>
    <button class="btn btn-primary"><?= e(t('login_submit')) ?></button>
  </form>
  <p class="muted small" style="margin-top:0.8rem"><?= e(t('totp_step_recovery')) ?></p>
  <p class="muted small"><a href="/login"><?= e(t('cancel')) ?></a></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
