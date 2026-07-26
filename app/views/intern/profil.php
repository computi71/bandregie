<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('mem_my_profile')) ?></h1>

<div class="card">
  <div class="row-buttons" style="margin-bottom:0.8rem">
    <?php if ($profile['avatar_file']): ?>
      <img class="avatar avatar-big" src="/uploads/<?= e($profile['avatar_file']) ?>" alt="<?= e($profile['name']) ?>">
      <form class="inline" method="post" action="/intern/profil/avatar/delete"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(t('prof_avatar_remove')) ?></button></form>
    <?php else: ?>
      <span class="avatar avatar-big avatar-placeholder"><?= e(mb_substr($profile['name'], 0, 1)) ?></span>
      <span class="muted small"><?= e(t('prof_no_avatar')) ?></span>
    <?php endif; ?>
  </div>
  <form method="post" action="/intern/profil" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('name')) ?><input name="name" value="<?= e($profile['name']) ?>" required></label>
    <label><?= e(t('stage_name')) ?><input name="stage_name" value="<?= e($profile['stage_name']) ?>" placeholder="<?= e(t('prof_stage_name_ph')) ?>"></label>
    <label><?= e(t('instrument')) ?><input name="instrument" value="<?= e($profile['instrument']) ?>" placeholder="z. B. Drums"></label>
    <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($profile['email']) ?>" required></label>
    <label><?= e(t('prof_lang')) ?>
      <select name="pref_lang">
        <?php foreach (LANGS as $code => $name): ?>
          <option value="<?= $code ?>" <?= ($profile['pref_lang'] ?? 'de') === $code ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('prof_avatar_lbl')) ?><input type="file" name="avatar" accept="image/*"></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
  <p class="muted small"><?= e(t('prof_pw_hint')) ?> <a href="/intern/mitglieder"><?= e(t('mem_title')) ?> →</a></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
