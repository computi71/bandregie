<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Die Bausteinsammlung der Band (#359).
//
// Jeder Punkt ein eigenes Formular: Ein einziges großes über vierzig
// Textfelder würde bei jedem Speichern alles schreiben, und ein Tippfehler in
// einer Klausel ginge mit allen anderen zusammen unter.
$darf = perm_allows($user, 'vertraege', 'write');
?>
<h1>🧩 <?= e(t('cb_title')) ?></h1>
<p class="muted"><?= e(t('cb_intro')) ?></p>
<p class="warn small">⚖ <?= e(t('cb_disclaimer')) ?></p>

<?php if (!contract_blocks_aktiv()): ?>
  <p class="warn small">⚖ <?= e(t('cb_own_text')) ?></p>
<?php endif; ?>

<details class="card acc">
  <summary>👁 <?= e(t('cb_preview')) ?></summary>
  <p class="prewrap small"><?= e($vorschau) ?></p>
</details>

<?php $bsGruppe = null; ?>
<?php foreach ($blocks as $b): ?>
  <?php if ($b['gruppe'] !== $bsGruppe):
          $bsGruppe = (string) $b['gruppe'];
          $bsTitel = CONTRACT_BLOCK_GROUPS[$bsGruppe]['title'] ?? ''; ?>
    <h2 class="small"><?= $bsTitel !== '' ? e(t($bsTitel)) : e(t('cb_g_rahmen')) ?></h2>
  <?php endif; ?>

  <details class="card acc">
    <summary>
      <?= e($b['label']) ?>
      <?php if ($b['fest']): ?><span class="badge">🔒 <?= e(t('cb_fixed')) ?></span><?php endif; ?>
      <?php if ($b['default_on']): ?><span class="badge public">✓</span><?php endif; ?>
      <?php if (!$b['active']): ?><span class="badge ev-abgesagt">✕</span><?php endif; ?>
      <?php if ($b['wahl']): ?><span class="badge"><?= e($b['wahl']) ?></span><?php endif; ?>
    </summary>

    <?php if (!$darf): ?>
      <p class="prewrap small"><?= e((string) $b['body']) ?></p>
    <?php else: ?>
      <form method="post" action="/intern/bausteine/<?= (int) $b['id'] ?>" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('cb_label')) ?><input name="label" value="<?= e($b['label']) ?>" maxlength="120" required></label>
        <label><?= e(t('cb_group')) ?>
          <select name="gruppe">
            <?php foreach (CONTRACT_BLOCK_GROUPS as $bsKey => $bsG): ?>
              <option value="<?= e($bsKey) ?>" <?= $b['gruppe'] === $bsKey ? 'selected' : '' ?>>
                <?= $bsG['title'] !== '' ? e(t($bsG['title'])) : e($bsKey) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><?= e(t('cb_sort')) ?><input type="number" name="sort" value="<?= (int) $b['sort'] ?>" min="0" step="10"></label>
        <label><?= e(t('cb_choice')) ?>
          <input name="wahl" value="<?= e($b['wahl']) ?>" maxlength="20">
          <span class="muted small"><?= e(t('cb_choice_hint')) ?></span></label>

        <label class="span2"><?= e(t('cb_body')) ?>
          <textarea name="body" rows="6" class="prewrap"><?= e((string) $b['body']) ?></textarea>
          <span class="muted small"><?= e(implode('  ', contract_placeholders())) ?></span></label>

        <label class="span2"><?= e(t('cb_note')) ?>
          <textarea name="hinweis" rows="2" maxlength="500"><?= e((string) $b['hinweis']) ?></textarea>
          <span class="muted small"><?= e(t('cb_note_hint')) ?></span></label>

        <label class="checkbox span2">
          <input type="checkbox" name="default_on" value="1" <?= $b['default_on'] ? 'checked' : '' ?>
                 <?= $b['fest'] ? 'disabled' : '' ?>>
          <?= e(t('cb_default')) ?></label>
        <label class="checkbox span2">
          <input type="checkbox" name="active" value="1" <?= $b['active'] ? 'checked' : '' ?>
                 <?= $b['fest'] ? 'disabled' : '' ?>>
          <?= e(t('cb_active')) ?>
          <br><span class="muted small"><?= e(t('cb_active_hint')) ?></span></label>

        <?php // Feste Punkte schicken ihre beiden Häkchen nicht mit, weil sie
              // abgeschaltet sind. Ohne diese zwei Zeilen fiele ein
              // Vertragskopf beim Speichern aus der Vorauswahl. ?>
        <?php if ($b['fest']): ?>
          <input type="hidden" name="default_on" value="1">
          <input type="hidden" name="active" value="1">
        <?php endif; ?>

        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
      </form>

      <?php if (!$b['fest']): ?>
        <form class="inline" method="post" action="/intern/bausteine/<?= (int) $b['id'] ?>/delete"
              data-confirm="<?= e(t('cb_delete_confirm')) ?>"><?= csrf_field() ?>
          <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </details>
<?php endforeach; ?>

<?php if ($darf): ?>
  <form method="post" action="/intern/bausteine/neu" class="row-buttons"><?= csrf_field() ?>
    <button class="btn">➕ <?= e(t('cb_new')) ?></button>
  </form>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
