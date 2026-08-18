<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>🎵 <?= e($setlist['name']) ?><?= $locked ? ' 🔒' : '' ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/setlists/<?= $setlist['id'] ?>/print" target="_blank">🖨 <?= e(t('sl_print_view')) ?></a>
    <a class="btn btn-ghost" href="/intern/setlists/<?= $setlist['id'] ?>/gema" target="_blank">🏛 <?= e(t('sl_gema_list')) ?></a>
    <form class="inline" method="post" action="/intern/setlists/<?= $setlist['id'] ?>/copy"><?= csrf_field() ?><button class="btn btn-ghost"><?= e(t('copy')) ?></button></form>
    <a class="btn btn-ghost" href="/intern/setlists">← <?= e(t('sl_all')) ?></a>
  </div>
</div>
<p class="muted"><?= count(array_filter($entries, fn($x) => !$x['is_break'])) ?> <?= e(t('sl_songs')) ?> · <?= e(t('sl_total')) ?> <?= fmt_duration($totalSec) ?> min</p>

<?php if ($playedAt): ?>
  <p class="muted small">
    <?php foreach ($playedAt as $pa): ?>
      <span class="att">🎤 <?= fmt_date($pa['date']) ?> · <?= e($pa['title']) ?><?= $pa['venue_name'] ? ' (' . e($pa['venue_name']) . ')' : '' ?></span>
    <?php endforeach; ?>
  </p>
<?php endif; ?>
<?php if ($locked): ?>
  <p class="warn">🔒 <?= e(t('sl_locked_note')) ?></p>
<?php endif; ?>

