<?php
require BASE_DIR . '/app/views/_header.php';
require_once BASE_DIR . '/app/views/_flags.php';
$activeLangs = enabled_langs();
// Wert eines mehrsprachigen Feldes: Deutsch aus settings, andere Sprachen aus translations
$txtVal = fn(string $lang, string $key): string => $lang === 'de' ? ($settings[$key] ?? '') : ($contentAll[$lang]['content_' . $key] ?? '');
$impressumDefault = "Angaben gemäß § 5 DDG\n\n" . $settings['band_name'] . "\n[Straße Hausnummer]\n[PLZ Ort]\n\nKontakt:\nE-Mail: " . ($settings['contact_email'] ?: '[E-Mail-Adresse]') . "\nTelefon: [Telefonnummer]\n\nInhaltlich verantwortlich (§ 18 Abs. 2 MStV):\n" . $settings['band_name'] . ", Anschrift wie oben\n\nTechnische Umsetzung, Administration & Webspace:\n[Vorname Nachname]";
$privacyDefault = "Datenschutzerklärung\n\n1. Verantwortlicher\nVerantwortlich für die Datenverarbeitung auf dieser Website:\n" . $settings['band_name'] . ", [Straße Hausnummer], [PLZ Ort], E-Mail: " . ($settings['contact_email'] ?: '[E-Mail-Adresse]') . "\n\n2. Hosting und Server-Logfiles\nBeim Aufruf dieser Website verarbeitet unser Server automatisch Informationen (sog. Server-Logfiles: IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browsertyp), die dein Browser übermittelt. Diese Daten dienen der Sicherstellung eines störungsfreien Betriebs (Art. 6 Abs. 1 lit. f DSGVO) und werden nach [Speicherdauer] gelöscht.\nServer-Infrastruktur: [Name und Anschrift des Hosters]\n\n3. Cookies\nDiese Website verwendet ausschließlich ein technisch notwendiges Session-Cookie für den passwortgeschützten Bandbereich (§ 25 Abs. 2 Nr. 2 TDDDG).\n\n4. Deine Rechte\nDu hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch (Art. 15–21 DSGVO) sowie Beschwerde bei einer Aufsichtsbehörde (Art. 77 DSGVO).";
?>
<h1><?= e(t('inav_einstellungen')) ?></h1>

