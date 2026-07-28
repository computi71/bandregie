<?php require BASE_DIR . '/app/views/_header.php'; ?>
<h1>❓ <?= e(t('help_title')) ?></h1>
<p class="muted"><?= e(t('help_intro')) ?></p>

<?php $helpFirst = true; ?>
<?php foreach (array_keys(PERM_MODULES) as $helpMod): ?>
  <?php if (!perm_allows($user, $helpMod)) continue; ?>
  <details class="card acc" name="helpacc" <?= $helpFirst ? 'open' : '' ?>>
    <summary><?= e(t('inav_' . $helpMod)) ?></summary>
    <p class="muted"><?= e(t('help_' . $helpMod)) ?></p>
  </details>
  <?php $helpFirst = false; ?>
<?php endforeach; ?>

<?php // Nur wer die Kasse sieht, und nur wenn die Band die Regelung nutzt —
      // sonst erklärt die Hilfe etwas, das nirgends vorkommt. ?>
<?php if (perm_allows($user, 'kasse') && setting('tax_small_business', '0') === '1'): ?>
  <details class="card acc" name="helpacc">
    <summary>⚖ <?= e(t('help_tax_title')) ?></summary>
    <p class="muted"><?= e(t('help_tax_what')) ?></p>
    <p class="muted"><?= e(t('help_tax_band')) ?></p>
    <p class="muted"><?= e(t('help_tax_counts')) ?></p>
    <p class="muted"><?= e(t('help_tax_over')) ?></p>
    <p class="muted"><?= e(t('help_tax_next_year')) ?></p>
    <p class="muted"><?= e(t('help_tax_gwg')) ?></p>
    <h3><?= e(t('help_tax_offset_title')) ?></h3>
    <p class="muted"><?= e(t('help_tax_offset_intro')) ?></p>
    <ul class="task-list">
      <li><?= e(t('help_tax_offset_deposit')) ?></li>
      <li><?= e(t('help_tax_offset_payout')) ?></li>
      <li><?= e(t('help_tax_offset_private')) ?></li>
      <li><?= e(t('help_tax_offset_gwg')) ?></li>
    </ul>
    <p class="muted"><?= e(t('help_tax_levels')) ?></p>

    <p class="muted small">⚖ <?= e(t('tax_no_advice')) ?></p>

    <h3><?= e(t('help_est_title')) ?></h3>
    <p class="muted"><?= e(t('help_est_who')) ?></p>
    <p class="muted"><?= e(t('help_est_euer')) ?></p>
    <p class="muted"><?= e(t('help_est_merch')) ?></p>
    <p class="muted"><?= e(t('help_est_hobby')) ?></p>

    <h3><?= e(t('help_est_own_title')) ?></h3>
    <p class="muted"><?= e(t('help_est_own_taxed')) ?></p>
    <p class="muted"><?= e(t('help_est_own_costs')) ?></p>
    <p class="muted"><?= e(t('help_est_own_km')) ?></p>
    <p class="muted"><?= e(t('help_est_own_home')) ?></p>
    <p class="muted"><?= e(t('help_est_own_ksk')) ?></p>

    <h3><?= e(t('help_tax_sources')) ?></h3>
    <p class="muted small"><?= e(sprintf(t('help_tax_checked'), fmt_date(setting('tax_values_checked')))) ?></p>
    <ul class="task-list">
      <li><a href="https://www.gesetze-im-internet.de/ustg_1980/__19.html" rel="noopener" target="_blank">§ 19 UStG</a>
        <span class="muted small"><?= e(t('help_tax_src_ustg')) ?></span></li>
      <li><a href="https://www.gesetze-im-internet.de/estg/__6.html" rel="noopener" target="_blank">§ 6 Abs. 2 EStG</a>
        <span class="muted small"><?= e(t('help_tax_src_estg')) ?></span></li>
      <li><a href="https://www.bundesfinanzministerium.de/Content/DE/Standardartikel/Themen/Steuern/Weitere_Steuerthemen/Betriebspruefung/AfA-Tabellen/Ergaenzende-AfA-Tabellen/AfA-Tabelle_AV.html" rel="noopener" target="_blank">AfA-Tabelle AV</a>
        <span class="muted small"><?= e(t('help_tax_src_afa')) ?></span></li>
      <li><a href="https://www.gesetze-im-internet.de/estg/__15.html" rel="noopener" target="_blank">§ 15 Abs. 3 EStG</a>
        <span class="muted small"><?= e(t('help_est_src_est15')) ?></span></li>
      <li><a href="https://www.gesetze-im-internet.de/estg/__18.html" rel="noopener" target="_blank">§ 18 EStG</a>
        <span class="muted small"><?= e(t('help_est_src_est18')) ?></span></li>
      <li><a href="https://www.bundesfinanzhof.de/en/entscheidungen/entscheidungen-online/decision-detail/STRE201510047/" rel="noopener" target="_blank">BFH VIII R 16/11</a>
        <span class="muted small"><?= e(t('help_est_src_bfh')) ?></span></li>
      <li><a href="https://www.kuenstlersozialkasse.de/kuenstler-und-publizisten/voraussetzungen" rel="noopener" target="_blank">Künstlersozialkasse</a>
        <span class="muted small"><?= e(t('help_est_src_ksk')) ?></span></li>
    </ul>
  </details>
<?php endif; ?>

<details class="card acc" name="helpacc">
  <summary>📱 <?= e(t('app_install')) ?></summary>
  <p class="muted"><?= e(t('app_install_hint')) ?></p>
  <p class="muted"><?= e(t('app_install_offline')) ?></p>
  <p class="muted"><?= e(t('app_install_store')) ?></p>
  <p class="muted"><?= e(t('app_install_push')) ?></p>
</details>

<p class="muted small"><?= e(t('help_more')) ?> <a href="/intern/ueber"><?= e(t('about_open')) ?> →</a></p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
