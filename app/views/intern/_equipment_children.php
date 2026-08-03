<?php
/**
 * Bestandteile eines Geräts, eine Ebene je Aufruf. Wird ausschließlich über
 * eq_render_parts() eingebunden — nur so hat jede Ebene ihre eigenen
 * Variablen. Erwartet $childItems, $ctx (siehe eq_render_parts) und $depth.
 */
?>
<ul class="task-list eq-parts">
  <?php foreach ($childItems as $child): ?>
    <li>
      <?php if ($child['slot']): ?><span class="badge"><?= e($child['slot']) ?></span><?php endif; ?>
      <?php if ($childThumb = eq_thumb($filesByEq[$child['id']] ?? [])): ?>
        <img class="eq-thumb eq-thumb-small" src="/intern/datei/<?= (int) $childThumb['id'] ?>" alt="" loading="lazy">
      <?php endif; ?>
      <button type="button" class="linklike" data-eqopen="eq-dlg-<?= $child['id'] ?>"><strong><?= e($child['name']) ?></strong></button>
      <?php if (!empty($child['disposed_on'])): ?><span class="badge">📦 <?= e(sprintf(t('eqb_disposed_on'), fmt_date($child['disposed_on']))) ?></span><?php endif; ?>
      <?php if (eq_may_see_price($child, $user) && ($child['price_cents'] !== null || !empty($child['purchased_on']))): ?>
        <span class="muted small">🧾 <?= e(eq_purchase_label($child)) ?></span>
      <?php endif; ?>
      <?php $subParts = $childrenOf[(int) $child['id']] ?? []; ?>
      <?php if ($subParts): ?>
        <?php $subSum = eq_tree_value($child, $items, $user); ?>
        <?php if ($subSum > 0): ?>
          <span class="muted small">Σ <?= e(fmt_money($subSum)) ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($child['notes']): ?><div class="muted small prewrap"><?= e($child['notes']) ?></div><?php endif; ?>
      <dialog id="eq-dlg-<?= $child['id'] ?>" class="eq-dialog">
        <div class="eq-dialog-head">
          <strong><?= e($child['name']) ?></strong>
          <button type="button" class="btn btn-tiny" data-eqclose aria-label="<?= e(t('close')) ?>">✕</button>
        </div>
        <?php // Auch hier erst auf Verlangen: der Dialog eines Bestandteils
              // enthielt dasselbe Formular noch einmal. ?>
        <div class="eq-detail" data-eqdetail="/intern/equipment/<?= $child['id'] ?>/detail?teil=1">
          <a class="btn btn-small" href="/intern/equipment/<?= $child['id'] ?>/detail">✏️ <?= e(t('edit')) ?></a>
        </div>
      </dialog>
      <?php if ($subParts): ?>
        <?php eq_render_parts($subParts, $ctx, $depth + 1); ?>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
