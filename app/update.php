<?php
declare(strict_types=1);

/**
 * Aktualisierung: sagen, dass es etwas Neues gibt, und den Weg dorthin
 * nennen — den, der auf *dieser* Maschine tatsächlich funktioniert.
 *
 * Die Anwendung aktualisiert sich nicht selbst. Dafür müsste der Webserver
 * in sein eigenes Verzeichnis schreiben dürfen, und dann wird aus jeder
 * Lücke, die irgendwann einmal eine Datei schreiben lässt, eine dauerhafte
 * Übernahme. Ein Befehl zum Kopieren kostet zwei Sekunden mehr und diesen
 * Preis nicht.
 */

/** Wo nach neuen Fassungen gefragt wird. Ohne Schlüssel, ohne Anmeldung. */
const UPDATE_FEED = 'https://api.github.com/repos/computi71/bandregie/releases/latest';

/** Höchstens einmal am Tag fragen — öfter nützt niemandem. */
const UPDATE_INTERVAL = 86400;

/**
 * Darf von hier aus überhaupt auf diesen Pfad gesehen werden? open_basedir
 * sperrt alles außerhalb der erlaubten Verzeichnisse, und ein Zugriff darauf
 * kostet nicht nur die Antwort, sondern hinterlässt eine Warnung im Protokoll.
 * Ohne Sperre ist alles erlaubt.
 */
function update_path_allowed(string $pfad): bool {
  $sperre = (string) ini_get('open_basedir');
  if ($sperre === '') return true;
  foreach (explode(PATH_SEPARATOR, $sperre) as $erlaubt) {
    $erlaubt = rtrim(trim($erlaubt), '/');
    if ($erlaubt !== '' && str_starts_with($pfad, $erlaubt . '/')) return true;
  }
  return false;
}

/**
 * Läuft die Installation unter Plesk? Dann ist das ausgelieferte Verzeichnis
 * keine Git-Arbeitskopie, sondern eine Kopie, die Plesk dorthin legt — ein
 * „git pull" liefe dort ins Leere.
 *
 * Die Frage wird zweimal gestellt, weil eine Antwort allein nicht überall
 * ankommt (#226): Auf der Kommandozeile sind die Plesk-Pfade lesbar, unter dem
 * Webserver liegen sie außerhalb von open_basedir. Dort scheiterte die Prüfung
 * mit einer Warnung je Seitenaufruf und antwortete „nein" auf genau der
 * Maschine, die sie erkennen sollte — die Anwendung nannte dann keinen
 * Aktualisierungsweg. Also erst fragen, ob man hinsehen darf, und wenn nicht,
 * den eigenen Pfad befragen: Plesk legt jede Domain unter /var/www/vhosts ab,
 * und der eigene Pfad ist von innen immer lesbar.
 */
function update_is_plesk(): bool {
  foreach (['/usr/local/psa/version', '/opt/psa/version'] as $pfad) {
    if (update_path_allowed($pfad) && is_readable($pfad)) return true;
  }
  return str_contains(str_replace('\\', '/', BASE_DIR), '/var/www/vhosts/');
}

/** Ist das Verzeichnis eine Git-Arbeitskopie? */
function update_is_git(): bool {
  return is_dir(BASE_DIR . '/.git');
}

/**
 * Der Befehl, mit dem diese Installation aktualisiert wird.
 *
 * @return array{kind: string, command: string}
 */
function update_command(): array {
  if (update_is_plesk()) {
    // Plesk kennt das Repository, das Zielverzeichnis nicht. Der Domainname
    // steht in der Adresse, unter der die Seite gerade läuft.
    $domain = preg_replace('~^www\.~', '', (string) ($_SERVER['HTTP_HOST'] ?? 'example.com'));
    $domain = preg_replace('~[^a-z0-9.\-]~i', '', explode(':', $domain)[0]);
    return ['kind' => 'plesk', 'command' =>
      "plesk ext git --fetch -domain $domain -name bandregie && "
      . "plesk ext git --deploy -domain $domain -name bandregie"];
  }
  if (update_is_git()) {
    return ['kind' => 'git', 'command' => 'sh ' . BASE_DIR . '/bin/update.sh'];
  }
  return ['kind' => 'manual', 'command' => ''];
}

/**
 * Die neueste veröffentlichte Fassung, oder null. Das Ergebnis wird einen Tag
 * lang behalten: der Aufruf hängt an einer Seitenanzeige, und die soll nicht
 * darauf warten, dass ein fremder Server antwortet.
 */
function update_latest_version(): ?string {
  if (setting('update_check', '1') !== '1') return null;

  $stamp = (int) setting('update_checked_at', '0');
  if ($stamp > time() - UPDATE_INTERVAL) {
    return setting('update_latest') ?: null;
  }
  // Auch ein Fehlschlag zählt als Versuch, sonst fragt jede Seitenanzeige
  // erneut, solange die Verbindung klemmt.
  set_setting('update_checked_at', (string) time());

  $latest = update_fetch_latest();
  if ($latest !== null) set_setting('update_latest', $latest);
  return $latest ?? (setting('update_latest') ?: null);
}

/** Einmal nachfragen. Geht es nicht, ist das kein Grund für eine Fehlermeldung. */
function update_fetch_latest(): ?string {
  if (!function_exists('curl_init')) return null;
  $ch = curl_init(UPDATE_FEED);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_USERAGENT => 'Bandregie/' . BANDREGIE_VERSION,
    CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
  ]);
  $body = curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code !== 200 || !is_string($body)) return null;

  $data = json_decode($body, true);
  $tag = is_array($data) ? (string) ($data['tag_name'] ?? '') : '';
  $tag = ltrim(trim($tag), 'vV');
  return preg_match('~^\d+\.\d+\.\d+$~', $tag) ? $tag : null;
}

/** Gibt es etwas Neueres als das, was hier läuft? */
function update_available(): bool {
  $latest = update_latest_version();
  return $latest !== null && version_compare($latest, BANDREGIE_VERSION, '>');
}
