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
  <?php // Rechtliches gehört nicht in den Index — und eine Demo als Ganzes
        // ebenso wenig: erfundene Inhalte, stündlich zurückgesetzt, und sie
        // stünde als zweite Fassung neben der echten Seite (#174). ?>
  <?php if (is_demo() || in_array($path, ['/impressum', '/datenschutz'], true)): ?>
    <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <?php // Ohne eigenes Zeichen das App-Symbol nehmen — sonst fragt jeder
        // Browser bei jedem Aufruf vergeblich nach /favicon.ico ?>
  <?php if (!empty($settings['favicon_file'])): ?>
    <link rel="icon" type="image/png" href="/uploads/<?= e($settings['favicon_file']) ?>">
  <?php else: ?>
    <link rel="icon" type="image/png" href="<?= e(app_icon(192)) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <?php // Als App installierbar: Manifest, Farbe der Systemleiste, Symbol für iOS ?>
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#17120f">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?= e($settings['band_name']) ?>">
  <link rel="apple-touch-icon" href="<?= e(app_icon(192)) ?>">
  <script src="<?= e(asset('/assets/app.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/actions.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/lightbox.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/nav.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/accordion.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/seen.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/entwurf.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/absage.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/zusage.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/markers.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/geo.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/route.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/push.js')) ?>" defer></script>
  <?php // Passkey: blendet sich selbst ein, wenn der Browser es kann — auf der
        // Anmeldeseite zum Anmelden, im Profil zum Anlegen. ?>
  <script src="<?= e(asset('/assets/passkey.js')) ?>" defer></script>
  <?php // Der Hinweis auf einen Stand aus dem Zwischenspeicher gehört auf jede
        // Seite, die offline erscheinen kann — nicht nur auf die Terminliste. ?>
  <script src="<?= e(asset('/assets/offline.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/datepicker.js')) ?>" defer></script>
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
<?php // Die Vorlage für den Hinweis auf einen alten Stand reist mit der Seite in
      // den Zwischenspeicher — nur so kennt sie dort die Sprache. ?>
