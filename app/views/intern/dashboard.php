<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('dash_hello')) ?> <?= e($user['name']) ?>! 👋</h1>
<?php if (!empty($deadlines)): ?>
  <div class="card">
    <strong>⏰ <?= e(t('dash_deadlines')) ?>:</strong>
    <?php foreach ($deadlines as $dl): ?>
      <?php $late = $dl['due_date'] < date('Y-m-d'); ?>
      <span class="badge <?= $late ? 'ev-abgesagt' : '' ?>" style="margin-left:0.4rem">
        <?= e($dl['eq_name']) ?>: <?= e($dl['title']) ?> — <?= fmt_date($dl['due_date']) ?><?= $late ? ' ⚠ ' . e(t('eq_overdue')) : '' ?>
      </span>
    <?php endforeach; ?>
    <a class="btn btn-tiny btn-ghost" href="/intern/equipment" style="margin-left:0.5rem">→</a>
  </div>
<?php endif; ?>
<div class="grid-2">
  <?php if (perm_allows($user, 'termine')): ?>
  <section class="card">
    <h2><?= e(t('dash_next_events')) ?></h2>
    <?php if (!$events): ?><p class="muted"><?= e(t('dash_no_events')) ?> <a href="/intern/termine"><?= e(t('dash_create_event')) ?></a></p><?php endif; ?>
    <ul class="event-list">
      <?php foreach ($events as $ev): ?>
        <li>
          <span class="badge <?= e($ev['type']) ?>"><?= EVENT_TYPES[$ev['type']] ?? e($ev['type']) ?></span>
          <span class="event-date"><?= fmt_date($ev['date']) ?><?= $ev['time'] ? ' · ' . e($ev['time']) : '' ?></span>
          <strong><?= e($ev['title']) ?></strong>
          <?php if ($ev['location']): ?><span class="muted"><?= e($ev['location']) ?></span><?php endif; ?>
          <form class="inline attendance" action="/intern/termine/<?= $ev['id'] ?>/zusage" method="post"><?= csrf_field() ?>
            <button name="status" value="yes" class="chip <?= ($mine[$ev['id']] ?? '') === 'yes' ? 'chip-yes' : '' ?>"><?= e(t('att_yes')) ?></button>
            <button name="status" value="maybe" class="chip <?= ($mine[$ev['id']] ?? '') === 'maybe' ? 'chip-maybe' : '' ?>"><?= e(t('att_maybe')) ?></button>
            <button name="status" value="no" class="chip <?= ($mine[$ev['id']] ?? '') === 'no' ? 'chip-no' : '' ?>"><?= e(t('att_no')) ?></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="btn" href="/intern/termine"><?= e(t('dash_all_events')) ?></a>
  </section>
  <?php endif; ?>
  <?php if (perm_allows($user, 'aufgaben')): ?>
  <section class="card">
    <h2><?= e(t('dash_open_tasks')) ?></h2>
    <?php if (!$tasks): ?><p class="muted"><?= e(t('dash_nothing_open')) ?></p><?php endif; ?>
    <ul class="task-list">
      <?php foreach ($tasks as $task): ?>
        <li>
          <form class="inline" action="/intern/aufgaben/<?= $task['id'] ?>/toggle" method="post"><?= csrf_field() ?><button class="check" title="OK">☐</button></form>
          <strong><?= e($task['title']) ?></strong>
          <?php if ($task['assignee']): ?><span class="muted">→ <?= e($task['assignee']) ?></span><?php endif; ?>
          <?php if ($task['due_date']): ?><span class="muted"><?= e(t('due_until')) ?> <?= fmt_date($task['due_date']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="btn" href="/intern/aufgaben"><?= e(t('dash_all_tasks')) ?></a>
  </section>
  <?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
