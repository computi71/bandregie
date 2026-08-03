<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>📋 <?= e(t('rider_title')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/stagerider/print" target="_blank">🖨 <?= e(t('rider_print')) ?></a>
  </div>
</div>
<p class="muted"><?= e(t('rider_intro')) ?></p>

<div class="card">
  <h2>🎭 <?= e(t('stage_plot')) ?></h2>
  <p class="muted small"><?= e(t('stage_hint')) ?></p>
  <?php $stageEdit = perm_allows($user, 'rider', 'write'); require BASE_DIR . '/app/views/_buehnenplan.php'; ?>
  <?php if ($stageEdit): ?>
    <?php // Das Maß steht beim Plan und nicht in den Einstellungen: Wer den
          // Rider pflegt, weiß, von welcher Bühne die Band ausgeht. ?>
    <?php [$stageMB, $stageMT] = stage_size(); ?>
    <details class="subsection">
      <summary>📐 <?= e(t('stage_size')) ?> — <?= $stageMB ?> × <?= $stageMT ?> m</summary>
      <p class="muted small"><?= e(t('stage_size_hint')) ?></p>
      <form method="post" action="/intern/stagerider/mass" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('stage_width_m')) ?><input type="number" name="stage_width_m" min="2" max="30" value="<?= $stageMB ?>"></label>
        <label><?= e(t('stage_depth_m')) ?><input type="number" name="stage_depth_m" min="2" max="20" value="<?= $stageMT ?>"></label>
        <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
      </form>
    </details>
    <p class="muted small">📏 <?= e(t('stage_scale_hint')) ?></p>
  <?php endif; ?>

  <?php if ($stageEdit): ?>
    <?php if ($stageItems): ?>
      <form method="post" action="/intern/stagerider/plan/update"><?= csrf_field() ?>
        <p class="muted small"><?= e(t('stage_drag_hint')) ?></p>
        <ul class="task-list stage-list">
          <?php foreach ($stageItems as $si): ?>
            <li data-stagerow="<?= (int) $si['id'] ?>">
              <select name="item[<?= $si['id'] ?>][kind]" aria-label="<?= e(t('stage_kind')) ?>">
                <?php foreach (array_keys(STAGE_KINDS) as $k): ?>
                  <option value="<?= $k ?>" <?= $si['kind'] === $k ? 'selected' : '' ?>><?= e(t('stagekind_' . $k)) ?></option>
                <?php endforeach; ?>
              </select>
              <?php // Bei Menschen steht an zweiter Stelle das Mitglied und nicht ein
                    // getippter Name: Der Name kommt aus dem Profil, und ein Feld
                    // daneben wäre eine zweite Wahrheit. Ein Namensfeld gibt es nur,
                    // solange kein Mitglied gewählt ist — für Gäste ohne Konto. ?>
              <?php if ($si['kind'] === 'musiker'): ?>
                <select name="item[<?= $si['id'] ?>][user_id]" aria-label="<?= e(t('stage_member')) ?>">
                  <option value=""><?= e(t('stage_member_none')) ?></option>
                  <?php foreach ($stageMembers as $sm): ?>
                    <option value="<?= (int) $sm['id'] ?>" <?= (int) ($si['user_id'] ?? 0) === (int) $sm['id'] ? 'selected' : '' ?>><?= e($sm['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (!($si['user_id'] ?? null)): ?>
                  <input name="item[<?= $si['id'] ?>][label]" value="<?= e($si['label']) ?>" placeholder="<?= e(t('stage_guest')) ?>" aria-label="<?= e(t('stage_guest')) ?>">
                <?php endif; ?>
              <?php else: ?>
                <input name="item[<?= $si['id'] ?>][label]" value="<?= e($si['label']) ?>" aria-label="<?= e(t('stage_label')) ?>">
                <input type="hidden" name="item[<?= $si['id'] ?>][user_id]" value="<?= (int) ($si['user_id'] ?? 0) ?>">
              <?php endif; ?>
              <input name="item[<?= $si['id'] ?>][note]" value="<?= e($si['note']) ?>" placeholder="<?= e(t('stage_note')) ?>" aria-label="<?= e(t('stage_note')) ?>">
              <input type="number" name="item[<?= $si['id'] ?>][x]" value="<?= (int) $si['x'] ?>" min="0" max="100" class="stage-num" aria-label="<?= e(t('stage_x')) ?>">
              <input type="number" name="item[<?= $si['id'] ?>][y]" value="<?= (int) $si['y'] ?>" min="0" max="100" class="stage-num" aria-label="<?= e(t('stage_y')) ?>">
              <?php // Leer heißt „übliches Maß seiner Art" — nur wer abweicht,
                    // trägt etwas ein. Ein Podest von 3 x 2 m etwa besteht aus
                    // drei Modulen von 100 x 200 cm. ?>
              <input type="number" name="item[<?= $si['id'] ?>][width_cm]" value="<?= $si['width_cm'] !== null ? (int) $si['width_cm'] : '' ?>"
                     min="0" max="2000" class="stage-num" placeholder="<?= e(t('stage_w')) ?>" aria-label="<?= e(t('stage_w')) ?>">
              <input type="number" name="item[<?= $si['id'] ?>][depth_cm]" value="<?= $si['depth_cm'] !== null ? (int) $si['depth_cm'] : '' ?>"
                     min="0" max="2000" class="stage-num" placeholder="<?= e(t('stage_d')) ?>" aria-label="<?= e(t('stage_d')) ?>">
              <?php // Ein eigenes Formular je Zeile wäre verschachtelt und damit
                    // ungültig — der Knopf schickt stattdessen seine Kennung mit. ?>
              <button class="btn btn-tiny btn-danger" name="remove" value="<?= $si['id'] ?>"
                      title="<?= e(t('delete')) ?>" formnovalidate
                      data-confirm="<?= e(t('confirm_delete')) ?>">🗑</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>
    <?php endif; ?>

    <details class="subsection">
      <summary>➕ <?= e(t('stage_add')) ?></summary>
      <form method="post" action="/intern/stagerider/plan/add" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('stage_kind')) ?>
          <select name="kind">
            <?php foreach (array_keys(STAGE_KINDS) as $k): ?><option value="<?= $k ?>"><?= e(t('stagekind_' . $k)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <?php // Nicht mehr verlangt: Bei einem Menschen kommt der Name vom
              // gewählten Mitglied, und ein Pflichtfeld, dessen Inhalt beim
              // Speichern verworfen wird, ist eine Zumutung (#187). ?>
        <label><?= e(t('stage_label')) ?><input name="label" placeholder="<?= e(t('stage_label_opt')) ?>"></label>
        <label><?= e(t('stage_note')) ?><input name="note"></label>
        <label><?= e(t('stage_x')) ?><input type="number" name="x" min="0" max="100" value="50"></label>
        <label><?= e(t('stage_y')) ?><input type="number" name="y" min="0" max="100" value="50"></label>
        <label><?= e(t('stage_w')) ?><input type="number" name="width_cm" min="0" max="2000" placeholder="<?= e(t('stage_size_default')) ?>"></label>
        <label><?= e(t('stage_d')) ?><input type="number" name="depth_cm" min="0" max="2000" placeholder="<?= e(t('stage_size_default')) ?>"></label>
        <label><?= e(t('stage_member')) ?>
          <select name="user_id">
            <option value=""><?= e(t('stage_member_none')) ?></option>
            <?php foreach ($stageMembers as $sm): ?><option value="<?= (int) $sm['id'] ?>"><?= e($sm['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-primary span2"><?= e(t('stage_add')) ?></button>
      </form>
    </details>

    <form method="post" action="/intern/stagerider/plan/vorlage" class="inline" <?= $stageItems ? 'data-confirm="' . e(t('stage_replace_warn')) . '"' : '' ?>><?= csrf_field() ?>
      <button class="btn"><?= e(t('stage_from_members')) ?></button>
    </form>
    <p class="muted small"><?= e(t('stage_from_members_hint')) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= e(t('rider_requirements')) ?></h2>
  <p class="muted small"><?= e(t('rider_empty_hint')) ?></p>
  <form method="post" action="/intern/stagerider" class="form-grid"><?= csrf_field() ?>
    <label class="span2"><?= e(t('rider_stage_lbl')) ?><textarea name="rider_stage" rows="2"><?= e($settings['rider_stage'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_power_lbl')) ?><textarea name="rider_power" rows="2"><?= e($settings['rider_power'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_pa_lbl')) ?><textarea name="rider_pa" rows="2"><?= e($settings['rider_pa'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_monitor_lbl')) ?><textarea name="rider_monitor" rows="2"><?= e($settings['rider_monitor'] ?? '') ?></textarea></label>
    <label><?= e(t('rider_light_lbl')) ?><textarea name="rider_light" rows="2"><?= e($settings['rider_light'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_getin_lbl')) ?><textarea name="rider_getin" rows="2"><?= e($settings['rider_getin'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_extras_lbl')) ?><textarea name="rider_extras" rows="2"><?= e($settings['rider_extras'] ?? '') ?></textarea></label>
    <label class="span2"><?= e(t('rider_positions_lbl')) ?>
      <textarea name="rider_positions" rows="5" placeholder="<?= e(t('rider_positions_ph')) ?>"><?= e($settings['rider_positions'] ?? '') ?></textarea>
    </label>
    <?php // Ansprechpartner als Mitglied. Bei der Technik ist der Techniker
          // vorausgewählt, solange noch nichts gewählt wurde — geraten am
          // Instrument („Ton", „FOH", „Technik"), überschreibbar mit einem Klick. ?>
    <p class="muted small span2"><?= e(t('rider_contact_hint')) ?></p>
    <?php foreach (['tech' => rider_tech_guess($stageMembers), 'booking' => 0] as $riderArt => $riderVorschlag): ?>
      <?php $riderGesetzt = (int) ($settings['rider_contact_' . $riderArt . '_user'] ?? 0); ?>
      <?php $riderWahl = $riderGesetzt ?: (($settings['rider_contact_' . $riderArt] ?? '') === '' ? $riderVorschlag : 0); ?>
      <label><?= e(t('rider_contact_' . $riderArt . '_lbl')) ?> — <?= e(t('rider_contact_member')) ?>
        <select name="rider_contact_<?= $riderArt ?>_user">
          <option value="0"><?= e(t('rider_contact_none')) ?></option>
          <?php foreach ($stageMembers as $rm): ?>
            <option value="<?= (int) $rm['id'] ?>" <?= $riderWahl === (int) $rm['id'] ? 'selected' : '' ?>><?= e($rm['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= e(t('rider_contact_free')) ?>
        <input name="rider_contact_<?= $riderArt ?>" value="<?= e($settings['rider_contact_' . $riderArt] ?? '') ?>">
      </label>
    <?php endforeach; ?>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <h2><?= e(t('rider_inputs')) ?></h2>
  <?php if ($channels): ?>
    <table class="table">
      <thead><tr><th style="width:4rem"><?= e(t('ch_input')) ?></th><th><?= e(t('ch_name')) ?></th><th><?= e(t('ch_source')) ?></th><th><?= e(t('notes')) ?></th></tr></thead>
      <tbody>
        <?php // Der Veranstalter patcht Ports, nicht unsere Kanalnummern —
              // die braucht nur, wer bei uns am Pult steht. Wer keine Ports
              // gepflegt hat, bekommt die Kanalnummer: eine Spalte, nie zwei. ?>
        <?php foreach ($channels as $c): ?>
          <tr><td><?= e($c['patch'] !== '' ? $c['patch'] : (string) (int) $c['number']) ?></td><td><?= e($c['name']) ?></td><td><?= e($c['source']) ?></td><td class="muted"><?= e($c['notes']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted"><?= e(t('rider_inputs_empty')) ?></p>
  <?php endif; ?>
  <a class="btn btn-small btn-ghost" href="/intern/kanaele"><?= e(t('rider_inputs_from')) ?> →</a>
</div>
<script src="<?= e(asset('/assets/stageplot.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
