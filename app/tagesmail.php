<?php
/**
 * Die Tagesmail (#332).
 *
 * Was die Marken auf der Seite zeigen, steht abends im Postfach — für alle,
 * die nicht täglich hineinsehen. Eine Band, die eine Woche nicht hineinschaut,
 * erfährt sonst nichts, und genau dann wäre es nötig gewesen.
 *
 * Drei Quellen, weil es drei gibt: die Marken aus #331, der ungelesene Chat
 * mit seiner eigenen Mechanik, und was die Zahl am App-Symbol sonst noch
 * zählt. Die Mail und die Zahl sollen dasselbe meinen — zwei Begriffe von
 * „offen" wären schlimmer als die fehlende Mail.
 *
 * Gesendet wird als reiner Text: band_mail_send() verschickt text/plain, und
 * eine ausgeschriebene Adresse ist in jedem Mailprogramm anklickbar.
 */

/**
 * Die Sorten zu Abschnitten zusammenfassen.
 *
 * Neunzehn Überschriften liest niemand. Gruppiert nach dem, was ein Mensch
 * zusammen denkt — und in der Reihenfolge, in der es ihn interessiert: erst
 * die Termine, zuletzt die Buchhaltung.
 */
const DIGEST_GRUPPEN = [
  'termine'   => ['event', 'comment', 'attendance'],
  'musik'     => ['song', 'setlist'],
  'medien'    => ['file', 'photo', 'media'],
  'orte'      => ['venue', 'equipment', 'stageitem', 'channel'],
  'orga'      => ['task', 'absence', 'guest'],
  'geschaeft' => ['quote', 'contract', 'finance'],
  'post'      => ['post'],
];

/** Wie viele Tage zwischen zwei Mails? 0 heißt: gar keine. */
function digest_tage(array $user): int {
  return DIGEST_FREQ[$user['digest_freq'] ?? 'taeglich'] ?? 1;
}

/**
 * Ist der Zeitpunkt erreicht? Zwei Bedingungen: die Uhrzeit von heute ist
 * durch, und seit der letzten Mail sind genug Tage vergangen.
 *
 * Verglichen wird auf Tagesebene. „Alle 2 Tage" heißt dann wirklich jeden
 * zweiten Tag und nicht „nach 48 Stunden", was sich je nach Uhrzeit um einen
 * ganzen Tag verschieben würde.
 */
function digest_faellig(array $user): bool {
  $tage = digest_tage($user);
  if ($tage === 0) return false;
  if ((int) date('G') < (int) ($user['digest_hour'] ?? 18)) return false;
  $letzte = $user['digest_sent_at'] ?? null;
  if ($letzte === null) return true;
  return substr((string) $letzte, 0, 10) <= date('Y-m-d', strtotime('-' . $tage . ' days'));
}

/**
 * Was dieses Mitglied noch nicht gesehen hat — fertig zum Anzeigen.
 *
 * Die Sichtbarkeit wird hier geprüft und nicht später: items_unseen() kennt
 * sie nicht, weil die Ansichten ohnehin nur gefilterte Nummern nachschlagen.
 * Eine Mail baut ihre Liste selbst und verschickte sonst Titel von Terminen,
 * Liedern und Verträgen, die das Mitglied auf der Seite nie zu sehen bekäme.
 *
 * Rückgabe: Liste aus ['titel' => …, 'eintraege' => [['kopf','wer','url'], …]].
 */
