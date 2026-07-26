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

<div class="card">
  <h2><?= e(t('set_bandprofile')) ?></h2>
  <form method="post" action="/intern/einstellungen" class="form-grid">
    <label><?= e(t('set_bandname')) ?><input name="band_name" value="<?= e($settings['band_name']) ?>" required></label>
    <label><?= e(t('set_contact_email')) ?><input type="email" name="contact_email" value="<?= e($settings['contact_email']) ?>"></label>
    <label>Facebook<input name="facebook_url" value="<?= e($settings['facebook_url']) ?>" placeholder="https://facebook.com/..."></label>
    <label>Instagram<input name="instagram_url" value="<?= e($settings['instagram_url']) ?>" placeholder="https://instagram.com/..."></label>
    <label>Spotify<input name="spotify_url" value="<?= e($settings['spotify_url']) ?>" placeholder="https://open.spotify.com/artist/..."></label>
    <label>YouTube<input name="youtube_url" value="<?= e($settings['youtube_url']) ?>" placeholder="https://youtube.com/@..."></label>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <h2><?= e(t('set_texts')) ?></h2>
  <p class="muted small"><?= e(t('set_texts_hint')) ?></p>
  <form method="post" action="/intern/einstellungen" class="stack">
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
</div>

<div class="card">
  <h2><?= e(t('set_legal')) ?></h2>
  <p class="muted small"><?= e(t('set_legal_hint')) ?></p>
  <form method="post" action="/intern/einstellungen" class="stack">
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
</div>

<div class="card">
  <h2><?= e(t('set_public')) ?></h2>
  <form method="post" action="/intern/einstellungen" class="form-grid">
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
</div>

<div class="card">
  <h2><?= e(t('set_langs')) ?></h2>
  <p class="muted small"><?= e(t('set_langs_hint')) ?> <a href="/intern/uebersetzungen"><?= e(t('set_langs_check')) ?> →</a></p>
  <form method="post" action="/intern/einstellungen" class="form-grid">
    <input type="hidden" name="_langs_form" value="1">
    <div class="span2 row-buttons">
      <?php foreach (LANGS as $code => $name): ?>
        <label class="checkbox">
          <input type="checkbox" name="langs[]" value="<?= $code ?>" <?= in_array($code, $activeLangs, true) ? 'checked' : '' ?> <?= $code === 'de' ? 'disabled' : '' ?>>
          <?= flag_svg($code) ?> <?= $name ?>
        </label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary span2"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="card">
  <h2><?= e(t('set_branding')) ?></h2>
  <form method="post" action="/intern/einstellungen/branding" enctype="multipart/form-data" class="form-grid">
    <label><?= e(t('set_logo_lbl')) ?><input type="file" name="logo" accept="image/*"></label>
    <label><?= e(t('set_bg_lbl')) ?><input type="file" name="background" accept="image/*"></label>
    <label><?= e(t('set_favicon_lbl')) ?><input type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml"></label>
    <button class="btn btn-primary span2"><?= e(t('upload')) ?></button>
  </form>
  <div class="row-buttons">
    <?php if (!empty($settings['logo_file'])): ?>
      <img src="/uploads/<?= e($settings['logo_file']) ?>" alt="Logo" style="max-height:60px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/logo/delete"><button class="btn btn-tiny btn-danger"><?= e(t('set_logo_remove')) ?></button></form>
    <?php endif; ?>
    <?php if (!empty($settings['background_file'])): ?>
      <img src="/uploads/<?= e($settings['background_file']) ?>" alt="Hintergrund" style="max-height:60px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/background/delete"><button class="btn btn-tiny btn-danger"><?= e(t('set_bg_remove')) ?></button></form>
    <?php endif; ?>
    <?php if (!empty($settings['favicon_file'])): ?>
      <img src="/uploads/<?= e($settings['favicon_file']) ?>" alt="Favicon" style="max-height:32px">
      <form class="inline" method="post" action="/intern/einstellungen/branding/favicon/delete"><button class="btn btn-tiny btn-danger"><?= e(t('set_favicon_remove')) ?></button></form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2><?= e(t('set_media')) ?></h2>
  <p class="muted small"><?= e(t('set_media_hint')) ?></p>
  <form method="post" action="/intern/einstellungen/links" class="form-grid">
    <label><?= e(t('title_lbl')) ?><input name="title" placeholder="z. B. Live beim Stadtfest"></label>
    <label>URL<input name="url" required placeholder="https://youtu.be/... oder https://open.spotify.com/..."></label>
    <button class="btn btn-primary span2"><?= e(t('add')) ?></button>
  </form>
  <ul class="task-list">
    <?php foreach ($links as $link): ?>
      <li>
        <strong><?= e($link['title'] ?: $link['url']) ?></strong>
        <span class="muted small"><?= e($link['url']) ?></span>
        <form class="inline" method="post" action="/intern/einstellungen/links/<?= $link['id'] ?>/delete"><button class="btn btn-tiny btn-danger">🗑</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="card">
  <h2><?= e(t('set_ical')) ?></h2>
  <p><code id="ical-link"><?= e($ical_url) ?></code>
  <button class="btn btn-small" onclick="navigator.clipboard.writeText(document.getElementById('ical-link').textContent).then(() => this.textContent = '✔ <?= e(t('copied')) ?>')"><?= e(t('copy')) ?></button></p>
  <p class="muted small"><?= e(t('set_ical_hint')) ?> <a href="/intern/kalender"><?= e(t('set_ical_link')) ?> →</a></p>
</div>

<?php require_once BASE_DIR . '/app/demo.php'; ?>
<div class="card">
  <h2>🧪 <?= e(t('set_demo')) ?></h2>
  <?php if (demo_installed()): ?>
    <p class="muted small"><?= e(t('set_demo_active')) ?></p>
    <form method="post" action="/intern/einstellungen/demo/remove" onsubmit="return confirm('<?= e(t('set_demo_confirm')) ?>')">
      <button class="btn btn-danger"><?= e(t('set_demo_remove')) ?></button>
    </form>
  <?php else: ?>
    <p class="muted small"><?= e(t('set_demo_hint')) ?></p>
    <form method="post" action="/intern/einstellungen/demo/add">
      <button class="btn btn-primary"><?= e(t('set_demo_add')) ?></button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= e(t('set_meta')) ?></h2>
  <p class="muted"><?= e(t('set_meta_hint')) ?></p>
</div>
<?php require BASE_DIR . '/app/views/_footer.php'; ?>
