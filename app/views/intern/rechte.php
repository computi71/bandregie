<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>🔑 <?= e(t('perm_title')) ?></h1>
<p class="muted"><?= e(t('perm_intro')) ?></p>
<p class="muted small"><?= e(t('perm_admin_all')) ?></p>

<?php foreach ($members as $pm): ?>
  <section class="card">
    <div class="event-head">
      <strong><?= e($pm['name']) ?></strong>
      <span class="badge <?= $pm['role'] === 'admin' ? 'public' : '' ?>"><?= e(t('role_' . ($pm['role'] === 'ersatz' ? 'ersatz' : ($pm['role'] === 'admin' ? 'admin' : 'member')))) ?></span>
    </div>
    <?php if ($pm['role'] === 'admin'): ?>
      <p class="muted small"><?= e(t('perm_admin_all')) ?></p>
    <?php else: ?>
      <?php $mine = $perms[(int) $pm['id']] ?? []; ?>
      <form method="post" action="/intern/rechte/<?= $pm['id'] ?>"><?= csrf_field() ?>
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
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