<details class="card acc" name="setacc" open>
  <summary><?= e(t('set_bandprofile')) ?></summary>
  <form method="post" action="/intern/einstellungen" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('set_bandname')) ?><input name="band_name" value="<?= e($settings['band_name']) ?>" required></label>
    <label><?= e(t('set_contact_email')) ?><input type="email" name="contact_email" value="<?= e($settings['contact_email']) ?>"></label>
    <label class="span2"><?= e(t('set_site_url')) ?>
      <input name="site_url" value="<?= e($settings['site_url'] ?? '') ?>" placeholder="https://<?= e($_SERVER['HTTP_HOST'] ?? 'example.de') ?>">
      <span class="muted small"><?= e(t('set_site_url_hint')) ?></span>
    </label>
    <label class="span2"><?= e(t('set_copyright')) ?>
      <input name="copyright_text" value="<?= e($settings['copyright_text'] ?? '') ?>"
             placeholder="© <?= date('Y') ?> <?= e($settings['band_name']) ?>">
      <span class="muted small"><?= e(t('set_copyright_hint')) ?></span>
    </label>
    <label>Facebook<input name="facebook_url" value="<?= e($settings['facebook_url']) ?>" placeholder="https://facebook.com/..."></label>
    <label>Instagram<input name="instagram_url" value="<?= e($settings['instagram_url']) ?>" placeholder="https://instagram.com/..."></label>
    <label>Spotify<input name="spotify_url" value="<?= e($settings['spotify_url']) ?>" placeholder="https://open.spotify.com/artist/..."></label>
    <label>YouTube<input name="youtube_url" value="<?= e($settings['youtube_url']) ?>" placeholder="https://youtube.com/@..."></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_texts')) ?></summary>
  <p class="muted small"><?= e(t('set_texts_hint')) ?></p>
  <form method="post" action="/intern/einstellungen" class="stack"><?= csrf_field() ?>
    <input type="hidden" name="_texts_form" value="1">
    <?php foreach ($activeLangs as $lang): ?>
      <details class="subsection lang-block" <?= $lang === 'de' ? 'open' : '' ?>>
        <summary><?= flag_svg($lang) ?> <strong><?= LANGS[$lang] ?></strong><?= $lang === 'de' ? ' <span class="muted small">(Standard / Fallback)</span>' : '' ?></summary>
        <div class="form-grid">
          <label><?= e(t('set_tagline')) ?><input name="txt[<?= $lang ?>][tagline]" value="<?= e($txtVal($lang, 'tagline')) ?>" <?= $lang !== 'de' ? 'placeholder="' . e($settings['tagline']) . '"' : '' ?>></label>
          <label><?= e(t('set_booking')) ?><input name="txt[<?= $lang ?>][booking_text]" value="<?= e($txtVal($lang, 'booking_text')) ?>" <?= $lang !== 'de' ? 'placeholder="' . e($settings['booking_text']) . '"' : '' ?>></label>
          <label class="span2"><?= e(t('set_about')) ?><textarea name="txt[<?= $lang ?>][bio]" rows="5" <?= $lang !== 'de' ? 'placeholder="' . e(mb_substr($settings['bio'], 0, 120)) . ' …"' : '' ?>><?= e($txtVal($lang, 'bio')) ?></textarea></label>
        </div>
      </details>
    <?php endforeach; ?>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_public')) ?></summary>
  <form method="post" action="/intern/einstellungen" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="_termine_form" value="1">
    <label class="span2"><?= e(t('set_pm')) ?>
      <select name="public_mode">
        <option value="website" <?= ($settings['public_mode'] ?? 'website') === 'website' ? 'selected' : '' ?>><?= e(t('set_pm_website')) ?></option>
        <option value="redirect" <?= ($settings['public_mode'] ?? '') === 'redirect' ? 'selected' : '' ?>><?= e(t('set_pm_redirect')) ?></option>
      </select>
    </label>
    <label class="span2"><?= e(t('set_redirect_target')) ?><input name="redirect_url" value="<?= e($settings['redirect_url'] ?? '') ?>" placeholder="https://www.facebook.com/your-band"></label>
    <label class="checkbox span2"><input type="checkbox" name="public_show_past" value="1" <?= $settings['public_show_past'] === '1' ? 'checked' : '' ?>> <?= e(t('set_show_past')) ?></label>
    <label><?= e(t('set_max_upcoming')) ?><input type="number" name="public_limit_upcoming" min="0" value="<?= e($settings['public_limit_upcoming']) ?>"></label>
    <label><?= e(t('set_max_past')) ?><input type="number" name="public_limit_past" min="0" value="<?= e($settings['public_limit_past']) ?>"></label>
    <label class="span2"><?= e(t('set_embed')) ?>
      <select name="public_embed_mode">
        <option value="consent" <?= ($settings['public_embed_mode'] ?? 'consent') === 'consent' ? 'selected' : '' ?>><?= e(t('set_embed_consent')) ?></option>
        <option value="direct" <?= ($settings['public_embed_mode'] ?? '') === 'direct' ? 'selected' : '' ?>><?= e(t('set_embed_direct')) ?></option>
      </select>
    </label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_branding')) ?></summary>
  <form method="post" action="/intern/einstellungen/branding" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('set_logo_lbl')) ?><input type="file" name="logo" accept="image/*"></label>
    <label><?= e(t('set_bg_lbl')) ?><input type="file" name="background" accept="image/*"></label>
    <label><?= e(t('set_favicon_lbl')) ?><input type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml">
      <span class="muted small"><?= e(t('set_favicon_hint')) ?></span></label>
    <button class="btn btn-primary span2"><?= e(t('upload')) ?></button>
  </form>
  <div class="row-buttons">
    <?php if (!empty($settings['logo_file'])): ?>
      <img src="/uploads/<?= e($settings['logo_file']) ?>" alt="Logo" style="max-height:60px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/logo/delete"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(t('set_logo_remove')) ?></button></form>
    <?php endif; ?>
    <?php if (!empty($settings['background_file'])): ?>
      <img src="/uploads/<?= e($settings['background_file']) ?>" alt="Hintergrund" style="max-height:60px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/background/delete"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(t('set_bg_remove')) ?></button></form>
    <?php endif; ?>
    <?php if (!empty($settings['favicon_file'])): ?>
      <img src="/uploads/<?= e($settings['favicon_file']) ?>" alt="Favicon" style="max-height:32px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/favicon/delete"><?= csrf_field() ?><button class="btn btn-tiny btn-danger"><?= e(t('set_favicon_remove')) ?></button></form>
      <?php // Das Favicon ist auch das Symbol auf dem Startbildschirm. Ein
            // Browsertab kommt mit 64 Pixeln aus, eine Kachel auf dem Handy
            // nicht — und hochrechnen kann das niemand. ?>
      <?php $favInfo = @getimagesize(UPLOADS_DIR . '/' . $settings['favicon_file']); ?>
      <?php if ($favInfo && min($favInfo[0], $favInfo[1]) < 192): ?>
        <p class="warn small"><?= e(sprintf(t('set_favicon_small'), $favInfo[0], $favInfo[1])) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_meta')) ?></summary>
  <p class="muted"><?= e(t('set_meta_hint')) ?></p>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_legal')) ?></summary>
  <p class="muted small"><?= e(t('set_legal_hint')) ?></p>
  <form method="post" action="/intern/einstellungen" class="stack"><?= csrf_field() ?>
    <input type="hidden" name="_legal_form" value="1">
    <?php foreach ($activeLangs as $lang): ?>
      <details class="subsection lang-block" <?= $lang === 'de' ? 'open' : '' ?>>
        <summary><?= flag_svg($lang) ?> <strong><?= LANGS[$lang] ?></strong><?= $lang === 'de' ? ' <span class="muted small">(Standard / Fallback)</span>' : '' ?></summary>
        <div class="form-grid">
          <label class="span2"><?= e(t('nav_impressum')) ?><textarea name="txt[<?= $lang ?>][impressum_text]" rows="10"><?= e($lang === 'de' ? ($settings['impressum_text'] ?: $impressumDefault) : $txtVal($lang, 'impressum_text')) ?></textarea></label>
          <label class="span2"><?= e(t('privacy_title')) ?><textarea name="txt[<?= $lang ?>][privacy_text]" rows="14"><?= e($lang === 'de' ? ($settings['privacy_text'] ?: $privacyDefault) : $txtVal($lang, 'privacy_text')) ?></textarea></label>
        </div>
      </details>
    <?php endforeach; ?>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary><?= e(t('set_langs')) ?></summary>
  <p class="muted small"><?= e(t('set_langs_hint')) ?> <a href="/intern/uebersetzungen"><?= e(t('set_langs_check')) ?> →</a></p>
  <form method="post" action="/intern/einstellungen" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="_langs_form" value="1">
    <label class="span2"><?= e(t('set_default_lang')) ?>
      <?php // Alle Sprachen zur Wahl: eine neu gewählte Standardsprache
            // schaltet sich beim Speichern selbst ein. Sonst müsste man erst
            // aktivieren, speichern und dann noch einmal auswählen. ?>
      <select name="default_lang">
        <?php foreach (LANGS as $code => $name): ?>
          <option value="<?= $code ?>" <?= default_lang() === $code ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="muted small span2"><?= e(t('set_default_lang_hint')) ?></p>
    <div class="span2 row-buttons">
      <?php // Die Standardsprache lässt sich nicht abwählen — sie ist die
            // Rückfallebene, wenn keine andere passt. ?>
      <?php foreach (LANGS as $code => $name): ?>
        <label class="checkbox">
          <input type="checkbox" name="langs[]" value="<?= $code ?>" <?= in_array($code, $activeLangs, true) ? 'checked' : '' ?> <?= $code === default_lang() ? 'disabled' : '' ?>>
          <?= flag_svg($code) ?> <?= $name ?>
          <?php if ($code === default_lang()): ?><span class="muted small">(<?= e(t('set_langs_default_locked')) ?>)</span><?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary>💰 <?= e(t('set_fin')) ?></summary>
  <form method="post" action="/intern/einstellungen/kasse" class="form-grid"><?= csrf_field() ?>
    <label class="checkbox span2"><input type="checkbox" name="fin_open_fees" value="1" <?= setting('fin_open_fees') === '1' ? 'checked' : '' ?>> <?= e(t('set_fin_open_fees')) ?></label>
    <p class="muted small span2"><?= e(t('set_fin_open_fees_hint')) ?></p>
    <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary>🔁 <?= e(t('set_sub_auto')) ?></summary>
  <p class="muted small"><?= e(t('set_sub_auto_hint')) ?></p>
  <form method="post" action="/intern/einstellungen/ersatz" class="form-grid"><?= csrf_field() ?>
    <label><?= e(t('set_sub_auto')) ?>
      <select name="substitute_auto">
        <?php foreach (SUB_AUTO_MODES as $mode): ?>
          <option value="<?= $mode ?>" <?= (setting('substitute_auto') ?: 'off') === $mode ? 'selected' : '' ?>><?= e(t('sub_auto_' . $mode)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary>💾 <?= e(t('bk_title')) ?></summary>
  <p class="muted small"><?= e(t('bk_content')) ?></p>
  <?php
    $bkLast = null;
    foreach ($backupRuns as $bkRun) { if ($bkRun['status'] === 'ok') { $bkLast = $bkRun; break; } }
    $bkEvery = BACKUP_INTERVALS[setting('backup_interval') ?: 'daily'] ?? 86400;
  ?>
  <?php if (setting('backup_enabled') === '1' && ($backupRuns[0]['status'] ?? '') === 'error'): ?>
    <p class="warn">⚠ <?= e(t('bk_warn_failed')) ?> <?= e($backupRuns[0]['message']) ?></p>
  <?php elseif (setting('backup_enabled') === '1' && $bkLast && (time() - strtotime($bkLast['created_at'])) > $bkEvery * 2): ?>
    <p class="warn">⚠ <?= e(t('bk_warn_old')) ?></p>
  <?php endif; ?>
  <?php if (isset($backupRuns[0]) && $backupRuns[0]['ftp_ok'] !== null && !(int) $backupRuns[0]['ftp_ok']): ?>
    <p class="warn">⚠ <?= e(t('bk_warn_ftp')) ?> <?= e($backupRuns[0]['message']) ?></p>
  <?php endif; ?>

  <form method="post" action="/intern/einstellungen/backup" class="form-grid"><?= csrf_field() ?>
    <label class="checkbox span2"><input type="checkbox" name="backup_enabled" value="1" <?= setting('backup_enabled') === '1' ? 'checked' : '' ?>> <?= e(t('bk_enabled')) ?></label>
    <label><?= e(t('bk_interval')) ?>
      <select name="backup_interval">
        <?php foreach (['daily' => t('bk_daily'), 'weekly' => t('bk_weekly')] as $bkVal => $bkLbl): ?>
          <option value="<?= $bkVal ?>" <?= (setting('backup_interval') ?: 'daily') === $bkVal ? 'selected' : '' ?>><?= e($bkLbl) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('bk_keep')) ?><input type="number" name="backup_keep" min="1" max="365" value="<?= e((string) backup_keep()) ?>"></label>
    <p class="muted small span2"><?= e(t('bk_keep_hint')) ?></p>
    <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
  </form>

  <p class="muted small"><?= e(t('bk_auto_hint')) ?> <code>php <?= e(BASE_DIR) ?>/app/backup.php</code></p>

  <h3><?= e(t('bk_targets')) ?></h3>
  <?php $ftp = backup_ftp_config(); ?>
  <p class="muted small">📁 <strong><?= e(t('bk_target_local')) ?></strong> — <?= e(t('bk_target_local_hint')) ?></p>

  <form method="post" action="/intern/einstellungen/backup-ziele" class="form-grid"><?= csrf_field() ?>
    <label class="checkbox span2"><input type="checkbox" name="backup_ftp_enabled" value="1" <?= $ftp['enabled'] ? 'checked' : '' ?>> 🖧 <?= e(t('bk_ftp_enabled')) ?></label>
    <label><?= e(t('bk_ftp_host')) ?><input name="backup_ftp_host" value="<?= e($ftp['host']) ?>" placeholder="ftp.example.com"></label>
    <label><?= e(t('bk_ftp_port')) ?><input type="number" name="backup_ftp_port" min="1" max="65535" value="<?= (int) $ftp['port'] ?>"></label>
    <label><?= e(t('bk_ftp_user')) ?><input name="backup_ftp_user" value="<?= e($ftp['user']) ?>" autocomplete="off"></label>
    <label><?= e(t('bk_ftp_pass')) ?>
      <input type="password" name="backup_ftp_pass" autocomplete="new-password"
             placeholder="<?= $ftp['pass'] !== '' ? e(t('bk_ftp_pass_set')) : '' ?>">
    </label>
    <label><?= e(t('bk_ftp_dir')) ?><input name="backup_ftp_dir" value="<?= e($ftp['dir']) ?>" placeholder="/backups/bandregie"></label>
    <label><?= e(t('bk_ftp_keep')) ?><input type="number" name="backup_ftp_keep" min="1" max="365" value="<?= (int) $ftp['keep'] ?>"></label>
    <label class="checkbox"><input type="checkbox" name="backup_ftp_tls" value="1" <?= $ftp['tls'] ? 'checked' : '' ?>> 🔒 <?= e(t('bk_ftp_tls')) ?></label>
    <label class="checkbox"><input type="checkbox" name="backup_ftp_passive" value="1" <?= $ftp['passive'] ? 'checked' : '' ?>> <?= e(t('bk_ftp_passive')) ?></label>
    <p class="muted small span2">🔑 <?= e(t('bk_ftp_note')) ?></p>
    <div class="span2 row-buttons"><button class="btn btn-primary"><?= e(t('save')) ?></button></div>
  </form>
  <form method="post" action="/intern/backup/ftp-test" class="inline"><?= csrf_field() ?>
    <button class="btn"><?= e(t('bk_ftp_test')) ?></button>
  </form>

  <p class="muted small">☁ <strong><?= e(t('bk_target_onedrive')) ?></strong> — <?= e(t('bk_onedrive_pending')) ?></p>

  <form method="post" action="/intern/backup/run" class="inline"><?= csrf_field() ?>
    <button class="btn"><?= e(t('bk_run_now')) ?></button>
  </form>

  <h3><?= e(t('bk_runs')) ?></h3>
  <?php if (!$backupRuns): ?><p class="muted small"><?= e(t('bk_none')) ?></p><?php endif; ?>
  <ul class="task-list">
    <?php foreach ($backupRuns as $bkRun): ?>
      <li>
        <span class="badge <?= $bkRun['status'] === 'ok' ? '' : 'ev-abgesagt' ?>"><?= e($bkRun['status'] === 'ok' ? t('bk_status_ok') : t('bk_status_error')) ?></span>
        <span class="muted"><?= e($bkRun['created_at']) ?></span>
        <?php if ($bkRun['filename'] !== ''): ?>
          <a href="/intern/backup/<?= $bkRun['id'] ?>/download"><?= e($bkRun['filename']) ?></a>
          <span class="muted small"><?= e(fmt_bytes((int) $bkRun['size_bytes'])) ?> · <?= e($bkRun['trigger_kind']) ?></span>
        <?php elseif ($bkRun['status'] === 'ok'): ?>
          <span class="muted small"><?= e(t('bk_gone')) ?></span>
        <?php endif; ?>
        <?php if ($bkRun['message'] !== ''): ?><span class="muted small"><?= e($bkRun['message']) ?></span><?php endif; ?>
        <?php if ($bkRun['filename'] !== ''): ?>
          <form method="post" action="/intern/backup/<?= $bkRun['id'] ?>/restore" class="inline" data-confirm="<?= e(t('bk_restore_confirm')) ?>"><?= csrf_field() ?>
            <button class="btn btn-tiny">⏪ <?= e(t('bk_restore')) ?></button>
          </form>
        <?php endif; ?>
        <form method="post" action="/intern/backup/<?= $bkRun['id'] ?>/delete" class="inline" data-confirm="<?= e(t('confirm_delete')) ?>"><?= csrf_field() ?>
          <button class="btn btn-tiny btn-danger">🗑</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>

  <h3><?= e(t('bk_restore')) ?></h3>
  <p class="muted small">⏪ <?= e(t('bk_restore_hint')) ?></p>
  <p class="muted small"><?= e(t('bk_restore_cli')) ?> <code>php <?= e(BASE_DIR) ?>/app/backup.php restore &lt;archiv.tar.gz&gt;</code></p>
  <form method="post" action="/intern/backup/upload" enctype="multipart/form-data" class="comment-form"><?= csrf_field() ?>
    <input type="file" name="archive" accept=".gz,.enc,application/gzip" required>
    <button class="btn btn-small"><?= e(t('bk_upload')) ?></button>
  </form>
  <p class="muted small"><?= e(t('bk_upload_hint')) ?></p>
