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
      <?php // Ein Klick fragt nach — siehe assets/version.js. Ohne Klick
            // fragt niemand, die Fußzeile steht auf jeder Seite. ?>
      <button type="button" class="linklike" data-versioncheck
              data-checking="<?= e(t('up_checking')) ?>" data-failed="<?= e(t('up_failed')) ?>">
        Bandroadie v<?= e(BANDROADIE_VERSION) ?>
      </button>
      <?php // Nur für Admins, und nur wenn es wirklich etwas Neueres gibt —
            // eine Klammer, in der die eigene Version steht, sagt nichts. ?>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <?php require_once BASE_DIR . '/app/update.php'; ?>
        <?php if (update_available()): ?>
          <a class="warn" href="/intern/einstellungen">(<?= e(update_latest_version()) ?> <?= e(t('up_out')) ?>)</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <dialog id="version-dialog" class="eq-dialog">
      <div class="eq-dialog-head">
        <strong><?= e(t('up_title')) ?></strong>
        <button type="button" class="btn btn-tiny" data-versionclose aria-label="<?= e(t('close')) ?>">✕</button>
      </div>
      <div data-versionbody></div>
      <div class="row-buttons">
        <a class="btn btn-small" href="/intern/einstellungen"><?= e(t('inav_einstellungen')) ?> →</a>
        <a class="btn btn-small btn-ghost" href="/intern/ueber"><?= e(t('about_open')) ?> →</a>
      </div>
    </dialog>
    <script src="<?= e(asset('/assets/version.js')) ?>" defer></script>
  <?php endif; ?>
</footer>
</body>
</html>
