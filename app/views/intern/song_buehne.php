<?php
// Vollbild-Bühnenansicht: nur der Liedtext, groß und selbstlaufend. Bewusst
// ohne Kopf- und Menüleiste — auf der Bühne zählt der Text, sonst nichts. Der
// eigene <head> ist deshalb schlank, lädt aber dasselbe Stylesheet und, anders
// als jede andere Seite, das Bühnen-Skript. Suchmaschinen haben hier nichts zu
// suchen.
//
// Die Lieder stehen fertig zerlegt als JSON im data-Attribut: buehne.js
// wechselt zwischen ihnen im Browser, ohne die Seite zu verlassen, damit
// Vollbild und Offline-Stand erhalten bleiben.
$json = json_encode($stageSongs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · <?= e($settings['band_name']) ?></title>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <meta name="theme-color" content="#000000">
  <script src="<?= e(asset('/assets/buehne.js')) ?>" defer></script>
</head>
<body class="buehne-body">
<div class="buehne" id="buehne"
     data-songs="<?= e($json) ?>"
     data-start="<?= (int) $startId ?>"
     data-empty="<?= e(t('stage_empty')) ?>">
  <header class="buehne-bar">
    <a class="buehne-btn" href="/intern/songs/<?= (int) $startId ?>" title="<?= e(t('stage_exit')) ?>" aria-label="<?= e(t('stage_exit')) ?>">✕</a>
    <span class="buehne-title"></span>
    <span class="buehne-pos"></span>
  </header>

  <div class="buehne-scroll" role="button" tabindex="0" aria-label="<?= e(t('stage_play')) ?>"></div>

  <div class="buehne-controls">
    <button type="button" class="buehne-btn" data-act="slower" title="<?= e(t('stage_slower')) ?>" aria-label="<?= e(t('stage_slower')) ?>">–</button>
    <span class="buehne-speed" aria-hidden="true"></span>
    <button type="button" class="buehne-btn" data-act="faster" title="<?= e(t('stage_faster')) ?>" aria-label="<?= e(t('stage_faster')) ?>">+</button>
    <button type="button" class="buehne-btn" data-act="prev" title="<?= e(t('stage_prev')) ?>" aria-label="<?= e(t('stage_prev')) ?>">◀</button>
    <button type="button" class="buehne-btn buehne-play" data-act="play" title="<?= e(t('stage_play')) ?>" aria-label="<?= e(t('stage_play')) ?>">▶</button>
    <button type="button" class="buehne-btn" data-act="next" title="<?= e(t('stage_next')) ?>" aria-label="<?= e(t('stage_next')) ?>">▶▶</button>
  </div>
</div>
</body>
</html>
