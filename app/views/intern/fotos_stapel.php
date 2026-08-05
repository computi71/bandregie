<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🗇 <?= e(str_replace('%1', (string) count($photos), t('photo_stack_title'))) ?></h1>
<p><a class="btn btn-ghost btn-small" href="/intern/fotos">← <?= e(t('photo_stack_back')) ?></a></p>

<?php // Eine eigene Seite je Serie: Das Blättern in der Großansicht bleibt so
      // innerhalb der Serie, und die Kachel-Formulare stehen nicht im Weg. Die
      // Termin-Zuordnung gilt hier für das einzelne Bild — ein verrutschtes
      // Foto muss man allein zurechtrücken können (#198). ?>
<div class="photo-grid large" data-prev="<?= e(t('photo_prev')) ?>" data-next="<?= e(t('photo_next')) ?>" data-show-start="<?= e(t('photo_show_start')) ?>" data-show-stop="<?= e(t('photo_show_stop')) ?>">
  <?php foreach ($photos as $photo): ?>
    <figure class="photo-admin">
      <img src="/thumb/<?= e($photo['filename']) ?>" data-full="/uploads/<?= e($photo['filename']) ?>"
           alt="<?= e($photo['caption']) ?>" loading="lazy">
      <figcaption>
        <?= $photo['caption'] ? e($photo['caption']) . ' · ' : '' ?><span class="muted"><?= e($photo['uploader'] ?? '') ?></span>
        <?php if (($photo['source'] ?? '') !== ''): ?>
          <span class="photo-source" title="<?= e(t('photo_source')) ?>">📄 <?= e($photo['source']) ?></span>
        <?php endif; ?>
        <div class="row-buttons">
          <?php if ((int) $photo['id'] === (int) $stack): ?>
            <span class="btn btn-tiny btn-ghost is-cover">⭐ <?= e(t('photo_stack_is_cover')) ?></span>
          <?php else: ?>
            <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/titelbild"><?= csrf_field() ?><button class="btn btn-tiny btn-ghost">⭐ <?= e(t('photo_stack_cover')) ?></button></form>
          <?php endif; ?>
          <?php // Die Presse-Auswahl gehört gerade HIERHER (#202): Aus einer
                // Serie nimmt man das beste Bild, und das sieht man nur hier. ?>
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/presse"><?= csrf_field() ?>
            <button class="btn btn-tiny <?= $photo['is_press'] ? '' : 'btn-ghost' ?>" title="<?= e(t('photo_press_title')) ?>">📣 <?= e(t('photo_press')) ?></button>
          </form>
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/toggle"><?= csrf_field() ?>
            <button class="btn btn-tiny <?= $photo['is_public'] ? '' : 'btn-ghost' ?>"><?= $photo['is_public'] ? '🌐 ' . e(t('ev_public_badge')) : '🔒 ' . e(t('photo_intern')) ?></button>
          </form>
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        </div>
        <form class="inline photo-event" method="post" action="/intern/fotos/<?= $photo['id'] ?>/event"><?= csrf_field() ?>📅
          <select name="event_id">
            <option value="">– <?= e(t('photo_no_event')) ?> –</option>
            <?php // Auch hier der naheliegendste zuerst (#207). ?>
            <?php foreach (events_by_closeness($events, $photo['taken_at'] ?? null) as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= (int) $photo['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= fmt_date($ev['date']) ?> · <?= e($ev['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-tiny"><?= e(t('save')) ?></button>
        </form>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
<?php // lightbox.js kommt aus dem Kopf für alle Seiten — hier nichts nachladen. ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
