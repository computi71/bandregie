<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1><?= e(t('tr_title')) ?></h1>
  <div class="row-buttons">
    <?php foreach (LANGS as $code => $name): ?>
      <?php if ($code === 'de' || !in_array($code, enabled_langs(), true)) continue; ?>
      <a class="btn btn-small <?= $editLang === $code ? 'btn-primary' : 'btn-ghost' ?>" href="/intern/uebersetzungen?sprache=<?= $code ?>"><?= $name ?></a>
    <?php endforeach; ?>
  </div>
</div>
<p class="muted"><strong><?= LANGS[$editLang] ?></strong> — <?= e(t('tr_intro')) ?></p>

<div class="card">
  <form method="post" action="/intern/uebersetzungen" class="stack"><?= csrf_field() ?>
    <input type="hidden" name="sprache" value="<?= e($editLang) ?>">
    <table class="table">
      <thead><tr><th style="width:40%"><?= e(t('tr_col_de')) ?></th><th><?= LANGS[$editLang] ?></th></tr></thead>
      <tbody>
        <?php foreach (UI_STRINGS as $key => $german): ?>
          <tr>
            <td><?= e($german) ?><div class="muted small"><?= e($key) ?></div></td>
            <td><input name="t_<?= e($key) ?>" value="<?= e($existing[$key] ?? '') ?>" style="width:100%"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
