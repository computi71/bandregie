</main>
<footer class="site-footer">
  <div>
    <strong><?= e($settings['band_name']) ?></strong> · <?= e(content('tagline')) ?>
    <div class="small"><a href="/impressum"><?= e(t('nav_impressum')) ?></a> · <a href="/datenschutz"><?= e(t('nav_datenschutz')) ?></a></div>
  </div>
  <?php require BASE_DIR . '/app/views/_social.php'; ?>
  <?php if ($user && str_starts_with($path, '/intern')): ?>
    <div class="version small muted">
      <a href="https://github.com/computi71/bandroadie/releases" target="_blank" rel="noopener">Bandroadie v<?= e(BANDROADIE_VERSION) ?></a>
    </div>
  <?php endif; ?>
</footer>
</body>
</html>
