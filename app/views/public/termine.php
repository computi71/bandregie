<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('nav_termine')) ?></h1>
<section class="card">
  <h2><?= e(t('events_upcoming')) ?></h2>
  <?php if (!$gigs): ?><p class="muted"><?= e(t('events_none')) ?></p><?php endif; ?>
  <ul class="event-list">
    <?php foreach ($gigs as $gig): ?>
      <li>
        <span class="event-date"><?= fmt_date($gig['date']) ?><?= $gig['time'] ? ' · ' . e($gig['time']) . ' ' . e(t('events_oclock')) : '' ?></span>
        <strong><?= e($gig['public_title'] ?: $gig['title']) ?></strong>
        <?php if ($gig['location']): ?><span class="muted"><?= e($gig['location']) ?></span><?php endif; ?>
        <?php if ($gig['public_info']): ?><span class="muted"><?= e($gig['public_info']) ?></span><?php endif; ?>
        <?php if ($gig['public_link']): ?><a class="btn btn-small" href="<?= e($gig['public_link']) ?>" target="_blank" rel="noopener"><?= e(t('events_tickets')) ?></a><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php if ($past): ?>
<section class="card">
  <h2><?= e(t('events_past')) ?></h2>
  <ul class="event-list muted">
    <?php foreach ($past as $gig): ?>
      <li><span class="event-date"><?= fmt_date($gig['date']) ?></span> <?= e($gig['public_title'] ?: $gig['title']) ?><?= $gig['location'] ? ' · ' . e($gig['location']) : '' ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
