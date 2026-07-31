<?php // Abschnittsmarken zum Einklicken über einem Textfeld. $markerTarget ist der
      // name des zugehörigen <textarea>; markers.js verdrahtet den Rest. Die
      // Namen sind die deutsche Konvention, die die Bühne farbig hervorhebt. ?>
<div class="marker-bar" data-target="<?= e($markerTarget) ?>">
  <?php foreach (['Strophe', 'Refrain', 'Bridge', 'Solo', 'Intro', 'Outro'] as $mk): ?>
    <button type="button" class="btn btn-ghost btn-small" data-mark="[<?= $mk ?>]">[<?= e($mk) ?>]</button>
  <?php endforeach; ?>
</div>