<body data-staletpl="<?= e(t('off_stale')) ?>" data-offlineusetpl="<?= e(t('off_use')) ?>" data-navi-pick="<?= e(t('navi_pick')) ?>" data-navi-pick-hint="<?= e(t('navi_pick_hint')) ?>" data-date-pick="<?= e(t('date_pick')) ?>"
      data-entwurf-found="<?= e(t('draft_found')) ?>"
      data-entwurf-restore="<?= e(t('draft_restore')) ?>"
      data-entwurf-drop="<?= e(t('draft_drop')) ?>">
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
        // Jeder Eintrag trägt sein Zeichen, wie die Hilfe es schon tat: In einer
        // Spalte aus siebzehn ähnlich langen Wörtern findet das Auge die Zeile
        // am Bild schneller als am Text — besonders auf dem Handy, wo das Menü
        // über den halben Bildschirm läuft.
        //
        // Wo die Seite selbst schon ein Zeichen im Titel führt, steht hier
        // dasselbe; sonst hätte dieselbe Sache zwei Gesichter. Die Gruppen
        // bekommen bewusst Zeichen aus einer anderen Familie — Ordner, Kasten,
        // Stecker —, damit Überschrift und Eintrag nicht gleich aussehen.
        // Zeichen und Beschriftung stehen getrennt und nicht als ein Text: Sonst
        // steckt Darstellung im Schlüssel eines Feldes, und zwei Gruppen mit
        // gleicher Beschriftung würden sich überschreiben.
        $navGroups = [
          // Innerhalb einer Gruppe steht vorn, was am häufigsten gebraucht wird,
          // und hinten das Nachschlagewerk: Orte ändert man selten, Termine und
          // Aufgaben täglich. Die Post steht bei der Verständigung und nicht bei
          // den Medien — sie ist eingehende Korrespondenz und nicht etwas, das
          // die Band veröffentlicht (#268).
          ['🗂', t('inavg_planung'), array_filter([
            '/intern/termine' => [MODULE_ICONS['termine'], t('inav_termine')],
            '/intern/aufgaben' => [MODULE_ICONS['aufgaben'], t('inav_aufgaben')],
            '/intern/abwesenheiten' => [MODULE_ICONS['abwesenheiten'], t('inav_abwesenheiten')],
            '/intern/themen' => [MODULE_ICONS['themen'], t('inav_themen')],
            '/intern/post' => [MODULE_ICONS['post'], t('inav_post')],
            '/intern/orte' => [MODULE_ICONS['orte'], t('inav_orte')],
            '/intern/gaeste' => [MODULE_ICONS['gaeste'], t('inav_gaeste')],
            // Beim direkten Weg gibt es keinen Angebotsschritt (#312).
            '/intern/angebote' => quote_step_active() ? [MODULE_ICONS['angebote'], t('inav_angebote')] : null,
            '/intern/vertraege' => [MODULE_ICONS['vertraege'], t('inav_vertraege')],
            // Die Rechnung steht hinter dem Vertrag, weil sie daraus entsteht.
            // Sie fehlte hier seit ihrer Einfuehrung ganz - erreichbar war der
            // Bereich nur vom Termin aus oder ueber die Adresse (#369).
            '/intern/rechnungen' => [MODULE_ICONS['rechnungen'], t('inav_rechnungen')],
          ])],
          ['🎼', t('inavg_musik'), [
            '/intern/songs' => [MODULE_ICONS['songs'], t('inav_songs')],
            '/intern/setlists' => [MODULE_ICONS['setlists'], t('inav_setlists')],
          ]],
          ['🔌', t('inavg_technik'), [
            '/intern/equipment' => [MODULE_ICONS['equipment'], t('inav_equipment')],
            '/intern/stagerider' => [MODULE_ICONS['rider'], t('inav_rider')],
            '/intern/kanaele' => ['🎚', t('inav_kanaele')],
          ]],
          ['📦', t('inavg_material'), [
            '/intern/fotos' => [MODULE_ICONS['fotos'], t('inav_fotos')],
            '/intern/musik' => [MODULE_ICONS['musik'], t('inav_musik')],
            '/intern/downloads' => [MODULE_ICONS['downloads'], t('inav_downloads')],
          ]],
          ['🎸', t('inavg_band'), [
            '/intern/kasse' => [MODULE_ICONS['kasse'], t('inav_kasse')],
            '/intern/mitglieder' => [MODULE_ICONS['mitglieder'], t('inav_mitglieder')],
          ]],
          // „Über" erzählt von der Anwendung — das liest man einmal. Es stand
          // zwischen den beiden Einträgen, an denen man etwas tut (#268).
          ['🔑', t('inavg_konto'), array_filter([
            '/intern/profil' => ['👤', t('inav_profil')],
            '/intern/einstellungen' => $user['role'] === 'admin' ? ['⚙️', t('inav_einstellungen')] : null,
            '/intern/ueber' => ['ℹ️', t('inav_ueber')],
          ])],
        ];
      ?>
      <?php // Wo etwas ansteht (#384). Dieselbe Quelle wie die Zahl am
            // App-Symbol — zwei Zählungen nebeneinander würden irgendwann
            // verschiedene Zahlen nennen, und dann glaubt man keiner mehr.
            //
            // $navRest sammelt, was zu keinem sichtbaren Menüpunkt gehört: Wer
            // Aufgaben nicht sehen darf, hat seit #393 trotzdem eigene — nur
            // stehen die auf der Übersicht und nicht in einer Liste. Dann
            // gehört die Zahl auch dorthin. ?>
      <?php
        $navOffen = open_items_by_path($user);
        // Erst aussieben, dann zeichnen: Die Übersicht steht ganz oben und
        // muss schon wissen, was unter ihr KEINEN Menüpunkt hat.
        //
        // Was jemand nicht sehen darf, steht auch nicht im Menü — sonst führt
        // jeder zweite Eintrag nur auf eine Absage.
        $navSichtbar = [];
        $navRest = $navOffen;
        foreach ($navGroups as [$gIcon, $gLabel, $gItems]) {
          $gItems = array_filter($gItems, static function ($itemPath) use ($user) {
            $mod = perm_module_for($itemPath);
            return $mod === null || perm_allows($user, $mod, 'read');
          }, ARRAY_FILTER_USE_KEY);
          if (!$gItems) continue;
          foreach (array_keys($gItems) as $gPath) unset($navRest[$gPath]);
          $navSichtbar[] = [$gIcon, $gLabel, $gItems];
        }
      ?>
      <a href="/intern" class="<?= $path === '/intern' ? 'active' : '' ?>"><span class="nav-icon">🏠</span><?= e(t('inav_uebersicht')) ?><?php
        // Was zu keinem sichtbaren Menüpunkt gehört, steht hier: Wer Aufgaben
        // nicht sehen darf, hat seit #393 trotzdem eigene — die stehen auf der
        // Übersicht, also gehört die Zahl auch dorthin.
        if (array_sum($navRest)): ?><span class="nav-count"><?= (int) array_sum($navRest) ?></span><?php endif; ?></a>
      <?php foreach ($navSichtbar as [$groupIcon, $groupLabel, $groupItems]): ?>
        <?php
          // Die Gruppe der aktuellen Seite steht offen, die übrigen bleiben zu
          $groupActive = false;
          foreach (array_keys($groupItems) as $groupPath) {
            if (str_starts_with($path, $groupPath)) $groupActive = true;
          }
        ?>
        <?php // Die Gruppe trägt die Summe ihrer Einträge: Zugeklappt sieht man
              // sonst nicht, dass darin etwas wartet. ?>
        <?php
          $groupOffen = 0;
          foreach (array_keys($groupItems) as $groupPath) {
            $groupOffen += $navOffen[$groupPath] ?? 0;
          }
        ?>
        <details class="nav-group <?= $groupActive ? 'has-active' : '' ?>" <?= $groupActive ? 'open' : '' ?>>
          <summary><span class="nav-icon"><?= $groupIcon ?></span><?= e($groupLabel) ?><?php
            if ($groupOffen): ?><span class="nav-count"><?= (int) $groupOffen ?></span><?php endif; ?></summary>
          <?php foreach ($groupItems as $itemPath => [$itemIcon, $itemLabel]): ?>
            <a href="<?= e($itemPath) ?>" class="<?= str_starts_with($path, $itemPath) ? 'active' : '' ?>"><span class="nav-icon"><?= $itemIcon ?></span><?= e($itemLabel) ?><?php
              if (!empty($navOffen[$itemPath])): ?><span class="nav-count"><?= (int) $navOffen[$itemPath] ?></span><?php endif; ?></a>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>
      <?php // Die Hilfe stand in der Gruppe „Konto" und war damit erst nach
            // zwei Klicks zu finden — gesucht wird sie aber in dem Moment, in
            // dem etwas unklar ist. Deshalb steht sie offen im Menü, unten,
            // wo sie die Arbeitsbereiche nicht nach unten schiebt. ?>
      <a href="/intern/hilfe" class="<?= str_starts_with($path, '/intern/hilfe') ? 'active' : '' ?>"><span class="nav-icon">❓</span><?= e(t('inav_hilfe')) ?></a>
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
<?php // Hier meldet sich die Seite, wenn sich hinter ihrem Rücken etwas getan
      // hat (#378). Leer und verborgen im Seitentext, damit das Skript nur
      // einblenden muss — und ohne Skript nie etwas erscheint. ?>
