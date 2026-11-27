<?php
// Was ein Gast spielen kann (#450): das Repertoire mit drei Knöpfen je Lied.
//
// Dieselbe schlanke Seite wie die anderen Gastseiten — keine Navigation der
// Band, kein Verweis nach innen. Drei Knöpfe und nicht ein Häkchen, weil „kann
// ich mit Vorbereitung" die Antwort ist, die am häufigsten stimmt; aus einem
// Häkchen allein kann die Band sie nicht herauslesen.
//
// Kein Text und keine Akkorde: Die gehören zur Setliste und haben ihren
// eigenen Haken. Hier steht nur, was zum Wiedererkennen nötig ist.
//
// Erwartet $b (Buchung), $songs, $answers (Liednummer => Stand) und $zurueck.
$band = $settings['band_name'] ?? 'Bandregie';
$logo = !empty($settings['logo_file']) ? '/uploads/' . rawurlencode($settings['logo_file']) : '';
$gezaehlt = guest_song_counts((int) $b['guest_id']);
?><!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> – <?= e($band) ?></title>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <style>
    .gast { max-width: 40rem; margin: 1.5rem auto; padding: 0 1rem; }
    .gast .song { display: flex; flex-wrap: wrap; align-items: baseline; gap: .3rem .7rem; padding: .6rem 0; border-bottom: 1px solid var(--line, #3334); }
    .gast .song .name { flex: 1 1 12rem; }
    .gast .song form { display: flex; gap: .3rem; }
  </style>
</head>
<body>
<main class="gast">
  <p class="muted small"><a href="<?= e($zurueck) ?>">← <?= e(t('gast_back')) ?></a></p>
  <p class="logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($band) ?>" style="max-height:3rem"><?php else: ?><strong><?= e($band) ?></strong><?php endif; ?></p>
  <h1><?= e(t('gast_songs_can')) ?></h1>
  <p class="muted"><?= e(t('gast_songs_can_hint')) ?></p>
  <p class="muted small"><?= e(str_replace(['%1', '%2'], [(string) $gezaehlt['answered'], (string) $gezaehlt['total']], t('gast_songs_progress'))) ?></p>

  <?php if (!$songs): ?>
    <p class="muted"><?= e(t('gast_songs_none')) ?></p>
  <?php endif; ?>
  <?php foreach ($songs as $s): ?>
    <?php $mein = (string) ($answers[(int) $s['id']] ?? ''); ?>
    <div class="song">
      <span class="name">
        <strong><?= e($s['title']) ?></strong>
        <?php if ((string) $s['artist'] !== ''): ?><span class="muted small"> · <?= e($s['artist']) ?></span><?php endif; ?>
        <?php if ((string) $s['song_key'] !== ''): ?><span class="muted small"> · <?= e($s['song_key']) ?></span><?php endif; ?>
      </span>
      <form method="post" action="<?= e($zurueck) ?>/lied/<?= (int) $s['id'] ?>"><?= csrf_field() ?>
        <?php foreach (['yes', 'maybe', 'no'] as $wahl): ?>
          <button name="status" value="<?= $wahl ?>" class="chip<?= $mein === $wahl ? ' chip-' . $wahl : '' ?>"><?= e(t('gast_can_' . $wahl)) ?></button>
        <?php endforeach; ?>
      </form>
    </div>
  <?php endforeach; ?>
</main>
</body>
</html>
