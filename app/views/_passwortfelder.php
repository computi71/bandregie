<?php
/**
 * Die beiden Passwortfelder samt Stärkeanzeige. Werden an zwei Stellen
 * gebraucht: beim Zurücksetzen über den Link aus der E-Mail und beim Ändern
 * im Bandbereich. Passwortregeln ändert man damit nur an einer Stelle.
 */
?>
<label><?= e(t('pw_new')) ?>
  <input type="password" name="password" minlength="8" required autofocus autocomplete="new-password"
         data-strength data-labels="<?= e(t('pw_weak')) ?>|<?= e(t('pw_medium')) ?>|<?= e(t('pw_strong')) ?>|<?= e(t('pw_very_strong')) ?>">
</label>
<label><?= e(t('pw_repeat')) ?>
  <input type="password" name="password2" minlength="8" required autocomplete="new-password">
</label>
<button class="btn btn-primary"><?= e(t('save')) ?></button>
