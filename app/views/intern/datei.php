<?php
// Anhang mit Rahmen (#183). Die installierte App läuft in einem eigenen Fenster
// ohne Adressleiste; eine PDF, die dieses Fenster übernimmt, ist eine
// Sackgasse. Deshalb liegt der Weg zurück hier im Inhalt.
$ext     = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
$istBild = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
$istPdf  = $ext === 'pdf';
$istTon  = in_array($ext, ['mp3', 'wav'], true);
$roh     = '/intern/datei/' . (int) $file['id'];
$fmtSize = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
require BASE_DIR . '/app/views/_header.php';
?>
<p><a class="btn btn-ghost btn-small" href="<?= e($backUrl) ?>">← <?= e(t('file_back')) ?></a></p>
<h1><?= e($file['original_name']) ?></h1>
<p class="muted small">
  <?= $fmtSize((int) $file['size']) ?><?= $file['uploader'] ? ' · ' . e($file['uploader']) : '' ?>
</p>

<div class="card file-view">
  <?php if ($istBild): ?>
    <img src="<?= e($roh) ?>" alt="<?= e($file['original_name']) ?>">
  <?php elseif ($istPdf): ?>
    <object data="<?= e($roh) ?>" type="application/pdf">
      <?php // Manche Betrachter, iOS voran, stellen keine PDF im Rahmen dar. ?>
      <p class="muted"><?= e(t('file_no_preview')) ?></p>
    </object>
  <?php elseif ($istTon): ?>
    <audio controls src="<?= e($roh) ?>"></audio>
  <?php else: ?>
    <p class="muted"><?= e(t('file_no_preview')) ?></p>
  <?php endif; ?>
</div>

<p>
  <a class="btn btn-small" href="<?= e($roh) ?>?speichern=1"><?= e(t('file_save')) ?></a>
  <a class="btn btn-ghost btn-small" href="<?= e($roh) ?>" target="_blank" rel="noopener"><?= e(t('file_open_tab')) ?></a>
</p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
