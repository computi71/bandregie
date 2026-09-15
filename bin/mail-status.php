<?php
declare(strict_types=1);

// Was aus den Einladungen wurde: Postfix-Log gegen mail_log abgleichen (#293).
//
// mail() weiß nur, dass der eigene Mailserver die Nachricht genommen hat. Ob
// Gmail sie eine Sekunde später abweist, steht im Log des Mailservers — und
// das gehört root, nicht der Anwendung. Deshalb liest dieses Skript keine
// Datei, sondern bekommt die Zeilen durchgereicht; der Cron läuft als root nur
// für das tail, die Anwendung bleibt bei ihrem Benutzer:
//
//   * * * * *  tail -n 4000 /var/log/maillog | sudo -u <web-user> php /pfad/zur/anwendung/bin/mail-status.php
//
// Jede Minute ist billig — ein tail und ein paar Vergleiche — und die
// Mitgliederliste zeigt dann beim nächsten Laden, was der Empfänger gesagt hat.
// Ohne den Cron steht dort „übergeben — Zustellung noch unbekannt": ehrlich,
// und immer noch mehr als vorher.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$zeilen = [];
while (($z = fgets(STDIN)) !== false) $zeilen[] = $z;
$n = mail_status_apply($zeilen);
printf("%d Zeilen gelesen, %d Einladungen aktualisiert.\n", count($zeilen), $n);
