<?php
// Eine Terminliste an einem Ort. Zweimal gebraucht — einmal für das, was war
// und kommt, einmal für die Absagen darunter (#319) —, deshalb hier und nicht
// zweimal in orte.php.
//
// Erwartet: $venueRows, $today.
?>
<ul class="event-list">
  <?php foreach ($venueRows as $ev): ?>
    <li>
      <span class="event-date"><?= fmt_date($ev['date']) ?></span>
      <?= $ev['date'] < $today ? '🔒' : '' ?>
      <span class="badge <?= e($ev['type']) ?>"><?= e(event_type_label($ev['type'])) ?></span>
      <?= e($ev['title']) ?>
      <?php if ($ev['setlist_id']): ?>
        <a class="badge link" href="/intern/setlists/<?= $ev['setlist_id'] ?>"><?= e(t('ev_setlist')) ?>: <?= e($ev['setlist_name']) ?></a>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
