<?php // Geocoding-Feld unter der Adresse: „Adresse suchen" + versteckte
      // Koordinaten. Weil es nach außen kommuniziert, ist der Knopf
      // standardmäßig ausgegraut, mit Hinweis; erst der Schalter in den
      // Einstellungen schaltet ihn frei. $geoLat/$geoLng vorher setzen. ?>
<div class="span2 geo-field" data-geo-endpoint="/intern/geo/suggest"
     data-t-searching="<?= e(t('geo_searching')) ?>" data-t-none="<?= e(t('geo_no_results')) ?>">
  <button type="button" class="btn btn-ghost btn-small" data-geosearch<?= setting('geocoding_enabled') === '1' ? '' : ' disabled' ?>>🗺 <?= e(t('geo_search')) ?></button>
  <?php if (setting('geocoding_enabled') === '1'): ?>
    <span class="muted small"><?= e(t('geo_attribution')) ?></span>
  <?php else: ?>
    <span class="muted small"><?= e(t('geo_off_hint')) ?></span>
  <?php endif; ?>
  <div class="geo-results"></div>
  <input type="hidden" name="lat" value="<?= e($geoLat ?? '') ?>">
  <input type="hidden" name="lng" value="<?= e($geoLng ?? '') ?>">
</div>
