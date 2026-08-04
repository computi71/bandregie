<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('inav_fotos')) ?></h1>

<div class="card">
  <form method="post" action="/intern/fotos" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('photos_upload_lbl')) ?><input type="file" name="photos[]" accept="image/*" multiple required></label>
    <label><?= e(t('photos_caption')) ?><input name="caption" placeholder="<?= e(t('optional')) ?>"></label>
    <label class="checkbox span2"><input type="checkbox" name="is_public" value="1"> <?= e(t('photos_public_now')) ?></label>
    <?php // Warum manche Fotos keinen Termin-Vorschlag bekommen: Messenger und
          // soziale Netze entfernen die EXIF-Daten beim Teilen (#143). ?>
    <p class="muted small span2">💡 <?= e(t('photo_exif_hint')) ?></p>
    <button class="btn btn-primary span2"><?= e(t('upload')) ?></button>
  </form>
</div>

<?php // Viele Fotos auf einen Termin. Das Formular steht hier für sich und
      // umschließt das Raster NICHT: In den Kacheln stecken eigene Formulare,
      // und ein Formular im Formular ist ungültiges HTML — der Browser verwirft
      // dann die inneren. Die Häkchen unten hängen über form="fotos-termin" an
      // diesem hier, dafür gibt es das Attribut. ?>
<form method="post" action="/intern/fotos/termin" id="fotos-termin" class="card photo-mass"><?= csrf_field() ?>
  <strong>📅 <?= e(t('photo_mass')) ?></strong>
  <span class="muted small"><?= e(t('photo_mass_hint')) ?></span>
  <div class="row-buttons">
    <button type="button" class="btn btn-ghost btn-small" data-massall><?= e(t('photo_mass_all')) ?></button>
    <button type="button" class="btn btn-ghost btn-small" data-massnone><?= e(t('photo_mass_none')) ?></button>
    <select name="event_id" aria-label="<?= e(t('photo_mass')) ?>">
      <option value="">– <?= e(t('photo_no_event')) ?> –</option>
      <?php foreach ($events as $ev): ?>
        <option value="<?= $ev['id'] ?>"><?= fmt_date($ev['date']) ?> · <?= e($ev['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-small"><?= e(t('photo_mass_go')) ?></button>
  </div>
  <span class="muted small" data-masscount data-template="<?= e(t('photo_mass_count')) ?>"></span>
  <span class="warn small" data-massempty hidden><?= e(t('fl_photo_mass_nothing')) ?></span>
</form>

<div class="photo-grid large" data-prev="<?= e(t('photo_prev')) ?>" data-next="<?= e(t('photo_next')) ?>" data-show-start="<?= e(t('photo_show_start')) ?>" data-show-stop="<?= e(t('photo_show_stop')) ?>">
  <?php foreach ($photos as $photo): ?>
    <figure class="photo-admin">
      <?php // Das Häkchen gehört sichtbar an die Kachel, nicht in die Beschriftung. ?>
      <label class="photo-tick"><input type="checkbox" form="fotos-termin" name="pick[]" value="<?= (int) $photo['id'] ?>"> <span class="muted small"><?= e(t('photo_mass_pick')) ?></span></label>
      <?php // Kachel lädt die verkleinerte Fassung; das Original zeigt erst die Lupe ?>
      <img src="/thumb/<?= e($photo['filename']) ?>" data-full="/uploads/<?= e($photo['filename']) ?>"
           alt="<?= e($photo['caption']) ?>" loading="lazy">
      <figcaption>
        <?= $photo['caption'] ? e($photo['caption']) . ' · ' : '' ?><span class="muted"><?= e($photo['uploader'] ?? '') ?></span>
        <div class="row-buttons">
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/toggle"><?= csrf_field() ?>
            <button class="btn btn-tiny <?= $photo['is_public'] ? '' : 'btn-ghost' ?>"><?= $photo['is_public'] ? '🌐 ' . e(t('ev_public_badge')) : '🔒 ' . e(t('photo_intern')) ?></button>
          </form>
          <?php if ($user['role'] === 'admin'): ?>
            <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/hintergrund"><?= csrf_field() ?><button class="btn btn-tiny btn-ghost" title="<?= e(t('photo_bg_title')) ?>">🖼 <?= e(t('photo_bg')) ?></button></form>
          <?php endif; ?>
          <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        </div>
        <?php // Termin-Zuordnung: der Vorschlag (Aufnahmedatum, bei mehreren am
              // Tag der nächste Ort per GPS) ist vorgewählt — zugeordnet wird
              // aber erst auf Klick, nie automatisch. ?>
        <form class="inline photo-event" method="post" action="/intern/fotos/<?= $photo['id'] ?>/event"><?= csrf_field() ?>📅
          <select name="event_id">
            <option value="">– <?= e(t('photo_no_event')) ?> –</option>
            <?php foreach ($events as $ev): ?>
              <?php $sel = $photo['event_id'] ? (int) $photo['event_id'] === (int) $ev['id'] : (($photo['suggested']['id'] ?? null) == $ev['id']); ?>
              <option value="<?= $ev['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= fmt_date($ev['date']) ?> · <?= e($ev['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-tiny"><?= e($photo['event_id'] ? t('save') : t('photo_assign')) ?></button>
          <?php if (!$photo['event_id'] && !empty($photo['suggested'])): ?><span class="muted small">💡 <?= e(t('photo_suggested')) ?></span><?php endif; ?>
        </form>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
<?php if (!$photos): ?><p class="muted center"><?= e(t('photos_none_intern')) ?></p><?php endif; ?>
<script src="<?= e(asset('/assets/fotos.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