</details>

<?php // Verschlüsselung ruhender Daten. Die Seite sagt beides: was geschützt
      // ist und was nicht — ein Halbsatz „verschlüsselt" ohne Grenze wäre eine
      // Beruhigung und keine Auskunft. ?>
<details class="card acc" name="setacc">
  <summary>🔐 <?= e(t('set_crypt')) ?></summary>
  <?php $cryptOn = crypt_available(); $cryptTest = $cryptOn ? crypt_selftest() : null; ?>
  <p class="<?= $cryptOn ? 'muted' : 'warn' ?>">
    <strong><?= e($cryptOn ? t('set_crypt_on') : t('set_crypt_off')) ?></strong>
  </p>
  <?php if ($cryptOn): ?>
    <p class="muted small"><?= e(t('set_crypt_scope')) ?></p>
    <p class="<?= $cryptTest['ok'] ? 'muted' : 'warn' ?> small">
      <?= e(sprintf(t('set_crypt_test'), $cryptTest['message'])) ?>
    </p>
    <?php $sealCount = count(array_filter(glob(FILES_DIR . '/*') ?: [], fn($p) => is_file($p) && !crypt_is_sealed($p))); ?>
    <?php if ($sealCount > 0): ?>
      <p class="warn small"><?= e(sprintf(t('set_crypt_plain_files'), $sealCount)) ?></p>
      <form method="post" action="/intern/dateien/versiegeln" class="inline"><?= csrf_field() ?>
        <button class="btn btn-small">🔐 <?= e(t('set_crypt_seal_now')) ?></button>
      </form>
    <?php else: ?>
      <p class="muted small"><?= e(t('set_crypt_files_done')) ?></p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted small"><?= e(t('set_crypt_how')) ?></p>
    <p class="muted small"><code>php <?= e(BASE_DIR) ?>/app/backup.php key</code></p>
    <p class="muted small">⚠ <?= e(t('set_crypt_lost')) ?></p>
  <?php endif; ?>
  <p class="muted small"><?= e(t('set_crypt_law')) ?></p>