function digest_collect(array $user): array {
  $lang = array_key_exists($user['pref_lang'] ?? '', LANGS) ? $user['pref_lang'] : 'de';
  $abschnitte = [];

  foreach (DIGEST_GRUPPEN as $gruppe => $sorten) {
    $eintraege = [];
    foreach ($sorten as $kind) {
      if (!isset(ITEM_KINDS[$kind])) continue;
      foreach (items_unseen($user, $kind) as $id => $marke) {
        if (!item_visible($user, $kind, (int) $id)) continue;
        $text = item_label($kind, (int) $id);
        if ($text === '') continue;              // gelöscht — überspringen
        $kopf = push_t($lang, $marke['neu'] ? 'mark_new' : 'mark_changed')
              . ' · ' . push_t($lang, 'itemkind_' . $kind) . ': ' . $text;
        $wer = [];
        if (!$marke['neu'] && $marke['wer']) $wer[] = $marke['wer'];
        if ($marke['wann']) $wer[] = fmt_date_lang(substr((string) $marke['wann'], 0, 10), $lang);
        $eintraege[] = ['kopf' => $kopf, 'wer' => implode(', ', $wer),
                        'url' => item_url($kind, (int) $id)];
      }
    }
    if ($eintraege) {
      $abschnitte[] = ['titel' => push_t($lang, 'digest_' . $gruppe), 'eintraege' => $eintraege];
    }
  }

  // Der Chat zählt je Beitrag und nicht je Eintrag — eigene Mechanik, eigener
  // Abschnitt. Der Link springt an die erste ungelesene Stelle (#318).
  if (perm_allows($user, 'themen')) {
    $eintraege = [];
    foreach (topic_unread($user) as $themaId => $anzahl) {
      $thema = row('SELECT title FROM topics WHERE id = ?', [$themaId]);
      if (!$thema) continue;
      $ab = topic_first_unread($user, (int) $themaId);
      $eintraege[] = [
        'kopf' => str_replace('%1', (string) $anzahl, push_t($lang, 'digest_chat_n')) . ': ' . $thema['title'],
        'wer' => '',
        'url' => '/intern/themen/' . (int) $themaId . ($ab ? '#p' . $ab : ''),
      ];
    }
    if ($eintraege) $abschnitte[] = ['titel' => push_t($lang, 'digest_chat'), 'eintraege' => $eintraege];
  }

  // Offene Aufgaben und fehlende Rückmeldungen: dasselbe, was die Zahl am
  // App-Symbol zählt (open_items_count). Zwei Begriffe von „offen" wären
  // schlimmer als die fehlende Mail.
  if (perm_allows($user, 'aufgaben')) {
    $eintraege = [];
    foreach (rows("SELECT t.id, t.title, t.due_date FROM tasks t
                   JOIN task_assignees ta ON ta.task_id = t.id
                   WHERE ta.user_id = ? AND t.status = 'offen' AND ta.done_at IS NULL
                   ORDER BY CASE WHEN t.due_date='' THEN 1 ELSE 0 END, t.due_date",
                  [(int) $user['id']]) as $a) {
      $eintraege[] = ['kopf' => $a['title'],
                      'wer' => $a['due_date'] ? push_t($lang, 'due_until') . ' ' . fmt_date_lang($a['due_date'], $lang) : '',
                      'url' => '/intern/aufgaben'];
    }
    if ($eintraege) $abschnitte[] = ['titel' => push_t($lang, 'digest_tasks'), 'eintraege' => $eintraege];
  }

  if (perm_allows($user, 'termine')) {
    $eintraege = [];
    foreach (open_votes($user) as $ev) {
      $eintraege[] = ['kopf' => $ev['title'] . ' · ' . fmt_date_lang($ev['date'], $lang), 'wer' => '',
                      'url' => item_url('event', (int) $ev['id'])];
    }
    if ($eintraege) $abschnitte[] = ['titel' => push_t($lang, 'digest_votes'), 'eintraege' => $eintraege];
  }

  return $abschnitte;
}

/** Wie viele Einträge stehen insgesamt drin? */
function digest_anzahl(array $abschnitte): int {
  $n = 0;
  foreach ($abschnitte as $a) $n += count($a['eintraege']);
  return $n;
}

/**
 * Kam seit der letzten Mail überhaupt etwas dazu?
 *
 * Nur nötig, wenn jemand die Erinnerung abgeschaltet hat: Dann soll dieselbe
 * Mail nicht jeden Tag erneut kommen. Ohne diesen Schalter erinnert die Mail
 * weiter, bis alles geöffnet ist — so hat der Nutzer es gewählt.
 */
function digest_neues_seit(array $user, array $abschnitte): bool {
  $letzte = $user['digest_sent_at'] ?? null;
  if ($letzte === null) return true;
  foreach (ITEM_KINDS as $kind => $art) {
    foreach (items_unseen($user, $kind) as $id => $marke) {
      if ($marke['wann'] && (string) $marke['wann'] > (string) $letzte
          && item_visible($user, $kind, (int) $id)) {
        return true;
      }
    }
  }
  // Chat und offene Punkte zählen mit: Ein neuer Beitrag ist neu, auch wenn
  // keine Marke daran hängt.
  return false;
}

