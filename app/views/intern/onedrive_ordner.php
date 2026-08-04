<?php
// Das Laufwerk durchsehen und Ordner verknüpfen (#20). Verknüpft, nicht kopiert:
// Was hier entsteht, ist ein Verweis — die Dateien bleiben bei Microsoft liegen.
$odVerbunden = od_connection();
// Kennung -> Verknüpfungsnummer, damit die Liste zeigen kann, was schon hängt.
$odSchon = array_column($odLinked, 'id', 'item_id');
require BASE_DIR . '/app/views/_header.php';
?>
<p><a class="btn btn-ghost btn-small" href="/intern/einstellungen">← <?= e(t('inav_einstellungen')) ?></a></p>
<h1>☁ <?= e(t('od_browse_title')) ?></h1>
<p class="muted"><?= e(t('od_browse_intro')) ?></p>

<?php if (!$odVerbunden['connected']): ?>
  <div class="card"><p class="muted"><?= e(t('od_not_connected')) ?></p></div>
<?php elseif ($odInhalt === null): ?>
  <div class="card">
    <p class="warn"><?= e(t('od_unreachable')) ?></p>
    <?php if ($odVerbunden['error'] !== ''): ?><p class="muted small"><?= e($odVerbunden['error']) ?></p><?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <h2><?= $odItem === '' ? e(t('od_root')) : e($odName ?: t('od_folder')) ?></h2>
    <?php if ($odItem !== ''): ?>
      <p><a class="btn btn-ghost btn-small" href="/intern/einstellungen/onedrive/ordner">⌂ <?= e(t('od_root')) ?></a></p>
    <?php endif; ?>

    <?php // Der Ordner, in dem man steht, ist der, den man verknüpfen will —
          // deshalb steht der Knopf hier oben und nicht neben jedem Eintrag. ?>
    <?php if ($odItem !== ''): ?>
      <?php if (isset($odSchon[$odItem])): ?>
        <p class="muted small">✓ <?= e(t('od_already_linked')) ?></p>
      <?php else: ?>
        <form method="post" action="/intern/einstellungen/onedrive/ordner/verknuepfen" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="item_id" value="<?= e($odItem) ?>">
          <input type="hidden" name="name" value="<?= e($odName) ?>">
          <input type="hidden" name="path" value="<?= e(implode('/', $odWeg)) ?>">
          <button class="btn btn-primary btn-small">🔗 <?= e(t('od_link_this')) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$odInhalt['folders'] && !$odInhalt['files']): ?>
      <p class="muted"><?= e(t('od_empty')) ?></p>
    <?php endif; ?>

    <?php if ($odInhalt['folders']): ?>
      <ul class="task-list">
        <?php foreach ($odInhalt['folders'] as $odF): ?>
          <li>
            <a href="/intern/einstellungen/onedrive/ordner?id=<?= urlencode($odF['id']) ?>&amp;name=<?= urlencode($odF['name']) ?>&amp;weg=<?= urlencode(implode('/', [...$odWeg, $odF['name']])) ?>">📁 <?= e($odF['name']) ?></a>
            <span class="muted small"><?= (int) $odF['count'] ?></span>
            <?php if (isset($odSchon[$odF['id']])): ?><span class="badge">🔗</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($odInhalt['files']): ?>
      <p class="muted small"><?= e(str_replace('%1', (string) count($odInhalt['files']), t('od_files_here'))) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2><?= e(t('od_linked_title')) ?></h2>
  <p class="muted small"><?= e(t('od_linked_hint')) ?></p>
  <?php if (!$odLinked): ?>
    <p class="muted"><?= e(t('od_linked_none')) ?></p>
  <?php endif; ?>
  <?php foreach ($odLinked as $odL): ?>
    <?php
      $odZahl = row('SELECT COUNT(*) AS n, SUM(missing_since IS NOT NULL) AS fehlt FROM od_items WHERE folder_id = ?', [(int) $odL['id']]);
      $odFehlt = (int) ($odZahl['fehlt'] ?? 0);
    ?>
    <div class="subsection">
      <strong>📁 <?= e($odL['name'] ?: t('od_folder')) ?></strong>
      <?php if ($odL['path'] !== ''): ?><span class="muted small"><?= e($odL['path']) ?></span><?php endif; ?>
      <p class="muted small">
        <?= e(str_replace('%1', (string) (int) ($odZahl['n'] ?? 0), t('od_items_count'))) ?>
        <?php if ($odFehlt > 0): ?>
          · <span class="warn"><?= e(str_replace('%1', (string) $odFehlt, t('od_items_missing'))) ?></span>
        <?php endif; ?>
        <?php if ($odL['checked_at']): ?> · <?= e(str_replace('%1', fmt_date($odL["checked_at"]), t('od_checked_at'))) ?><?php endif; ?>
      </p>
      <?php // Verschwundenes namentlich: „drei fehlen" hilft beim Suchen nicht. ?>
      <?php $odWeg2 = rows('SELECT name, missing_since FROM od_items WHERE folder_id = ? AND missing_since IS NOT NULL ORDER BY name LIMIT 20', [(int) $odL['id']]); ?>
      <?php if ($odWeg2): ?>
        <ul class="task-list">
          <?php foreach ($odWeg2 as $odW): ?>
            <li><span class="muted">🚫 <?= e($odW['name']) ?></span>
              <span class="muted small"><?= e(str_replace('%1', fmt_date($odW["missing_since"]), t('od_missing_since'))) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/aktualisieren" class="inline"><?= csrf_field() ?>
        <button class="btn btn-small">↻ <?= e(t('od_refresh')) ?></button>
      </form>
      <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/loesen" class="inline"
            data-confirm="<?= e(t('od_unlink_confirm')) ?>"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('od_unlink')) ?></button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
