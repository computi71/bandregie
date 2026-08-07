<?php
declare(strict_types=1);

/**
 * Das Postfach der Band (#219).
 *
 * Anfragen kommen als E-Mail. Bisher las sie jemand in irgendeinem Programm,
 * tippte den Termin von Hand ab und antwortete von seiner privaten Adresse —
 * das Gedächtnis der Band hing daran, wer die Mail zuerst gesehen hatte, und
 * die Antwort hinterließ hier keine Spur. Das ist kein Mailprogramm; es ist
 * der Posteingang, der die Band angeht, an der Stelle, an der aus der Antwort
 * ein Termin wird.
 *
 * Zwei Schichten, bewusst getrennt:
 *
 * Der TRANSPORT (Abholen, Versenden) braucht ein Postfach und die
 * imap-Erweiterung. Die gibt es nicht überall — auf dem Prüfsystem dieses
 * Projekts etwa nicht, und in PHP 8.4 ist sie aus dem Kern gewandert. Er
 * steckt deshalb hinter wenigen Funktionen, die sich ersetzen lassen.
 *
 * Das VERSTEHEN (Kopfzeilen entschlüsseln, Text herausschälen, Datum, Ort und
 * Honorar erraten) ist reine Rechnung, hängt an keiner Erweiterung und ist
 * ohne Postfach prüfbar. Dort liegt die eigentliche Arbeit, und dort werden
 * auch die Fehler wohnen — also gehört genau dieser Teil unter Kontrolle.
 */

/** Höchstens so viele Nachrichten je Durchgang. Ein Aufruf im Netz hat ein Ende. */
const POST_BATCH = 40;

/** Und keine Nachricht über dieser Größe herunterladen. */
const POST_MAX_BYTES = 2097152;

/** Ist ein Postfach eingerichtet? */
function post_configured(): bool {
  return setting('imap_host') !== '' && setting('imap_user') !== '';
}

/** Darf diese Installation das Postfach abholen? */
function post_enabled(): bool {
  return setting('imap_enabled') === '1' && post_configured();
}

/** Die Zugangsdaten; das Passwort liegt versiegelt, wenn ein Schlüssel gesetzt ist. */
function post_config(): array {
  return [
    'host'   => setting('imap_host'),
    'port'   => (int) (setting('imap_port') ?: 993),
    'tls'    => setting('imap_tls', '1') === '1',
    'user'   => setting('imap_user'),
    'pass'   => crypt_reveal(setting('imap_pass')),
    'folder' => setting('imap_folder') ?: 'INBOX',
  ];
}

/**
 * Eine Kopfzeile in lesbaren Text verwandeln.
 *
 * Betreffzeilen kommen als „=?UTF-8?B?…?=" oder „=?iso-8859-1?Q?…?=" an — und
 * oft in mehreren Stücken, die erst zusammengesetzt einen Satz ergeben. Ohne
 * das steht in der Liste Kauderwelsch, und die Suche findet nichts.
 */
function post_decode_header(string $roh): string {
  $roh = trim(preg_replace('~\s+~u', ' ', $roh) ?? '');
  if ($roh === '') return '';
  // mb_decode_mimeheader kennt die Zusammensetzung und die Kodierungen. Ohne
  // mbstring bleibt der Rohtext — falsch aussehen ist besser als leer sein.
  if (function_exists('mb_decode_mimeheader')) {
    $text = @mb_decode_mimeheader($roh);
    if ($text !== '' && $text !== false) $roh = $text;
  }
  // Steuerzeichen fliegen: Sie gehören in keine Liste und in keine Kopfzeile
  // einer Antwort (dieselbe Überlegung wie bei mail_header_value(), #220).
  return trim(preg_replace('~[\r\n\x00\x0B\x0C]+~', ' ', $roh) ?? '');
}

/**
 * Aus einer Absenderzeile Name und Adresse trennen.
 *
 * „Klaus Meier <klaus@example.org>" wird zu beidem; steht nur eine Adresse da,
 * ist der Name leer — erfunden wird keiner.
 *
 * @return array{name: string, mail: string}
 */
