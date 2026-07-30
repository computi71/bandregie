<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Ein Lied zum Lesen, nicht zum Ändern: Text, Tonart, Tempo, Notizen, Noten.
// Das ist die Seite, die auf einem Notenständer liegt — geändert wird woanders.
//
// Die Abschnittsmarken stehen als Klartext im Text ([Refrain] in eigener
// Zeile). Hier werden sie nur erkannt und hervorgehoben; der Text bleibt, wie
// er eingetippt wurde, damit ihn auch ein Drucker und ein fremdes Programm
// versteht.
$zeilen = preg_split('~\R~', (string) ($song['lyrics'] ?? '')) ?: [];
?>
<div class="page-head">
  <h1>🎵 <?= e($song['title']) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost btn-small" href="/intern/songs">← <?= e(t('inav_songs')) ?></a>
    <a class="btn btn-ghost btn-small" href="/intern/songs/<?= (int) $song['id'] ?>/edit">✏️ <?= e(t('song_edit_link')) ?></a>
  </div>
</div>

<p class="muted">
  <?php if ($song['artist']): ?><?= e($song['artist']) ?><?php endif; ?>
  <?php if ($song['song_key']): ?> · <?= e(t('song_keylbl')) ?> <strong><?= e($song['song_key']) ?></strong><?php endif; ?>
  <?php if ($song['tempo']): ?> · <?= e(t('song_tempo')) ?> <strong><?= e($song['tempo']) ?></strong><?php endif; ?>
  <?php if ($song['duration_sec']): ?> · <?= (int) floor($song['duration_sec'] / 60) ?>:<?= sprintf('%02d', $song['duration_sec'] % 60) ?><?php endif; ?>
</p>

<?php if (trim((string) $song['notes']) !== ''): ?>
  <p class="card prewrap muted"><?= e($song['notes']) ?></p>
<?php endif; ?>

<div class="card">
  <h2><?= e(t('song_lyrics')) ?></h2>
  <?php if (trim(implode('', $zeilen)) === ''): ?>
    <p class="muted"><?= e(t('song_no_lyrics')) ?></p>
  <?php else: ?>
    <div class="lyrics">
      <?php foreach ($zeilen as $zeile): ?>
        <?php $marke = preg_match('~^\s*\[(.{1,40})\]\s*$~u', $zeile, $m); ?>
        <?php if ($marke): ?>
          <p class="lyrics-part"><?= e($m[1]) ?></p>
        <?php else: ?>
          <p class="lyrics-line<?= trim($zeile) === '' ? ' lyrics-gap' : '' ?>"><?= e($zeile) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php // Die Noten hängen als Anhang am Lied. Hier nur zum Ansehen — hochladen
      // gehört zum Bearbeiten. ?>
<?php if ($songFiles): ?>
  <div class="card">
    <h2>📎 <?= e(t('files_word')) ?></h2>
    <ul class="task-list">
      <?php foreach ($songFiles as $f): ?>
        <li><a href="/intern/datei/<?= (int) $f['id'] ?>" target="_blank"><?= e($f['original_name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
