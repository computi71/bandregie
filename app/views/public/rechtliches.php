<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1><?= e($heading) ?></h1>
<div class="card">
  <?php if ($text): ?>
    <p class="prewrap"><?= e($text) ?></p>
  <?php else: ?>
    <p class="muted">Diese Seite wird gerade noch befüllt. Kontakt: <?= $settings['contact_email'] ? '<a href="mailto:' . e($settings['contact_email']) . '">' . e($settings['contact_email']) . '</a>' : e($settings['band_name']) ?></p>
  <?php endif; ?>
</div>

<?php // Die mitgelieferten Bilder stehen unter CC0 und verlangen keine Nennung.
      // Genannt werden sie trotzdem, solange sie im Einsatz sind — wer ein Bild
      // verschenkt, darf im Impressum stehen. Mit den eigenen Bildern der Band
      // verschwindet der Hinweis von selbst. ?>
<?php if ($imageCredits): ?>
  <div class="card">
    <h2><?= e(t('legal_credits')) ?></h2>
    <?php // Bewusst unmaskiert: jede Zeile besteht aus festen Verweisen und
          // bereits maskierten Namen, siehe demo_image_credits(). ?>
    <?php foreach ($imageCredits as $nachweis): ?>
      <p class="muted small"><?= $nachweis ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
