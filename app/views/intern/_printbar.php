<?php
// Die Leiste über jedem Ausdruck: zurück, drucken, und was die Ansicht sonst
// noch anzubieten hat. Einmal hier, weil vier Ansichten sie brauchen und vier
// Kopien irgendwann auseinanderlaufen (#263).
//
// Vorher setzen:
//   $zurueckUrl  — wohin „Zurück" führt (Pflicht)
//   $leisteExtra — zusätzliche Bedienelemente als HTML (optional)
//
// Ein echter Link, kein history.back(): Der Ausdruck öffnet sich in einem neuen
// Tab, und dort gibt es keine Geschichte, in die man zurückgehen könnte.
?>
<style>
  /* Am Bildschirm klebt die Leiste oben: Ein Setlisten-Ausdruck ist drei Blätter
     lang, und auf dem Telefon will niemand erst nach oben wischen, um zurück zu
     kommen. Gedruckt wird sie nie. */
  .printbar {
    position: sticky; top: 0; z-index: 5;
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.8rem;
    background: #fff; border-bottom: 1px solid #ccc;
    font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
  }
  /* 44 px: die Fingerbreite, unter der auf einem Telefon danebengetippt wird. */
  .printbar a, .printbar button {
    min-height: 2.75rem; display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0 0.9rem; border: 1px solid #999; border-radius: 8px;
    background: #f4f4f4; color: #000; font-size: 1rem; text-decoration: none;
    cursor: pointer;
  }
  .printbar a:focus-visible, .printbar button:focus-visible { outline: 2px solid #06c; outline-offset: 2px; }
  .printbar .drucken { font-weight: 700; }
  .printbar .extra { display: flex; flex-wrap: wrap; align-items: center; gap: 0.2rem 0.9rem; }
  /* Auch die Schalter sind Ziele: Kästchen und Beschriftung zusammen hoch genug. */
  .printbar .extra label { display: inline-flex; align-items: center; gap: 0.4rem; min-height: 2.75rem; font-size: 0.95rem; }
  .printbar .extra input[type="checkbox"] { width: 1.15rem; height: 1.15rem; }
  .printbar .extra select { min-height: 2.25rem; font-size: 0.95rem; }
  @media print { .printbar { display: none; } }
</style>
<div class="printbar">
  <a href="<?= e($zurueckUrl) ?>">← <?= e(t('back')) ?></a>
  <button class="drucken" data-print>🖨 <?= e(t('sl_print')) ?></button>
  <?php if (!empty($leisteExtra)): ?><span class="extra"><?= $leisteExtra ?></span><?php endif; ?>
</div>
