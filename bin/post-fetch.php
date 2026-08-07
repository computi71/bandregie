<?php
declare(strict_types=1);

// Das Bandpostfach abholen, als Cron (#219).
//
// Wer keinen Cron einrichtet, braucht dieses Skript nicht: Derselbe Durchgang
// läuft gedrosselt beim Seitenaufruf. Der Cron ist für die, die den Takt
// bestimmen wollen — etwa alle zehn Minuten, damit eine Anfrage nicht bis zum
// nächsten Besuch liegen bleibt:
//
//   */10 * * * *  php /pfad/zur/anwendung/bin/post-fetch.php
//
// Doppelt schadet nicht: Die Fälligkeit gilt für beide Wege gemeinsam, und
// erkannt wird an der UID des Servers.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/post.php';

if (!post_due()) {
  echo "Nichts fällig.\n";
  exit;
}
$r = post_fetch();
printf("%s: %d neu von %d angesehenen%s\n", $r['ok'] ? 'ok' : 'Fehler',
  $r['neu'], $r['gesehen'], $r['message'] !== '' ? ' — ' . $r['message'] : '');
exit($r['ok'] ? 0 : 1);
