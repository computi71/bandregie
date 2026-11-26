<?php
// „Weitere mögliche Daten" (#443) — dasselbe Feld beim Anlegen und beim
// Ändern. Ein leeres Datumsfeld genügt ohne Skript: Speichern hängt ein Datum
// an, das nächste kommt beim nächsten Mal. Mit Skript holt „+ Datum" weitere
// Felder dazu (eventfields.js).
?>
<div class="span2 extra-dates" data-extra-dates>
  <span class="label"><?= e(t('ev_extra_dates')) ?></span>
  <input type="date" name="extra_dates[]">
  <button type="button" class="btn btn-tiny" data-extra-dates-add hidden><?= e(t('ev_extra_dates_add')) ?></button>
  <span class="muted small"><?= e(t('ev_extra_dates_hint')) ?></span>
</div>