function post_split_from(string $roh): array {
  $roh = post_decode_header($roh);
  if (preg_match('~^\s*(.*?)\s*<([^>]+)>\s*$~', $roh, $m)) {
    return ['name' => trim($m[1], " \"'"), 'mail' => trim($m[2])];
  }
  return ['name' => '', 'mail' => trim($roh, " <>\"'")];
}

/**
 * Aus einer Nachricht den lesbaren Text herausschälen.
 *
 * Anfragen kommen oft als HTML, manchmal als beides. Gebraucht wird der Text:
 * Er steht in der Liste, er wird zitiert, und aus ihm wird der Terminvorschlag
 * gelesen. Aus HTML wird deshalb Text gemacht statt es anzuzeigen — fremdes
 * HTML im eigenen Bandbereich wäre eine offene Tür.
 */
function post_body_text(string $roh, string $mime = ''): string {
  $text = $roh;
  if (stripos($mime, 'html') !== false || preg_match('~<(p|br|div|table)\b~i', $roh)) {
    // Blockenden zu Umbrüchen machen, bevor die Marken fallen — sonst klebt
    // der ganze Brief in einer Zeile.
    $text = preg_replace('~<(br|/p|/div|/tr|/h[1-6])\s*/?>~i', "\n", $roh) ?? $roh;
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }
  $text = str_replace(["\r\n", "\r"], "\n", $text);
  // Höchstens zwei Leerzeilen hintereinander: Signaturen und Zitate blähen
  // sonst jede Ansicht auf.
  $text = preg_replace('~\n{3,}~', "\n\n", $text) ?? $text;
  return trim($text);
}

/**
 * Was in einer Anfrage nach einem Termin aussieht (#219).
 *
 * Absichtlich zurückhaltend: Der Vorschlag füllt ein Formular vor, das ein
 * Mensch prüft und abschickt. Er darf danebenliegen — er darf aber nicht so
 * aussehen, als wüsste er etwas, das er nur geraten hat. Deshalb steht zu
 * jedem Feld, woher es kommt: Wer den Fund im Text wiederfindet, kann ihn
 * beurteilen; eine Zahl ohne Herkunft muss man glauben.
 *
 * Was NICHT versucht wird: aus „nächsten Samstag" ein Datum zu rechnen (der
 * Bezugspunkt ist die Mail, nicht heute), aus Fließtext eine Adresse zu
 * schnitzen, oder zwischen mehreren Daten das richtige zu wählen — bei
 * mehreren Funden gewinnt der erste, und die anderen stehen daneben.
 *
 * Rein rechnend, ohne Datenbank und ohne Netz.
 *
 * @return array{date: ?string, times: list<string>, fee: ?int, place: ?string,
 *                dates_found: list<string>, evidence: array<string, string>}
 */
