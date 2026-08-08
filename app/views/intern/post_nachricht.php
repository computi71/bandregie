<?php require BASE_DIR . '/app/views/_header.php'; ?>
<p><a class="btn btn-ghost btn-small" href="/intern/post">← <?= e(t('post_back')) ?></a></p>
<h1>✉ <?= e($msg['subject'] !== '' ? $msg['subject'] : t('post_title')) ?></h1>

<div class="card">
  <p class="muted small">
    <strong><?= e(t('post_from')) ?>:</strong>
    <?= e($msg['from_name'] !== '' ? $msg['from_name'] . ' · ' : '') ?><?= e($msg['from_mail']) ?>
    <?php if ($msg['sent_at']): ?> · <?= e(fmt_date(substr((string) $msg['sent_at'], 0, 10))) ?><?php endif; ?>
  </p>
  <?php // Der Text steht als Text da, nicht als HTML: Was ein Fremder schickt,
        // wird in dieser Anwendung nicht als Auszeichnung ausgeführt. ?>
  <p class="prewrap"><?= e($msg['body_text']) ?></p>
  <form method="post" action="/intern/post/<?= (int) $msg['id'] ?>/archiv" class="inline"><?= csrf_field() ?>
    <button class="btn btn-tiny btn-ghost">📦 <?= e($msg['archived_at'] === null ? t('post_archive') : t('post_unarchive')) ?></button>
  </form>
</div>

<?php if ($msg['event_id'] !== null): ?>
  <div class="card">
    <p>📅 <strong><?= e(t('post_event_linked')) ?></strong>
      <a href="/intern/termine"><?= e($msg['event_title'] ?? '') ?></a>
      <?php if ($msg['event_date']): ?><span class="muted"><?= e(fmt_date($msg['event_date'])) ?></span><?php endif; ?></p>
  </div>
<?php else: ?>
  <?php // Der Vorschlag füllt nur vor. Zu jedem Fund steht, woher er kommt —
        // wer die Stelle im Text wiederfindet, kann ihn beurteilen; eine Zahl
        // ohne Herkunft muss man glauben (#219). ?>
  <div class="card">
    <h2>📅 <?= e(t('post_proposal')) ?></h2>
    <p class="muted small"><?= e(t('post_proposal_hint')) ?></p>
    <?php $leer = $vorschlag['date'] === null && !$vorschlag['times']
                  && $vorschlag['fee'] === null && $vorschlag['place'] === null; ?>
    <?php if ($leer): ?><p class="muted small">💡 <?= e(t('post_nothing_found')) ?></p><?php endif; ?>
    <form method="post" action="/intern/post/<?= (int) $msg['id'] ?>/termin" class="form-grid"><?= csrf_field() ?>
      <label class="span2"><?= e(t('name')) ?>
        <input name="title" required maxlength="255"
               value="<?= e($msg['subject'] !== '' ? $msg['subject'] : ($msg['from_name'] ?: $msg['from_mail'])) ?>"></label>
      <label><?= e(t('date')) ?>
        <input type="date" name="date" required value="<?= e((string) $vorschlag['date']) ?>">
        <?php if (isset($vorschlag['evidence']['date'])): ?>
          <span class="muted small"><?= e(t('post_evidence')) ?> „<?= e($vorschlag['evidence']['date']) ?>"</span>
        <?php endif; ?>
      </label>
      <label><?= e(t('ev_type')) ?>
        <select name="type">
          <option value="gig"><?= e(event_type_label('gig')) ?></option>
          <option value="probe"><?= e(event_type_label('probe')) ?></option>
        </select></label>
      <label><?= e(t('ev_start')) ?>
        <input type="time" name="time" value="<?= e($vorschlag['times'][0] ?? '') ?>"></label>
      <label><?= e(t('ev_end')) ?>
        <input type="time" name="time_end" value="<?= e($vorschlag['times'][1] ?? '') ?>"></label>
      <label class="span2"><?= e(t('ev_venue')) ?>
        <input name="location" maxlength="255" value="<?= e((string) $vorschlag['place']) ?>"></label>
      <label><?= e(t('ev_fee')) ?>
        <input name="fee" maxlength="100" value="<?= $vorschlag['fee'] !== null ? e(fmt_money($vorschlag['fee'])) : '' ?>">
        <?php if (isset($vorschlag['evidence']['fee'])): ?>
          <span class="muted small"><?= e(t('post_evidence')) ?> „<?= e($vorschlag['evidence']['fee']) ?>"</span>
        <?php endif; ?>
      </label>
      <?php if (count($vorschlag['dates_found']) > 1): ?>
        <p class="muted small span2">📆 <?= e(t('post_more_dates')) ?>
          <?= e(implode(' · ', array_slice($vorschlag['dates_found'], 1, 6))) ?></p>
      <?php endif; ?>
      <?php // Die Anfrage wandert als Notiz mit: Nächstes Jahr fragt jemand,
            // was genau zugesagt war. ?>
      <label class="span2"><?= e(t('ev_notes')) ?>
        <textarea name="notes" rows="4"><?= e("Aus der Anfrage von " . ($msg['from_name'] ?: $msg['from_mail'])
          . ($msg['sent_at'] ? ' vom ' . fmt_date(substr((string) $msg['sent_at'], 0, 10)) : '') . ":\n\n"
          . mb_substr((string) $msg['body_text'], 0, 2000)) ?></textarea></label>
      <div class="span2"><button class="btn btn-primary"><?= e(t('post_make_event')) ?></button></div>
    </form>
  </div>
