<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Ein Angebot: links die Angaben, rechts das Ergebnis (#302).
//
// Die Posten aus der Preisliste stehen am Anfang der Liste und werden bei jedem
// Speichern neu gebildet; alles dahinter sind die von Hand eingetragenen
// Zeilen. Deshalb kommt $standardCount aus der Route — sonst müsste die Ansicht
// raten, welche Zeile sie zum Bearbeiten anbieten darf.
$freie = array_slice($items, $standardCount);
$darf  = perm_allows($user, 'angebote', 'write');
?>
<h1>🧮 <?= e($quote['title']) ?></h1>
<p class="muted">
  <a href="/intern/angebote">← <?= e(t('quote_title')) ?></a>
  <?php if ($quote['event_id']): ?> · 📅 <?= e(fmt_date($quote['event_date'])) ?> <?= e($quote['event_title']) ?><?php endif; ?>
</p>

<section class="card">
  <h2><?= e(t('quote_total')) ?>: <?= e(fmt_money($sums['total'])) ?></h2>
  <table class="table small">
    <?php foreach ($items as $p): ?>
      <tr><td><?= e($p['label']) ?></td><td class="num"><?= e(fmt_money((int) $p['amount_cents'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($sums['surcharge'] !== 0): ?>
      <tr><td><?= e($quote['surcharge_label'] !== '' ? $quote['surcharge_label'] : t('quote_surcharge')) ?></td>
          <td class="num"><?= e(fmt_money($sums['surcharge'])) ?></td></tr>
    <?php endif; ?>
    <?php if ($sums['discount'] > 0): ?>
      <tr><td><?= e(t('quote_list_price')) ?></td><td class="num"><?= e(fmt_money($sums['before_discount'])) ?></td></tr>
      <?php // Intern immer sichtbar, auch wenn das Angebot ihn verschweigt. ?>
      <tr><td><?= e(t('quote_discount_given')) ?><?= $quote['discount_label'] !== '' ? ' · ' . e($quote['discount_label']) : '' ?>
              <span class="muted">(<?= e((string) $sums['discount_percent']) ?> %)</span></td>
          <td class="num">− <?= e(fmt_money($sums['discount'])) ?></td></tr>
    <?php endif; ?>
    <tr><td><?= e(t('quote_net')) ?></td><td class="num"><?= e(fmt_money($sums['net'])) ?></td></tr>
    <?php if ($sums['vat_rate'] > 0): ?>
      <tr><td><?= e(sprintf(t('quote_vat'), $sums['vat_rate'])) ?></td><td class="num"><?= e(fmt_money($sums['vat'])) ?></td></tr>
    <?php endif; ?>
    <tr class="sum"><td><?= e(t('quote_total')) ?></td><td class="num"><?= e(fmt_money($sums['total'])) ?></td></tr>
  </table>

  <?php if ((int) setting('quote_min_cents') > 0 && $sums['net'] < (int) setting('quote_min_cents')): ?>
    <p class="warn small"><?= e(sprintf(t('quote_min_warn'), fmt_money((int) setting('quote_min_cents')))) ?></p>
  <?php endif; ?>

  <?php // Die Zahl, nach der in der Band als Erstes gefragt wird. Sie steht
        // nirgends im Angebot, sie ist für drinnen. ?>
  <?php if ($memberCount > 0): ?>
    <p class="muted small" title="<?= e(t('quote_per_member_hint')) ?>">
      <?= e(t('quote_per_member')) ?>: <?= e(fmt_money((int) floor($sums['net'] / $memberCount))) ?></p>
  <?php endif; ?>

  <div class="row-buttons">
    <a class="btn btn-small" href="/intern/angebote/<?= (int) $quote['id'] ?>/druck" target="_blank" rel="noopener">🖨 <?= e(t('quote_print')) ?></a>
    <?php if ($darf && $quote['event_id']): ?>
      <form class="inline" method="post" action="/intern/angebote/<?= (int) $quote['id'] ?>/gage"><?= csrf_field() ?>
        <button class="btn btn-small">💶 <?= e(t('quote_to_fee')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($darf): ?>
<form method="post" action="/intern/angebote/<?= (int) $quote['id'] ?>/update" class="card form-grid"><?= csrf_field() ?>
  <label><?= e(t('title_lbl')) ?><input name="title" value="<?= e($quote['title']) ?>" required maxlength="190"></label>
  <label><?= e(t('quote_customer')) ?><input name="customer" value="<?= e($quote['customer']) ?>" maxlength="190"
         placeholder="<?= e(t('quote_customer_ph')) ?>"></label>
  <label><?= e(t('quote_event')) ?>
    <select name="event_id">
      <option value=""><?= e(t('quote_event_none')) ?></option>
      <?php foreach ($events as $qEv): ?>
        <option value="<?= (int) $qEv['id'] ?>" <?= (int) $quote['event_id'] === (int) $qEv['id'] ? 'selected' : '' ?>>
          <?= e(fmt_date($qEv['date'])) ?> · <?= e($qEv['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('quote_date')) ?><input type="date" name="quote_date" value="<?= e($quote['quote_date']) ?>"></label>

  <label><?= e(t('quote_playtime')) ?><input type="number" name="play_minutes" min="0" step="5" value="<?= (int) $quote['play_minutes'] ?>">
    <span class="muted small"><?= e(t('quote_playtime_hint')) ?></span></label>
  <label><?= e(t('quote_km')) ?><input type="number" name="km" min="0" value="<?= (int) $quote['km'] ?>">
    <span class="muted small"><?= e(t('quote_km_hint')) ?></span></label>
  <label><?= e(t('quote_nights')) ?><input type="number" name="nights" min="0" value="<?= (int) $quote['nights'] ?>"></label>
  <label class="checkbox"><input type="checkbox" name="own_pa" value="1" <?= (int) $quote['own_pa'] === 1 ? 'checked' : '' ?>>
    <?= e(t('quote_own_pa')) ?></label>

  <fieldset class="pubfields span2">
    <legend><?= e(t('quote_extra')) ?></legend>
    <p class="muted small"><?= e(t('quote_extra_hint')) ?></p>
    <?php // Eine leere Zeile mehr, als es gibt — so lässt sich ohne Knopf und
          // ohne JavaScript etwas hinzufügen. ?>
    <?php for ($i = 0; $i <= count($freie); $i++): ?>
      <?php $fz = $freie[$i] ?? null; ?>
      <div class="row-fields">
        <input name="extra_label[]" maxlength="190" placeholder="<?= e(t('quote_extra_label')) ?>"
               value="<?= e($fz['label'] ?? '') ?>">
        <input name="extra_amount[]" placeholder="<?= e(t('quote_extra_amount')) ?>"
               value="<?= $fz ? e(number_format((int) $fz['amount_cents'] / 100, 2, ',', '')) : '' ?>">
      </div>
    <?php endfor; ?>
  </fieldset>

  <label><?= e(t('quote_surcharge_percent')) ?>
    <input name="surcharge_percent" value="<?= e(rtrim(rtrim(number_format((float) $quote['surcharge_percent'], 2, ',', ''), '0'), ',')) ?>"></label>
  <label><?= e(t('quote_surcharge_label')) ?>
    <input name="surcharge_label" value="<?= e($quote['surcharge_label']) ?>" maxlength="120"
           placeholder="<?= e(t('quote_surcharge_ph')) ?>"></label>

  <fieldset class="pubfields span2">
    <legend><?= e(t('quote_discount')) ?></legend>
    <label><?= e(t('quote_discount_mode')) ?>
      <select name="discount_mode">
        <?php foreach (['none' => 'quote_discount_none', 'percent' => 'quote_discount_as_percent',
                        'total' => 'quote_discount_as_total'] as $wert => $tkey): ?>
          <option value="<?= e($wert) ?>" <?= (string) $quote['discount_mode'] === $wert ? 'selected' : '' ?>><?= e(t($tkey)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('quote_discount_percent_lbl')) ?>
      <input name="discount_percent" value="<?= e(rtrim(rtrim(number_format((float) $quote['discount_percent'], 2, ',', ''), '0'), ',')) ?>"></label>
    <label><?= e(t('quote_discount_total_lbl')) ?>
      <input name="discount_total" placeholder="<?= e(fmt_money($sums['total'])) ?>">
      <span class="muted small"><?= e(t('quote_discount_total_hint')) ?></span></label>
    <label><?= e(t('quote_discount_label')) ?>
      <input name="discount_label" value="<?= e($quote['discount_label']) ?>" maxlength="120"
             placeholder="<?= e(t('quote_discount_ph')) ?>"></label>
    <label class="checkbox"><input type="checkbox" name="discount_show" value="1" <?= (int) $quote['discount_show'] === 1 ? 'checked' : '' ?>>
      <?= e(t('quote_discount_show')) ?></label>
    <p class="muted small"><?= e(t('quote_discount_show_hint')) ?></p>
  </fieldset>

  <label class="span2"><?= e(t('notes')) ?><textarea name="notes" rows="2"><?= e((string) $quote['notes']) ?></textarea></label>
  <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
</form>

<form method="post" action="/intern/angebote/<?= (int) $quote['id'] ?>/delete"
      data-confirm="<?= e(t('quote_delete_confirm')) ?>" class="inline"><?= csrf_field() ?>
  <button class="btn btn-danger btn-small"><?= e(t('delete')) ?></button>
</form>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
