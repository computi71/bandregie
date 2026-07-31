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
<div class="buehne<?= ($mono ?? false) ? ' is-mono' : '' ?>" id="buehne"
     data-songs="<?= e($json) ?>"
     data-start="<?= (int) $startId ?>"
     data-empty="<?= e(t('stage_empty')) ?>">
  <header class="buehne-bar">
    <span class="buehne-title"></span>
    <?php if ($mono ?? false): ?><select class="buehne-musician" aria-label="<?= e(t('stage_chords')) ?>"></select><?php endif; ?>
    <span class="buehne-pos"></span>
  </header>

  <div class="buehne-scroll" role="button" tabindex="0" aria-label="<?= e(t('stage_play')) ?>"></div>

  <div class="buehne-controls">
    <?php // Schließen gehört zu den unteren Knöpfen: am iPhone ist die obere
          // Leiste unter der Safari-/Notch-Zone nicht bedienbar. Das Tempo (−, Zahl,
          // +, Tippen) steckt im Popup, damit die Leiste nicht überläuft. ?>
    <a class="buehne-btn" href="/intern/songs/<?= (int) $startId ?>" title="<?= e(t('stage_exit')) ?>" aria-label="<?= e(t('stage_exit')) ?>">✕</a>
    <button type="button" class="buehne-btn" data-act="prev" title="<?= e(t('stage_prev')) ?>" aria-label="<?= e(t('stage_prev')) ?>">◀</button>
    <button type="button" class="buehne-btn buehne-play" data-act="play" title="<?= e(t('stage_play')) ?>" aria-label="<?= e(t('stage_play')) ?>">▶</button>
    <button type="button" class="buehne-btn" data-act="next" title="<?= e(t('stage_next')) ?>" aria-label="<?= e(t('stage_next')) ?>">▶▶</button>
    <button type="button" class="buehne-btn buehne-tempo-btn" data-act="tempo" title="<?= e(t('stage_tempo')) ?>" aria-label="<?= e(t('stage_tempo')) ?>"><span class="buehne-speed" aria-hidden="true"></span></button>
  </div>

  <?php // Tempo-Popup: die BPM-Zahl direkt eintippbar, dazu −/+ und Tempo-Tippen,
        // mit Luft dazwischen. Der Hintergrund schließt beim Antippen. ?>
  <div class="buehne-tempo" hidden role="dialog" aria-label="<?= e(t('stage_tempo')) ?>">
    <div class="buehne-tempo-card">
      <div class="buehne-tempo-set">
        <button type="button" class="buehne-btn" data-act="slower" aria-label="<?= e(t('stage_slower')) ?>">–</button>
        <input class="buehne-bpm" type="number" inputmode="numeric" min="30" max="260" step="1" aria-label="<?= e(t('stage_tempo')) ?> (BPM)">
        <span class="buehne-bpm-unit">BPM</span>
        <button type="button" class="buehne-btn" data-act="faster" aria-label="<?= e(t('stage_faster')) ?>">+</button>
      </div>
      <button type="button" class="buehne-btn buehne-tap" data-act="tap">👆 <?= e(t('stage_tap')) ?></button>
      <p class="buehne-tempo-hint"><?= e(t('stage_bpm_hint')) ?></p>
      <button type="button" class="buehne-btn buehne-tempo-done" data-act="tempo-close"><?= e(t('stage_done')) ?></button>
    </div>
  </div>
</div>
</body>
</html>
