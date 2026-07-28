<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1>🎚 <?= e(t('ch_title')) ?></h1>
  <div class="row-buttons">
    <?php if ($channels): ?><a class="btn btn-ghost" href="/intern/kanaele/export">⬇ <?= e(t('ch_export')) ?></a><?php endif; ?>
  </div>
</div>
<p class="muted"><?= e(t('ch_intro')) ?></p>

<details class="card collapsible" <?= $channels ? '' : 'open' ?>>
  <summary>📥 <?= e(t('ch_import')) ?></summary>
  <p class="muted small"><?= e(t('ch_import_hint')) ?></p>
  <form method="post" action="/intern/kanaele/import" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label class="span2"><?= e(t('ch_file')) ?><input type="file" name="scene" accept=".scn,.snap,.txt" required></label>
    <label class="checkbox span2"><input type="checkbox" name="replace" value="1"> <?= e(t('ch_replace')) ?></label>
    <button class="btn btn-primary span2"><?= e(t('upload')) ?></button>
  </form>
</details>

<div class="card">
  <table class="table">
    <thead><tr>
      <th style="width:4rem"><?= e(t('ch_number')) ?></th>
      <th style="width:6rem"><?= e(t('ch_patch')) ?></th>
      <th><?= e(t('ch_name')) ?></th>
      <th><?= e(t('ch_source')) ?></th><th><?= e(t('notes')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($channels as $c): ?>
        <tr>
          <form method="post" action="/intern/kanaele/<?= $c['id'] ?>/update"><?= csrf_field() ?>
            <td><strong><?= (int) $c['number'] ?></strong></td>
            <td><input name="patch" value="<?= e($c['patch']) ?>" style="width:100%" placeholder="<?= e(t('ch_patch_ph')) ?>"></td>
            <td><input name="name" value="<?= e($c['name']) ?>" style="width:100%"></td>
            <td><input name="source" value="<?= e($c['source']) ?>" style="width:100%" placeholder="z. B. SM57, DI"></td>
            <td><input name="notes" value="<?= e($c['notes']) ?>" style="width:100%"></td>
            <td class="row-buttons">
              <button class="btn btn-tiny btn-primary">✓</button>
          </form>
          <form class="inline" method="post" action="/intern/kanaele/<?= $c['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
            <button class="btn btn-tiny btn-danger">🗑</button>
          </form>
            </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$channels): ?><p class="muted center"><?= e(t('ch_none')) ?></p>
  <?php else: ?><p class="muted small"><?= count($channels) ?> <?= e(t('ch_count')) ?></p><?php endif; ?>

  <form method="post" action="/intern/kanaele/neu" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('ch_number')) ?><input type="number" name="number" min="1" max="128" required
      value="<?= $channels ? max(array_column($channels, 'number')) + 1 : 1 ?>"></label>
    <label><?= e(t('ch_patch')) ?><input name="patch" placeholder="<?= e(t('ch_patch_ph')) ?>"></label>
    <label><?= e(t('ch_name')) ?><input name="name"></label>
    <label><?= e(t('ch_source')) ?><input name="source" placeholder="z. B. SM57, DI"></label>
    <label><?= e(t('notes')) ?><input name="notes"></label>
    <button class="btn btn-primary span2"><?= e(t('ch_add')) ?></button>
  </form>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