function post_extract(string $text): array {
  $fund = ['date' => null, 'times' => [], 'fee' => null, 'place' => null,
           'dates_found' => [], 'evidence' => []];
  $zeilen = explode("\n", $text);

  // ---- Datum. Drei Schreibweisen, die in deutschen Anfragen vorkommen.
  $monate = ['januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4,
             'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8, 'september' => 9,
             'oktober' => 10, 'november' => 11, 'dezember' => 12];
  $gefunden = [];
  // 04.07.2026 / 4.7.26 / 04-07-2026
  if (preg_match_all('~\b(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2,4})\b~', $text, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
      $j = (int) $m[3];
      if ($j < 100) $j += 2000;
      $gefunden[] = [$j, (int) $m[2], (int) $m[1], $m[0]];
    }
  }
  // 4. Juli 2026 — der Monatsname macht es eindeutig, auch ohne Jahr
  if (preg_match_all('~\b(\d{1,2})\.?\s*(' . implode('|', array_keys($monate)) . ')\.?\s*(\d{4})?~iu',
                     $text, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
      $monat = $monate[mb_strtolower($m[2])] ?? 0;
      if (!$monat) continue;
      $gefunden[] = [(int) ($m[3] ?? 0) ?: 0, $monat, (int) $m[1], trim($m[0])];
    }
  }
  // 2026-07-04
  if (preg_match_all('~\b(\d{4})-(\d{2})-(\d{2})\b~', $text, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) $gefunden[] = [(int) $m[1], (int) $m[2], (int) $m[3], $m[0]];
  }
  foreach ($gefunden as [$j, $mo, $tg, $roh]) {
    // Ohne Jahr kein Datum: Ein Termin im falschen Jahr ist schlimmer als
    // keiner. Der Fund bleibt trotzdem sichtbar, damit man ihn von Hand nimmt.
    if ($j < 2000 || $j > 2100 || $mo < 1 || $mo > 12 || $tg < 1 || $tg > 31) {
      $fund['dates_found'][] = $roh;
      continue;
    }
    if (!checkdate($mo, $tg, $j)) { $fund['dates_found'][] = $roh; continue; }
    $iso = sprintf('%04d-%02d-%02d', $j, $mo, $tg);
    $fund['dates_found'][] = $roh;
    if ($fund['date'] === null) {
      $fund['date'] = $iso;
      $fund['evidence']['date'] = $roh;
    }
  }
  $fund['dates_found'] = array_values(array_unique($fund['dates_found']));

  // ---- Uhrzeiten, in der Reihenfolge des Textes: Die erste ist oft der
  // Beginn, die zweite das Ende — welche welche ist, entscheidet ein Mensch.
  //
  // Der Punkt zählt NUR mit „Uhr" dahinter. Sonst liest sich „04.07.2026" als
  // 04:07 und „1.200 €" als 01:20 — beides ist beim ersten Versuch genau so
  // passiert. Minuten müssen Minuten sein (00–59), auch das schließt Datums-
  // und Preisbruchstücke aus.
  $zeitFunde = [];
  $sammle = function (string $muster, callable $bau) use ($text, &$zeitFunde): void {
    if (!preg_match_all($muster, $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return;
    foreach ($mm as $m) $zeitFunde[(int) $m[0][1]] = $bau($m);
  };
  $sammle('~\b([01]?\d|2[0-3]):([0-5]\d)\b~',
          fn(array $m) => sprintf('%02d:%02d', (int) $m[1][0], (int) $m[2][0]));
  $sammle('~\b([01]?\d|2[0-3])\.([0-5]\d)\s*uhr\b~iu',
          fn(array $m) => sprintf('%02d:%02d', (int) $m[1][0], (int) $m[2][0]));
  $sammle('~\b([01]?\d|2[0-3])\s*uhr\b~iu',
          fn(array $m) => sprintf('%02d:00', (int) $m[1][0]));
  // Nach Fundstelle sortieren, damit die Reihenfolge die des Textes ist und
  // nicht die der Muster.
  ksort($zeitFunde);
  foreach ($zeitFunde as $zeit) {
    if (!in_array($zeit, $fund['times'], true)) $fund['times'][] = $zeit;
  }
  $fund['times'] = array_slice($fund['times'], 0, 4);

  // ---- Honorar. Nur mit Währung — eine nackte Zahl im Text ist kein Preis.
  if (preg_match_all('~(\d{1,3}(?:[.\s]\d{3})*(?:,\d{1,2})?|\d+(?:,\d{1,2})?)\s*(?:€|EUR\b|Euro\b)~iu',
                     $text, $mm, PREG_SET_ORDER)) {
    // Der größte Betrag ist bei Anfragen fast immer die Gage; kleinere sind
    // Fahrtkosten oder Eintrittspreise. „Fast immer" heißt: prüfen lassen.
    $beste = null;
    foreach ($mm as $m) {
      $cents = price_to_cents($m[1]);
      if ($cents !== null && ($beste === null || $cents > $beste[0])) $beste = [$cents, trim($m[0])];
    }
    if ($beste !== null) {
      $fund['fee'] = $beste[0];
      $fund['evidence']['fee'] = $beste[1];
    }
  }

  // ---- Ort. Eine Zeile mit Postleitzahl und Ort ist eine Adresse; alles
  // andere wäre Raten im Fließtext.
  foreach ($zeilen as $z) {
    $z = trim($z);
    if ($z === '' || mb_strlen($z) > 120) continue;
    if (preg_match('~\b\d{5}\s+[A-ZÄÖÜ][\wÄÖÜäöüß.\- ]{2,}~u', $z)) {
      $fund['place'] = $z;
      $fund['evidence']['place'] = $z;
      break;
    }
  }
  return $fund;
}

/**
 * Der Verbindungsstring für die imap-Erweiterung.
 *
 * `validate-cert` bleibt an: Ein Postfach ohne geprüftes Zertifikat wäre eine
 * Einladung, sich dazwischenzusetzen — und die Zugangsdaten gingen mit.
 */
function post_mailbox(array $cfg, string $ordner = ''): string {
  $flags = $cfg['tls'] ? '/imap/ssl' : '/imap/notls';
  return '{' . $cfg['host'] . ':' . $cfg['port'] . $flags . '}' . ($ordner ?: $cfg['folder']);
}

/**
 * Ist das Abholen fällig (#219)? Dasselbe Muster wie bei der Sicherung und
 * beim OneDrive-Blick: ein Fälligkeits-Check, zwei Auslöser. Nach einem
 * Fehlschlag frühestens in einer Stunde wieder — ein Postfach, das gerade
 * nicht antwortet, soll nicht bei jedem Seitenaufruf angeklopft bekommen.
 */
function post_due(): bool {
  if (is_demo()) return false;
  if (!post_enabled()) return false;
  if (!function_exists('imap_open')) return false;
  $intervall = max(300, (int) (setting('imap_interval_min') ?: 30) * 60);
  $versuch = (int) setting('imap_last_attempt', '0');
  if (time() - $versuch < 3600 && (int) setting('imap_last_ok', '0') < $versuch) return false;
  return time() - (int) setting('imap_last_ok', '0') >= $intervall;
}

/**
 * Das Postfach ansehen und neue Nachrichten ablegen.
 *
 * Nur lesen: Ob eine Nachricht im Postfach als gelesen gilt, ist die
 * Entscheidung dessen, der sie liest — nicht die Nebenwirkung eines
 * Abgleichs. Wer das Postfach nebenher im Handy hat, fände es sonst leer.
 *
 * Erkannt wird an der UID des Servers, nicht an Betreff oder Datum: Dieselbe
 * Nachricht zweimal abzulegen wäre schlimmer als eine zu verpassen.
 *
 * @return array{ok: bool, neu: int, gesehen: int, message: string}
 */
function post_fetch(int $hoechstens = POST_BATCH): array {
  set_setting('imap_last_attempt', (string) time());
  if (!function_exists('imap_open')) {
    return ['ok' => false, 'neu' => 0, 'gesehen' => 0, 'message' => t('post_no_imap')];
  }
  $cfg = post_config();
  // Fehler der Erweiterung landen sonst in der Ausgabe der Seite.
  $mbox = @imap_open(post_mailbox($cfg), $cfg['user'], $cfg['pass'], OP_READONLY, 1);
  if (!$mbox) {
    $grund = imap_last_error() ?: 'unbekannt';
    imap_errors();
    return ['ok' => false, 'neu' => 0, 'gesehen' => 0,
            'message' => str_replace('%1', mb_substr((string) $grund, 0, 160), t('post_connect_failed'))];
  }
  $anzahl = (int) imap_num_msg($mbox);
  $neu = 0;
  $gesehen = 0;
  // Von hinten: Die jüngsten Nachrichten sind die, auf die jemand wartet.
  for ($i = $anzahl; $i >= 1 && ($neu + $gesehen) < $hoechstens; $i--) {
    $uid = (string) imap_uid($mbox, $i);
    if ($uid === '') continue;
    $gesehen++;
    if (row('SELECT 1 FROM post_messages WHERE uid = ? AND folder = ?', [$uid, $cfg['folder']])) continue;

    $kopf = imap_headerinfo($mbox, $i);
    $groesse = (int) ($kopf->Size ?? 0);
    // Zu große Nachrichten nur mit Kopf ablegen: Der Text käme sonst als
    // Anhangwust, und der Speicher wäre voll mit Dingen, die niemand liest.
    $text = $groesse > POST_MAX_BYTES ? '' : post_body_of($mbox, $i);
    $von = post_split_from((string) ($kopf->fromaddress ?? ''));
    q('INSERT INTO post_messages (uid, folder, from_name, from_mail, subject, sent_at, body_text, size_bytes)
       VALUES (?,?,?,?,?,?,?,?)', [
      $uid, $cfg['folder'],
      mb_substr($von['name'], 0, 190), mb_substr($von['mail'], 0, 190),
      mb_substr(post_decode_header((string) ($kopf->subject ?? '')), 0, 400),
      isset($kopf->udate) ? date('Y-m-d H:i:s', (int) $kopf->udate) : date('Y-m-d H:i:s'),
      mb_substr($text, 0, 60000), $groesse,
    ]);
    $neu++;
  }
  imap_close($mbox);
  imap_errors();
  set_setting('imap_last_ok', (string) time());
  if ($neu > 0) {
    $anzahlNeu = $neu;
    push_notify('post', 0, fn(string $lang): array => [
      'title' => push_t($lang, 'push_post_title'),
      'body' => str_replace('%1', (string) $anzahlNeu, push_t($lang, 'push_post_body')),
      'url' => '/intern/post',
    ]);
  }
  return ['ok' => true, 'neu' => $neu, 'gesehen' => $gesehen, 'message' => ''];
}

/**
 * Den lesbaren Teil einer Nachricht holen.
 *
 * Bevorzugt der reine Text; gibt es nur HTML, wird daraus Text. Anhänge
 * bleiben liegen — sie sind eine eigene Frage (Größe, Art, Rechte), und ein
 * Postfach ist kein Dateiablage-Ersatz.
 */
function post_body_of($mbox, int $nr): string {
  $struktur = @imap_fetchstructure($mbox, $nr);
  if (!$struktur) return post_body_text((string) @imap_body($mbox, $nr));
  // Einteilig: der Rumpf ist der Text.
  if (empty($struktur->parts)) {
    return post_body_text(post_decode_part((string) @imap_body($mbox, $nr), (int) ($struktur->encoding ?? 0)),
                          (string) ($struktur->subtype ?? ''));
  }
  $text = '';
  $html = '';
  foreach ($struktur->parts as $i => $teil) {
    $nummer = (string) ($i + 1);
    $art = strtoupper((string) ($teil->subtype ?? ''));
    if ((int) ($teil->type ?? 0) !== 0) continue;          // 0 = text
    if (!empty($teil->disposition) && strtoupper((string) $teil->disposition) === 'ATTACHMENT') continue;
    $roh = post_decode_part((string) @imap_fetchbody($mbox, $nr, $nummer), (int) ($teil->encoding ?? 0));
    if ($art === 'PLAIN' && $text === '') $text = $roh;
    if ($art === 'HTML' && $html === '') $html = $roh;
  }
  if ($text !== '') return post_body_text($text);
  return $html !== '' ? post_body_text($html, 'html') : '';
}

/** Base64 und Quoted-Printable auflösen; alles andere kommt, wie es ist. */
function post_decode_part(string $roh, int $encoding): string {
  return match ($encoding) {
    3 => (string) base64_decode($roh, false),
    4 => quoted_printable_decode($roh),
    default => $roh,
  };
}
