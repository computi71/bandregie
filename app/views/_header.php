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
  <script src="/assets/accordion.js" defer></script>
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
      <?php
        // Der Bandbereich hat inzwischen 17 Seiten. Als flache Liste war das
        // eine Wand aus gleichwertigen Zeilen; nach Themen gruppiert findet
        // man wieder etwas. Die Übersicht bleibt als Einstieg oben stehen.
        $navGroups = [
          t('inavg_planung')  => [
            '/intern/termine' => t('inav_termine'),
            '/intern/abwesenheiten' => t('inav_abwesenheiten'),
            '/intern/aufgaben' => t('inav_aufgaben'),
            '/intern/themen' => t('inav_themen'),
            '/intern/orte' => t('inav_orte'),
          ],
          t('inavg_musik')    => [
            '/intern/songs' => t('inav_songs'),
            '/intern/setlists' => t('inav_setlists'),
          ],
          t('inavg_technik')  => [
            '/intern/equipment' => t('inav_equipment'),
            '/intern/stagerider' => t('inav_rider'),
            '/intern/kanaele' => t('inav_kanaele'),
          ],
          t('inavg_material') => [
            '/intern/fotos' => t('inav_fotos'),
            '/intern/musik' => t('inav_musik'),
            '/intern/downloads' => t('inav_downloads'),
          ],
          t('inavg_band')     => [
            '/intern/kasse' => t('inav_kasse'),
            '/intern/mitglieder' => t('inav_mitglieder'),
          ],
          t('inavg_konto')    => array_filter([
            '/intern/profil' => t('inav_profil'),
            '/intern/hilfe' => t('inav_hilfe'),
            '/intern/ueber' => t('about_open'),
            '/intern/einstellungen' => $user['role'] === 'admin' ? t('inav_einstellungen') : null,
          ]),
        ];
      ?>
      <a href="/intern" class="<?= $path === '/intern' ? 'active' : '' ?>"><?= e(t('inav_uebersicht')) ?></a>
      <?php foreach ($navGroups as $groupLabel => $groupItems): ?>
        <?php
          // Was jemand nicht sehen darf, steht auch nicht im Menü — sonst
          // führt jeder zweite Eintrag nur auf eine Absage.
          $groupItems = array_filter($groupItems, function ($itemPath) use ($user) {
            $mod = perm_module_for($itemPath);
            return $mod === null || perm_allows($user, $mod, 'read');
          }, ARRAY_FILTER_USE_KEY);
          if (!$groupItems) continue;
          // Die Gruppe der aktuellen Seite steht offen, die übrigen bleiben zu
          $groupActive = false;
          foreach (array_keys($groupItems) as $groupPath) {
            if (str_starts_with($path, $groupPath)) $groupActive = true;
          }
        ?>
        <details class="nav-group <?= $groupActive ? 'has-active' : '' ?>" <?= $groupActive ? 'open' : '' ?>>
          <summary><?= e($groupLabel) ?></summary>
          <?php foreach ($groupItems as $itemPath => $itemLabel): ?>
            <a href="<?= e($itemPath) ?>" class="<?= str_starts_with($path, $itemPath) ? 'active' : '' ?>"><?= e($itemLabel) ?></a>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>
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
