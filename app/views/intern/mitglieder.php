<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('mem_title')) ?></h1>

<?php if ($user['role'] === 'admin'): ?>
<details class="card collapsible">
  <summary>➕ <?= e(t('mem_new')) ?></summary>
  <form method="post" action="/intern/mitglieder" class="form-grid">
    <label><?= e(t('name')) ?><input name="name" required></label>
    <label><?= e(t('email')) ?><input type="email" name="email" required></label>
    <label><?= e(t('instrument')) ?><input name="instrument" placeholder="z. B. Drums"></label>
    <label><?= e(t('role')) ?>
      <select name="role"><option value="member"><?= e(t('role_member')) ?></option><option value="admin"><?= e(t('role_admin')) ?></option></select>
    </label>
    <p class="muted small span2"><?= e(t('mem_invite_hint')) ?></p>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<?php foreach ($members as $m): ?>
  <section class="card">
    <div class="event-head">
      <?php if ($m['avatar_file']): ?>
        <img class="avatar" src="/uploads/<?= e($m['avatar_file']) ?>" alt="<?= e($m['name']) ?>">
      <?php else: ?>
        <span class="avatar avatar-placeholder"><?= e(mb_substr($m['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <strong><?= e($m['stage_name'] ?: $m['name']) ?></strong>
      <?php if ($m['stage_name']): ?><span class="muted">(<?= e($m['name']) ?>)</span><?php endif; ?>
      <?= (int) $m['id'] === (int) $user['id'] ? '<span class="badge">' . e(t('mem_you')) . '</span>' : '' ?>
      <?php if ($m['instrument']): ?><span class="muted">🎸 <?= e($m['instrument']) ?></span><?php endif; ?>
      <span class="badge <?= $m['role'] === 'admin' ? 'public' : '' ?>"><?= e($m['role'] === 'admin' ? t('role_admin') : t('role_member')) ?></span>
      <?php if (!empty($m['can_finance'])): ?><span class="badge">💰 <?= e(t('fin_badge')) ?></span><?php endif; ?>
      <span class="row-buttons">
        <?php if ((int) $m['id'] === (int) $user['id']): ?><a class="btn btn-tiny" href="/intern/profil">✏️ <?= e(t('mem_my_profile')) ?></a><?php endif; ?>
        <?php if ((int) $m['id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
          <details class="inline-details">
            <summary class="btn btn-tiny">🔑 <?= e(t('mem_password')) ?></summary>
            <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/passwort" class="comment-form">
              <input type="password" name="password" minlength="8" required placeholder="<?= e(t('mem_new_pw')) ?>"
                     data-strength data-labels="<?= e(t('pw_weak')) ?>|<?= e(t('pw_medium')) ?>|<?= e(t('pw_strong')) ?>|<?= e(t('pw_very_strong')) ?>">
              <button class="btn btn-tiny"><?= e(t('mem_set')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin' && (int) $m['id'] !== (int) $user['id']): ?>
          <form class="inline" method="post" action="/intern/mitglieder/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><button class="btn btn-tiny btn-danger">🗑</button></form>
        <?php endif; ?>
      </span>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
      <details class="subsection">
        <summary>✏️ <?= e(t('mem_edit_admin')) ?></summary>
        <?php $mFull = row('SELECT * FROM users WHERE id = ?', [$m['id']]); ?>
        <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/update" class="form-grid">
          <label><?= e(t('name')) ?><input name="name" value="<?= e($mFull['name']) ?>" required></label>
          <label><?= e(t('stage_name')) ?><input name="stage_name" value="<?= e($mFull['stage_name']) ?>"></label>
          <label><?= e(t('instrument')) ?><input name="instrument" value="<?= e($mFull['instrument']) ?>"></label>
          <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($mFull['email']) ?>" required></label>
          <label><?= e(t('role')) ?>
            <select name="role" <?= (int) $m['id'] === (int) $user['id'] ? 'disabled title="' . e(t('mem_own_role')) . '"' : '' ?>>
              <option value="member" <?= $mFull['role'] === 'member' ? 'selected' : '' ?>><?= e(t('role_member')) ?></option>
              <option value="admin" <?= $mFull['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('role_admin')) ?></option>
            </select>
          </label>
          <label class="checkbox"><input type="checkbox" name="can_finance" value="1" <?= $mFull['can_finance'] ? 'checked' : '' ?>> 💰 <?= e(t('mem_finance')) ?></label>
          <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
        </form>
      </details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<script src="/assets/strength.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