</details>

<?php require_once BASE_DIR . '/app/steuer.php'; ?>
<details class="card acc" name="setacc">
  <summary>⚖ <?= e(t('set_tax')) ?></summary>
  <p class="muted small"><?= e(t('set_tax_hint')) ?></p>
  <form method="post" action="/intern/einstellungen" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="_tax_form" value="1">
    <label class="checkbox span2">
      <input type="checkbox" name="tax_small_business" value="1" <?= setting('tax_small_business', '0') === '1' ? 'checked' : '' ?>>
      <?= e(t('set_tax_small')) ?>
    </label>
    <p class="muted small span2"><?= e(t('set_tax_small_hint')) ?></p>
    <label><?= e(t('set_tax_prev')) ?><input name="tax_limit_prev_year" inputmode="decimal" value="<?= e(setting('tax_limit_prev_year', '25000')) ?>"></label>
    <label><?= e(t('set_tax_this')) ?><input name="tax_limit_this_year" inputmode="decimal" value="<?= e(setting('tax_limit_this_year', '100000')) ?>"></label>
    <label><?= e(t('set_tax_gwg')) ?><input name="tax_gwg_limit" inputmode="decimal" value="<?= e(setting('tax_gwg_limit', '800')) ?>">
      <span class="muted small"><?= e(t('set_tax_gwg_hint')) ?></span>
    </label>
    <label><?= e(t('set_tax_afa_years')) ?><input name="tax_afa_years" inputmode="numeric" value="<?= e(setting('tax_afa_years', '7')) ?>">
      <span class="muted small"><?= e(t('set_tax_afa_hint')) ?></span>
    </label>
    <label><?= e(t('set_tax_comm_share')) ?><input name="tax_commercial_share" inputmode="decimal" value="<?= e(setting('tax_commercial_share', '3')) ?>"></label>
    <label><?= e(t('set_tax_comm_abs')) ?><input name="tax_commercial_abs" inputmode="decimal" value="<?= e(setting('tax_commercial_abs', '24500')) ?>"></label>
    <p class="muted small span2"><?= e(t('set_tax_comm_hint')) ?></p>
    <label class="span2"><?= e(t('set_tax_checked')) ?>
      <input type="date" name="tax_values_checked" value="<?= e(setting('tax_values_checked', '')) ?>">
    </label>
    <p class="muted small span2"><?= e(t('set_tax_source')) ?></p>
    <p class="muted small span2">⚖ <?= e(t('tax_no_advice')) ?></p>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</details>

