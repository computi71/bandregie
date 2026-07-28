<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('inav_fotos')) ?></h1>

<div class="card">
  <form method="post" action="/intern/fotos" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('photos_upload_lbl')) ?><input type="file" name="photos[]" accept="image/*" multiple required></label>
    <label><?= e(t('photos_caption')) ?><input name="caption" placeholder="<?= e(t('optional')) ?>"></label>
    <label class="checkbox span2"><input type="checkbox" name="is_public" value="1"> <?= e(t('photos_public_now')) ?></label>
    <button class="btn btn-primary span2"><?= e(t('upload')) ?></button>
  </form>
</div>

<div class="photo-grid large">
  <?php foreach ($photos as $photo): ?>
    <figure class="photo-admin">
      <?php // Kachel lädt die verkleinerte Fassung; das Original zeigt erst die Lupe ?>
      <img src="/thumb/<?= e($photo['filename']) ?>" data-full="/uploads/<?= e($photo['filename']) ?>"
           alt="<?= e($photo['caption']) ?>" loading="lazy">
      <figcaption>
        <?= $photo['caption'] ? e($photo['caption']) . ' · ' : '' ?><span class="muted"><?= e($photo['uploader'] ?? '') ?></span>
        <div class="row-buttons">
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/toggle"><?= csrf_field() ?>
            <button class="btn btn-tiny <?= $photo['is_public'] ? '' : 'btn-ghost' ?>"><?= $photo['is_public'] ? '🌐 ' . e(t('ev_public_badge')) : '🔒 ' . e(t('photo_intern')) ?></button>
          </form>
          <?php if ($user['role'] === 'admin'): ?>
            <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/hintergrund"><?= csrf_field() ?><button class="btn btn-tiny btn-ghost" title="<?= e(t('photo_bg_title')) ?>">🖼 <?= e(t('photo_bg')) ?></button></form>
          <?php endif; ?>
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        </div>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
<?php if (!$photos): ?><p class="muted center"><?= e(t('photos_none_intern')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
