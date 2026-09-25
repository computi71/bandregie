<?php
// Die Rechnung auf Papier (#356). Gemeinsamer Druckkopf wie Vertrag, Angebot
// und Rider — der Veranstalter bekommt Blätter, die erkennbar von derselben
// Band kommen.
//
// Die Steuerlage steht in der Zeile, nicht in den Einstellungen: Wer im
// nächsten Jahr die Kleinunternehmerregelung verlässt, darf damit nicht
// ändern, was einmal verschickt wurde.
$printDoc = 'invoice';
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('inv_sheet_title')) ?> · <?= e($invoice['invoice_no']) ?></title>
  <style>
<?php require BASE_DIR . '/app/views/intern/_print_style.php'; ?>
    .an { margin-top: 12mm; line-height: 1.45; }
    .meta { margin-top: 8mm; }
    .meta div { margin: 0.6mm 0; }
    .meta b { display: inline-block; min-width: 34mm; }
    table { width: 100%; border-collapse: collapse; margin-top: 8mm; position: relative; z-index: 1; }
    th, td { border-bottom: 0.2mm solid #bbb; padding: 1.6mm 2mm; text-align: left; vertical-align: top; }
    th { border-bottom: 0.4mm solid #000; }
    .num { text-align: right; white-space: nowrap; }
    .sum td { border-top: 0.4mm solid #000; border-bottom: 0; font-weight: 700; font-size: 12pt; }
    .hinweis { margin-top: 8mm; font-size: 9pt; }

    /* Der Barstempel (#356). Er soll aussehen wie ein Stempel und nicht wie
       eine Zeile Text: schräg, umrandet, in der Farbe eines Stempelkissens.
       Über den Posten, nicht daneben — auf einer bezahlten Rechnung ist das
       die Angabe, auf die zuerst jemand schaut.
       pointer-events:none, damit er im Browser nichts verdeckt, was man
       anklicken will; im Druck ist das ohnehin egal. */
    .stempel {
      position: absolute; top: 38mm; right: 16mm; z-index: 2; pointer-events: none;
      border: 1mm solid #b3261e; color: #b3261e; border-radius: 2mm;
      padding: 2mm 6mm; font-size: 15pt; font-weight: 800; letter-spacing: 0.08em;
      text-transform: uppercase; transform: rotate(-11deg); opacity: 0.85;
    }
    .stempel small { display: block; font-size: 8pt; letter-spacing: 0; font-weight: 600; }
    @media print { .stempel { opacity: 1; } }
  </style>
</head>
<body class="brief">
<?php $zurueckUrl = '/intern/rechnungen/' . (int) $invoice['id']; $sprachwahl = true;
      require BASE_DIR . '/app/views/intern/_printbar.php'; ?>
<div class="sheet">
  <?= print_watermark_html($printDoc) ?>

  <?php // Der Stempel gehoert auf das Blatt, nicht daneben - deshalb innerhalb
        // der Seite, die position:relative traegt. ?>
  <?php if (!empty($invoice['paid_cash'])): ?>
    <div class="stempel"><?= e(t('inv_stamp_cash')) ?>
      <?php if ($invoice['paid_at']): ?><small><?= e(fmt_date(substr((string) $invoice['paid_at'], 0, 10))) ?></small><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="head-row">
    <div>
      <h1><?= e(t('inv_sheet_title')) ?></h1>
      <div class="muted"><?= e(setting('band_name')) ?> · <?= e(fmt_date($invoice['invoice_date'])) ?> · <?= e($invoice['invoice_no']) ?></div>
    </div>
    <?= print_logo_html($printDoc) ?>
  </div>

<div class="an">
  <?php if ($invoice['promoter_name']): ?><strong><?= e($invoice['promoter_name']) ?></strong><br><?php endif; ?>
  <?php if (!empty($invoice['contact_name'])): ?><?= e($invoice['contact_name']) ?><br><?php endif; ?>
  <?php if (!empty($invoice['street'])): ?><?= e($invoice['street']) ?><br><?php endif; ?>
  <?php if (!empty($invoice['postcode']) || !empty($invoice['city'])): ?>
    <?= e(trim(($invoice['postcode'] ?? '') . ' ' . ($invoice['city'] ?? ''))) ?>
  <?php endif; ?>
</div>

<div class="meta">
  <div><b><?= e(t('inv_no')) ?></b><?= e($invoice['invoice_no']) ?></div>
  <div><b><?= e(t('date')) ?></b><?= e(fmt_date($invoice['invoice_date'])) ?></div>
  <?php if ($invoice['event_title']): ?>
    <div><b><?= e(t('itemkind_event')) ?></b><?= e(fmt_date($invoice['event_date'])) ?> · <?= e($invoice['event_title']) ?></div>
  <?php endif; ?>
</div>

<table>
  <tr><th><?= e(t('inv_item_label_ph')) ?></th><th class="num"><?= e(t('inv_item_amount')) ?></th></tr>
  <?php foreach ($items as $p): ?>
    <tr><td><?= e($p['label']) ?></td><td class="num"><?= e(fmt_money((int) $p['amount_cents'])) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$invoice['small_business']): ?>
    <tr><td><?= e(t('inv_net')) ?></td><td class="num"><?= e(fmt_money($totals['net'])) ?></td></tr>
    <tr><td><?= e(str_replace('%1', (string) (int) $invoice['vat_percent'], t('inv_vat'))) ?></td>
        <td class="num"><?= e(fmt_money($totals['vat'])) ?></td></tr>
  <?php endif; ?>
  <tr class="sum"><td><?= e(t('inv_out_total')) ?></td><td class="num"><?= e(fmt_money($totals['total'])) ?></td></tr>
</table>

<?php if ($invoice['small_business']): ?>
  <?php // Pflichtangabe nach § 19 UStG, keine Höflichkeit. ?>
  <p class="hinweis"><?= e(t('inv_small_business_note')) ?></p>
<?php endif; ?>
<?php if (!empty($invoice['notes'])): ?>
  <p class="hinweis prewrap"><?= e($invoice['notes']) ?></p>
<?php endif; ?>
<?php if (!empty($invoice['paid_cash'])): ?>
  <p class="hinweis"><?= e(t('inv_paid_cash_note')) ?></p>
<?php endif; ?>
</div>
</body>
</html>
