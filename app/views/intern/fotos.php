<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= $im_archiv ? '📦 ' . e(t('photo_archive_title')) : e(t('inav_fotos')) ?></h1>

<?php // Der Umschalter zwischen Galerie und Archiv (#200), mit der Zahl der
      // anderen Seite — sonst wüsste niemand, dass dort etwas liegt. ?>
<p>
  <?php if ($im_archiv): ?>
    <a class="btn btn-ghost btn-small" href="/intern/fotos">← <?= e(t('photo_archive_back')) ?></a>
    <span class="muted small"><?= e(t('photo_archive_hint')) ?></span>
  <?php elseif ($archiv_zahl > 0): ?>
    <a class="btn btn-ghost btn-small" href="/intern/fotos?archiv=1">📦 <?= e(str_replace('%1', (string) $archiv_zahl, t('photo_archive_view'))) ?></a>
  <?php endif; ?>
  <?php if (!$im_archiv && $presse_zahl > 0 && !$f_presse): ?>
    <a class="btn btn-ghost btn-small" href="/intern/fotos?presse=1">📣 <?= e(str_replace('%1', (string) $presse_zahl, t('photo_press_filter'))) ?></a>
  <?php endif; ?>
</p>

<?php // Ein Suchfeld über alles (#204): Beschreibung, Herkunft, Termin,
      // Schlagwort, Person. Kein eigener Filterbaukasten — tippen, finden. ?>
<form method="get" action="/intern/fotos" class="card photo-mass">
  <div class="row-buttons">
    🔍 <input type="search" name="q" value="<?= e($f_suche) ?>" placeholder="<?= e(t('photo_search_ph')) ?>" maxlength="100">
    <button class="btn btn-small"><?= e(t('photo_search')) ?></button>
    <?php if ($gefiltert): ?>
      <a class="btn btn-ghost btn-small" href="/intern/fotos"><?= e(t('photo_filter_off')) ?></a>
    <?php endif; ?>
  </div>
  <?php // Was gerade eingrenzt, steht benannt da — ein Filter, den man nicht
        // sieht, erklärt eine leere Galerie nicht. ?>
  <?php if ($gefiltert): ?>
    <span class="muted small">
      <?php if ($f_suche !== ''): ?><?= e(str_replace(['%1', '%2'], [(string) count($photos), $f_suche], t('photo_search_count'))) ?><?php endif; ?>
      <?php if ($f_tag !== ''): ?>🏷 <?= e(str_replace('%1', $f_tag, t('photo_tag_filter'))) ?><?php endif; ?>
      <?php if ($f_presse): ?>📣 <?= e(str_replace('%1', (string) count($photos), t('photo_press_filter'))) ?><?php endif; ?>
      <?php if ($f_person > 0): ?>
        <?php foreach ($members as $mg) { if ((int) $mg['id'] === $f_person) { ?>👤 <?= e(str_replace('%1', $mg['name'], t('photo_person_filter'))) ?><?php } } ?>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</form>

<?php // Vorschlagsliste für alle Schlagwort-Eingaben auf der Seite: die
      // vergebenen Wörter plus eine Grundmenge, solange sie unbenutzt ist. ?>
<datalist id="tagliste">
  <?php foreach ($alle_tags as $tg): ?>
    <option value="<?= e($tg['tag']) ?>"><?= $tg['count'] > 0 ? e($tg['tag'] . ' (' . $tg['count'] . ')') : '' ?></option>
  <?php endforeach; ?>
</datalist>

<?php if (!$im_archiv): ?>
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
    <?php // Angehaktes ins Archiv (#200) — derselbe Haken, anderes Ziel. formaction
          // statt zweitem Formular: Die Häkchen hängen ohnehin an diesem hier. ?>
    <button class="btn btn-small btn-ghost" formaction="/intern/fotos/massenarchiv">📦 <?= e(t('photo_archive')) ?></button>
  </div>
  <?php // Schlagwort für alles Angehakte (#201): setzen oder entfernen —
        // dieselben Haken, drittes Ziel. ?>
  <div class="row-buttons">
    🏷 <input name="tag" list="tagliste" maxlength="60" placeholder="<?= e(t('photo_tag')) ?>">
    <button class="btn btn-small" name="mode" value="set" formaction="/intern/fotos/massentag"><?= e(t('photo_tag_set')) ?></button>
    <button class="btn btn-small btn-ghost" name="mode" value="unset" formaction="/intern/fotos/massentag"><?= e(t('photo_tag_unset')) ?></button>
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
<?php endif; // Ende des Galerie-Formularbereichs — im Archiv gibt es nichts
             // hochzuladen und nichts zuzuordnen, nur anzusehen und zurückzuholen. ?>

