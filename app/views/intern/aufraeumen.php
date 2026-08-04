<?php
// Aufräumen (#193). Erst zeigen, dann löschen: Wer Dateien wegräumt, will vorher
// wissen, welche — und die Liste ist die einzige Stelle, an der diese Reste
// überhaupt sichtbar sind.
$fmtGroesse = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB'
  : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
$summe = count($fund['entity_gone']) + count($fund['file_missing'])
  + count($fund['photo_missing']) + count($fund['files_extra']);
$platzFrei = array_sum(array_column($fund['files_extra'], 'size'));
require BASE_DIR . '/app/views/_header.php';
?>
<p><a class="btn btn-ghost btn-small" href="/intern/einstellungen">← <?= e(t('inav_einstellungen')) ?></a></p>
<h1>🧹 <?= e(t('clean_title')) ?></h1>
<p class="muted"><?= e(t('clean_intro')) ?></p>

<?php if (!$summe): ?>
  <div class="card"><p><?= e(t('clean_nothing')) ?></p></div>
<?php else: ?>

  <?php // Vier Arten, je mit eigener Erklärung: „12 Reste" sagt nicht, was
        // gelöscht wird, und genau das will man vor dem Klick wissen. ?>
  <?php
    $bloecke = [
      ['entity_gone',   'clean_entity_gone',   'clean_entity_gone_hint'],
      ['file_missing',  'clean_file_missing',  'clean_file_missing_hint'],
      ['photo_missing', 'clean_photo_missing', 'clean_photo_missing_hint'],
      ['files_extra',   'clean_files_extra',   'clean_files_extra_hint'],
    ];
  ?>
  <?php foreach ($bloecke as [$schluessel, $titel, $hinweis]): ?>
    <?php if (!$fund[$schluessel]) continue; ?>
    <div class="card">
      <h2><?= e(str_replace('%1', (string) count($fund[$schluessel]), t($titel))) ?></h2>
      <p class="muted small"><?= e(t($hinweis)) ?></p>
      <ul class="task-list">
        <?php foreach (array_slice($fund[$schluessel], 0, 40) as $eintrag): ?>
          <li>
            <span class="muted">
              <?= e($eintrag['original_name'] ?? $eintrag['caption'] ?? $eintrag['filename'] ?? '?') ?>
            </span>
            <?php if (isset($eintrag['entity_type'])): ?>
              <span class="muted small"><?= e($eintrag['entity_type']) ?> <?= (int) ($eintrag['entity_id'] ?? 0) ?></span>
            <?php endif; ?>
            <?php if (isset($eintrag['size'])): ?>
              <span class="muted small"><?= e($fmtGroesse((int) $eintrag['size'])) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($fund[$schluessel]) > 40): ?>
        <p class="muted small"><?= e(str_replace('%1', (string) (count($fund[$schluessel]) - 40), t('clean_more'))) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <p><?= e(str_replace(['%1', '%2'], [(string) $summe, $fmtGroesse($platzFrei)], t('clean_sum'))) ?></p>
    <form method="post" action="/intern/einstellungen/aufraeumen"
          data-confirm="<?= e(t('clean_confirm')) ?>"><?= csrf_field() ?>
      <button class="btn btn-danger">🧹 <?= e(t('clean_go')) ?></button>
    </form>
  </div>
<?php endif; ?>

<?php // Der Bilder-Ordner wird gezählt, aber nie angefasst — die Zahl gehört
      // trotzdem hierher, sonst wundert sich jemand über belegten Platz. ?>
<?php if ($fund['uploads_extra'] > 0): ?>
  <div class="card">
    <h2><?= e(str_replace('%1', (string) $fund['uploads_extra'], t('clean_uploads_extra'))) ?></h2>
    <p class="muted small"><?= e(t('clean_uploads_extra_hint')) ?></p>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
