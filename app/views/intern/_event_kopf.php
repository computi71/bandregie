<?php
// Der Kopf eines Termins: was ist es, wann, wo, und die zwei Wege, die man am
// Termin wirklich braucht — Navi zum Ort und die Setliste. Einmal hier, weil die
// Übersicht und die Terminliste dasselbe zeigen sollen; vorher stand auf der
// Übersicht nur Datum und Titel, und wer zum Auftritt fahren wollte, musste
// erst in die Liste wechseln (#264).
//
// Vorher setzen:
//   $ev           — Termin-Zeile (Pflicht)
//   $venue        — Ort-Zeile oder null (Pflicht)
//   $memberNames  — id => Name, für den Verantwortlichen (optional)
//   $kompakt      — true auf der Übersicht: dort ist jeder Termin bestätigt,
//                   und Gage und Produktion gehören nicht auf die erste Seite
$memberNames = $memberNames ?? [];
$kompakt = $kompakt ?? false;
// Navi-Link zum Ort: am Handy öffnet route.js die native Karten-App, am
// Desktop führt der Link ins Web.
$naviDest = $venue ? venue_dest($venue) : navi_dest((string) $ev['location']);
$zeiten = [];
if ($ev['time_meet']) $zeiten[] = t('ev_meet') . ' ' . $ev['time_meet'];
if ($ev['time']) $zeiten[] = t('ev_start') . ' ' . $ev['time'];
if ($ev['time_end']) $zeiten[] = t('ev_end') . ' ' . $ev['time_end'];
if ($ev['responsible_id'] && isset($memberNames[$ev['responsible_id']])) {
  $zeiten[] = t('ev_responsible') . ': ' . $memberNames[$ev['responsible_id']];
}
if (!$kompakt) {
  if ($ev['fee']) $zeiten[] = t('ev_fee') . ': ' . $ev['fee'];
  if ($ev['invoice_no']) $zeiten[] = t('ev_invoice') . ': ' . $ev['invoice_no'];
  if (!empty($ev['pa_source'])) $zeiten[] = t('prod_pa') . ': ' . production_label($ev['pa_source']);
  if (!empty($ev['light_source'])) $zeiten[] = t('prod_light') . ': ' . production_label($ev['light_source']);
}
?>
<div class="event-head">
  <span class="badge <?= e($ev['type']) ?>"><?= e(event_type_label($ev['type'])) ?></span>
  <?php if (!$kompakt): ?><span class="badge ev-<?= e($ev['status']) ?>"><?= e(event_status_label($ev['status'])) ?></span><?php endif; ?>
  <span class="event-date"><?= fmt_date($ev['date']) ?><?= $ev['time'] ? ' · ' . e($ev['time']) . ' ' . e(t('events_oclock')) : '' ?></span>
  <strong><?= e($ev['title']) ?></strong>
  <?php if ($venue): ?><span class="muted">📍 <?= e($venue['name']) ?><?= $venue['city'] ? ', ' . e($venue['city']) : '' ?></span>
  <?php elseif ($ev['location']): ?><span class="muted">📍 <?= e($ev['location']) ?></span><?php endif; ?>
  <?php if ($naviDest !== ''): ?><a class="badge link navi-link" data-navi="<?= e($naviDest) ?>" href="<?= e(navi_web($naviDest)) ?>" target="_blank" rel="noopener" title="<?= e(t('geo_navigate')) ?>">🧭 <?= e(t('geo_navigate')) ?></a><?php endif; ?>
  <?php if (!$kompakt && $ev['is_public']): ?><span class="badge public"><?= e(t('ev_public_badge')) ?></span><?php endif; ?>
  <?php if ($ev['setlist_id']): ?><a class="badge link" href="/intern/setlists/<?= (int) $ev['setlist_id'] ?>">🎵 <?= e(t('ev_setlist')) ?></a><?php endif; ?>
</div>
<?php if ($zeiten): ?><p class="muted small"><?= e(implode(' · ', $zeiten)) ?></p><?php endif; ?>
