<?php
// Der Vertrag auf Papier (#303). Gemeinsamer Druckkopf wie Setliste, Rider und
// Angebot — der Veranstalter bekommt Blätter, die erkennbar von derselben Band
// kommen.
//
// Der Wortlaut steht fertig in der Zeile und wird hier nur gesetzt, nicht mehr
// gebildet: Was verschickt wurde, soll sich beim Nachdrucken nicht ändern.
$printDoc = 'contract';
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('contract_sheet_title')) ?> · <?= e($contract['event_title'] ?? '') ?></title>
  <style>
<?php require BASE_DIR . '/app/views/intern/_print_style.php'; ?>
    body { font-size: 10.5pt; }
    .head-row { border-bottom: 0.5mm solid #000; padding-bottom: 4mm; }
    h1 { font-size: 17pt; margin: 0 0 1mm; }
    /* Der Vertragstext kommt als Fließtext mit eigenen Umbrüchen. Er wird nicht
       umformatiert: Wer Paragraphen einrückt, meint das so. */
    .body { white-space: pre-wrap; margin-top: 8mm; position: relative; z-index: 1; line-height: 1.45; }
    .note { font-size: 9pt; color: #555; margin-top: 8mm; }
  </style>
</head>
<body>
<?php $zurueckUrl = '/intern/vertraege/' . (int) $contract['id']; require BASE_DIR . '/app/views/intern/_printbar.php'; ?>
<div class="sheet">
  <?= print_watermark_html($printDoc) ?>
  <div class="head-row">
    <div>
      <h1><?= e(t('contract_sheet_title')) ?></h1>
      <div class="muted">
        <?= e(setting('band_name')) ?> · <?= e(fmt_date($contract['contract_date'])) ?>
        <?php if (trim((string) $contract['contract_no']) !== ''): ?> · <?= e($contract['contract_no']) ?><?php endif; ?>
      </div>
    </div>
    <?= print_logo_html($printDoc) ?>
  </div>

  <div class="body"><?= e((string) $contract['body']) ?></div>

  <?php if (trim((string) $contract['notes']) !== ''): ?>
    <p class="note" style="white-space: pre-wrap"><?= e($contract['notes']) ?></p>
  <?php endif; ?>
</div>
</body>
</html>