/** Die Mail als reiner Text. */
function digest_text(array $user, array $abschnitte): string {
  $lang = array_key_exists($user['pref_lang'] ?? '', LANGS) ? $user['pref_lang'] : 'de';
  $band = setting('band_name');
  $zeilen = [];
  $zeilen[] = str_replace('%1', (string) $user['name'], push_t($lang, 'digest_hello'));
  $zeilen[] = '';
  $zeilen[] = push_t($lang, 'digest_intro');
  foreach ($abschnitte as $a) {
    $zeilen[] = '';
    $zeilen[] = $a['titel'];
    $zeilen[] = str_repeat('-', mb_strlen($a['titel']));
    foreach ($a['eintraege'] as $e) {
      $zeilen[] = '  ' . $e['kopf'];
      if ($e['wer'] !== '') $zeilen[] = '  ' . $e['wer'];
      // Der Link führt über die Anmeldung ans Ziel: Wer abends im Zug liest,
      // hat meistens keine Sitzung mehr (#332).
      $zeilen[] = '  ' . absolute_url('/login?weiter=' . rawurlencode($e['url']));
      $zeilen[] = '';
    }
  }
  $zeilen[] = '';
  $zeilen[] = push_t($lang, 'digest_footer');
  $zeilen[] = absolute_url('/intern/profil');
  $zeilen[] = '';
  $zeilen[] = '-- ';
  $zeilen[] = $band;
  return implode("\n", $zeilen);
}

/**
 * Eine Tagesmail an ein Mitglied — mit Platzbeanspruchung.
 *
 * Der Stempel wird VOR dem Senden gesetzt und nur dann, wenn ihn noch niemand
 * anders gesetzt hat. Zwei gleichzeitige Aufrufe schicken sonst dieselbe Mail
 * zweimal. Scheitert das Senden, steht der Grund in mail_log — eine zweite
 * Welle wäre schlimmer als eine ausgefallene Mail.
 */
function digest_send(array $user): bool {
  if (!$user['email']) return false;
  $abschnitte = digest_collect($user);
  if (!$abschnitte) return false;                       // nichts offen: keine Mail
  if (empty($user['digest_repeat']) && !digest_neues_seit($user, $abschnitte)) return false;

  $letzte = $user['digest_sent_at'] ?? null;
  $belegt = q('UPDATE users SET digest_sent_at = NOW() WHERE id = ? AND digest_sent_at <=> ?',
              [(int) $user['id'], $letzte])->rowCount();
  if ($belegt !== 1) return false;                      // jemand war schneller

  $lang = array_key_exists($user['pref_lang'] ?? '', LANGS) ? $user['pref_lang'] : 'de';
  $betreff = str_replace('%1', (string) digest_anzahl($abschnitte), push_t($lang, 'digest_subject'))
           . ' · ' . setting('band_name');
  return band_mail_send((string) $user['email'], $betreff,
                        digest_text($user, $abschnitte), 'tagesmail', (int) $user['id']);
}

/**
 * Könnte überhaupt jemand dran sein? Eine Abfrage, damit die Fälligkeitsfrage
 * vor der Anmeldung nichts kostet.
 */
function digest_due(): bool {
  $r = row("SELECT 1 x FROM users
            WHERE digest_freq <> 'aus' AND email <> '' AND digest_hour <= ?
              AND (digest_sent_at IS NULL OR DATE(digest_sent_at) < CURDATE())
            LIMIT 1", [(int) date('G')]);
  return $r !== null;
}

/** Alle fälligen Mitglieder abarbeiten. Gibt zurück, wie viele Mails hinausgingen. */
function digest_run(): int {
  $gesendet = 0;
  foreach (rows("SELECT * FROM users WHERE digest_freq <> 'aus' AND email <> ''") as $u) {
    if (!digest_faellig($u)) continue;
    if (digest_send($u)) $gesendet++;
  }
  return $gesendet;
}