<?php endif; ?>

<?php // Anhänge stehen zwischen Nachricht und Antwort: Ein Bühnenplan gehört zum
      // Termin, nicht in irgendeinen Posteingang (#19). Geholt wird auf Klick. ?>
<?php if ($attachments): ?>
  <div class="card">
    <h2>📎 <?= e(t('post_attachments')) ?></h2>
    <p class="muted small"><?= e(t('post_attach_hint')) ?></p>
    <?php if ($msg['event_id'] === null): ?>
      <p class="muted small">💡 <?= e(t('post_attach_need_event')) ?></p>
    <?php endif; ?>
    <ul class="task-list">
      <?php foreach ($attachments as $anhang): ?>
        <li>
          <strong><?= e($anhang['name']) ?></strong>
          <span class="muted small"><?= e($anhang['mime']) ?> · <?= e(fmt_bytes((int) $anhang['size_bytes'])) ?></span>
          <?php if ($anhang['taken_at'] !== null): ?>
            <span class="badge">✔ <?= e(t('post_attach_taken')) ?></span>
          <?php elseif ($msg['event_id'] !== null): ?>
            <form method="post" action="/intern/post/<?= (int) $msg['id'] ?>/anhang/<?= (int) $anhang['id'] ?>" class="inline"><?= csrf_field() ?>
              <button class="btn btn-tiny">📥 <?= e(t('post_attach_take')) ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <h2>↩ <?= e(t('post_reply')) ?></h2>
  <?php // Der Empfänger steht fest und kommt aus der Nachricht — er ist kein
        // Formularfeld, sonst wäre das ein Versandweg für beliebige Adressen. ?>
  <p class="muted small"><?= e(t('post_from')) ?>: <?= e($msg['from_mail']) ?></p>
  <form method="post" action="/intern/post/<?= (int) $msg['id'] ?>/antwort" class="stack"><?= csrf_field() ?>
    <textarea name="body" rows="8" required><?= e("\n\n---\n"
      . ($msg['from_name'] ?: $msg['from_mail']) . " schrieb:\n"
      . preg_replace('~^~m', '> ', mb_substr((string) $msg['body_text'], 0, 1500))) ?></textarea>
    <div><button class="btn btn-primary"><?= e(t('post_reply_send')) ?></button></div>
  </form>

  <?php if ($replies): ?>
    <h3><?= e(t('post_replies')) ?></h3>
    <ul class="task-list">
      <?php foreach ($replies as $r): ?>
        <li>
          <span class="muted small"><?= e($r['sent_at']) ?> · <?= e($r['sender'] ?? '') ?> → <?= e($r['to_mail']) ?></span>
          <p class="prewrap small"><?= e(mb_substr((string) $r['body'], 0, 400)) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require BASE_DIR . '/app/views/_footer.php'; ?>
