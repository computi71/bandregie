<?php
// Ein Bild übernehmen, das schon im Inventar liegt (#184). Erwartet: $detailEq.
//
// Bilder statt einer Auswahlliste: Wer ein Foto sucht, erkennt es am Bild und
// nicht am Dateinamen — „thomann-312341.jpg" sagt niemandem etwas.
$photoChoices = eq_photo_choices((int) $detailEq['id']);
if (!$photoChoices) return;
?>
<details class="subsection">
  <summary>🖼 <?= e(t('eq_photo_reuse')) ?></summary>
  <p class="muted small"><?= e(t('eq_photo_reuse_hint')) ?></p>
  <form method="post" action="/intern/dateien/uebernehmen" class="photo-pick"><?= csrf_field() ?>
    <input type="hidden" name="entity_id" value="<?= (int) $detailEq['id'] ?>">
    <div class="photo-pick-strip">
      <?php foreach ($photoChoices as $i => $c): ?>
        <label class="photo-pick-item">
          <input type="radio" name="file_id" value="<?= (int) $c['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
          <img src="/intern/datei/<?= (int) $c['id'] ?>" alt="" loading="lazy">
          <span class="small"><?= e($c['eq_name']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-small"><?= e(t('eq_photo_take')) ?></button>
  </form>
</details>
