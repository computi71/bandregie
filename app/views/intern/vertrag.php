<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Ein Vertrag (#303): oben der Stand und die Knöpfe, darunter die Angaben und
// der Wortlaut.
//
// Der Wortlaut ist nur änderbar, solange nichts verschickt ist. Danach steht er
// so, wie er das Haus verlassen hat — was der Veranstalter unterschrieben hat,
// darf sich hier nicht nachträglich anders lesen.
$darf = perm_allows($user, 'vertraege', 'write');
$entwurf = $contract['status'] === 'entwurf';
?>
<h1>✍ <?= e($contract['event_title'] ?: t('contract_sheet_title')) ?></h1>
<p class="muted">
  <a href="/intern/vertraege">← <?= e(t('contract_title')) ?></a>
  <?php if ($contract['event_date']): ?> · 📅 <?= e(fmt_date($contract['event_date'])) ?><?php endif; ?>
</p>

<section class="card">
  <div class="event-head">
    <span class="badge <?= $contract['status'] === 'unterschrieben' ? 'public' : ($entwurf ? '' : 'ev-abgesagt') ?>">
      <?= e(contract_status_label((string) $contract['status'])) ?></span>
    <?php if ((int) $contract['fee_cents'] > 0): ?>
      <span class="badge"><?= e(fmt_money((int) $contract['fee_cents'])) ?></span>
    <?php endif; ?>
    <?php if ($contract['promoter_name']): ?><span class="muted"><?= e($contract['promoter_name']) ?></span><?php endif; ?>
  </div>
  <p class="muted small">
    <?php if ($contract['sent_at']): ?><?= e(t('contract_status_verschickt')) ?>: <?= e(fmt_date(substr((string) $contract['sent_at'], 0, 10))) ?><?php endif; ?>
    <?php if ($contract['signed_at']): ?> · <?= e(t('contract_status_unterschrieben')) ?>: <?= e(fmt_date(substr((string) $contract['signed_at'], 0, 10))) ?><?php endif; ?>
  </p>
  <div class="row-buttons">
    <a class="btn btn-small" href="/intern/vertraege/<?= (int) $contract['id'] ?>/druck" target="_blank" rel="noopener">🖨 <?= e(t('contract_print')) ?></a>
    <?php if ($darf): ?>
      <?php // Nur der jeweils nächste Schritt, und der Rückweg auf Entwurf.
            // Drei gleichrangige Knöpfe laden dazu ein, den falschen zu treffen. ?>
      <?php foreach (['verschickt' => 'contract_mark_sent', 'unterschrieben' => 'contract_mark_signed',
                      'entwurf' => 'contract_mark_draft'] as $vStand => $vKey): ?>
        <?php if ($vStand === $contract['status']) continue; ?>
        <form class="inline" method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/stand"><?= csrf_field() ?>
          <input type="hidden" name="status" value="<?= e($vStand) ?>">
          <button class="btn btn-small btn-ghost"><?= e(t($vKey)) ?></button>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <p class="muted small">📎 <?= e(t('contract_signed_file')) ?></p>
</section>

<?php if ($darf): ?>
<form method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/update" class="card form-grid"><?= csrf_field() ?>
  <label><?= e(t('contract_event')) ?>
    <select name="event_id">
      <?php foreach ($events as $vEv): ?>
        <option value="<?= (int) $vEv['id'] ?>" <?= (int) $contract['event_id'] === (int) $vEv['id'] ? 'selected' : '' ?>>
          <?= e(fmt_date($vEv['date'])) ?> · <?= e($vEv['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('contract_promoter')) ?>
    <select name="promoter_id">
      <?php foreach ($promoters as $vP): ?>
        <option value="<?= (int) $vP['id'] ?>" <?= (int) $contract['promoter_id'] === (int) $vP['id'] ? 'selected' : '' ?>>
          <?= e($vP['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('contract_no')) ?><input name="contract_no" value="<?= e($contract['contract_no']) ?>" maxlength="60"></label>
  <label><?= e(t('contract_date')) ?><input type="date" name="contract_date" value="<?= e($contract['contract_date']) ?>"></label>
  <label><?= e(t('contract_fee')) ?>
    <input name="fee" value="<?= e(number_format((int) $contract['fee_cents'] / 100, 2, ',', '')) ?>">
    <span class="muted small"><?= e(t('contract_fee_hint')) ?></span></label>
  <label><?= e(t('contract_get_in')) ?><input name="get_in" value="<?= e($contract['get_in']) ?>" placeholder="16:00"></label>
  <label><?= e(t('contract_play_from')) ?><input name="play_from" value="<?= e($contract['play_from']) ?>" placeholder="20:00"></label>
  <label><?= e(t('contract_play_to')) ?><input name="play_to" value="<?= e($contract['play_to']) ?>" placeholder="23:00"></label>

  <label class="span2"><?= e(t('contract_body')) ?>
    <textarea name="body" rows="18" class="prewrap" <?= $entwurf ? '' : 'readonly' ?>><?= e((string) $contract['body']) ?></textarea>
    <span class="muted small"><?= e(t('contract_body_hint')) ?></span></label>
  <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e((string) $contract['notes']) ?></textarea></label>
  <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
</form>

<div class="row-buttons">
  <?php if ($entwurf): ?>
    <form class="inline" method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/neu-bilden"
          data-confirm="<?= e(t('contract_rebuild_confirm')) ?>"><?= csrf_field() ?>
      <button class="btn btn-small btn-ghost">↺ <?= e(t('contract_rebuild')) ?></button>
    </form>
  <?php endif; ?>
  <form class="inline" method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/delete"
        data-confirm="<?= e(t('contract_delete_confirm')) ?>"><?= csrf_field() ?>
    <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
  </form>
</div>
<?php endif; ?>

<?php // Wen die Band von außen zu diesem Vertrag geholt hat (#309). Ein
      // Bookingagent sieht sonst nur, was er selbst angelegt hat. ?>
<?php if (!is_outsider($user) && ($outsideAccounts ?? [])): ?>
  <details class="card acc">
    <summary>👤 <?= e(t('contract_guests')) ?><?= $outsiders ? ' (' . count($outsiders) . ')' : '' ?></summary>
    <p class="muted small"><?= e(t('contract_guests_hint')) ?></p>
    <?php if (!$outsiders): ?><p class="muted small"><?= e(t('contract_guests_none')) ?></p><?php endif; ?>
    <?php foreach ($outsiders as $ou): ?>
      <p class="row-buttons">
        <span><?= e($ou['name']) ?></span>
        <?php if ($darf): ?>
          <form class="inline" method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/gast"><?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $ou['id'] ?>">
            <input type="hidden" name="do" value="remove">
            <button class="btn btn-tiny btn-danger"><?= e(t('topic_guest_remove')) ?></button>
          </form>
        <?php endif; ?>
      </p>
    <?php endforeach; ?>
    <?php if ($darf): ?>
      <form method="post" action="/intern/vertraege/<?= (int) $contract['id'] ?>/gast" class="row-buttons"><?= csrf_field() ?>
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
