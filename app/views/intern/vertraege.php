<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php // Die Verträge und die Veranstalter, die sie unterschreiben (#303).
      // Oben steht, was noch fehlt — danach fragt vier Wochen vor dem Auftritt
      // ohnehin jemand. ?>
<h1>✍ <?= e(t('contract_title')) ?></h1>
<p class="muted"><?= e(t('contract_intro')) ?></p>

<?php $darf = perm_allows($user, 'vertraege', 'write'); ?>

<?php if ($ohneVertrag): ?>
  <section class="card">
    <strong>⚠ <?= e(t('contract_open_missing')) ?></strong>
    <ul class="small">
      <?php foreach ($ohneVertrag as $ov): ?>
        <li><?= e(fmt_date($ov['date'])) ?> · <?= e($ov['title']) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($darf): ?>
<details class="card collapsible">
  <summary>➕ <?= e(t('contract_new')) ?></summary>
  <form method="post" action="/intern/vertraege" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('contract_event')) ?>
      <select name="event_id" required>
        <?php foreach ($events as $vEv): ?>
          <option value="<?= (int) $vEv['id'] ?>"><?= e(fmt_date($vEv['date'])) ?> · <?= e($vEv['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('contract_promoter')) ?>
      <select name="promoter_id">
        <option value=""><?= e(t('contract_promoter_new')) ?></option>
        <?php foreach ($promoters as $vP): ?>
          <option value="<?= (int) $vP['id'] ?>"><?= e($vP['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="span2"><?= e(t('promoter_name')) ?> (<?= e(t('contract_promoter_new')) ?>)
      <input name="new_promoter" maxlength="190">
      <span class="muted small"><?= e(t('contract_promoter_hint')) ?></span></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<?php if (!$contracts): ?>
  <p class="muted"><?= e(t('contract_none')) ?></p>
<?php endif; ?>

<?php foreach ($contracts as $c): ?>
  <section class="card">
    <div class="event-head">
      <strong><a href="/intern/vertraege/<?= (int) $c['id'] ?>"><?= e($c['event_title'] ?: t('contract_sheet_title')) ?></a></strong>
      <?= item_mark_html($unseen ?? [], (int) $c['id']) ?>
      <?php if ($c['promoter_name']): ?><span class="muted"><?= e($c['promoter_name']) ?></span><?php endif; ?>
      <span class="badge <?= $c['status'] === 'unterschrieben' ? 'public' : ($c['status'] === 'entwurf' ? '' : 'ev-abgesagt') ?>">
        <?= e(contract_status_label((string) $c['status'])) ?></span>
      <?php if ((int) $c['fee_cents'] > 0): ?><span class="badge"><?= e(fmt_money((int) $c['fee_cents'])) ?></span><?php endif; ?>
    </div>
    <p class="muted small">
      <?php if ($c['event_date']): ?>📅 <?= e(fmt_date($c['event_date'])) ?><?php endif; ?>
      <?php if ($c['contract_no'] !== ''): ?> · <?= e($c['contract_no']) ?><?php endif; ?>
      <?php if ($c['sent_at']): ?> · <?= e(t('contract_status_verschickt')) ?> <?= e(date('d.m.Y', strtotime($c['sent_at']))) ?><?php endif; ?>
      <?php if ($c['signed_at']): ?> · <?= e(t('contract_status_unterschrieben')) ?> <?= e(date('d.m.Y', strtotime($c['signed_at']))) ?><?php endif; ?>
    </p>
  </section>
<?php endforeach; ?>

<?php // Die Veranstalter selbst: angelegt wird meist beim ersten Vertrag, hier
      // werden Anschrift und Ansprechpartner nachgetragen. ?>
<details class="card acc">
  <summary>🏛 <?= e(t('promoter_title')) ?></summary>
  <?php if (!$promoters): ?><p class="muted"><?= e(t('promoter_none')) ?></p><?php endif; ?>
  <?php foreach ($promoters as $p): ?>
    <details class="subsection">
      <summary><?= e($p['name']) ?><?= $p['city'] !== '' ? ' · ' . e($p['city']) : '' ?></summary>
      <?php if ($darf): ?>
        <form method="post" action="/intern/vertraege/veranstalter/<?= (int) $p['id'] ?>/update" class="form-grid"><?= csrf_field() ?>
          <label><?= e(t('promoter_name')) ?><input name="name" value="<?= e($p['name']) ?>" required maxlength="190"></label>
          <label><?= e(t('promoter_contact')) ?><input name="contact_name" value="<?= e($p['contact_name']) ?>" maxlength="190"></label>
          <?php $kfWerte = $p; $kfFelder = ['email', 'phone', 'mobile', 'street', 'postcode', 'city']; $kfNamen = '';
                require BASE_DIR . '/app/views/_contact_fields.php'; ?>
          <?php // In welcher Sprache dieser Veranstalter seine Blätter bekommt (#363).
                // Leer heißt: die der Band. ?>
          <label><?= e(t('promoter_lang')) ?>
            <select name="lang">
              <option value=""><?= e(t('promoter_lang_band')) ?></option>
              <?php foreach (LANGS as $lCode => $lName): ?>
                <option value="<?= e($lCode) ?>" <?= ($p['lang'] ?? '') === $lCode ? 'selected' : '' ?>>
                  <?= e($lName) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="muted small"><?= e(t('promoter_lang_hint')) ?></span></label>
          <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e((string) $p['notes']) ?></textarea></label>
          <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
        </form>
      <?php endif; ?>
    </details>
  <?php endforeach; ?>
  <?php if ($darf): ?>
    <details class="subsection">
      <summary>➕ <?= e(t('promoter_title')) ?></summary>
      <form method="post" action="/intern/vertraege/veranstalter" class="form-grid"><?= csrf_field() ?>
        <label><?= e(t('promoter_name')) ?><input name="name" required maxlength="190"></label>
        <label><?= e(t('promoter_contact')) ?><input name="contact_name" maxlength="190"></label>
        <?php $kfWerte = []; $kfFelder = ['email', 'phone', 'mobile', 'street', 'postcode', 'city']; $kfNamen = '';
              require BASE_DIR . '/app/views/_contact_fields.php'; ?>
        <?php // In welcher Sprache dieser Veranstalter seine Blätter bekommt (#363).
              // Leer heißt: die der Band. ?>
        <label><?= e(t('promoter_lang')) ?>
          <select name="lang">
            <option value=""><?= e(t('promoter_lang_band')) ?></option>
            <?php foreach (LANGS as $lCode => $lName): ?>
              <option value="<?= e($lCode) ?>" <?= ('') === $lCode ? 'selected' : '' ?>>
                <?= e($lName) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="muted small"><?= e(t('promoter_lang_hint')) ?></span></label>
        <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"></textarea></label>
        <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('create')) ?></button></div>
      </form>
    </details>
  <?php endif; ?>
</details>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
