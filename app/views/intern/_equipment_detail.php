<?php
/**
 * Alles, was ein Gerät zum Bearbeiten braucht: Fristen anlegen, Anhänge,
 * Formular. Erwartet $detailEq und den üblichen Zusammenhang ($members,
 * $items, $filesByEq, $user).
 *
 * Der Block steht nicht in der Liste, sondern wird nachgeladen, sobald jemand
 * ein Gerät aufklappt. In der Liste stand er hundertfach im Quelltext — allein
 * die Auswahl des übergeordneten Geräts führt jedes Gerät noch einmal auf, und
 * das machte zwei Drittel der Seite aus, die niemand zu sehen bekam.
 */
?>
<details class="subsection">
  <summary>⏰ <?= e(t('eq_deadline_new')) ?></summary>
  <p class="muted small"><?= e(t('eq_done_hint')) ?></p>
  <form method="post" action="/intern/equipment/<?= (int) $detailEq['id'] ?>/frist" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('title_lbl')) ?><input name="title" required placeholder="<?= e(t('eq_deadline_title_ph')) ?>"></label>
    <label><?= e(t('eq_due')) ?><input type="date" name="due_date" required></label>
    <label><?= e(t('eq_interval')) ?>
      <select name="interval_months">
        <option value="0"><?= e(t('eq_interval_0')) ?></option>
        <option value="6"><?= e(t('eq_interval_6')) ?></option>
        <option value="12" selected><?= e(t('eq_interval_12')) ?></option>
        <option value="24"><?= e(t('eq_interval_24')) ?></option>
      </select>
    </label>
    <label><?= e(t('notes')) ?><input name="notes"></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<?php $attachFiles = $filesByEq[$detailEq['id']] ?? []; $attachType = 'equipment'; $attachId = $detailEq['id'];
      require BASE_DIR . '/app/views/_dateien.php'; ?>

<details class="subsection" open>
  <summary>✏️ <?= e(t('edit')) ?></summary>
  <?php $formEq = $detailEq; require BASE_DIR . '/app/views/intern/_equipment_form.php'; ?>
</details>
