<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php // Die Liste der gerechneten Angebote (#302). Gerechnet und gepflegt wird
      // im Angebot selbst; hier steht nur, was es gibt und was es ergeben hat. ?>
<h1>🧮 <?= e(t(quote_ist_angebot() ? 'quote_title' : 'quote_calc_title')) ?></h1>
<p class="muted"><?= e(t(quote_ist_angebot() ? 'quote_intro' : 'quote_calc_intro')) ?></p>

<?php if ($ratesMissing): ?>
  <p class="warn small"><?= e(t('quote_rates_missing')) ?></p>
<?php endif; ?>

<?php if (perm_allows($user, 'angebote', 'write')): ?>
<details class="card collapsible" <?= !empty($vorgabeEvent) ? 'open' : '' ?>>
  <summary>➕ <?= e(t(quote_ist_angebot() ? 'quote_new' : 'quote_calc_new')) ?></summary>
  <form method="post" action="/intern/angebote" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('quote_event')) ?>
      <select name="event_id">
        <option value=""><?= e(t('quote_event_none')) ?></option>
        <?php foreach ($events as $qEv): ?>
          <option value="<?= (int) $qEv['id'] ?>" <?= (int) $qEv['id'] === (int) ($vorgabeEvent ?? 0) ? 'selected' : '' ?>><?= e(fmt_date($qEv['date'])) ?> · <?= e($qEv['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('title_lbl')) ?><input name="title" maxlength="190"></label>
    <label class="span2"><?= e(t('quote_customer')) ?>
      <input name="customer" maxlength="190" placeholder="<?= e(t('quote_customer_ph')) ?>"></label>
    <button class="btn btn-primary span2"><?= e(t('create')) ?></button>
  </form>
</details>
<?php endif; ?>

<?php if (!$quotes): ?>
  <p class="muted"><?= e(t(quote_ist_angebot() ? 'quote_none' : 'quote_calc_none')) ?></p>
<?php endif; ?>

<?php foreach ($quotes as $q): ?>
  <?php $s = $totals[(int) $q['id']]; ?>
  <section class="card">
    <div class="event-head">
      <strong><a href="/intern/angebote/<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a></strong>
      <?= item_mark_html($unseen ?? [], (int) $q['id']) ?>
      <?php if ($q['customer'] !== ''): ?><span class="muted"><?= e($q['customer']) ?></span><?php endif; ?>
      <span class="badge"><?= e(fmt_money($s['total'])) ?></span>
      <?php if ($s['discount'] > 0): ?>
        <span class="badge" title="<?= e(t('quote_discount_given')) ?>">− <?= e(fmt_money($s['discount'])) ?></span>
      <?php endif; ?>
    </div>
    <p class="muted small">
      <?= e(fmt_date($q['quote_date'])) ?>
      <?php if ($q['event_id']): ?> · 📅 <?= e(fmt_date($q['event_date'])) ?> <?= e($q['event_title']) ?><?php endif; ?>
      <?php if ((int) $q['play_minutes'] > 0): ?> · <?= e(quote_time_text((int) $q['play_minutes'])) ?><?php endif; ?>
    </p>
  </section>
<?php endforeach; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