<?php require_once BASE_DIR . '/app/update.php'; $upCmd = update_command(); $upLatest = update_latest_version(); ?>
<details class="card acc" name="setacc" <?= update_available() ? 'open' : '' ?>>
  <summary>⬆ <?= e(t('up_title')) ?><?= update_available() ? ' — ' . e(sprintf(t('up_available'), $upLatest)) : '' ?></summary>
  <p class="muted small"><?= e(t('up_intro')) ?></p>
  <ul class="task-list">
    <li><strong><?= e(t('up_installed')) ?></strong> <span class="muted"><?= e(BANDREGIE_VERSION) ?></span></li>
    <li>
      <strong><?= e(t('up_latest')) ?></strong>
      <span class="<?= update_available() ? 'warn' : 'muted' ?>"><?= e($upLatest ?: t('up_unknown')) ?></span>
    </li>
  </ul>

  <?php // Der Weg, der auf dieser Maschine funktioniert — nicht der, der
        // im Lehrbuch steht. ?>
  <?php if ($upCmd['kind'] === 'manual'): ?>
    <p class="muted small"><?= e(t('up_manual')) ?></p>
  <?php else: ?>
    <p class="muted small"><?= e($upCmd['kind'] === 'plesk' ? t('up_how_plesk') : t('up_how_git')) ?></p>
    <pre class="prewrap"><?= e($upCmd['command']) ?></pre>
    <?php if ($upCmd['kind'] === 'git'): ?>
      <p class="muted small"><?= e(t('up_cron')) ?></p>
      <pre class="prewrap">30 4 * * 1  sh <?= e(BASE_DIR) ?>/bin/update.sh &gt;&gt; /var/log/bandregie-update.log 2&gt;&amp;1</pre>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" action="/intern/einstellungen"><?= csrf_field() ?>
    <input type="hidden" name="_update_form" value="1">
    <label class="checkbox">
      <input type="checkbox" name="update_check" value="1" <?= setting('update_check', '1') === '1' ? 'checked' : '' ?>>
      <?= e(t('up_check')) ?>
    </label>
    <p class="muted small"><?= e(t('up_check_hint')) ?></p>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</details>

