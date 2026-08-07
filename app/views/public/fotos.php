<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('nav_fotos')) ?></h1>
<?php if (!$photos): ?>
  <div class="card"><p class="muted"><?= e(t('photos_none')) ?></p></div>
<?php endif; ?>
<div class="photo-grid large" data-prev="<?= e(t('photo_prev')) ?>" data-next="<?= e(t('photo_next')) ?>" data-show-start="<?= e(t('photo_show_start')) ?>" data-show-stop="<?= e(t('photo_show_stop')) ?>">
  <?php foreach ($photos as $photo): ?>
    <figure>
      <img src="/thumb/<?= e($photo['filename']) ?>" data-full="/uploads/<?= e($photo['filename']) ?>"
           alt="<?= e($photo['caption'] ?: $settings['band_name']) ?>" loading="lazy">
      <?php if ($photo['caption']): ?><figcaption><?= e($photo['caption']) ?></figcaption><?php endif; ?>
    </figure>
  <?php endforeach; ?>
</div>
<?php // Unter den Bildern, nicht darüber: Der Nachweis erklärt, was man gerade
      // gesehen hat. Zeilen siehe demo_image_credits() — festes HTML. ?>
<?php if ($imageCredits): ?>
  <?php // Die Zeile trägt ihre Beschriftung schon ("Bilder in der Galerie:") —
        // ein zweites „Bildnachweis" davor stünde doppelt. ?>
  <p class="muted small center"><?= implode(' · ', $imageCredits) ?></p>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
