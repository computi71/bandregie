<?php require BASE_DIR . '/app/views/_header.php'; ?>
<div class="page-head">
  <h1><?= $topic['closed'] ? '🔒 ' : '💬 ' ?><?= e($topic['title']) ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="/intern/themen">← <?= e(t('topic_back')) ?></a>
    <form class="inline" method="post" action="/intern/themen/<?= $topic['id'] ?>/schliessen"><?= csrf_field() ?>
      <button class="btn btn-ghost"><?= e($topic['closed'] ? t('topic_reopen') : t('topic_close')) ?></button>
    </form>
    <?php if ((int) $topic['created_by'] === (int) $user['id'] || $user['role'] === 'admin'): ?>
      <form class="inline" method="post" action="/intern/themen/<?= $topic['id'] ?>/delete" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><?= csrf_field() ?>
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
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
