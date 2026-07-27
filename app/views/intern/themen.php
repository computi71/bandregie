<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>💬 <?= e(t('inav_themen')) ?></h1>

<details class="card collapsible" <?= $topics ? '' : 'open' ?>>
  <summary>➕ <?= e(t('topic_new')) ?></summary>
  <form method="post" action="/intern/themen" class="form-grid"><?= csrf_field() ?>
    <label class="span2"><?= e(t('title_lbl')) ?><input name="title" required placeholder="<?= e(t('topic_title_ph')) ?>"></label>
    <label class="span2"><?= e(t('topic_first_post')) ?><textarea name="text" rows="3"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>

<?php foreach ($topics as $topic): ?>
  <div class="card setlist-row">
    <div>
      <a class="setlist-name" href="/intern/themen/<?= $topic['id'] ?>">
        <?= $topic['closed'] ? '🔒 ' : '' ?><?= e($topic['title']) ?>
      </a>
      <span class="muted small">
        <?= (int) $topic['posts'] ?> <?= e(t('topic_posts')) ?>
        · <?= e(t('topic_by')) ?> <?= e($topic['author'] ?? t('unknown')) ?>
        <?php if ($topic['last_post']): ?> · <?= e(t('topic_last')) ?> <?= e(substr($topic['last_post'], 0, 16)) ?><?php endif; ?>
        <?= $topic['closed'] ? ' · ' . e(t('topic_closed')) : '' ?>
      </span>
    </div>
    <div class="row-buttons">
      <a class="btn btn-small" href="/intern/themen/<?= $topic['id'] ?>"><?= e(t('topic_open')) ?></a>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$topics): ?><p class="muted center"><?= e(t('topic_none')) ?></p><?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
