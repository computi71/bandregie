<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="login-box card">
  <h1>🔑 <?= e(t('pwreset_new_title')) ?></h1>
  <form method="post" action="/passwort-reset/<?= e($token) ?>" class="stack"><?= csrf_field() ?>
    <?php require BASE_DIR . '/app/views/_passwortfelder.php'; ?>
  </form>
</div>
<script src="<?= e(asset('/assets/strength.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
