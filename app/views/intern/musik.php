<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🎬 <?= e(t('inav_musik')) ?></h1>
<p class="muted"><?= e(t('set_media_hint')) ?></p>

<?php if (perm_allows($user, 'musik', 'write')): ?>
  <details class="card collapsible" <?= $links ? '' : 'open' ?>>
    <summary>➕ <?= e(t('add')) ?></summary>
    <form method="post" action="/intern/musik" class="form-grid"><?= csrf_field() ?>
      <label><?= e(t('title_lbl')) ?><input name="title" placeholder="z. B. Live beim Stadtfest"></label>
      <label>URL<input name="url" required placeholder="https://youtu.be/... oder https://open.spotify.com/..."></label>
      <button class="btn btn-primary span2"><?= e(t('add')) ?></button>
    </form>
  </details>
<?php endif; ?>

<ul class="task-list">
  <?php foreach ($links as $link): ?>
    <li>
      <strong><?= e($link['title'] ?: $link['url']) ?></strong>
      <a class="muted small" href="<?= e($link['url']) ?>" target="_blank" rel="noopener"><?= e($link['url']) ?></a>
      <?php if (perm_allows($user, 'musik', 'write')): ?>
        <form class="inline" method="post" action="/intern/musik/<?= $link['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
<?php if (!$links): ?><p class="muted center"><?= e(t('downloads_soon')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
