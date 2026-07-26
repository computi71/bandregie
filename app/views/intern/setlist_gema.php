<?php
function hms(int|string|null $sec): string {
  $sec = (int) $sec;
  return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
}
$totalSec = array_sum(array_map(fn($x) => (int) $x['duration_sec'], $entries));
$missingComposer = array_filter($entries, fn($x) => trim((string) $x['composer']) === '');
$missingDuration = array_filter($entries, fn($x) => (int) $x['duration_sec'] === 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>GEMA-Musikfolge · <?= e($setlist['name']) ?></title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 1.5rem 2rem; font-size: 11pt; }
    h1 { font-size: 1.3rem; margin: 0 0 0.2rem; }
    .frame { border: 1px solid #999; padding: 0.6rem 0.9rem; margin: 0.8rem 0 1rem; }
    .frame div { margin: 0.15rem 0; }
    .frame b { display: inline-block; min-width: 11rem; }
    table { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
    th, td { border: 1px solid #999; padding: 0.25rem 0.45rem; text-align: left; vertical-align: top; }
    th { background: #eee; }
    td.num, th.num { text-align: right; width: 2.2rem; }
    td.dur, th.dur { text-align: center; width: 5.5rem; }
    td.wnr, th.wnr { width: 7rem; }
    tfoot td { font-weight: 700; }
    .warn { background: #fff3cd; border: 1px solid #d4a017; padding: 0.5rem 0.8rem; margin: 0.8rem 0; }
    .hint { color: #555; font-size: 9.5pt; margin-top: 0.8rem; }
    .toolbar { margin-bottom: 1rem; }
    @media print { .toolbar, .warn { display: none; } }
  </style>
</head>
<body>
  <div class="toolbar"><button onclick="window.print()">🖨 Als PDF drucken</button></div>

  <h1>Musikfolge (Setlist) für die GEMA-Meldung</h1>

  <div class="frame">
    <div><b>Ausführende Band:</b> <?= e($settings['band_name']) ?></div>
    <?php if ($event): ?>
      <div><b>Veranstaltung:</b> <?= e($event['public_title'] ?: $event['title']) ?></div>
      <div><b>Datum:</b> <?= fmt_date($event['date']) ?><?= $event['time'] ? ', Beginn ' . e($event['time']) . ' Uhr' : '' ?></div>
      <div><b>Veranstaltungsort:</b> <?= e($event['venue_name'] ? $event['venue_name'] . ($event['venue_city'] ? ', ' . $event['venue_city'] : '') : ($event['location'] ?: '–')) ?></div>
    <?php else: ?>
      <div><b>Veranstaltung:</b> <?= e($setlist['name']) ?> (kein Termin verknüpft)</div>
    <?php endif; ?>
    <div><b>Anzahl der Werke:</b> <?= count($entries) ?></div>
  </div>

  <?php if ($missingComposer || $missingDuration): ?>
    <div class="warn">
      ⚠ <strong>Vor dem Einreichen ergänzen:</strong>
      <?php if ($missingComposer): ?><br>Komponist fehlt bei <?= count($missingComposer) ?> Werk(en) — die GEMA verlangt die Urheber (Komponist/Textdichter), nicht den Interpreten. In der Songverwaltung nachtragen.<?php endif; ?>
      <?php if ($missingDuration): ?><br>Spieldauer fehlt bei <?= count($missingDuration) ?> Werk(en).<?php endif; ?>
    </div>
  <?php endif; ?>

  <table>
    <thead>
      <tr><th class="num">Nr.</th><th>Werktitel</th><th>Komponist / Urheber</th><th>Interpret (Original)</th><th class="wnr">GEMA-Werknr.</th><th class="dur">Spieldauer</th></tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $i => $entry): ?>
        <tr>
          <td class="num"><?= $i + 1 ?></td>
          <td><?= e($entry['title']) ?></td>
          <td><?= e($entry['composer'] ?: '—') ?></td>
          <td><?= e($entry['artist'] ?: $settings['band_name'] . ' (Eigenkomposition)') ?></td>
          <td class="wnr"><?= e($entry['gema_werknr'] ?: '') ?></td>
          <td class="dur"><?= hms($entry['duration_sec']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="5">Gesamtspieldauer</td><td class="dur"><?= hms($totalSec) ?></td></tr>
    </tfoot>
  </table>

  <p class="hint">
    Hinweis: Die Musikfolge muss spätestens <strong>6 Wochen nach der Veranstaltung</strong> bei der GEMA eingereicht werden
    (Online-Portal → „Meine Setlists" oder per Upload mit der offiziellen GEMA-Vorlage), sonst fallen 10 % Zuschlag an.
    Medleys sind mit „P" (Potpourri), Ausschnitte mit „F" (Fragment) zu kennzeichnen.
    Diese Liste dient als Vorlage/Beleg für die Meldung.
  </p>
</body>
</html>
