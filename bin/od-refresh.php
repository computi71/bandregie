<?php
declare(strict_types=1);

// Der tägliche Blick in die verknüpften OneDrive-Ordner, als Cron (#214).
//
// Wer keinen Cron einrichtet, braucht dieses Skript nicht: Derselbe Durchgang
// läuft gedrosselt beim ersten Seitenaufruf des Tages. Der Cron ist für die,
// die den Zeitpunkt bestimmen wollen — etwa nachts, bevor jemand hineinschaut:
//
//   15 5 * * *  php /pfad/zur/anwendung/bin/od-refresh.php
//
// Doppelt schadet nicht: Die Fälligkeit gilt für beide Wege gemeinsam.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

if (!od_refresh_due()) {
  echo "Nichts fällig.\n";
  exit;
}
$r = od_refresh_all();
printf("Ordner: %d, neu: %d, verschwunden: %d, %s\n",
  $r['folders'], $r['neu'], $r['fehlt'], $r['ok'] ? 'ok' : 'teilweise nicht erreichbar');
exit($r['ok'] ? 0 : 1);
