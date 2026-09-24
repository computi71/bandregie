<?php
/**
 * Die Kennzeichnung „wichtig" an einem Eintrag (#333).
 *
 * Erwartet drei Werte vom Aufrufer:
 *   $wArt   Sorte aus ITEM_KINDS, z. B. 'event' oder 'task'
 *   $wNr    Nummer des Eintrags
 *   $wFlag  die Zeile aus wichtig_map(), oder null
 *
 * Als eigener Baustein und nicht zweimal abgeschrieben: Der Knopf soll an jeder
 * Liste gleich aussehen und gleich prüfen, und beim dritten Ort will niemand
 * dieselbe Stelle ein drittes Mal pflegen.
 *
 * Der Knopf erscheint nur, wer ihn benutzen darf — einer, der „keine
 * Berechtigung" antwortet, ist eine Falle und keine Bedienung.
 */
$wDarf = wichtig_darf($user, $wArt, (int) $wNr);
if ($wFlag || $wDarf):
?>
<?php if ($wFlag): ?>
  <p class="warn">❗ <strong><?= e(t('wichtig_flag')) ?></strong>
    <?php if ($wFlag['note'] !== ''): ?>· <?= e($wFlag['note']) ?><?php endif; ?>
    <?php if (!empty($wFlag['wer'])): ?><span class="muted small">· <?= e(str_replace('%1', $wFlag['wer'], t('wichtig_by'))) ?></span><?php endif; ?>
    <?php if ($wDarf): ?>
      <form class="inline" method="post" action="/intern/wichtig"><?= csrf_field() ?>
        <input type="hidden" name="art" value="<?= e($wArt) ?>">
        <input type="hidden" name="nr" value="<?= (int) $wNr ?>">
        <input type="hidden" name="aus" value="1">
        <button class="btn btn-tiny btn-ghost"><?= e(t('wichtig_unset')) ?></button>
      </form>
    <?php endif; ?>
  </p>
<?php else: ?>
  <details class="subsection">
    <summary>❗ <?= e(t('wichtig_btn')) ?></summary>
    <form method="post" action="/intern/wichtig" class="row-buttons"><?= csrf_field() ?>
      <input type="hidden" name="art" value="<?= e($wArt) ?>">
      <input type="hidden" name="nr" value="<?= (int) $wNr ?>">
      <input name="notiz" maxlength="200" placeholder="<?= e(t('wichtig_note_ph')) ?>"
             aria-label="<?= e(t('wichtig_note_ph')) ?>">
      <button class="btn btn-small" data-confirm="<?= e(t('wichtig_confirm')) ?>"><?= e(t('wichtig_send')) ?></button>
    </form>
    <p class="muted small"><?= e(t('wichtig_hint')) ?></p>
  </details>
<?php endif; ?>
<?php endif; ?>
