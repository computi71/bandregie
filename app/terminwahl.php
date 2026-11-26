<?php
/**
 * Ein Termin mit mehreren möglichen Daten (#443).
 *
 * Anfragen kommen oft mit zwei, drei Daten („10.4. oder 17.4."), und die Band
 * klärte das bisher im Messenger mit Häkchen je Name, bevor überhaupt etwas in
 * der App stand. Jetzt ist jedes Datum ein gewöhnlicher Termin auf
 * „Angefragt", und events.choice_of hält sie als eine Auswahl zusammen. Der
 * Wert ist die Nummer des ersten Datums — eine Marke, kein Verweis: Wird
 * genau dieses Datum gelöscht, bleiben die anderen trotzdem eine Auswahl.
 *
 * Warum gewöhnliche Termine und keine Datumsoptionen an einem Termin: Zusagen,
 * Erinnerungen, Abwesenheiten, Kalender und Kalender-Abo arbeiten alle je
 * Termin. Als eigene Termine bekommt jedes Datum das alles ohne zweiten Weg.
 */

/**
 * Die Spalten, die ein Datum vom anderen übernimmt.
 *
 * Nicht dabei: das Datum selbst, die Nummer, die Marken (created_at,
 * updated_*), die Rundenmarke answers_complete und ein offener Zeitvorschlag —
 * die gehören zu genau diesem Tag. Auch nicht alternative_for (#403): Ein
 * neues Datum ist kein Ersatz für eine Absage.
 */
const CHOICE_COPY_COLUMNS = ['type', 'title', 'time', 'time_meet', 'time_end', 'location',
  'support_act', 'venue_id', 'notes', 'is_public', 'setlist_id', 'status', 'responsible_id',
  'fee', 'invoice_no', 'public_title', 'public_link', 'public_info', 'pa_source',
  'light_source', 'created_by', 'needs_invoice'];

/** Zu welcher Auswahl gehört der Termin? 0 = zu keiner. */
function choice_group(int $eventId): int {
  return (int) (row('SELECT choice_of FROM events WHERE id = ?', [$eventId])['choice_of'] ?? 0);
}

/**
 * Weitere mögliche Daten an einen Termin hängen.
 *
 * Verworfen wird still, was keine Frage ist: ein Tag, den es nicht gibt, einer,
 * der schon vorbei ist, und einer, der in der Auswahl schon steht. Ein
 * Doppelklick im Formular soll keine zwei gleichen Daten zur Wahl stellen.
 *
 * Alle Daten der Auswahl stehen danach auf „Angefragt", auch wenn das erste
 * vorher bestätigt war: Ein Termin, der feststeht, steht nicht zur Wahl.
 *
 * @return int[] Die Nummern der neu angelegten Daten
 */
