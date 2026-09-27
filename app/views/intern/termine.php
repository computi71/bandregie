<?php
require BASE_DIR . '/app/views/_header.php';
?>
<?php
// Die Schalter erhalten sich gegenseitig: Wer die Abgesagten eingeblendet hat
// und dann „auch vergangene" drückt, will nicht beides von vorn einstellen.
$evLink = function (array $anders) use ($showPast, $showCancelled, $evArt): string {
  $q = array_filter([
    'alle' => $showPast ? '1' : '',
    'abgesagt' => $showCancelled ? '1' : '',
    'art' => $evArt,
  ], 'strlen');
  foreach ($anders as $k => $v) {
    if ($v === '') unset($q[$k]); else $q[$k] = $v;
  }
  return '/intern/termine' . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="page-head">
  <h1><?= e(t('inav_termine')) ?></h1>
  <?php // Eine Zeile, die sagt, was man vor sich hat — und was gerade nicht. ?>
  <p class="muted small">
    <?php
      $evTeile = [str_replace('%1', (string) count($events), t('ev_count'))];
      if ($requestedCount > 0) $evTeile[] = str_replace('%1', (string) $requestedCount, t('ev_count_requested'));
      if (!$showCancelled && $cancelledCount > 0) {
        $evTeile[] = str_replace('%1', (string) $cancelledCount, t('ev_count_cancelled'));
      }
      echo e(implode(' · ', $evTeile));
    ?>
  </p>
  <div class="row-buttons">
    <a class="btn btn-ghost" href="<?= e($evLink(['alle' => $showPast ? '' : '1'])) ?>"><?= e($showPast ? t('ev_only_upcoming') : t('ev_also_past')) ?></a>
    <?php if ($showCancelled): ?>
      <a class="btn btn-ghost" href="<?= e($evLink(['abgesagt' => ''])) ?>">🚫 <?= e(t('ev_hide_cancelled')) ?></a>
    <?php elseif ($cancelledCount > 0): ?>
      <a class="btn btn-ghost" href="<?= e($evLink(['abgesagt' => '1'])) ?>">🚫 <?= e(str_replace('%1', (string) $cancelledCount, t('ev_show_cancelled'))) ?></a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="/intern/kalender">📅 <?= e(t('ev_cal_abo')) ?></a>
    <?php // Die Ausfuhr nimmt dieselben Schalter mit: Erst filtern und dann
          // alles herunterladen waere eine Falle (#372). ?>
    <?php $evFrage = (string) parse_url($evLink([]), PHP_URL_QUERY); ?>
    <a class="btn btn-ghost" href="/intern/termine/export<?= $evFrage !== '' ? '?' . e($evFrage) : '' ?>">⬇ <?= e(t('ev_export')) ?></a>
  </div>

  <?php // Nach Terminart filtern (#372). Eine Auswahlliste und keine Knopfreihe:
        // Es gibt zehn Arten, und zehn Knöpfe nebeneinander verdecken die zwei
        // Schalter darüber. Angeboten wird nur, was wirklich vorkommt — wer nie
        // aufnimmt, soll nicht an „Aufnahme-Session" vorbeilesen müssen.
        //
        // Ein Formular mit GET, damit der Filter in der Adresse steht: So lässt
        // er sich als Lesezeichen ablegen und überlebt das Zurückgehen. Die
        // beiden anderen Schalter reisen als verstecktes Feld mit. ?>
  <?php if ($evArten): ?>
    <form method="get" action="/intern/termine" class="row-buttons">
      <?php if ($showPast): ?><input type="hidden" name="alle" value="1"><?php endif; ?>
      <?php if ($showCancelled): ?><input type="hidden" name="abgesagt" value="1"><?php endif; ?>
      <label><?= e(t('ev_filter_type')) ?>
      <select name="art" data-autosubmit>
        <option value=""><?= e(str_replace('%1', (string) array_sum($evArten), t('ev_filter_all'))) ?></option>
        <?php foreach (EVENT_TYPES as $artKey => $artLabel): ?>
          <?php if (!isset($evArten[$artKey])) continue; ?>
          <option value="<?= e($artKey) ?>" <?= $evArt === $artKey ? 'selected' : '' ?>>
            <?= e(event_type_label($artKey)) ?> (<?= (int) $evArten[$artKey] ?>)</option>
        <?php endforeach; ?>
      </select></label>
      <?php // Ohne JavaScript bleibt der Knopf der Weg. Mit JavaScript ist er
            // überflüssig und verschwindet — siehe assets/actions.js. ?>
      <button class="btn btn-small" data-autosubmit-hide><?= e(t('ev_filter_apply')) ?></button>
    </form>
  <?php endif; ?>
</div>

<details class="card collapsible" <?= $events ? '' : 'open' ?>>
  <summary>➕ <?= e(t('ev_new')) ?></summary>
  <form method="post" action="/intern/termine" class="form-grid" data-eventfields='<?= e(json_encode(EVENT_TYPE_FIELDS)) ?>'><?= csrf_field() ?>
    <label><?= e(t('ev_type')) ?>
      <select name="type"><?php foreach (EVENT_TYPES as $val => $lbl): ?><option value="<?= $val ?>"><?= e(event_type_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('name')) ?><input name="title" required placeholder="<?= e(t('ev_name_ph')) ?>"></label>
    <label><?= e(t('date')) ?><input type="date" name="date" required></label>
    <label><?= e(t('status')) ?>
      <select name="status"><?php foreach (EVENT_STATUS as $val => $lbl): ?><option value="<?= $val ?>"><?= e(event_status_label($val)) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eventfield="times"><?= e(t('ev_meet')) ?><input type="time" name="time_meet"></label>
    <label data-eventfield="times"><?= e(t('ev_start')) ?><input type="time" name="time"></label>
    <label data-eventfield="times"><?= e(t('ev_end')) ?><input type="time" name="time_end"></label>
    <label data-eventfield="venue"><?= e(t('ev_venue')) ?>
      <select name="venue_id"><option value="">–</option><?php foreach ($venues as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['name']) ?><?= $v['city'] ? ' (' . e($v['city']) . ')' : '' ?></option><?php endforeach; ?></select>
    </label>
    <label data-eventfield="venue"><?= e(t('ev_location_free')) ?><input name="location" placeholder="<?= e(t('ev_location_free_ph')) ?>"></label>
    <label data-eventfield="setlist"><?= e(t('ev_setlist')) ?>
      <select name="setlist_id"><option value="">–</option><?php foreach ($setlists as $sl): ?><option value="<?= $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label data-eventfield="support"><?= e(t('ev_support')) ?><input name="support_act" maxlength="255" placeholder="<?= e(t('ev_support_ph')) ?>"></label>
    <label><?= e(t('ev_responsible')) ?>
      <select name="responsible_id"><option value="">–</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
    </label>
    <?php $prodEv = null; $gearSel = []; require BASE_DIR . '/app/views/intern/_event_production.php'; ?>
    <label data-eventfield="fee"><?= e(t('ev_fee')) ?><input name="fee" placeholder="<?= e(t('ev_fee_ph')) ?>"></label>
    <label data-eventfield="fee"><?= e(t('ev_invoice')) ?><input name="invoice_no"></label>
    <label class="span2"><?= e(t('ev_notes')) ?><textarea name="notes" rows="2" placeholder="<?= e(t('ev_notes_ph')) ?>"></textarea></label>
    <?php // Ohne öffentliche Seite gibt es nichts zu veröffentlichen (#288).
          // Ein neuer Termin hat noch keine Werte, die zu erhalten wären. ?>
    <?php if (public_page_active()): ?>
    <fieldset class="span2 pubfields" data-eventfield="public">
      <legend><?= e(t('ev_public_display')) ?></legend>
      <label class="checkbox"><input type="checkbox" name="is_public" value="1"> <?= e(t('ev_show_on_site')) ?></label>
      <label><?= e(t('ev_public_title')) ?><input name="public_title" placeholder="<?= e(t('ev_public_title_ph')) ?>"></label>
      <label><?= e(t('ev_public_link')) ?><input name="public_link" placeholder="https://..."></label>
      <label><?= e(t('ev_public_info')) ?><input name="public_info" placeholder="<?= e(t('ev_public_info_ph')) ?>"></label>
    </fieldset>
    <?php endif; ?>
    <button class="btn btn-primary span2"><?= e(t('dash_create_event')) ?></button>
  </form>
</details>

<?php // Zwischenüberschrift je Monat: Wer den September sucht, soll nicht den
      // August lesen müssen (#233). ?>
<?php $evMonat = ''; ?>
<?php foreach ($events as $ev): ?>
  <?php if (fmt_month($ev['date']) !== $evMonat): ?>
    <?php $evMonat = fmt_month($ev['date']); ?>
    <h2 class="event-month"><?= e($evMonat) ?></h2>
  <?php endif; ?>
  <?php // In der Liste steht jede Karte offen — und lässt sich jetzt auch
        // zuklappen, wenn man sie gerade nicht braucht. Keine Gruppe: Hier
        // dürfen beliebig viele offen sein (#325). ?>
  <?php $evGruppe = ''; $evOpen = true; ?>
  <?php require BASE_DIR . '/app/views/intern/_event_card.php'; ?>
<?php endforeach; ?>
<?php if (!$events): ?><p class="muted center"><?= e(t('ev_none')) ?></p><?php endif; ?>
<script src="<?= e(asset('/assets/eventfields.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
