<?php
// Ein Lied für einen Gast (#294): Titel, Tonart, Tempo, Dauer und der Text mit
// seinen Abschnitten — dieselbe Erkennung wie in der Bühnenansicht, aber eine
// eigene, schlanke Seite ohne Navigation der Band. Erwartet $b (Buchung),
// $song und $zurueck.
$band = $settings['band_name'] ?? 'Bandregie';
?><!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($song['title']) ?> – <?= e($band) ?></title>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <style>
    .gast { max-width: 40rem; margin: 1.5rem auto; padding: 0 1rem; }
    .gast .facts { display: flex; flex-wrap: wrap; gap: 0.5rem 1.2rem; }
    .gast .lyrics { font-size: 1.15rem; line-height: 1.6; margin-top: 1.5rem; }
    .gast .lyrics .part { margin: 1.2rem 0 0.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.85rem; opacity: 0.7; }
    .gast .lyrics .chorus { border-left: 3px solid var(--accent, #c33); padding-left: 0.8rem; }
    .gast .lyrics p { margin: 0; white-space: pre-wrap; }
  </style>
</head>
<body>
<main class="gast">
  <p class="muted small"><a href="<?= e($zurueck) ?>">← <?= e(t('gast_back')) ?></a></p>
  <h1><?= e($song['title']) ?></h1>
  <?php if ($song['artist'] !== ''): ?><p class="muted"><?= e($song['artist']) ?></p><?php endif; ?>
  <p class="facts muted small">
    <?php if ($song['song_key'] !== ''): ?><span>🎼 <?= e($song['song_key']) ?></span><?php endif; ?>
    <?php if ($song['tempo'] !== ''): ?><span>⏱ <?= e($song['tempo']) ?></span><?php endif; ?>
    <?php if ((int) $song['duration_sec'] > 0): ?><span>⌛ <?= e(fmt_duration((int) $song['duration_sec'])) ?></span><?php endif; ?>
  </p>
  <?php if (trim((string) $song['lyrics']) === ''): ?>
    <p class="muted"><?= e(t('gast_no_lyrics')) ?></p>
  <?php else: ?>
    <div class="lyrics">
      <?php $abschnitt = ''; ?>
      <?php foreach (lyrics_lines($song['lyrics']) as $z): ?>
        <?php if (isset($z['part'])): ?>
          <?php $abschnitt = $z['cat']; ?>
          <div class="part"><?= e($z['part']) ?></div>
        <?php else: ?>
          <p class="<?= e($abschnitt) ?>"><?= e($z['text']) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