<details class="card acc" name="setacc">
  <summary>🩺 <?= e(t('sys_title')) ?></summary>
  <p class="muted small"><?= e(t('sys_intro')) ?></p>
  <?php require_once BASE_DIR . '/app/systemcheck.php'; ?>
  <?php foreach (system_checks() as $sysGroup => $sysRows): ?>
    <h3><?= e($sysGroup) ?></h3>
    <ul class="task-list sys-list">
      <?php foreach ($sysRows as $sysRow): ?>
        <li class="sys-<?= e($sysRow['state']) ?>">
          <span class="sys-mark"><?= $sysRow['state'] === 'ok' ? '✔' : ($sysRow['state'] === 'warn' ? '!' : '✘') ?></span>
          <strong><?= e($sysRow['name']) ?></strong>
          <span class="muted"><?= e($sysRow['detail']) ?></span>
          <?php if ($sysRow['consequence'] !== ''): ?>
            <div class="muted small"><?= e($sysRow['consequence']) ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</details>

<?php require_once BASE_DIR . '/app/demo.php'; ?>

<details class="card acc" name="setacc">
  <summary>🧪 <?= e(t('set_demo')) ?></summary>
  <?php if (demo_installed()): ?>
    <p class="muted small"><?= e(t('set_demo_active')) ?></p>
    <form method="post" action="/intern/einstellungen/demo/remove" data-confirm="<?= e(t('set_demo_confirm')) ?>"><?= csrf_field() ?>
      <button class="btn btn-danger"><?= e(t('set_demo_remove')) ?></button>
    </form>
  <?php elseif (demo_in_real_use()): ?>
    <?php // Entfernen bleibt oben stehen, solange etwas zu entfernen ist —
          // hinzufügen gibt es nicht mehr, sobald die Installation benutzt wird. ?>
    <p class="muted small"><?= e(t('set_demo_in_use')) ?></p>
  <?php else: ?>
    <p class="muted small"><?= e(t('set_demo_hint')) ?></p>
    <form method="post" action="/intern/einstellungen/demo/add"><?= csrf_field() ?>
      <button class="btn btn-primary"><?= e(t('set_demo_add')) ?></button>
    </form>
  <?php endif; ?>
</details>

<?php require BASE_DIR . '/app/views/_footer.php'; ?>
