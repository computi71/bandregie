<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('task_title')) ?></h1>

<div class="card">
  <form method="post" action="/intern/aufgaben" class="form-grid">
    <label><?= e(t('task_lbl')) ?><input name="title" required placeholder="<?= e(t('task_ph')) ?>"></label>
    <label><?= e(t('task_who')) ?>
      <select name="assigned_to"><option value="">–</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('task_due')) ?><input type="date" name="due_date"></label>
    <label class="span2"><?= e(t('task_details')) ?><textarea name="notes" rows="2"></textarea></label>
    <button class="btn btn-primary span2"><?= e(t('task_add')) ?></button>
  </form>
</div>

<div class="card">
  <ul class="task-list">
    <?php foreach ($tasks as $task): ?>
      <li class="<?= $task['status'] === 'erledigt' ? 'done' : '' ?>">
        <form class="inline" action="/intern/aufgaben/<?= $task['id'] ?>/toggle" method="post">
          <button class="check" title="<?= e(t('task_toggle')) ?>"><?= $task['status'] === 'erledigt' ? '☑' : '☐' ?></button>
        </form>
        <strong><?= e($task['title']) ?></strong>
        <?php if ($task['assignee']): ?><span class="muted">→ <?= e($task['assignee']) ?></span><?php endif; ?>
        <?php if ($task['due_date']): ?><span class="muted"><?= e(t('due_until')) ?> <?= fmt_date($task['due_date']) ?></span><?php endif; ?>
        <?php if ($task['notes']): ?><div class="muted small prewrap"><?= e($task['notes']) ?></div><?php endif; ?>
        <form class="inline" action="/intern/aufgaben/<?= $task['id'] ?>/delete" method="post" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')"><button class="btn btn-tiny btn-danger">🗑</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$tasks): ?><p class="muted center"><?= e(t('task_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
