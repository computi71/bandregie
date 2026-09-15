<?php
// Die Seite hinter dem Gast-Link (#294). Eine eigene, schlanke Seite ohne die
// Navigation der Band: Ein Gast ist kein Mitglied, und nichts hier darf auf
// eine interne Seite zeigen. Erwartet $b (Buchung samt Termin, oder null, wenn
// der Link nicht mehr gilt) und $antwort ('ja' | 'nein' | null).
$band = $settings['band_name'] ?? 'Bandregie';
$logo = !empty($settings['logo_file']) ? '/uploads/' . rawurlencode($settings['logo_file']) : '';
$venue = $b && !empty($b['venue_id']) ? row('SELECT * FROM venues WHERE id = ?', [$b['venue_id']]) : null;
$ort = $venue ? event_place(['venue_name' => $venue['name'], 'venue_address' => $venue['address'], 'venue_postcode' => $venue['postcode'], 'venue_city' => $venue['city']]) : trim((string) ($b['location'] ?? ''));
$navi = $venue ? venue_dest($venue) : navi_dest((string) ($b['location'] ?? ''));
?><!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> – <?= e($band) ?></title>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <style>
    .gast { max-width: 34rem; margin: 2rem auto; padding: 0 1rem; }
    .gast .logo img { max-height: 3.5rem; }
    .gast .answer { display: flex; gap: 1rem; margin: 1.5rem 0; }
    .gast .answer button { flex: 1; padding: 1rem; font-size: 1.1rem; }
    .gast dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.3rem 1rem; }
    .gast dt { color: var(--muted, #888); }
  </style>
</head>
<body data-navi-pick="<?= e(t('navi_pick')) ?>">
<main class="gast">
  <p class="logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($band) ?>"><?php else: ?><strong><?= e($band) ?></strong><?php endif; ?></p>

  <?php if (!$b): ?>
    <h1><?= e(t('gast_invalid')) ?></h1>
    <p class="muted"><?= e(t('gast_invalid_hint')) ?></p>

  <?php else: ?>
    <h1><?= e(t('gast_hello')) ?> <?= e($b['guest_name']) ?>,</h1>
    <?php if ($antwort === 'ja'): ?>
      <p class="flash"><?= e(t('gast_thanks_yes')) ?></p>
    <?php elseif ($antwort === 'nein'): ?>
      <p class="flash"><?= e(t('gast_thanks_no')) ?></p>
    <?php elseif ($b['status'] === 'angefragt'): ?>
      <p><strong><?= e($band) ?></strong> <?= e(t('gast_asks')) ?>:</p>
    <?php endif; ?>

    <?php if ($antwort !== 'nein'): ?>
      <section class="card">
        <dl>
          <dt><?= e(t('date')) ?></dt><dd><strong><?= e(fmt_date($b['date'])) ?></strong><?= $b['time'] ? ' · ' . e($b['time']) . ' ' . e(t('events_oclock')) : '' ?></dd>
          <dt><?= e(t('name')) ?></dt><dd><?= e($b['title']) ?></dd>
          <?php if ($ort !== ''): ?><dt><?= e(t('ev_venue')) ?></dt><dd><?= e($ort) ?><?php if ($navi !== ''): ?> · <a class="navi-link" data-navi="<?= e($navi) ?>" href="<?= e(navi_web($navi)) ?>" target="_blank" rel="noopener">🧭 <?= e(t('geo_navigate')) ?></a><?php endif; ?></dd><?php endif; ?>
          <?php if ($b['time_meet']): ?><dt><?= e(t('ev_meet')) ?></dt><dd><?= e($b['time_meet']) ?></dd><?php endif; ?>
          <?php if ($b['function_name'] !== ''): ?><dt><?= e(t('gast_your_part')) ?></dt><dd><?= e($b['function_name']) ?></dd><?php endif; ?>
        </dl>
      </section>
    <?php endif; ?>

    <?php // Nach der Zusage: alles für den Abend, nur lesend, nur dieser Termin (#294). ?>
    <?php if ($b['status'] === 'zugesagt' && $antwort !== 'nein'): ?>
      <section class="card">
        <h2><?= e(t('gast_for_evening')) ?></h2>
        <p><a class="btn" href="/gast/<?= e($b['token']) ?>/rider">📋 <?= e(t('gast_rider')) ?></a></p>
        <?php if ($entries): ?>
          <p><a class="btn" href="/gast/<?= e($b['token']) ?>/setliste">🎤 <?= e(t('gast_setlist')) ?></a></p>
          <h3><?= e(t('gast_songs')) ?></h3>
          <ol>
            <?php foreach ($entries as $en): ?>
              <?php if (!empty($en['is_break'])) continue; ?>
              <li><a href="/gast/<?= e($b['token']) ?>/song/<?= (int) $en['id'] ?>"><?= e($en['title']) ?></a><?= !empty($en['song_key']) ? ' <span class="muted small">' . e($en['song_key']) . '</span>' : '' ?></li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="muted small"><?= e(t('gast_no_setlist')) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($b['status'] === 'angefragt' && $antwort === null): ?>
      <form method="post" action="/gast/<?= e($b['token']) ?>/antwort" class="answer"><?= csrf_field() ?>
        <button class="btn btn-primary" name="antwort" value="ja">✔ <?= e(t('gast_yes')) ?></button>
        <button class="btn" name="antwort" value="nein">✘ <?= e(t('gast_no')) ?></button>
      </form>
    <?php elseif ($b['status'] === 'zugesagt' && $antwort === null): ?>
      <p class="muted small"><?= e(t('gast_until')) ?> <?= e(date('d.m.Y H:i', strtotime($b['access_until']))) ?></p>
      <details class="subsection">
        <summary class="muted small"><?= e(t('gast_change')) ?></summary>
        <form method="post" action="/gast/<?= e($b['token']) ?>/antwort" class="answer"><?= csrf_field() ?>
          <button class="btn" name="antwort" value="nein">✘ <?= e(t('gast_no')) ?></button>
        </form>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <p class="muted small" style="margin-top:2rem"><a href="/datenschutz"><?= e(t('nav_datenschutz')) ?></a> · <a href="/impressum"><?= e(t('nav_impressum')) ?></a></p>
</main>
<script src="<?= e(asset('/assets/route.js')) ?>" defer></script>
</body>
</html>
