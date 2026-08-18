<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Ein Lied zum Lesen, nicht zum Ändern: Text, Tonart, Tempo, Notizen, Noten.
// Das ist die Seite, die auf einem Notenständer liegt — geändert wird woanders.
//
// Die Abschnittsmarken stehen als Klartext im Text ([Refrain] in eigener
// Zeile). Hier werden sie nur erkannt und hervorgehoben; der Text bleibt, wie
// er eingetippt wurde, damit ihn auch ein Drucker und ein fremdes Programm
// versteht.
// Zeilen fürs Anzeigen strukturieren — dieselbe Erkennung wie in der
// Bühnenansicht, damit beide nicht auseinanderlaufen.
$zeilen = lyrics_lines($song['lyrics'] ?? '');
?>
<div class="page-head">
  <h1>🎵 <?= e($song['title']) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost btn-small" href="/intern/songs">← <?= e(t('inav_songs')) ?></a>
    <?php // Nur anbieten, was auch etwas anzeigt (#250). ?>
    <?php if (trim((string) ($song['lyrics'] ?? '')) !== ''): ?>
      <a class="btn btn-small" href="/intern/songs/<?= (int) $song['id'] ?>/buehne" title="<?= e(t('stage_hint')) ?>">🎤 <?= e(t('stage_open')) ?></a>
    <?php endif; ?>
    <?php if (trim((string) ($myChords ?? '')) !== '' || ($otherChordsCount ?? 0) > 0): ?>
      <a class="btn btn-small" href="/intern/songs/<?= (int) $song['id'] ?>/noten" title="<?= e(t('song_chords')) ?>">🎸 <?= e(t('stage_chords')) ?></a>
    <?php endif; ?>
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
  <?php if (trim((string) ($song['lyrics'] ?? '')) === ''): ?>
    <p class="muted"><?= e(t('song_no_lyrics')) ?></p>
  <?php else: ?>
    <div class="lyrics">
      <?php foreach ($zeilen as $z): ?>
        <?php if (isset($z['part'])): ?>
          <p class="lyrics-part part-<?= e($z['cat']) ?>"><?= e($z['part']) ?></p>
        <?php else: ?>
          <p class="lyrics-line<?= trim($z['text']) === '' ? ' lyrics-gap' : '' ?>"><?= e($z['text']) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (trim((string) ($myChords ?? '')) !== ''): ?>
  <div class="card">
    <h2>🎸 <?= e(t('song_chords')) ?></h2>
    <pre class="chords"><?= e($myChords) ?></pre>
  </div>
<?php endif; ?>
<?php if (($otherChordsCount ?? 0) > 0): ?>
  <p class="muted small">🎸 <?= e(t('song_chords_more')) ?></p>
<?php endif; ?>

<?php // Die Noten hängen als Anhang am Lied. Hier nur zum Ansehen — hochladen
      // gehört zum Bearbeiten. ?>
<?php if ($songFiles): ?>
  <div class="card">
    <h2>📎 <?= e(t('files_word')) ?></h2>
    <ul class="task-list">
      <?php foreach ($songFiles as $f): ?>
        <li><a href="/intern/datei/<?= (int) $f['id'] ?>/ansicht"><?= e($f['original_name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
