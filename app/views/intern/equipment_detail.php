<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php
// Ein einzelnes Gerät als eigene Seite. Sie ist der Weg ohne JavaScript und
// zugleich das, was das Nachladen holt — beides dieselbe Quelle, damit nicht
// zwei Fassungen auseinanderlaufen.
?>
<div class="page-head">
  <h1>🎛 <?= e($detailEq['name']) ?><?php if ($detailQty = eq_quantity_label($detailEq)): ?> <span class="muted">· <?= e($detailQty) ?></span><?php endif; ?></h1>
  <div class="row-buttons">
    <a class="btn btn-ghost btn-small" href="/intern/equipment">← <?= e(t('inav_equipment')) ?></a>
  </div>
</div>
<p class="muted">
  <?= e(t('eq_owner')) ?>: <?= e($detailEq['owner_name'] ?: t('eq_owner_band')) ?>
  <?php if ($detailEq['location']): ?> · 📍 <?= e($detailEq['location']) ?><?php endif; ?>
</p>
<div class="card">
  <?php require BASE_DIR . '/app/views/intern/_equipment_detail.php'; ?>
</div>
<script src="<?= e(asset('/assets/equipment.js')) ?>" defer></script>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
