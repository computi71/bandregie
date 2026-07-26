<?php require BASE_DIR . '/app/views/_header.php'; ?>
<section class="hero">
  <?php if (!empty($settings['logo_file'])): ?>
    <img class="hero-logo" src="/uploads/<?= e($settings['logo_file']) ?>" alt="<?= e($settings['band_name']) ?>">
  <?php else: ?>
    <h1><?= e($settings['band_name']) ?></h1>
  <?php endif; ?>
  <p class="tagline"><?= e(content('tagline')) ?></p>
</section>

<section class="card">
  <h2><?= e(t('home_about')) ?></h2>
  <p class="prewrap"><?= e(content('bio')) ?></p>
</section>

<?php if ($gigs): ?>
<section class="card">
  <h2><?= e(t('home_next_gigs')) ?></h2>
  <ul class="event-list">
    <?php foreach ($gigs as $gig): ?>
      <li>
        <span class="event-date"><?= fmt_date($gig['date']) ?><?= $gig['time'] ? ' · ' . e($gig['time']) . ' ' . e(t('events_oclock')) : '' ?></span>
        <strong><?= e($gig['public_title'] ?: $gig['title']) ?></strong>
        <?php if ($gig['location']): ?><span class="muted"><?= e($gig['location']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <a class="btn" href="/termine"><?= e(t('home_all_events')) ?></a>
</section>
<?php endif; ?>

<?php if ($photos): ?>
<section class="card">
  <h2><?= e(t('home_impressions')) ?></h2>
  <div class="photo-grid">
    <?php foreach ($photos as $photo): ?>
      <figure><img src="/uploads/<?= e($photo['filename']) ?>" alt="<?= e($photo['caption'] ?: $settings['band_name']) ?>" loading="lazy"></figure>
    <?php endforeach; ?>
  </div>
  <a class="btn" href="/fotos"><?= e(t('home_more_photos')) ?></a>
</section>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
