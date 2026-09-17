<?php
// Zwei Fassungen, eine Datei: die Seite im Bandbereich und dieselbe zum Drucken
// (#311). Zwei Dateien bedeuteten zwei Hilfen, von denen eine still veraltet.
$druck = !empty($druck);
// Auf Papier kosten neunzehn dunkle Bildschirmfotos viel Farbe, und die
// Erklärung steht daneben im Text. Deshalb ist der Ausdruck ohne Bilder die
// Vorgabe und mit Bildern ein Klick (#311).
$druckBilder = $druck && ($_GET['bilder'] ?? '') === '1';
require_once BASE_DIR . '/app/help.php';
?>
<?php if ($druck): $printDoc = 'help'; ?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('help_title')) ?> · <?= e(setting('band_name')) ?></title>
  <style>
<?php require BASE_DIR . '/app/views/intern/_print_style.php'; ?>
    body { font-size: 10.5pt; }
    .head-row { border-bottom: 0.5mm solid #000; padding-bottom: 4mm; }
    h1 { font-size: 17pt; margin: 0 0 1mm; }
    /* Jeder Abschnitt beginnt auf einer neuen Seite: So lässt sich ein einzelnes
       Blatt herausziehen und jemandem in die Hand drücken. */
    details { break-before: page; margin-top: 6mm; }
    details:first-of-type { break-before: auto; }
    summary { font-size: 13pt; font-weight: 700; list-style: none; margin-bottom: 2mm; }
    summary::-webkit-details-marker { display: none; }
    .help-lead { font-size: 11.5pt; }
    p { margin: 0 0 2.5mm; }
    .helpshot, .helpfig { max-width: 120mm; }
    .help-tasks { display: none; }
    @media screen { body { padding: 1rem 0; } }
  </style>
</head>
<body>
<?php
  $zurueckUrl = '/intern/hilfe';
  $leisteExtra = '<a href="/intern/hilfe/druck' . ($druckBilder ? '' : '?bilder=1') . '">'
    . ($druckBilder ? '📄 ' . e(t('help_print_nopics')) : '🖼 ' . e(t('help_print_pics'))) . '</a>';
  require BASE_DIR . '/app/views/intern/_printbar.php';
?>
<div class="sheet">
  <?= print_watermark_html($printDoc) ?>
  <div class="head-row">
    <div>
      <h1><?= e(t('help_title')) ?></h1>
      <div class="muted"><?= e(setting('band_name')) ?> · <?= e(fmt_date(date('Y-m-d'))) ?></div>
    </div>
    <?= print_logo_html($printDoc) ?>
  </div>
<?php else: ?>
<?php require BASE_DIR . '/app/views/_header.php'; ?>
<?php endif; ?>
<?php
// Die Hilfe für Leute, die kein Programm bedienen wollen, sondern Musik machen
// (#305). Drei Regeln halten sie brauchbar:
//
//   1. Jeder Bereich beginnt mit einem Bild. Wer nicht liest, sieht wenigstens,
//      wie die Stelle aussieht, um die es geht.
//   2. Der erste Satz beantwortet „wofür ist das?" und steht deshalb größer.
//   3. Ganz oben steht „Ich möchte …", denn wer eine Frage hat, kennt selten
//      den Namen des Bereichs, in dem die Antwort wohnt.
//
// Gezeigt wird nur, was diesem Konto offensteht. Eine Hilfe, die von Knöpfen
// erzählt, die es für den Lesenden nicht gibt, verunsichert mehr, als sie hilft.
?>
<?= help_figure_defs() ?>
<?php if (!$druck): ?><h1>❓ <?= e(t('help_title')) ?></h1><?php endif; ?>
<p class="muted"><?= e(t('help_intro')) ?></p>
<p class="muted"><?= e(t('help_intro2')) ?></p>

<?php // „Ich möchte …" — Einstieg über das Vorhaben statt über den Bereichsnamen. ?>
<h2><?= e(t('help_tasks_title')) ?></h2>
<p class="muted small"><?= e(t('help_tasks_hint')) ?></p>
<ul class="help-tasks">
  <?php foreach (HELP_TASKS as [$taskMod, $taskKey, $taskAnker]): ?>
    <?php if ($taskMod !== '' && !perm_allows($user, $taskMod)) continue; ?>
    <?php // Die beiden Einträge ohne Bereich betreffen das eigene Gerät und
          // stehen jedem offen; sie tragen deshalb ein eigenes Zeichen. ?>
    <?php $taskIcon = $taskMod !== '' ? (MODULE_ICONS[$taskMod] ?? '•') : ($taskAnker === 'hilfe-app' ? '📱' : '🔔'); ?>
    <?php if ($taskAnker === 'hilfe-push' && !push_available()) continue; ?>
    <li><a href="#<?= e($taskAnker) ?>"><span class="tick"><?= $taskIcon ?></span> <?= e(t($taskKey)) ?></a></li>
  <?php endforeach; ?>
</ul>

<?php // Der zweite Einstieg: über den Namen, für alle, die ihn kennen (#311).
      // Sortiert wird mit der Sprache des Lesers. ?>
<details class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
  <summary>🔤 <?= e(t('help_register_title')) ?></summary>
  <ul class="help-tasks">
    <?php foreach (help_sections_sorted($user) as $regAnker => [$regIcon, $regName]): ?>
      <li><a href="#hilfe-<?= e($regAnker) ?>"><span class="tick"><?= $regIcon ?></span> <?= e($regName) ?></a></li>
    <?php endforeach; ?>
  </ul>
</details>

<?php if (!$druck): ?>
  <p class="muted small"><a href="/intern/hilfe/druck" target="_blank" rel="noopener">🖨 <?= e(t('help_print')) ?></a>
    — <?= e(t('help_print_hint')) ?></p>
<?php endif; ?>

<?php // Wie die Bereiche zusammenhängen (#310). Steht vor den Bereichen, weil
      // die häufigste Frage nicht „was ist X" lautet, sondern „warum steht das
      // hier und nicht dort". Nur die Absätze, deren Bereich offensteht. ?>
<details id="hilfe-zusammenhang" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
  <summary>🔗 <?= e(t('help_flow_title')) ?></summary>
  <?php if (!$druck || $druckBilder) echo help_figure('flow'); ?>
  <p class="help-lead"><?= e(t('help_flow_intro')) ?></p>
  <?php foreach ([['termine', 'help_flow_gig'], ['setlists', 'help_flow_setlist'],
                  ['gaeste', 'help_flow_guest'], ['termine', 'help_flow_booking'],
                  ['mitglieder', 'help_flow_rights'], ['kasse', 'help_flow_money']] as [$flowMod, $flowKey]): ?>
    <?php if (!perm_allows($user, $flowMod)) continue; ?>
    <p class="muted"><?= e(t($flowKey)) ?></p>
  <?php endforeach; ?>
</details>

<?php $helpFirst = true; ?>
<?php foreach (PERM_MODULES as $helpMod => $helpPfade): ?>
  <?php if (!perm_allows($user, $helpMod)) continue; ?>
  <details id="hilfe-<?= e($helpMod) ?>" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck || $helpFirst ? 'open' : '' ?>>
    <summary><?= MODULE_ICONS[$helpMod] ?? '' ?> <?= e(t('inav_' . $helpMod)) ?></summary>
    <?php if (!$druck || $druckBilder) echo help_picture($helpMod, t('inav_' . $helpMod)); ?>
    <p class="help-lead"><?= e(t('help_' . $helpMod)) ?></p>
    <?php // Bis zu drei Zusatzabsätze: Ein Bereich wächst, und jeder neue Absatz
          // ist ein neuer Schlüssel — so bleibt der alte Text samt seinen
          // Übersetzungen stehen. Fehlt der Schlüssel, liefert t() ihn selbst
          // zurück, und dann steht hier nichts (#276, #298). ?>
    <?php foreach (['_2', '_3', '_4'] as $helpNr): ?>
      <?php $helpMehr = t('help_' . $helpMod . $helpNr); ?>
      <?php if ($helpMehr !== 'help_' . $helpMod . $helpNr): ?>
        <p class="muted"><?= e($helpMehr) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php // Der Weg dorthin, direkt aus der Erklärung heraus. „E-Mail-Versand"
          // hat keine eigene Seite und deshalb keinen Link. ?>
    <?php if (!empty($helpPfade[0])): ?>
      <p class="muted small"><a href="<?= e($helpPfade[0]) ?>"><?= e(t('help_open')) ?> →</a></p>
    <?php endif; ?>
  </details>
  <?php $helpFirst = false; ?>
<?php endforeach; ?>

<?php // Den Jahresbericht hat jeder, der die Kasse sieht — die eigenen Zahlen
      // hängen nicht daran, ob die Band die Kleinunternehmerregelung nutzt. ?>
<?php if (perm_allows($user, 'kasse')): ?>
  <details id="hilfe-steuer" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
    <summary>⚖ <?= e(t('taxr_title')) ?></summary>
    <?php if (!$druck || $druckBilder) echo help_picture('steuer', t('taxr_title')); ?>
    <p class="help-lead"><?= e(t('help_taxr_what')) ?></p>
    <p class="muted"><?= e(t('help_taxr_scope')) ?></p>
    <p class="muted"><?= e(t('help_taxr_afa')) ?></p>
    <p class="muted"><?= e(t('help_taxr_shares')) ?></p>
    <?php // Rechtsform und Haftung hängen nicht an der Kleinunternehmerregelung
          // — sie gelten so oder so, deshalb stehen sie hier und nicht dort. ?>
    <h3><?= e(t('help_gbr_title')) ?></h3>
    <p class="muted"><?= e(t('help_gbr_form')) ?></p>
    <p class="muted">⚠ <?= e(t('help_gbr_liability')) ?></p>
    <p class="muted"><?= e(t('help_gbr_register')) ?></p>
    <p class="muted">📦 <?= e(t('taxr_package_hint')) ?></p>
    <p class="muted small">⚖ <?= e(t('tax_no_advice')) ?></p>
    <p class="muted small"><a href="/intern/kasse/steuer"><?= e(t('taxr_open')) ?> →</a></p>
  </details>
<?php endif; ?>

<?php // Nur wer die Kasse sieht, und nur wenn die Band die Regelung nutzt —
      // sonst erklärt die Hilfe etwas, das nirgends vorkommt. ?>
<?php if (perm_allows($user, 'kasse') && setting('tax_small_business', '0') === '1'): ?>
  <details id="hilfe-kleinunternehmer" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
    <summary>⚖ <?= e(t('help_tax_title')) ?></summary>
    <p class="help-lead"><?= e(t('help_tax_what')) ?></p>
    <p class="muted"><?= e(t('help_tax_limits')) ?></p>
    <p class="muted"><?= e(t('help_tax_band')) ?></p>
    <p class="muted"><?= e(t('help_tax_counts')) ?></p>
    <p class="muted"><?= e(t('help_tax_over')) ?></p>
    <p class="muted"><?= e(t('help_tax_next_year')) ?></p>
    <p class="muted"><?= e(t('help_tax_first_year')) ?></p>
    <p class="muted"><?= e(t('help_tax_back')) ?></p>
    <p class="muted"><?= e(t('help_tax_changed')) ?></p>
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

<?php // Wenn Mitteilungen ausbleiben, liegt es fast nie an der Anwendung —
      // sondern an einem von vier Schaltern, die alle stumm sperren. Die
      // Reihenfolge ist die Suchreihenfolge: von innen nach außen. ?>
<?php if (push_available()): ?>
  <details id="hilfe-push" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
    <summary>🔕 <?= e(t('help_push_trouble_title')) ?></summary>
    <?php if (!$druck || $druckBilder) echo help_picture('push', t('help_push_trouble_title')); ?>
    <p class="help-lead"><?= e(t('help_push_trouble_intro')) ?></p>
    <ul class="task-list">
      <li><?= e(t('help_push_trouble_app')) ?></li>
      <li><?= e(t('help_push_trouble_site')) ?></li>
      <li><?= e(t('help_push_trouble_browser')) ?></li>
      <li><?= e(t('help_push_trouble_os')) ?></li>
    </ul>
    <p class="muted">📱 <?= e(t('help_push_trouble_ios')) ?></p>
    <p class="muted">🔄 <?= e(t('help_push_trouble_dead')) ?></p>
    <p class="muted small"><a href="/intern/profil#mitteilungen"><?= e(t('prof_push')) ?> →</a></p>
  </details>
<?php endif; ?>

<?php // Zweiter Faktor: erklärt, solange es ihn hier gibt — oder solange
      // dieses Konto noch einen hat und die Abfrage kennt. ?>
<?php // totp_active() statt totp_active_for($user): $user kommt aus
      // current_user() und trägt die Felder des zweiten Faktors nicht mit. ?>
<?php if (totp_available() || totp_active((int) $user['id'])): ?>
  <details id="hilfe-totp" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
    <summary>🔑 <?= e(t('help_totp_title')) ?></summary>
    <?php if (!$druck || $druckBilder) echo help_picture('totp', t('help_totp_title')); ?>
    <p class="help-lead"><?= e(t('help_totp_what')) ?></p>
    <p class="muted"><?= e(t('help_totp_apps')) ?></p>
    <p class="muted"><?= e(t('help_totp_setup')) ?></p>
    <p class="muted">📄 <?= e(t('help_totp_recovery')) ?></p>
    <?php if (passkey_available()): ?><p class="muted">🔐 <?= e(t('help_totp_passkey')) ?></p><?php endif; ?>
    <p class="muted">⏰ <?= e(t('help_totp_clock')) ?></p>
    <?php if (perm_allows($user, 'mitglieder')): ?><p class="muted">⚙ <?= e(t('help_totp_admin')) ?></p><?php endif; ?>
    <p class="muted small"><a href="/intern/zwei-faktor"><?= e(t('totp_title')) ?> →</a></p>
  </details>
<?php endif; ?>

<?php if (passkey_available()): ?>
  <details id="hilfe-passkey" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
    <summary>🔐 <?= e(t('help_passkey_title')) ?></summary>
    <?php if (!$druck || $druckBilder) echo help_picture('passkey', t('help_passkey_title')); ?>
    <p class="help-lead"><?= e(t('help_passkey')) ?></p>
    <p class="muted">☁ <?= e(t('help_passkey_sync')) ?></p>
    <p class="muted small"><a href="/intern/profil"><?= e(t('prof_passkeys')) ?> →</a></p>
  </details>
<?php endif; ?>

<details id="hilfe-app" class="card acc" <?= $druck ? '' : 'name="helpacc"' ?> <?= $druck ? 'open' : '' ?>>
  <summary>📱 <?= e(t('app_install')) ?></summary>
  <?php if (!$druck || $druckBilder) echo help_picture('app', t('app_install')); ?>
  <p class="help-lead"><?= e(t('app_install_hint')) ?></p>
  <p class="muted"><?= e(t('app_install_offline')) ?></p>
  <p class="muted"><?= e(t('app_install_store')) ?></p>
  <p class="muted"><?= e(t('off_help')) ?></p>
  <?php // Mitteilungen nur erklären, wenn es sie hier auch gibt — sonst schickt
        // die Hilfe die Leute in einen Bereich, den sie im Profil nicht finden. ?>
  <?php if (push_available()): ?>
    <p class="muted"><?= e(t('app_install_push')) ?></p>
    <p class="muted"><?= e(t('app_install_badge')) ?></p>
  <?php endif; ?>
</details>


<?php if ($druck): ?>
</div>
</body>
</html>
<?php else: ?>
<p class="muted small"><?= e(t('help_more')) ?> <a href="/intern/ueber"><?= e(t('about_open')) ?> →</a></p>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
<?php endif; ?>
