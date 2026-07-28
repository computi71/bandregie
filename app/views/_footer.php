</main>
<footer class="site-footer">
  <div>
    <strong><?= e($settings['band_name']) ?></strong> · <?= e(content('tagline')) ?>
    <div class="small"><a href="/impressum"><?= e(t('nav_impressum')) ?></a> · <a href="/datenschutz"><?= e(t('nav_datenschutz')) ?></a></div>
    <div class="small muted"><?= e(($settings['copyright_text'] ?? '') !== ''
      ? $settings['copyright_text']
      : '© ' . date('Y') . ' ' . $settings['band_name']) ?></div>
  </div>
  <?php require BASE_DIR . '/app/views/_social.php'; ?>
  <?php if ($user && str_starts_with($path, '/intern')): ?>
    <div class="version small muted">
      <a href="/intern/ueber">Bandroadie v<?= e(BANDROADIE_VERSION) ?></a>
      <?php // Nur für Admins, und nur wenn es wirklich etwas Neueres gibt —
            // eine Klammer, in der die eigene Version steht, sagt nichts. ?>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <?php require_once BASE_DIR . '/app/update.php'; ?>
        <?php if (update_available()): ?>
          <a class="warn" href="/intern/einstellungen">(<?= e(update_latest_version()) ?> <?= e(t('up_out')) ?>)</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</footer>
</body>
</html>
