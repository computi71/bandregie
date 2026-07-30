<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Texte einpflegen: alle Lieder auf einer Seite, je ein Feld. Gedacht, damit
// die Band ihre eigenen Texte zügig einfügt — Abschnitte in eckigen Klammern
// ([Refrain]) werden auf der Bühne farbig. Gespeichert wird der Text so, wie er
// eingefügt wurde; Zeilenumbrüche sind in einem Liedtext die halbe Information.
?>
<div class="page-head">
  <h1>📝 <?= e(t('song_lyrics_bulk')) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost btn-small" href="/intern/songs">← <?= e(t('inav_songs')) ?></a>
  </div>
</div>
<p class="muted"><?= e(t('song_lyrics_bulk_hint')) ?></p>

<form method="post" action="/intern/songs/lyrics"><?= csrf_field() ?>
  <?php foreach ($songs as $s): ?>
    <label class="card">
      <strong><?= e($s['title']) ?></strong><?php if ($s['artist']): ?> <span class="muted">· <?= e($s['artist']) ?></span><?php endif; ?>
      <textarea name="lyrics[<?= (int) $s['id'] ?>]" rows="6" placeholder="<?= e(t('song_lyrics_ph')) ?>"><?= e($s['lyrics'] ?? '') ?></textarea>
    </label>
  <?php endforeach; ?>
  <div class="row-buttons">
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </div>
</form>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
