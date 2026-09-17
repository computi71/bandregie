<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1><?= $topic['closed'] ? '🔒 ' : '💬 ' ?><?= e($topic['title']) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/themen">← <?= e(t('topic_back')) ?></a>
    <form class="inline" method="post" action="/intern/themen/<?= $topic['id'] ?>/schliessen"><?= csrf_field() ?>
      <button class="btn btn-ghost"><?= e($topic['closed'] ? t('topic_reopen') : t('topic_close')) ?></button>
    </form>
    <?php if ((int) $topic['created_by'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
      <form class="inline" method="post" action="/intern/themen/<?= $topic['id'] ?>/delete" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
        <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<p class="muted small"><?= e(t('topic_by')) ?> <?= e($topic['author'] ?? t('unknown')) ?> · <?= e(substr($topic['created_at'], 0, 16)) ?></p>

<div class="card">
  <ul class="comment-list">
    <?php foreach ($posts as $post): ?>
      <li>
        <strong><?= e($post['author'] ?? t('unknown')) ?></strong>
        <span class="muted small"><?= e(substr($post['created_at'], 0, 16)) ?></span>
        <p class="prewrap"><?= e($post['text']) ?></p>
        <?php if ((int) $post['user_id'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
          <form class="inline" method="post" action="/intern/beitrag/<?= $post['id'] ?>/delete"><?= csrf_field() ?>
            <button class="btn btn-tiny btn-danger"><?= e(mb_strtolower(t('delete'))) ?></button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($topic['closed']): ?>
    <p class="warn small"><?= e(t('topic_closed_hint')) ?></p>
  <?php else: ?>
    <form method="post" action="/intern/themen/<?= $topic['id'] ?>/antwort" class="stack"><?= csrf_field() ?>
      <textarea name="text" rows="3" required placeholder="<?= e(t('topic_reply_ph')) ?>"></textarea>
      <button class="btn btn-primary"><?= e(t('topic_reply')) ?></button>
    </form>
  <?php endif; ?>
</div>

<?php // Wen die Band von außen dazugeholt hat (#308). Für Mitglieder ist das
      // eine Randnotiz; für den Bookingagenten ist es der Unterschied zwischen
      // „sieht das Thema" und „sieht es nicht". ?>
<?php if (!is_outsider($user) && ($outsideAccounts ?? [])): ?>
  <details class="card acc">
    <summary>👤 <?= e(t('topic_guests')) ?><?= $outsiders ? ' (' . count($outsiders) . ')' : '' ?></summary>
    <p class="muted small"><?= e(t('topic_guests_hint')) ?></p>
    <?php if (!$outsiders): ?><p class="muted small"><?= e(t('topic_guests_none')) ?></p><?php endif; ?>
    <?php foreach ($outsiders as $ou): ?>
      <p class="row-buttons">
        <span><?= e($ou['name']) ?></span>
        <?php if (perm_allows($user, 'themen', 'write')): ?>
          <form class="inline" method="post" action="/intern/themen/<?= (int) $topic['id'] ?>/gast"><?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $ou['id'] ?>">
            <input type="hidden" name="do" value="remove">
            <button class="btn btn-tiny btn-danger"><?= e(t('topic_guest_remove')) ?></button>
          </form>
        <?php endif; ?>
      </p>
    <?php endforeach; ?>
    <?php if (perm_allows($user, 'themen', 'write')): ?>
      <form method="post" action="/intern/themen/<?= (int) $topic['id'] ?>/gast" class="row-buttons"><?= csrf_field() ?>
        <select name="user_id">
          <?php foreach ($outsideAccounts as $oa): ?>
            <option value="<?= (int) $oa['id'] ?>"><?= e($oa['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-small"><?= e(t('topic_guest_add')) ?></button>
      </form>
    <?php endif; ?>
  </details>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