<?php // Der Baum (#216): Jahr → Termin → Fotograf, wie im verknüpften Ordner.
      // <details> statt JavaScript — das klappt von sich aus, funktioniert im
      // Druck und auf dem Telefon. Beim Filtern und Suchen steht alles offen:
      // Ein Treffer in einem geschlossenen Ordner ist kein Treffer.
      //
      // Je Blatt ein eigenes Raster: Das Blättern in der Großansicht bleibt
      // dadurch innerhalb einer Quelle — vom letzten Bild eines Fotografen zum
      // ersten eines anderen ist nicht „weiter" (#196). ?>
<p class="muted small">🗂 <?= e(t('photo_tree_hint')) ?></p>
<?php foreach ($baum as $jahr): ?>
  <?php // Das jüngste Jahr offen, ältere zu — und beim Suchen alles offen. ?>
  <details class="card photo-year"<?= ($gefiltert || $jahr === $baum[0]) ? ' open' : '' ?>>
    <summary>
      <strong>📅 <?= e($jahr['label']) ?></strong>
      <span class="muted small"><?= e(str_replace('%1', (string) $jahr['total'], t('photo_folder_count'))) ?></span>
    </summary>
    <?php foreach ($jahr['events'] as $ev): ?>
      <details class="photo-event-folder"<?= $gefiltert ? ' open' : '' ?>>
        <summary>
          📁 <?= e($ev['label']) ?>
          <span class="muted small"><?= e(str_replace('%1', (string) $ev['total'], t('photo_folder_count'))) ?></span>
        </summary>
        <?php // Die dritte Ebene nur, wenn ein Termin wirklich mehrere Quellen
              // hat — eine Zwischenebene, die nichts trennt, ist keine Ordnung.
              // Die Hülle wird bedingt auf- und zugemacht, damit das Raster
              // genau einmal im Quelltext steht. ?>
        <?php $mehrere = count($ev['groups']) > 1; ?>
        <?php foreach ($ev['groups'] as $gr): ?>
          <?php if ($mehrere): ?>
            <details class="photo-src-folder"<?= $gefiltert ? ' open' : '' ?>>
              <summary>
                📷 <?= e($gr['label']) ?>
                <span class="muted small"><?= e(str_replace('%1', (string) count($gr['photos']), t('photo_folder_count'))) ?></span>
              </summary>
          <?php endif; ?>
          <div class="photo-grid large" data-prev="<?= e(t('photo_prev')) ?>" data-next="<?= e(t('photo_next')) ?>" data-show-start="<?= e(t('photo_show_start')) ?>" data-show-stop="<?= e(t('photo_show_stop')) ?>">
            <?php foreach ($gr['photos'] as $photo): ?>
              <figure class="photo-admin">
                <?php // Häkchen in die Ecke des Bildes und ohne Beschriftung: Es soll die
                      // Kachel nicht länger machen, und was es tut, sagt die Leiste oben. ?>
                <?php // Im Archiv keine Haken: Das Formular, an dem sie hängen, steht
                      // dort nicht — ein Haken ohne Wirkung wäre ein Versprechen ohne
                      // Einlösung. ?>
                <?php if (!$im_archiv): ?>
                <label class="photo-tick" title="<?= e(t('photo_mass_pick')) ?>">
                  <input type="checkbox" form="fotos-termin" name="pick[]" value="<?= (int) $photo['id'] ?>"
                         aria-label="<?= e(t('photo_mass_pick')) ?>">
                </label>
                <?php endif; ?>
                <?php if (!empty($photo['is_new'])): ?><span class="photo-new"><?= e(t('photo_new')) ?></span><?php endif; ?>
                <?php // Die Suche sieht auch ins Archiv (#204) — dann muss dranstehen,
                      // warum dieses Bild in der Galerie fehlt. ?>
                <?php if (!$im_archiv && $photo['archived_at'] !== null): ?><span class="photo-new photo-archived">📦 <?= e(t('photo_archived_badge')) ?></span><?php endif; ?>
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
                  <?php // Schlagwörter und Personen als Chips (#201, #203): das Wort
                        // filtert auf Klick, das × daneben entfernt es von diesem Bild.
                        // Dahinter je ein kleines Eingabefeld zum Hinzufügen. ?>
                  <?php if ($photo['tags'] || $photo['people'] || !$im_archiv): ?>
                  <div class="photo-chips">
                    <?php foreach ($photo['tags'] as $ptg): ?>
                      <span class="chip">🏷 <a href="/intern/fotos?tag=<?= urlencode($ptg) ?>"><?= e($ptg) ?></a><?php if (!$im_archiv): ?><form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/tag"><?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($ptg) ?>"><button class="chip-x" name="entfernen" value="1" title="<?= e(t('photo_tag_remove_title')) ?>">×</button></form><?php endif; ?></span>
                    <?php endforeach; ?>
                    <?php foreach ($photo['people'] as $pp): ?>
                      <span class="chip">👤 <a href="/intern/fotos?person=<?= (int) $pp['id'] ?>"><?= e($pp['name']) ?></a><?php if (!$im_archiv): ?><form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/person"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $pp['id'] ?>"><button class="chip-x" name="entfernen" value="1" title="<?= e(t('photo_person_remove_title')) ?>">×</button></form><?php endif; ?></span>
                    <?php endforeach; ?>
                    <?php if (!$im_archiv): ?>
                      <form class="inline chip-add" method="post" action="/intern/fotos/<?= $photo['id'] ?>/tag"><?= csrf_field() ?><input name="tag" list="tagliste" maxlength="60" placeholder="🏷"><button class="btn btn-tiny btn-ghost">+</button></form>
                      <form class="inline chip-add" method="post" action="/intern/fotos/<?= $photo['id'] ?>/person"><?= csrf_field() ?><select name="user_id"><option value="">👤</option><?php foreach ($members as $mg): ?><option value="<?= (int) $mg['id'] ?>"><?= e($mg['name']) ?></option><?php endforeach; ?></select><button class="btn btn-tiny btn-ghost">+</button></form>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  <div class="row-buttons">
                    <?php if (!$im_archiv): ?>
                    <?php // Fürs Rausgeben markieren (#202) — bewusst je Bild, nie die
                          // Serie: Aus fünfunddreißig nimmt man das beste, nicht alle. ?>
                    <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/presse"><?= csrf_field() ?>
                      <button class="btn btn-tiny <?= $photo['is_press'] ? '' : 'btn-ghost' ?>" title="<?= e(t('photo_press_title')) ?>">📣 <?= e(t('photo_press')) ?></button>
                    </form>
                    <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/toggle"><?= csrf_field() ?>
                      <button class="btn btn-tiny <?= $photo['is_public'] ? '' : 'btn-ghost' ?>"><?= $photo['is_public'] ? '🌐 ' . e(t('ev_public_badge')) : '🔒 ' . e(t('photo_intern')) ?></button>
                    </form>
                    <?php if ($user['role'] === 'admin'): ?>
                      <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/hintergrund"><?= csrf_field() ?><button class="btn btn-tiny btn-ghost" title="<?= e(t('photo_bg_title')) ?>">🖼 <?= e(t('photo_bg')) ?></button></form>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php // Archivieren statt löschen (#200) — eine Serie-Kachel nimmt ihre
                          // Serie mit, wie bei der Termin-Zuordnung. Im Archiv wird daraus
                          // das Zurückholen. ?>
                    <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/archiv"><?= csrf_field() ?>
                      <?php if (!$im_archiv && !empty($photo['stack_count'])): ?><input type="hidden" name="whole_stack" value="1"><?php endif; ?>
                      <button class="btn btn-tiny btn-ghost">📦 <?= e($im_archiv ? t('photo_restore') : t('photo_archive')) ?></button>
                    </form>
                    <form class="inline" method="post" action="/intern/fotos/<?= $photo['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
                  </div>
                  <?php // Termin-Zuordnung: der Vorschlag (Aufnahmedatum, bei mehreren am
                        // Tag der nächste Ort per GPS) ist vorgewählt — zugeordnet wird
                        // aber erst auf Klick, nie automatisch. Im Archiv nicht: Erst
                        // zurückholen, dann zuordnen. ?>
                  <?php if (!$im_archiv): ?>
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
                  <?php endif; ?>
                </figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
          <?php if ($mehrere): ?>
            </details>
          <?php endif; ?>
        <?php endforeach; ?>
      </details>
    <?php endforeach; ?>
  </details>
<?php endforeach; ?>
<?php if (!$photos): ?><p class="muted center"><?= e($im_archiv ? t('photo_archive_empty') : t('photos_none_intern')) ?></p><?php endif; ?>
<script src="<?= e(asset('/assets/fotos.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
