<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('nav_musik')) ?></h1>
<?php if (!$links): ?>
  <div class="card"><p class="muted"><?= e(t('music_soon')) ?></p></div>
<?php endif; ?>
<div class="media-grid">
  <?php foreach ($links as $link): ?>
    <div class="card">
      <?php if ($link['title']): ?><h3><?= e($link['title']) ?></h3><?php endif; ?>
      <?php if (($settings['public_embed_mode'] ?? 'consent') === 'direct' && $link['etype'] === 'youtube'): ?>
        <div class="embed-16x9"><iframe src="<?= e($link['embed']) ?>" title="<?= e($link['title'] ?: 'Video') ?>" allowfullscreen loading="lazy"></iframe></div>
      <?php elseif (($settings['public_embed_mode'] ?? 'consent') === 'direct' && $link['etype'] === 'spotify'): ?>
        <iframe class="embed-spotify" src="<?= e($link['embed']) ?>" title="<?= e($link['title'] ?: 'Spotify') ?>" allow="encrypted-media" loading="lazy"></iframe>
      <?php elseif ($link['etype'] === 'youtube' || $link['etype'] === 'spotify'): ?>
        <?php $provider = $link['etype'] === 'youtube' ? 'YouTube (Google Ireland Ltd.)' : 'Spotify AB'; ?>
        <div class="embed-consent <?= $link['etype'] === 'youtube' ? 'embed-16x9' : 'embed-consent-spotify' ?>"
             data-embed="<?= e($link['embed']) ?>" data-provider="<?= e($link['etype']) ?>" data-title="<?= e($link['title'] ?: 'Media') ?>">
          <div class="embed-placeholder">
            <p>▶ <?= e(t('music_external_from')) ?> <?= e($provider) ?></p>
            <p class="small"><?= e(t('music_data_notice')) ?> <a href="/datenschutz"><?= e(t('nav_datenschutz')) ?></a></p>
            <button class="btn btn-primary embed-load"><?= e(t('music_load')) ?></button>
            <label class="checkbox small"><input type="checkbox" class="embed-remember"> <?= e(t('music_remember')) ?></label>
          </div>
        </div>
      <?php else: ?>
        <a class="btn" href="<?= e($link['url']) ?>" target="_blank" rel="noopener"><?= e(t('music_listen')) ?></a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<script src="/assets/embed.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
