<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('contact_title')) ?></h1>
<div class="card">
  <p class="prewrap"><?= e(content('booking_text')) ?></p>
  <?php if ($settings['contact_email']): ?>
    <p><a class="btn btn-primary" href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a></p>
  <?php endif; ?>
  <?php require BASE_DIR . '/app/views/_social.php'; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
