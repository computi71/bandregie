<?php
$isIntern = str_starts_with($path, '/intern');
// Auf den Anmeldeseiten (und beim erzwungenen Passwortwechsel) gibt es nichts
// zu navigieren — dort bleibt oben nur Logo und Sprachwahl stehen.
$hideNav = in_array($path, ['/login', '/passwort-vergessen'], true)
  || str_starts_with($path, '/passwort-reset')
  || ($path === '/intern/passwort' && !empty($user['must_change_pw']));
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e($settings['band_name']) ?></title>
  <?php if (in_array($path, ['/impressum', '/datenschutz'], true)): ?>
    <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <?php if (!empty($settings['favicon_file'])): ?>
    <link rel="icon" type="image/png" href="/uploads/<?= e($settings['favicon_file']) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/lightbox.js" defer></script>
  <script src="/assets/nav.js" defer></script>
  <?php if (!empty($settings['background_file'])): ?>
    <style>
      body {
        background-image: linear-gradient(rgba(20, 16, 13, 0.88), rgba(20, 16, 13, 0.92)), url('/uploads/<?= e($settings['background_file']) ?>');
        background-size: cover;
        background-attachment: fixed;
        background-position: center;
      }
    </style>
  <?php endif; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="/">
    <?php if (!empty($settings['logo_file'])): ?>
      <img class="brand-logo" src="/uploads/<?= e($settings['logo_file']) ?>" alt="<?= e($settings['band_name']) ?>">
    <?php else: ?>
      <span class="brand-mark">♨</span> <?= e($settings['band_name']) ?>
    <?php endif; ?>
  </a>
  <div class="header-user">
    <?php $activeLangs = enabled_langs(); ?>
    <?php if (count($activeLangs) > 1): ?>
      <?php require_once BASE_DIR . '/app/views/_flags.php'; ?>
      <details class="lang-dd">
        <summary><?= flag_svg(current_lang()) ?> <span><?= strtoupper(current_lang()) ?></span></summary>
        <div class="lang-menu">
          <?php foreach ($activeLangs as $code): ?>
            <a href="<?= e($path) ?>?lang=<?= $code ?>" class="<?= current_lang() === $code ? 'active' : '' ?>"><?= flag_svg($code) ?> <?= LANGS[$code] ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  </div>
  <?php if (!$hideNav): ?>
  <input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
  <label class="nav-burger" for="nav-toggle" aria-label="Menü"><span>☰</span></label>
  <nav>
    <?php if ($isIntern && $user): ?>
      <a href="/intern" class="<?= $path === '/intern' ? 'active' : '' ?>"><?= e(t('inav_uebersicht')) ?></a>
      <a href="/intern/termine" class="<?= str_starts_with($path, '/intern/termine') ? 'active' : '' ?>"><?= e(t('inav_termine')) ?></a>
      <a href="/intern/songs" class="<?= str_starts_with($path, '/intern/songs') ? 'active' : '' ?>"><?= e(t('inav_songs')) ?></a>
      <a href="/intern/setlists" class="<?= str_starts_with($path, '/intern/setlists') ? 'active' : '' ?>"><?= e(t('inav_setlists')) ?></a>
      <a href="/intern/orte" class="<?= str_starts_with($path, '/intern/orte') ? 'active' : '' ?>"><?= e(t('inav_orte')) ?></a>
      <a href="/intern/abwesenheiten" class="<?= str_starts_with($path, '/intern/abwesenheiten') ? 'active' : '' ?>"><?= e(t('inav_abwesenheiten')) ?></a>
      <a href="/intern/aufgaben" class="<?= str_starts_with($path, '/intern/aufgaben') ? 'active' : '' ?>"><?= e(t('inav_aufgaben')) ?></a>
      <a href="/intern/themen" class="<?= str_starts_with($path, '/intern/themen') ? 'active' : '' ?>"><?= e(t('inav_themen')) ?></a>
      <a href="/intern/kasse" class="<?= str_starts_with($path, '/intern/kasse') ? 'active' : '' ?>"><?= e(t('inav_kasse')) ?></a>
      <a href="/intern/stagerider" class="<?= str_starts_with($path, '/intern/stagerider') ? 'active' : '' ?>"><?= e(t('inav_rider')) ?></a>
      <a href="/intern/kanaele" class="<?= str_starts_with($path, '/intern/kanaele') ? 'active' : '' ?>"><?= e(t('inav_kanaele')) ?></a>
      <a href="/intern/equipment" class="<?= str_starts_with($path, '/intern/equipment') ? 'active' : '' ?>"><?= e(t('inav_equipment')) ?></a>
      <a href="/intern/fotos" class="<?= str_starts_with($path, '/intern/fotos') ? 'active' : '' ?>"><?= e(t('inav_fotos')) ?></a>
      <a href="/intern/downloads" class="<?= str_starts_with($path, '/intern/downloads') ? 'active' : '' ?>"><?= e(t('inav_downloads')) ?></a>
      <a href="/intern/mitglieder" class="<?= str_starts_with($path, '/intern/mitglieder') ? 'active' : '' ?>"><?= e(t('inav_mitglieder')) ?></a>
      <a href="/intern/profil" class="<?= str_starts_with($path, '/intern/profil') ? 'active' : '' ?>"><?= e(t('inav_profil')) ?></a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="/intern/einstellungen" class="<?= str_starts_with($path, '/intern/einstellungen') ? 'active' : '' ?>"><?= e(t('inav_einstellungen')) ?></a>
      <?php endif; ?>
    <?php else: ?>
      <a href="/" class="<?= $path === '/' ? 'active' : '' ?>"><?= e(t('nav_start')) ?></a>
      <a href="/termine" class="<?= $path === '/termine' ? 'active' : '' ?>"><?= e(t('nav_termine')) ?></a>
      <a href="/musik" class="<?= $path === '/musik' ? 'active' : '' ?>"><?= e(t('nav_musik')) ?></a>
      <a href="/fotos" class="<?= $path === '/fotos' ? 'active' : '' ?>"><?= e(t('nav_fotos')) ?></a>
      <a href="/kontakt" class="<?= $path === '/kontakt' ? 'active' : '' ?>"><?= e(t('nav_kontakt')) ?></a>
      <?php if (($settings['downloads_mode'] ?? '') === 'public'): ?>
        <a href="/downloads" class="<?= $path === '/downloads' ? 'active' : '' ?>"><?= e(t('nav_downloads')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
    <div class="nav-user">
      <?php if ($user): ?>
        <?php if (!$isIntern): ?>
          <a href="/intern"><?= e(t('inav_intern')) ?></a>
        <?php else: ?>
          <a href="/"><?= e(t('inav_zur_website')) ?></a>
        <?php endif; ?>
        <form action="/logout" method="post"><?= csrf_field() ?>
          <button class="nav-link-button"><?= e(t('logout')) ?> (<?= e($user['name']) ?>)</button>
        </form>
      <?php else: ?>
        <a href="/login"><?= e(t('nav_bandbereich')) ?></a>
      <?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</header>
<?php if ($flashMsg): ?><div class="flash"><?= e($flashMsg) ?></div><?php endif; ?>
<main class="container <?= $isIntern ? 'wide' : '' ?>">
