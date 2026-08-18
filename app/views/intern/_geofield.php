<?php // Geocoding-Feld unter der Adresse: „Adresse suchen" + versteckte
      // Koordinaten. Weil es nach außen kommuniziert, ist der Knopf
      // standardmäßig ausgegraut, mit Hinweis; erst der Schalter in den
      // Einstellungen schaltet ihn frei. $geoLat/$geoLng vorher setzen. ?>
<?php $geoOn = setting('geocoding_enabled') === '1'; ?>
<div class="span2 geo-field" data-geo-endpoint="/intern/geo/suggest"
     data-t-searching="<?= e(t('geo_searching')) ?>" data-t-none="<?= e(t('geo_no_results')) ?>"
     data-t-none-hint="<?= e(t('geo_none_hint')) ?>" data-t-searched-as="<?= e(t('geo_searched_as')) ?>">
  <?php // Ausgeschaltet gehört ausdrücklich am Knopf, nicht nur im Kleingedruckten
        // darunter — sonst tippt man einen toten Knopf und wundert sich. ?>
  <button type="button" class="btn btn-ghost btn-small" data-geosearch<?= $geoOn ? '' : ' disabled' ?>>🗺 <?= e(t('geo_search')) ?><?= $geoOn ? '' : ' · ' . e(t('geo_off_label')) ?></button>
  <?php if ($geoOn): ?>
    <span class="muted small"><?= e(t('geo_attribution')) ?></span>
  <?php else: ?>
    <span class="muted small">🔒 <?= e(t('geo_off_hint')) ?></span>
  <?php endif; ?>
  <div class="geo-results"></div>
  <input type="hidden" name="lat" value="<?= e($geoLat ?? '') ?>">
  <input type="hidden" name="lng" value="<?= e($geoLng ?? '') ?>">
</div>