function choice_add_dates(int $sourceId, array $dates, int $by): array {
  $quelle = row('SELECT id, choice_of FROM events WHERE id = ?', [$sourceId]);
  if (!$quelle) return [];
  $gruppe = (int) ($quelle['choice_of'] ?: $quelle['id']);
  $vergeben = array_column(rows('SELECT date FROM events WHERE id = ? OR choice_of = ?',
                                [$sourceId, $gruppe]), 'date');
  $heute = date('Y-m-d');
  $spalten = implode(', ', CHOICE_COPY_COLUMNS);
  $neu = [];
  foreach ($dates as $datum) {
    $datum = trim((string) $datum);
    $tag = DateTime::createFromFormat('!Y-m-d', $datum);
    if (!$tag || $tag->format('Y-m-d') !== $datum || $datum < $heute
        || in_array($datum, $vergeben, true)) continue;
    q("INSERT INTO events (date, $spalten) SELECT ?, $spalten FROM events WHERE id = ?",
      [$datum, $sourceId]);
    $nr = (int) $GLOBALS['db']->lastInsertId();
    q('INSERT INTO event_equipment (event_id, equipment_id)
       SELECT ?, equipment_id FROM event_equipment WHERE event_id = ?', [$nr, $sourceId]);
    q('INSERT INTO event_songs (event_id, song_id)
       SELECT ?, song_id FROM event_songs WHERE event_id = ?', [$nr, $sourceId]);
    item_new('event', $nr, $by);
    $vergeben[] = $datum;
    $neu[] = $nr;
  }
  if ($neu) {
    $in = implode(',', array_fill(0, count($neu), '?'));
    q("UPDATE events SET choice_of = ?, status = 'angefragt'
       WHERE id = ? OR choice_of = ? OR id IN ($in)", [$gruppe, $sourceId, $gruppe, ...$neu]);
  }
  return $neu;
}

/**
 * Verstrichene Daten aus der Auswahl nehmen.
 *
 * Ein Tag, der vorbei ist, steht nicht mehr zur Wahl; er bleibt als
 * gewöhnlicher angefragter Termin stehen wie jede Anfrage, aus der nichts
 * wurde. Bleibt danach nur ein Datum übrig, ist auch das ein gewöhnlicher
 * Termin: Eine Wahl aus einem ist keine.
 */
function choice_prune(int $groupId): void {
  if ($groupId <= 0) return;
  q('UPDATE events SET choice_of = NULL WHERE choice_of = ? AND date < CURDATE()', [$groupId]);
  $rest = rows('SELECT id FROM events WHERE choice_of = ?', [$groupId]);
  if (count($rest) === 1) q('UPDATE events SET choice_of = NULL WHERE choice_of = ?', [$groupId]);
}

/**
 * Alle Daten der Auswahl, zu der dieser Termin gehört, nach Datum.
 *
 * @return array<int, array> Zeilen mit id, date, title, status; leer, wenn er
 *                           zu keiner gehört
 */
function choice_dates(int $eventId): array {
  $gruppe = choice_group($eventId);
  if (!$gruppe) return [];
  choice_prune($gruppe);
  return rows('SELECT id, date, title, status FROM events WHERE choice_of = ? ORDER BY date, time, id',
              [$gruppe]);
}

/**
 * Die Auswahlen, zu denen diese Termine gehören, mit dem Stand je Datum.
 *
 * Gezählt wird, wer geantwortet hat; „offen" sind die, die antworten sollen
 * (event_answerers) und es noch nicht getan haben. Hervorgehoben wird ein
 * Datum nur, wenn wirklich alle ja gesagt haben, die gefragt sind — ein
 * Datum, auf das noch drei warten, ist keins, an dem „alle können".
 *
 * @return array<int, array{dates: array<int, array>}> je choice_of-Wert
 */
function choice_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_map('intval', $eventIds));
  $gruppen = array_map('intval', array_column(
    rows("SELECT DISTINCT choice_of FROM events WHERE id IN ($in) AND choice_of IS NOT NULL"), 'choice_of'));
  $karte = [];
  foreach ($gruppen as $gruppe) {
    choice_prune($gruppe);
    $daten = [];
    foreach (rows('SELECT id, date FROM events WHERE choice_of = ? ORDER BY date, time, id', [$gruppe]) as $d) {
      $nr = (int) $d['id'];
      $antwort = array_column(rows('SELECT user_id, status FROM attendance WHERE event_id = ?', [$nr]),
                              'status', 'user_id');
      $gefragt = array_map(static fn(array $u): int => (int) $u['id'], event_answerers($nr));
      $zahl = array_count_values($antwort);
      $offen = count(array_filter($gefragt, static fn(int $u): bool => !isset($antwort[$u])));
      $alleJa = $gefragt !== [] && !array_filter($gefragt,
        static fn(int $u): bool => ($antwort[$u] ?? '') !== 'yes');
      $daten[] = ['id' => $nr, 'date' => (string) $d['date'], 'yes' => $zahl['yes'] ?? 0,
                  'maybe' => $zahl['maybe'] ?? 0, 'no' => $zahl['no'] ?? 0,
                  'open' => $offen, 'all_yes' => $alleJa];
    }
    if ($daten) $karte[$gruppe] = ['dates' => $daten];
  }
  return $karte;
}

/**
 * Darf man an diesen Termin weitere Daten hängen?
 *
 * Nur solange er selbst eine offene Frage ist. Ein bestätigter Termin steht
 * vielleicht schon öffentlich auf der Seite, hat einen Vertrag oder ist
 * abgesagt; ihn mit einem Feld wieder zur Wahl zu stellen, nähme einen Gig
 * still von der Seite.
 */
function choice_may_add_dates(array $ev): bool {
  return in_array((string) ($ev['status'] ?? ''), ['angefragt', 'reserviert'], true)
    && (string) ($ev['date'] ?? '') >= date('Y-m-d');
}

/**
 * Der Stand, der beim Speichern gilt.
 *
 * Innerhalb einer Auswahl immer „Angefragt": Die beiden Dinge, die man dort
 * mit dem Stand täte, haben ihren eigenen Weg — ein Datum fällt weg, dann
 * löscht man es; eines steht fest, dann wählt man es. Ein drittes Datum auf
 * „Findet statt" neben zwei offenen wäre eine Entscheidung, die keiner
 * getroffen hat.
 */
function choice_forced_status(int $eventId, string $posted): string {
  return choice_group($eventId) ? 'angefragt' : $posted;
}

/**
 * Eine Mitteilung für neue Daten zur Wahl, statt einer je Datum.
 *
 * Drei Daten sind eine Frage an die Band, nicht drei. Die Daten stehen in der
 * Sprache des Empfängers (#349) im Text; der Verweis führt in die Übersicht,
 * wo man je Datum mit einem Tipp antwortet (#421).
 *
 * @param int[] $ids Die Daten, die neu zur Wahl stehen
 */
function choice_push_new(int $eventId, array $ids, int $by): void {
  $ev = row('SELECT title FROM events WHERE id = ?', [$eventId]);
  if (!$ev || !$ids) return;
  $in = implode(',', array_map('intval', $ids));
  $daten = array_column(rows("SELECT date FROM events WHERE id IN ($in) ORDER BY date"), 'date');
  $titel = (string) $ev['title'];
  $url = answer_url('ev', $eventId);
  push_notify('events', $by, static fn(string $lang): array => [
    'title' => str_replace('%1', $titel, push_t($lang, 'push_ev_choice_new')),
    'body'  => str_replace('%1', implode(' / ', array_map(
      static fn(string $d): string => fmt_date_lang($d, $lang), $daten)), push_t($lang, 'push_ev_choice_new_body')),
    'url'   => $url,
  ], $eventId);
}
