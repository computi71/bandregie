<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e($heading) ?></h1>
<div class="card">
  <?php if ($text): ?>
    <p class="prewrap"><?= e($text) ?></p>
  <?php else: ?>
    <p class="muted">Diese Seite wird gerade noch befüllt. Kontakt: <?= $settings['contact_email'] ? '<a href="mailto:' . e($settings['contact_email']) . '">' . e($settings['contact_email']) . '</a>' : e($settings['band_name']) ?></p>
  <?php endif; ?>
</div>

<?php // Das mitgelieferte Hintergrundbild steht unter CC0 und verlangt keine
      // Nennung. Genannt wird es trotzdem, solange es im Einsatz ist — wer ein
      // Bild verschenkt, darf im Impressum stehen. Mit dem eigenen Bild der
      // Band verschwindet der Hinweis von selbst. ?>
<?php if ($imageCredit): ?>
  <div class="card">
    <h2><?= e(t('legal_credits')) ?></h2>
    <?php // Bewusst unmaskiert: der Text besteht aus zwei festen Verweisen und
          // einem bereits maskierten Baustein, siehe demo_background_credit(). ?>
    <p class="muted small"><?= $imageCredit ?></p>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
