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
      <?php // Was der Weg verrät, ist der eigentliche Gewinn: Ordner = Termin,
            // Unterordner = Fotograf. Deshalb steht hier die Aufteilung und
            // nicht nur eine Gesamtzahl (#205). ?>
      <?php $odWege = rows('SELECT rel_path, COUNT(*) n, SUM(taken_at IS NOT NULL) mitDatum
                            FROM od_items WHERE folder_id = ? AND missing_since IS NULL
                            GROUP BY rel_path ORDER BY rel_path LIMIT 30', [(int) $odL['id']]); ?>
      <?php if (count($odWege) > 1 || ($odWege && $odWege[0]['rel_path'] !== '')): ?>
        <ul class="task-list">
          <?php foreach ($odWege as $odP): ?>
            <li><span class="muted">📂 <?= e($odP['rel_path'] !== '' ? $odP['rel_path'] : '/') ?></span>
              <span class="muted small"><?= e(str_replace('%1', (string) (int) $odP['n'], t('od_items_count'))) ?><?php
                if ((int) $odP['mitDatum'] > 0): ?>, <?= (int) $odP['mitDatum'] ?> <?= e(t('od_taken')) ?><?php endif; ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php // Verschwundenes namentlich: „drei fehlen" hilft beim Suchen nicht. ?>
      <?php $odWeg2 = rows('SELECT name, rel_path, missing_since FROM od_items WHERE folder_id = ? AND missing_since IS NOT NULL ORDER BY rel_path, name LIMIT 20', [(int) $odL['id']]); ?>
      <?php if ($odWeg2): ?>
        <ul class="task-list">
          <?php foreach ($odWeg2 as $odW): ?>
            <li><span class="muted">🚫 <?= e($odW['rel_path'] !== '' ? $odW['rel_path'] . '/' : '') ?><?= e($odW['name']) ?></span>
              <span class="muted small"><?= e(str_replace('%1', fmt_date($odW["missing_since"]), t('od_missing_since'))) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php // Ordner heißen nach dem Auftritt. Wer das einmal sagt, muss es
            // nicht bei jedem Bild wiederholen — auch nicht bei den Bildern,
            // die erst nächste Woche in den Ordner kommen (#21).
            //
            // Der Vorschlag steht daneben und wird nicht vorausgewählt: Ein
            // gesetzter Wert wird bestätigt, ohne hinzusehen. ?>
      <?php $odVorschlag = $odL['event_id'] === null ? od_folder_event_suggestion($odL) : null; ?>
      <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/termin" class="form-inline"><?= csrf_field() ?>
        <label><?= e(t('od_folder_event')) ?>
          <select name="event_id">
            <option value="0"><?= e(t('od_folder_event_none')) ?></option>
            <?php foreach ($odTermine as $odEv): ?>
              <option value="<?= (int) $odEv['id'] ?>"<?= (int) ($odL['event_id'] ?? 0) === (int) $odEv['id'] ? ' selected' : '' ?>>
                <?= e(fmt_date($odEv['date']) . ' · ' . $odEv['title']) ?></option>
            <?php endforeach; ?>
          </select></label>
        <button class="btn btn-small"><?= e(t('save')) ?></button>
        <?php if ($odVorschlag !== null): ?>
          <?php $odV = row('SELECT title, date FROM events WHERE id = ?', [$odVorschlag]); ?>
          <?php if ($odV): ?>
            <span class="muted small">💡 <?= e(str_replace('%1', fmt_date($odV['date']) . ' · ' . $odV['title'], t('od_folder_event_suggest'))) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </form>
      <p class="muted small"><?= e(t('od_folder_event_hint')) ?></p>
      <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/aktualisieren" class="inline"><?= csrf_field() ?>
        <button class="btn btn-small">↻ <?= e(t('od_refresh')) ?></button>
      </form>
      <?php // Übernehmen ist die zweite Entscheidung und braucht einen eigenen
            // Knopf: Nachsehen kostet nichts, fünfhundert Bilder holen schon.
            // Deshalb steht daneben, wie viele noch offen sind (#206). ?>
      <?php $odOffen = (int) row("SELECT COUNT(*) n FROM od_items
              WHERE folder_id = ? AND imported_at IS NULL AND missing_since IS NULL
                AND mime LIKE 'image/%'", [(int) $odL['id']])['n']; ?>
      <?php if ($odOffen > 0): ?>
        <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/uebernehmen" class="inline"><?= csrf_field() ?>
          <button class="btn btn-primary btn-small">⬇ <?= e(str_replace(['%1', '%2'],
            [(string) min($odOffen, OD_IMPORT_BATCH), (string) $odOffen], t('od_import'))) ?></button>
        </form>
      <?php endif; ?>
      <form method="post" action="/intern/einstellungen/onedrive/ordner/<?= (int) $odL['id'] ?>/loesen" class="inline"
            data-confirm="<?= e(t('od_unlink_confirm')) ?>"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('od_unlink')) ?></button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
