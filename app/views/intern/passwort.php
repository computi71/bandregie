<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🔑 <?= e(t('pw_change_title')) ?></h1>
<?php if ($forced): ?><p class="warn"><?= e(t('pw_forced_hint')) ?></p><?php endif; ?>

<div class="card" style="max-width:480px">
  <form method="post" action="/intern/passwort" class="form-grid" style="grid-template-columns:1fr"><?= csrf_field() ?>
    <?php require BASE_DIR . '/app/views/_passwortfelder.php'; ?>
  </form>
</div>
<script src="/assets/strength.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
