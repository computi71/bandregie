<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('mem_title')) ?></h1>

<?php if ($user['role'] === 'admin'): ?>
<details class="card collapsible">
  <summary>➕ <?= e(t('mem_new')) ?></summary>
  <form method="post" action="/intern/mitglieder" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('mem_first_name')) ?><input name="first_name" required></label>
    <label><?= e(t('mem_last_name')) ?><input name="last_name"></label>
    <label><?= e(t('email')) ?><input type="email" name="email" required></label>
    <label><?= e(t('instrument')) ?><input name="instrument" placeholder="z. B. Drums"></label>
    <label><?= e(t('role')) ?>
      <select name="role"><option value="member"><?= e(t('role_member')) ?></option><option value="ersatz"><?= e(t('role_ersatz')) ?></option><option value="admin"><?= e(t('role_admin')) ?></option></select>
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
      <?php if (!empty($m['mobile'])): ?><span class="muted">📱 <?= e($m['mobile']) ?></span><?php endif; ?>
      <?php if (!empty($m['phone'])): ?><span class="muted">📞 <?= e($m['phone']) ?></span><?php endif; ?>
      <?php if (!empty($m['substitute_for_name'])): ?><span class="badge">🔁 <?= e(t('mem_substitute_for')) ?> <?= e($m['substitute_for_name']) ?></span><?php endif; ?>
      <span class="badge <?= $m['role'] === 'admin' ? 'public' : '' ?>"><?= e(t('role_' . ($m['role'] === 'ersatz' ? 'ersatz' : ($m['role'] === 'admin' ? 'admin' : 'member')))) ?></span>
      <?php if (perm_allows($m, 'kasse', 'write')): ?><span class="badge">💰 <?= e(t('fin_badge')) ?></span><?php endif; ?>
      <span class="row-buttons">
        <?php if ((int) $m['id'] === (int) $user['id']): ?><a class="btn btn-tiny" href="/intern/profil">✏️ <?= e(t('mem_my_profile')) ?></a><?php endif; ?>
        <?php if ((int) $m['id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
          <details class="inline-details">
            <summary class="btn btn-tiny">🔑 <?= e(t('mem_password')) ?></summary>
            <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/passwort" class="comment-form"><?= csrf_field() ?>
              <input type="password" name="password" minlength="8" required placeholder="<?= e(t('mem_new_pw')) ?>"
                     data-strength data-labels="<?= e(t('pw_weak')) ?>|<?= e(t('pw_medium')) ?>|<?= e(t('pw_strong')) ?>|<?= e(t('pw_very_strong')) ?>">
              <button class="btn btn-tiny"><?= e(t('mem_set')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin' && (int) $m['id'] !== (int) $user['id']): ?>
          <form class="inline" method="post" action="/intern/mitglieder/<?= $m['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
        <?php endif; ?>
      </span>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
      <details class="subsection">
        <summary>✏️ <?= e(t('mem_edit_admin')) ?></summary>
        <?php $mFull = row('SELECT * FROM users WHERE id = ?', [$m['id']]); ?>
        <form method="post" action="/intern/mitglieder/<?= $m['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
          <label><?= e(t('stage_name')) ?><input name="stage_name" value="<?= e($mFull['stage_name']) ?>"></label>
          <p class="muted small span2"><?= e(t('mem_name_hint')) ?></p>
          <label><?= e(t('mem_first_name')) ?><input name="first_name" value="<?= e($mFull['first_name'] ?? '') ?>" required></label>
          <label><?= e(t('mem_last_name')) ?><input name="last_name" value="<?= e($mFull['last_name'] ?? '') ?>"></label>
          <label><?= e(t('phone')) ?><input name="phone" value="<?= e($mFull['phone'] ?? '') ?>"></label>
          <label><?= e(t('mem_mobile')) ?><input name="mobile" value="<?= e($mFull['mobile'] ?? '') ?>"></label>
          <label><?= e(t('instrument')) ?>
            <input name="instrument" value="<?= e($mFull['instrument']) ?>" list="instrument-list">
            <span class="muted small"><?= e($instruments ? t('mem_instrument_pick') : t('mem_instrument_free')) ?></span>
          </label>
          <label><?= e(t('mem_substitute_for')) ?>
            <select name="substitute_for">
              <option value=""><?= e(t('mem_substitute_none')) ?></option>
              <?php foreach ($members as $other): ?>
                <?php if ((int) $other['id'] === (int) $m['id']) continue; ?>
                <option value="<?= $other['id'] ?>" <?= (int) ($mFull['substitute_for'] ?? 0) === (int) $other['id'] ? 'selected' : '' ?>><?= e($other['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><?= e(t('mem_substitute_rank')) ?>
            <input type="number" name="substitute_rank" min="0" max="99" value="<?= (int) ($mFull['substitute_rank'] ?? 0) ?>">
            <span class="muted small"><?= e(t('mem_substitute_rank_hint')) ?></span>
          </label>
          <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($mFull['email']) ?>" required></label>
          <label><?= e(t('role')) ?>
            <select name="role" <?= (int) $m['id'] === (int) $user['id'] ? 'disabled title="' . e(t('mem_own_role')) . '"' : '' ?>>
              <option value="member" <?= $mFull['role'] === 'member' ? 'selected' : '' ?>><?= e(t('role_member')) ?></option>
              <option value="admin" <?= $mFull['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('role_admin')) ?></option>
              <option value="ersatz" <?= $mFull['role'] === 'ersatz' ? 'selected' : '' ?>><?= e(t('role_ersatz')) ?></option>
            </select>
          </label>
          <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
        </form>
      </details>
      <?php if ($mFull['role'] !== 'admin'): ?>
        <details class="subsection">
          <summary>🔑 <?= e(t('perm_title')) ?></summary>
          <p class="muted small"><?= e(t('perm_intro')) ?></p>
          <?php $mine = $perms[(int) $m['id']] ?? []; ?>
          <form method="post" action="/intern/rechte/<?= $m['id'] ?>"><?= csrf_field() ?>
            <ul class="perm-list">
              <?php foreach (array_keys(PERM_MODULES) as $mod): ?>
                <?php $canRead = (int) ($mine[$mod]['can_read'] ?? 0); $canWrite = (int) ($mine[$mod]['can_write'] ?? 0); ?>
                <li>
                  <span class="perm-name"><?= e(t('inav_' . $mod)) ?></span>
                  <label class="checkbox"><input type="checkbox" name="perm[<?= $mod ?>][read]" value="1" <?= $canRead ? 'checked' : '' ?>> <?= e(t('perm_read')) ?></label>
                  <label class="checkbox"><input type="checkbox" name="perm[<?= $mod ?>][write]" value="1" <?= $canWrite ? 'checked' : '' ?>> <?= e(t('perm_write')) ?></label>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="row-buttons">
              <button class="btn btn-primary"><?= e(t('save')) ?></button>
              <button class="btn btn-ghost" name="template" value="member"><?= e(t('perm_template')) ?>: <?= e(t('perm_tpl_member')) ?></button>
              <button class="btn btn-ghost" name="template" value="ersatz"><?= e(t('perm_template')) ?>: <?= e(t('perm_tpl_ersatz')) ?></button>
            </div>
            <p class="muted small"><?= e(t('perm_tpl_hint')) ?></p>
          </form>
        </details>
      <?php else: ?>
        <p class="muted small">🔑 <?= e(t('perm_admin_all')) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<datalist id="instrument-list">
  <?php foreach ($instruments as $i): ?><option value="<?= e($i) ?>"><?php endforeach; ?>
</datalist>
<script src="/assets/strength.js" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
