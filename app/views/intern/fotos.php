<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('inav_fotos')) ?></h1>

<div class="card">
  <form method="post" action="/intern/fotos" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <?php // Die Grenzen kommen vom Server, nicht aus dem Text: Sie ändern sich
          // mit der PHP-Einrichtung, und eine feste Zahl wäre spätestens beim
          // nächsten Umzug eine Lüge (#194). ?>
    <label><?= e(str_replace(['%1', '%2'], [fmt_bytes($limits['per_file']), (string) $limits['max_files']], t('photos_upload_lbl_lim'))) ?><input type="file" name="photos[]" accept="image/*" multiple required data-paths></label>
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

<?php // Ordnerweise zuordnen (#208): Der Herkunftsordner sagt schon, was
      // zusammengehört — ein Griff fasst den ganzen Auftritt oder einen
      // Fotografen. Eigenes Formular NEBEN der Massenleiste, nicht darin:
      // ein Formular im Formular verwirft der Browser (#191). Die Wahl des
      // Ordners wählt per JavaScript den Termin mit dem nächsten Datum vor;
      // ohne JavaScript trifft man beide Wahlen von Hand. ?>
<?php if ($herkunft): ?>
<form method="post" action="/intern/fotos/ordner" class="card photo-mass"><?= csrf_field() ?>
  <strong>📂 <?= e(t('photo_folder_assign')) ?></strong>
  <span class="muted small"><?= e(t('photo_folder_assign_hint')) ?></span>
  <div class="row-buttons">
    <select name="folder" data-folderpick aria-label="<?= e(t('photo_folder_assign')) ?>">
      <option value="">– <?= e(t('photo_folder_pick')) ?> –</option>
      <?php foreach ($herkunft as $hk): ?>
        <option value="<?= e($hk['path']) ?>" data-datum="<?= e($hk['date']) ?>"><?= e($hk['path']) ?> (<?= (int) $hk['count'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="event_id" data-foldertarget aria-label="<?= e(t('photo_mass')) ?>">
      <option value="">– <?= e(t('photo_no_event')) ?> –</option>
      <?php foreach ($events as $ev): ?>
        <option value="<?= $ev['id'] ?>" data-date="<?= e(substr((string) $ev['date'], 0, 10)) ?>"><?= fmt_date($ev['date']) ?> · <?= e($ev['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-small"><?= e(t('photo_mass_go')) ?></button>
  </div>
</form>
<?php endif; ?>

<?php foreach ($ordner as $ordSchluessel => $ord): ?>
  <?php // Je Ordner ein eigenes Raster: Das Blättern in der Großansicht
        // bleibt dadurch innerhalb eines Termins — vom letzten Bild eines
        // Auftritts zum ersten eines anderen ist nicht „weiter" (#196). ?>
  <h2 class="photo-folder">
    <?php if ($ordSchluessel === ''): ?>
      📂 <?= e(t('photo_folder_none')) ?>
    <?php else: ?>
      📁 <?= e($ord['title'] ?? '') ?><?php if ($ord['date']): ?> <span class="muted small"><?= e(fmt_date($ord['date'])) ?></span><?php endif; ?>
    <?php endif; ?>
    <span class="muted small"><?= e(str_replace('%1', (string) ($ord['total'] ?? count($ord['photos'])), t('photo_folder_count'))) ?></span>
  </h2>
<div class="photo-grid large" data-prev="<?= e(t('photo_prev')) ?>" data-next="<?= e(t('photo_next')) ?>" data-show-start="<?= e(t('photo_show_start')) ?>" data-show-stop="<?= e(t('photo_show_stop')) ?>">
  <?php foreach ($ord['photos'] as $photo): ?>
    <figure class="photo-admin">
      <?php // Häkchen in die Ecke des Bildes und ohne Beschriftung: Es soll die
            // Kachel nicht länger machen, und was es tut, sagt die Leiste oben. ?>
      <label class="photo-tick" title="<?= e(t('photo_mass_pick')) ?>">
        <input type="checkbox" form="fotos-termin" name="pick[]" value="<?= (int) $photo['id'] ?>"
               aria-label="<?= e(t('photo_mass_pick')) ?>">
      </label>
      <?php if (!empty($photo['is_new'])): ?><span class="photo-new"><?= e(t('photo_new')) ?></span><?php endif; ?>
      <?php // Serie (#198): Die Kachel steht für alle ihre Bilder. Die Zahl sagt
            // wie viele, der Klick macht die Serie auf. ?>
      <?php if (!empty($photo['stack_count'])): ?>
        <a class="photo-stack" href="/intern/fotos/stapel/<?= (int) $photo['stack_id'] ?>"
           title="<?= e(str_replace('%1', (string) $photo['stack_count'], t('photo_stack_count'))) ?>">🗇 <?= (int) $photo['stack_count'] ?></a>
      <?php endif; ?>
      <?php // Kachel lädt die verkleinerte Fassung; das Original zeigt erst die Lupe ?>
      <img src="/thumb/<?= e($photo['filename']) ?>" data-full="/uploads/<?= e($photo['filename']) ?>"
           alt="<?= e($photo['caption']) ?>" loading="lazy">
      <figcaption>
        <?= $photo['caption'] ? e($photo['caption']) . ' · ' : '' ?><span class="muted"><?= e($photo['uploader'] ?? '') ?></span>
        <?php // Die Herkunft leise darunter: eine Angabe für den, der sie sucht,
              // kein Schmuck. Bei Altbestand ist sie leer und steht dann nicht da. ?>
        <?php if (($photo['source'] ?? '') !== ''): ?>
          <span class="photo-source" title="<?= e(t('photo_source')) ?>">📄 <?= e($photo['source']) ?></span>
        <?php endif; ?>
        <?php // Bei einem verknüpften Bild liegt hier nur die gerechnete Fassung;
              // das Original steht bei OneDrive und wird verlinkt (#206). Was das
              // EXIF hergab, steht daneben — es ist die Auskunft, die man sucht,
              // wenn man wissen will, von welcher Kamera ein Bild kommt. ?>
        <?php if (($photo['od_web_url'] ?? '') !== ''): ?>
          <span class="photo-source">
            <a href="<?= e($photo['od_web_url']) ?>" target="_blank" rel="noopener noreferrer"
               title="<?= e(t('od_open_original_title')) ?>">☁ <?= e(t('od_open_original')) ?></a>
            <?php if (($photo['camera'] ?? '') !== ''): ?> · <?= e($photo['camera']) ?><?php endif; ?>
            <?php if ((int) ($photo['img_w'] ?? 0) > 0): ?> · <?= (int) $photo['img_w'] ?>×<?= (int) $photo['img_h'] ?><?php endif; ?>
          </span>
        <?php endif; ?>
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
          <?php if (!empty($photo['stack_count'])): ?>
            <input type="hidden" name="whole_stack" value="1">
            <span class="muted small"><?= e(t('photo_stack_whole')) ?></span>
          <?php endif; ?>
          <select name="event_id">
            <option value="">– <?= e(t('photo_no_event')) ?> –</option>
            <?php // Der naheliegendste Termin zuerst (#207): Was die Anwendung
                  // weiß, soll oben stehen, nicht vorgewählt in der Mitte. ?>
            <?php foreach (events_by_closeness($events, $photo['taken_at'] ?? null) as $ev): ?>
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
<?php endforeach; ?>
<?php if (!$photos): ?><p class="muted center"><?= e(t('photos_none_intern')) ?></p><?php endif; ?>
<script src="<?= e(asset('/assets/fotos.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