<div class="card">
  <?php // Die Auswahl liegt im Formular, damit die Häkchen mit dem Knopf unter der
        // Liste zusammen abgeschickt werden. Ohne umschließendes Formular wäre jede
        // Zeile eine eigene Insel (#245). ?>
  <?php if (!$locked): ?><form method="post" action="/intern/setlists/<?= $setlist['id'] ?>/klammer" id="klammerform"><?= csrf_field() ?><?php endif; ?>
  <ol class="setlist-songs<?= $locked ? '' : ' sortable' ?>"
      data-reorder="/intern/setlists/<?= $setlist['id'] ?>/reorder" data-token="<?= e(csrf_token()) ?>"
      data-saved-text="<?= e(t('sl_saved')) ?>">
    <?php foreach ($entries as $entry): ?>
      <?php // Kein draggable mehr: Gezogen wird am Griff, mit Zeigerereignissen —
            // die kennen Maus, Finger und Stift (#237). ?>
      <li class="<?= $entry['is_break'] ? 'break-row' : '' ?>" data-item="<?= $entry['item_id'] ?>">
        <?php if (!$locked): ?><span class="drag-handle" title="<?= e(t('sl_drag_hint')) ?>">⠿</span><?php endif; ?>
        <?php if (!$locked): ?>
          <input type="checkbox" class="sl-pick" name="rows[]" value="<?= $entry['item_id'] ?>"
                 title="<?= e(t('sl_brace_pick')) ?>">
        <?php endif; ?>
        <?php // Gezählt werden Lieder. Eine Pause, eine Blockgrenze und der
              // Zugabe-Strich sind keine Nummer wert — sonst heißt der zwölfte
              // Song „14", und danach sucht auf der Bühne niemand (#247). ?>
        <?php if (!$entry['is_break']) $slNr = ($slNr ?? 0) + 1; ?>
        <span class="pos"><?= $entry['is_break'] ? '' : (int) $slNr ?></span>
        <?php if ($entry['is_break']): ?>
          <?php if ((int) $entry['is_break'] === 3): ?>
            <?php // Der Strich vom Papier — mit der Anweisung, die dort daneben steht. ?>
            <strong class="muted">▬ <?= e(t('sl_block_word')) ?><?= $entry['item_note'] !== '' ? ': ' . e($entry['item_note']) : '' ?></strong>

          <?php else: ?>
            <strong class="muted"><?= (int) $entry['is_break'] === 2 ? '🎉 — ' . e(t('sl_encore_word')) . ' —' : '⏸ — ' . e(t('sl_pause_word')) . ' —' ?></strong>
          <?php endif; ?>
        <?php else: ?>
          <strong><?= e($entry['title']) ?></strong>
          <span class="muted"><?= e($entry['artist'] ?: t('own_song')) ?><?= $entry['song_key'] ? ' · ' . e($entry['song_key']) : '' ?><?= $entry['tempo'] ? ' · ' . e($entry['tempo']) : '' ?> · <?= fmt_duration($entry['duration_sec']) ?></span>
          <?php // Von hier auf die Bühne, mit der Setlist im Rücken: buehne.js
                // springt dann ohne Vollbild zu verlassen zum nächsten Song. ?>
          <a class="btn btn-tiny" href="/intern/songs/<?= (int) $entry['id'] ?>/buehne?sl=<?= (int) $setlist['id'] ?>" title="<?= e(t('stage_hint')) ?>">🎤</a>
          <a class="btn btn-tiny" href="/intern/songs/<?= (int) $entry['id'] ?>/noten?sl=<?= (int) $setlist['id'] ?>" title="<?= e(t('stage_chords')) ?>">🎸</a>
        <?php endif; ?>
        <?php // In der Zeile steht nur die Marke — mit der Anweisung an der ersten
              // Zeile, damit man sieht, was gilt. Geändert wird unten: ein Feld für
              // alles ist ruhiger als eines in jeder Klammer (#246). ?>
        <?php if ($entry['bracket'] !== null): ?>
          <?php $istErste = ($entry['bracket'] !== ($vorigeKlammer ?? null)); ?>
          <span class="badge" title="<?= e(t('sl_brace')) ?>">⎨<?= $istErste && $entry['bracket_note'] !== '' ? ' ' . e($entry['bracket_note']) : '' ?></span>
        <?php endif; ?>
        <?php $vorigeKlammer = $entry['bracket']; ?>
        <?php if (!$locked): ?>
          <span class="row-buttons">
            <form class="inline" method="post" action="/intern/setlists/<?= $setlist['id'] ?>/move"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $entry['item_id'] ?>"><button name="dir" value="up" class="btn btn-tiny">▲</button><button name="dir" value="down" class="btn btn-tiny">▼</button></form>
            <form class="inline" method="post" action="/intern/setlists/<?= $setlist['id'] ?>/remove"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $entry['item_id'] ?>"><button class="btn btn-tiny btn-danger">✕</button></form>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if (!$locked): ?>
    <?php // Der Knopf gehört zur Auswahl in der Liste. ?>
    <p class="row-buttons">
      <input form="klammerform" name="note" maxlength="200" placeholder="<?= e(t('sl_block_note_ph')) ?>">
      <button form="klammerform" class="btn">⎨ <?= e(t('sl_brace_add')) ?></button>
      <?php // Lösen geht über dieselbe Auswahl und dasselbe Formular — nur eine
            // andere Adresse. formaction spart ein zweites Formular um dieselben
            // Häkchen (#246). ?>
      <button form="klammerform" class="btn btn-ghost"
              formaction="/intern/setlists/<?= $setlist['id'] ?>/entklammer">⎨✕ <?= e(t('sl_brace_remove')) ?></button>
      <?php // Blockgrenze aus derselben Leiste und mit derselben Auswahl (#246). ?>
      <button form="klammerform" class="btn btn-ghost"
              formaction="/intern/setlists/<?= $setlist['id'] ?>/addblock">▬ <?= e(t('sl_block_add')) ?></button>
    </p>
    <p class="muted small">⎨ <?= e(t('sl_brace_hint_pick')) ?></p>
    <p class="muted small">▬ <?= e(t('sl_block_hint_pick')) ?></p>
    </form>
  <?php endif; ?>
  <?php if (!$entries): ?><p class="muted"><?= e(t('sl_empty')) ?></p><?php endif; ?>
  <?php if (!$locked): ?>
    <div class="row-buttons">
      <?php if ($available): ?>
        <form method="post" action="/intern/setlists/<?= $setlist['id'] ?>/add" class="comment-form grow"><?= csrf_field() ?>
          <select name="song_id" required>
            <option value=""><?= e(t('sl_pick')) ?></option>
            <?php foreach ($available as $song): ?><option value="<?= $song['id'] ?>"><?= e($song['title']) ?> (<?= fmt_duration($song['duration_sec']) ?>)</option><?php endforeach; ?>
          </select>
          <button class="btn btn-primary"><?= e(t('add')) ?></button>
        </form>
      <?php elseif ($entries): ?>
        <p class="muted small"><?= e(t('sl_all_used')) ?></p>
      <?php endif; ?>
      <form method="post" action="/intern/setlists/<?= $setlist['id'] ?>/addpause" class="inline"><?= csrf_field() ?><button class="btn btn-ghost">⏸ <?= e(t('sl_pause')) ?></button></form>
      <form method="post" action="/intern/setlists/<?= $setlist['id'] ?>/addzugabe" class="inline"><?= csrf_field() ?><button class="btn btn-ghost">🎉 <?= e(t('sl_encore')) ?></button></form>
      <?php // Blockgrenze mit Anweisung — die Anweisung darf leer bleiben, dann
            // ist es nur ein Strich wie auf dem Zettel. ?>

    </div>
  <?php endif; ?>
</div>
<?php if (!$locked): ?><p class="muted small">↕ <?= e(t('sl_drag_hint')) ?></p><script src="<?= e(asset('/assets/sortable.js')) ?>" defer></script><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
