<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e(t('task_title')) ?></h1>

<?php
// Anlegen und Ändern benutzen dasselbe Formular — einmal hier, damit ein neues
// Feld nicht an zwei Stellen nachgezogen werden muss (#334).
$taskFormular = static function (array $members, ?array $task, array $wer): void {
  $gewaehlt = array_map(static fn(array $a): int => (int) $a['user_id'], $wer);
  ?>
  <label class="span2"><?= e(t('task_lbl')) ?>
    <input name="title" required placeholder="<?= e(t('task_ph')) ?>" value="<?= e($task['title'] ?? '') ?>">
  </label>
  <?php // gear-picker statt einer eigenen Klasse: Dieselbe Sache — eine Liste
        // zum Ankreuzen in einem Formular — hat schon ein Aussehen, und ein
        // zweites dafür wäre nur eine Stelle mehr, die man vergisst. ?>
  <fieldset class="span2 gear-picker">
    <legend><?= e(t('task_who')) ?></legend>
    <?php foreach ($members as $mg): ?>
      <label class="checkbox">
        <input type="checkbox" name="assignees[]" value="<?= (int) $mg['id'] ?>"
               <?= in_array((int) $mg['id'], $gewaehlt, true) ? 'checked' : '' ?>>
        <?= e($mg['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <label><?= e(t('task_needed')) ?>
    <select name="required_done">
      <option value="0"><?= e(t('task_needed_all')) ?></option>
      <?php for ($i = 1; $i <= count($members); $i++): ?>
        <option value="<?= $i ?>" <?= (int) ($task['required_done'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label><?= e(t('task_due')) ?><input type="date" name="due_date" value="<?= e($task['due_date'] ?? '') ?>"></label>
  <label class="span2"><?= e(t('task_details')) ?><textarea name="notes" rows="2"><?= e($task['notes'] ?? '') ?></textarea></label>
  <?php
};
?>

<details class="card collapsible" <?= $tasks ? '' : 'open' ?>>
  <summary>➕ <?= e(t('task_add')) ?></summary>
  <form method="post" action="/intern/aufgaben" class="form-grid"><?= csrf_field() ?>
    <?php $taskFormular($members, null, []); ?>
    <button class="btn btn-primary span2"><?= e(t('task_add')) ?></button>
  </form>
</details>

<div class="card">
  <ul class="task-list">
    <?php foreach ($tasks as $task): ?>
      <?php
        $wer = $assigneesByTask[(int) $task['id']] ?? [];
        $fertig = array_values(array_filter($wer, static fn(array $a): bool => $a['done_at'] !== null));
        // Dieselbe Deckelung wie in task_status_apply(): Steht dort eine 3 und
        // es sind nur zwei zuständig, wären „2 von 3" eine Zahl, die nie käme.
        $noetig = (int) $task['required_done'] > 0
          ? min((int) $task['required_done'], count($wer))
          : count($wer);
        $meinHaken = false;
        foreach ($wer as $a) {
          if ((int) $a['user_id'] === (int) $user['id'] && $a['done_at'] !== null) $meinHaken = true;
        }
      ?>
      <li class="<?= $task['status'] === 'erledigt' ? 'done' : '' ?>">
        <form class="inline" action="/intern/aufgaben/<?= (int) $task['id'] ?>/toggle" method="post"><?= csrf_field() ?>
          <button class="check" title="<?= e(t('task_toggle')) ?>"><?= $meinHaken ? '☑' : '☐' ?></button>
        </form>
        <strong><?= e($task['title']) ?></strong>
        <?= item_mark_html($unseenTasks ?? [], (int) $task['id']) ?>
        <?php if ($task['due_date']): ?><span class="muted"><?= e(t('due_until')) ?> <?= fmt_date($task['due_date']) ?></span><?php endif; ?>
        <?php if (!$wer): ?>
          <span class="muted small"><?= e(t('task_nobody')) ?></span>
        <?php else: ?>
          <span class="muted">→ <?= e(implode(', ', array_column($wer, 'name'))) ?></span>
          <?php // Die Zahl nur bei mehreren: Bei einem sagt „1 von 1" nichts,
                // was das Häkchen daneben nicht schon sagt. ?>
          <?php if (count($wer) > 1): ?>
            <span class="badge"><?= e(str_replace(['%1', '%2'], [(string) count($fertig), (string) $noetig], t('task_progress'))) ?></span>
          <?php endif; ?>
          <?php if ($fertig): ?>
            <span class="muted small">✔ <?= e(t('task_done_by')) ?> <?= e(implode(', ', array_column($fertig, 'name'))) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($task['notes']): ?><div class="muted small prewrap"><?= e($task['notes']) ?></div><?php endif; ?>
        <?php if (perm_allows($user, 'aufgaben', 'write')): ?>
          <details class="subsection">
            <summary>✏️ <?= e(t('task_edit')) ?></summary>
            <form method="post" action="/intern/aufgaben/<?= (int) $task['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
              <?php $taskFormular($members, $task, $wer); ?>
              <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <form class="inline" action="/intern/aufgaben/<?= (int) $task['id'] ?>/delete" method="post" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-tiny btn-danger">🗑</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$tasks): ?><p class="muted center"><?= e(t('task_none')) ?></p><?php endif; ?>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
