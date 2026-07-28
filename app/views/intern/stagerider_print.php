<?php
// Druckfassung des Stageriders für Veranstalter. Ränder stecken wie bei der
// Setlist fest im Blatt, damit sie unabhängig vom Druckdialog stimmen.
$logo = ($settings['print_logo_file'] ?? '') ?: ($settings['logo_file'] ?? '');
$blocks = [
  'rider_stage_lbl' => $settings['rider_stage'] ?? '',
  'rider_power_lbl' => $settings['rider_power'] ?? '',
  'rider_pa_lbl' => $settings['rider_pa'] ?? '',
  'rider_monitor_lbl' => $settings['rider_monitor'] ?? '',
  'rider_light_lbl' => $settings['rider_light'] ?? '',
  'rider_getin_lbl' => $settings['rider_getin'] ?? '',
  'rider_extras_lbl' => $settings['rider_extras'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <title><?= e(t('rider_title')) ?> · <?= e($settings['band_name']) ?></title>
  <style>
    @page { size: A4 portrait; margin: 0; }
    body { font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; font-size: 10.5pt; }
    .sheet { box-sizing: border-box; width: 210mm; min-height: 296mm; padding: 14mm 16mm 12mm; }
    .head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm;
                border-bottom: 0.5mm solid #000; padding-bottom: 4mm; }
    h1 { font-size: 17pt; margin: 0 0 1mm; }
    .logo img { max-height: 20mm; max-width: 65mm; }
    h2 { font-size: 11.5pt; margin: 6mm 0 1.5mm; text-transform: uppercase; letter-spacing: 0.04em; }
    .block { margin-bottom: 2mm; }
    .block dt { font-weight: 700; }
    .block dd { margin: 0 0 2.5mm; white-space: pre-wrap; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th, td { border-bottom: 0.2mm solid #bbb; padding: 1.2mm 2mm; text-align: left; }
    th { border-bottom: 0.4mm solid #000; }
    .contacts { display: flex; gap: 10mm; margin-top: 4mm; }
    .muted { color: #555; }
    .toolbar { padding: 0.6rem 16mm; }
    @media print { .toolbar { display: none; } }
    @media screen { body { background: #777; padding: 1rem 0; }
                    .sheet { margin: 0 auto; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.4); } }
  </style>
</head>
<body>
<div class="toolbar"><button data-print>🖨 <?= e(t('sl_print')) ?></button></div>
<div class="sheet">
  <div class="head-row">
    <div>
      <h1><?= e($settings['band_name']) ?></h1>
      <div class="muted"><?= e(t('rider_for')) ?> · <?= e(fmt_date(date('Y-m-d'))) ?></div>
    </div>
    <div class="logo">
      <?php if ($logo): ?><img src="/uploads/<?= e($logo) ?>" alt="<?= e($settings['band_name']) ?>"><?php endif; ?>
    </div>
  </div>

  <?php $any = array_filter($blocks); ?>
  <?php if ($any): ?>
    <h2><?= e(t('rider_requirements')) ?></h2>
    <dl class="block">
      <?php foreach ($blocks as $key => $value): ?>
        <?php if (trim((string) $value) === '') continue; ?>
        <dt><?= e(t($key)) ?></dt>
        <dd><?= e($value) ?></dd>
      <?php endforeach; ?>
    </dl>
  <?php endif; ?>

  <?php if ($stageItems): ?>
    <h2><?= e(t('stage_plot')) ?></h2>
    <div class="block"><?php $stagePrint = true; require BASE_DIR . '/app/views/_buehnenplan.php'; ?></div>
  <?php endif; ?>

  <?php if (trim((string) ($settings['rider_positions'] ?? '')) !== ''): ?>
    <h2><?= e(t('rider_positions_lbl')) ?></h2>
    <div class="block"><dd><?= e($settings['rider_positions']) ?></dd></div>
  <?php endif; ?>

  <?php if ($channels): ?>
    <h2><?= e(t('rider_inputs')) ?></h2>
    <table>
      <thead><tr><th style="width:12mm"><?= e(t('ch_input')) ?></th><th><?= e(t('ch_name')) ?></th><th><?= e(t('ch_source')) ?></th><th><?= e(t('notes')) ?></th></tr></thead>
      <tbody>
        <?php // Der Veranstalter patcht Ports, nicht unsere Kanalnummern —
              // die braucht nur, wer bei uns am Pult steht. Wer keine Ports
              // gepflegt hat, bekommt die Kanalnummer: eine Spalte, nie zwei. ?>
        <?php foreach ($channels as $c): ?>
          <tr><td><?= e($c['patch'] !== '' ? $c['patch'] : (string) (int) $c['number']) ?></td><td><?= e($c['name']) ?></td><td><?= e($c['source']) ?></td><td><?= e($c['notes']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (($settings['rider_contact_tech'] ?? '') !== '' || ($settings['rider_contact_booking'] ?? '') !== ''): ?>
    <h2><?= e(t('rider_contacts')) ?></h2>
    <div class="contacts">
      <?php if (($settings['rider_contact_tech'] ?? '') !== ''): ?>
        <div><strong><?= e(t('rider_contact_tech_lbl')) ?></strong><br><?= e($settings['rider_contact_tech']) ?></div>
      <?php endif; ?>
      <?php if (($settings['rider_contact_booking'] ?? '') !== ''): ?>
        <div><strong><?= e(t('rider_contact_booking_lbl')) ?></strong><br><?= e($settings['rider_contact_booking']) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<script src="<?= e(asset('/assets/actions.js')) ?>" defer></script>
</body>
</html>