<?php // Wer absagt, bekommt einen Hinweis (#401). Dasselbe Muster wie beim
      // Versionsfenster: ein <dialog>, das ohne Skript gar nicht erscheint.
      //
      // Das Merkzeichen aus der Sitzung wird hier VERBRAUCHT — sonst poppte es
      // bei jedem weiteren Seitenaufruf wieder auf. ?>
<?php if ($isIntern): ?>
  <?php // Verbraucht wird das Merkzeichen NUR hier drin (#407). Diese Datei
        // zeichnet auch die oeffentlichen Seiten, und stand das Lesen davor,
        // ass ein Aufruf der Startseite den Hinweis auf, ohne ihn zeigen zu
        // koennen — einmal verbraucht ist es weg. ?>
  <?php $absageTipp = (int) ($_SESSION['absage_tipp'] ?? 0); unset($_SESSION['absage_tipp']); ?>
  <dialog id="absage-dialog" class="eq-dialog" data-absage-auto="<?= $absageTipp ?>">
    <div class="eq-dialog-head">
      <strong><?= e(t('decline_title')) ?></strong>
      <button type="button" class="btn btn-tiny" data-absage-close aria-label="<?= e(t('close')) ?>">✕</button>
    </div>
    <p><?= e(t('decline_hint')) ?></p>
    <div class="row-buttons">
      <a class="btn btn-primary btn-small" data-absage-link href="/intern/termine"><?= e(t('decline_add')) ?> →</a>
      <button type="button" class="btn btn-small btn-ghost" data-absage-close><?= e(t('decline_later')) ?></button>
    </div>
  </dialog>
<?php endif; ?>
<?php if ($isIntern): ?>
  <div class="flash" id="neues-balken" hidden>
    <?= e(t('refresh_new')) ?>
    <button type="button" class="btn btn-tiny" id="neues-knopf"><?= e(t('refresh_now')) ?></button>
  </div>
<?php endif; ?>
<main class="container <?= $isIntern ? 'wide' : '' ?>">
