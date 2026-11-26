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

/**
 * Der Stand eines Datums vor dem Speichern — damit choice_sync() weiß, was
 * sich wirklich geändert hat.
 *
 * @return array{row: array, gear: int[], songs: int[], proposal: ?array}
 */
function choice_snapshot(int $eventId): array {
  $ids = static fn(string $sql): array => array_map('intval', array_column(rows($sql, [$eventId]), 'x'));
  $vorschlag = event_time_proposal($eventId);
  return [
    'row' => row('SELECT * FROM events WHERE id = ?', [$eventId]) ?? [],
    'gear' => $ids('SELECT equipment_id x FROM event_equipment WHERE event_id = ? ORDER BY equipment_id'),
    'songs' => $ids('SELECT song_id x FROM event_songs WHERE event_id = ? ORDER BY song_id'),
    'proposal' => $vorschlag ? choice_proposed_times($vorschlag) : null,
  ];
}

/**
 * Was an einem Datum geändert wurde, gilt für die ganze Auswahl.
 *
 * Titel, Ort, Zeiten, Notizen, Technik und Lieder sind eine Anfrage, nicht
 * drei; wer den Ort am 10.4. korrigiert, meint auch den 17.4. Das Datum
 * selbst und der Stand bleiben je Datum.
 *
 * Übertragen wird nur, was sich bei diesem Speichern geändert hat, nicht die
 * ganze Zeile. Die Zeiten der Daten dürfen nämlich auseinanderlaufen: Eine
 * kleine Verschiebung gilt an einem Datum ohne Zusagen sofort und wartet am
 * anderen auf Bestätigung. Schriebe jedes Speichern alles hinüber, setzte ein
 * Tippfehler in den Notizen dort die alte Zeit zurück und verwürfe still
 * alle Antworten.
 *
 * Fragt eine Änderung neu (#402), oder wird eine kleine Verschiebung zum
 * Vorschlag (#413), geschieht das an jedem Datum — aber still: Die
 * Mitteilung kommt einmal, vom bearbeiteten Datum. Drei Daten dürfen nicht
 * drei Telefone je Speichern wecken.
 *
 * @param array $vorher choice_snapshot() des bearbeiteten Datums vor dem Speichern
 */
function choice_sync(int $sourceId, int $by, array $vorher): void {
  $nachher = choice_snapshot($sourceId);
  if (!($nachher['row']['choice_of'] ?? null)) return;
  $spalten = array_values(array_filter(array_diff(CHOICE_COPY_COLUMNS, ['status']),
    static fn(string $s): bool => (string) ($vorher['row'][$s] ?? '') !== (string) ($nachher['row'][$s] ?? '')));
  $technik = ($vorher['gear'] ?? []) !== $nachher['gear'];
  $lieder = ($vorher['songs'] ?? []) !== $nachher['songs'];
  $vorschlag = $nachher['proposal'] !== null && $nachher['proposal'] !== ($vorher['proposal'] ?? null)
    ? $nachher['proposal'] : null;
  if (!$spalten && !$technik && !$lieder && !$vorschlag) return;
  $set = implode(', ', array_map(static fn(string $s): string => "s.`$s` = q.`$s`", $spalten));
  $vorschlagVon = (int) ($nachher['row']['time_proposed_by'] ?? $by);

  foreach (choice_dates($sourceId) as $d) {
    $nr = (int) $d['id'];
    if ($nr === $sourceId) continue;
    $abdruck = event_answer_fingerprint($nr);
    if ($spalten) {
      item_update('event', $nr, static function () use ($nr, $sourceId, $set): void {
        q("UPDATE events s JOIN events q ON q.id = ? SET $set WHERE s.id = ?", [$sourceId, $nr]);
      }, $by);
    }
    if ($technik) {
      q('DELETE FROM event_equipment WHERE event_id = ?', [$nr]);
      q('INSERT INTO event_equipment (event_id, equipment_id)
         SELECT ?, equipment_id FROM event_equipment WHERE event_id = ?', [$nr, $sourceId]);
    }
    if ($lieder) {
      q('DELETE FROM event_songs WHERE event_id = ?', [$nr]);
      q('INSERT INTO event_songs (event_id, song_id)
         SELECT ?, song_id FROM event_songs WHERE event_id = ?', [$nr, $sourceId]);
    }
    // Dieselbe Reihenfolge wie die Route am bearbeiteten Datum: Ein anderer
    // Ort oder eine weite Verschiebung fragt neu, sonst wandert ein neuer
    // Vorschlag mit.
    if (event_answer_fingerprint($nr) !== $abdruck) {
      event_time_clear($nr);
      event_answers_reset($nr, $by, false);
    } elseif ($vorschlag) {
      event_time_propose($nr, $vorschlag, $vorschlagVon, false);
    }
  }
}

/** Die vorgeschlagenen Zeiten eines Termins in der Form, die event_time_propose() nimmt. */
function choice_proposed_times(array $vorschlag): array {
  return ['time' => (string) $vorschlag['time_proposed'],
          'time_meet' => (string) $vorschlag['time_meet_proposed'],
          'time_end' => (string) $vorschlag['time_end_proposed']];
}

/**
 * Der Name des Schlosses, unter dem eine Auswahl gewählt wird.
 *
 * Mit dem Namen der Datenbank, weil sich mehrere Installationen einen
 * MariaDB-Server teilen und GET_LOCK serverweit gilt — wie beim Schema-Lauf.
 */
function choice_lock_name(int $groupId): string {
  return 'bandregie_wahl_' . (string) row('SELECT DATABASE() AS d')['d'] . '_' . $groupId;
}

/**
 * Ein Datum wählen: Es bleibt, die anderen gehen.
 *
 * Was an den anderen Daten hing und nicht nur zu diesem Tag gehört, wandert
 * zum gewählten: die Unterhaltung, Vertrag, Angebot, Rechnung, Fotos,
 * Dateien, die Mail des Veranstalters, Buchungen, Gästeliste, die Bitte an
 * eine Aushilfe, verknüpfte Aufgaben (#335) und die Markierung „wichtig"
 * (#333). Sonst verschwände mit einem Datum, das nur zufällig das erste war,
 * der Vertrag. Zusagen, Technik und Lieder des anderen Tages gehen mit ihm —
 * sie beantworten eine Frage, die es nicht mehr gibt.
 *
 * Unter einem Schloss und in einer Transaktion: Drücken zwei Leute zugleich
 * bei verschiedenen Daten, verschöbe sonst der eine die Kommentare zu B, der
 * andere sie zurück zu A, und am Ende löschte jeder das Datum des anderen
 * samt allem, was gerade dorthin gewandert war. Wer das Schloss nicht
 * bekommt, ändert nichts.
 *
 * Ein Datum, das schon verstrichen ist, fällt vorher aus der Auswahl und
 * bleibt stehen: Gelöscht wird nur, was noch zur Wahl stand.
 *
 * @return bool false, wenn der Termin zu keiner Auswahl (mehr) gehört — etwa
 *              weil jemand anderes gerade gewählt hat oder noch wählt
 */
function choice_choose(int $chosenId, int $by): bool {
  $gruppe = choice_group($chosenId);
  if (!$gruppe) return false;
  $schloss = choice_lock_name($gruppe);
  if ((int) (row('SELECT GET_LOCK(?, 5) AS da', [$schloss])['da'] ?? 0) !== 1) return false;
  $db = $GLOBALS['db'];
  try {
    $db->beginTransaction();
    // Erst im Schloss gelesen: Wer davor gewählt hat, hat diese Auswahl schon
    // aufgelöst, und dann gibt es hier nichts mehr zu tun.
    $daten = choice_dates($chosenId);
    if (!$daten) {
      $db->rollBack();
      return false;
    }
    foreach ($daten as $d) {
      $alt = (int) $d['id'];
      if ($alt === $chosenId) continue;
      foreach (['comments', 'contracts', 'quotes', 'sales_invoices', 'photos', 'od_folders',
                'guest_bookings', 'post_messages', 'finances'] as $tabelle) {
        q("UPDATE `$tabelle` SET event_id = ? WHERE event_id = ?", [$chosenId, $alt]);
      }
      q("UPDATE files SET entity_id = ? WHERE entity_type = 'event' AND entity_id = ?", [$chosenId, $alt]);
      // Was es am gewählten Datum schon gibt, bleibt dort einmal: dieselbe
      // Aushilfe, dieselbe Aufgabe, die Markierung. Der Rest des anderen
      // Datums geht mit ihm.
      q('UPDATE IGNORE substitute_requests SET event_id = ? WHERE event_id = ?', [$chosenId, $alt]);
      foreach (['task_links', 'important_flags'] as $tabelle) {
        q("UPDATE IGNORE `$tabelle` SET item_id = ? WHERE kind = 'event' AND item_id = ?", [$chosenId, $alt]);
        q("DELETE FROM `$tabelle` WHERE kind = 'event' AND item_id = ?", [$alt]);
      }
      event_delete($alt);
    }
    item_update('event', $chosenId, static function () use ($chosenId): void {
      q('UPDATE events SET choice_of = NULL WHERE id = ?', [$chosenId]);
    }, $by);
    $db->commit();
  } catch (Throwable $fehler) {
    if ($db->inTransaction()) $db->rollBack();
    throw $fehler;
  } finally {
    row('SELECT RELEASE_LOCK(?) AS los', [$schloss]);
  }
  $ev = row('SELECT id, title, date, status FROM events WHERE id = ?', [$chosenId]);
  $titel = (string) $ev['title'];
  $datum = (string) $ev['date'];
  $url = event_url($ev);
  push_notify('events', $by, static fn(string $lang): array => [
    'title' => str_replace('%1', $titel, push_t($lang, 'push_ev_choice_done')),
    'body'  => str_replace('%1', fmt_date_lang($datum, $lang), push_t($lang, 'push_ev_choice_done_body')),
    'url'   => $url,
  ], $chosenId);
  return true;
}

/**
 * Die Daten einer Auswahl in der Liste zusammenholen.
 *
 * Die Liste ist nach Datum sortiert; dazwischen stehen andere Termine. Der
 * Block „Zur Wahl" soll aber an einer Stelle stehen — an der seines frühesten
 * Datums —, also rücken die anderen Daten direkt dahinter. Alles andere
 * behält seine Reihenfolge.
 */
function choice_order(array $events): array {
  $gruppen = [];
  foreach ($events as $ev) {
    $g = (int) ($ev['choice_of'] ?? 0);
    if ($g) $gruppen[$g][] = $ev;
  }
  $aus = [];
  $erledigt = [];
  foreach ($events as $ev) {
    $g = (int) ($ev['choice_of'] ?? 0);
    if (!$g) { $aus[] = $ev; continue; }
    if (isset($erledigt[$g])) continue;
    $erledigt[$g] = true;
    array_push($aus, ...$gruppen[$g]);
  }
  return $aus;
}

/**
 * Die Auswahlen für eine Terminansicht, beschnitten auf das, was dieses Konto
 * sehen darf.
 *
 * Eine Aushilfe sieht nur das Datum, zu dem sie gebeten wurde, und wer nur
 * „belegt" sieht (#309), sieht von einer fremden Anfrage gar nichts — auch
 * nicht, dass es noch zwei andere Tage gäbe.
 *
 * @param int[] $verdeckt Geschwärzte Termine aus events_redact()
 */
function choice_view_map(array $eventIds, array $verdeckt, array $me): array {
  $karte = choice_map(array_diff($eventIds, $verdeckt));
  foreach ($karte as $g => $wahl) {
    $karte[$g]['dates'] = array_values(array_filter($wahl['dates'],
      static fn(array $d): bool => !in_array($d['id'], $verdeckt, true) && may_see_event($me, $d['id'])));
    if (!$karte[$g]['dates']) unset($karte[$g]);
  }
  return $karte;
}

/**
 * „Alle haben geantwortet" für eine Auswahl: einmal, wenn alle Daten durch
 * sind, mit einer Zeile je Datum (#382, #443).
 *
 * Je Datum gemeldet, käme dieselbe Anfrage zwei-, dreimal aufs Telefon, und
 * die erste Meldung wäre die unwichtigste — sie sagt nichts über die anderen
 * Tage. Die Marke answers_complete steht an allen Daten; gemeldet ist, wenn
 * sie überall steht. Wird ein Datum wieder unvollständig, fällt sie überall,
 * und die nächste Vollzähligkeit ist eine echte Neuigkeit.
 */
function choice_completion_notify(int $eventId): void {
  $daten = choice_dates($eventId);
  if (!$daten) return;
  $ids = array_map(static fn(array $d): int => (int) $d['id'], $daten);
  $in = implode(',', $ids);
  foreach ($ids as $nr) {
    if (!event_answers_complete($nr)) {
      q("UPDATE events SET answers_complete = 0 WHERE id IN ($in)");
      return;
    }
  }
  $gemeldet = (int) row("SELECT COUNT(*) n FROM events WHERE id IN ($in) AND answers_complete = 1")['n'];
  if ($gemeldet === count($ids)) return;
  q("UPDATE events SET answers_complete = 1 WHERE id IN ($in)");

  $erstes = row('SELECT id, title, date, status, created_by FROM events WHERE id = ?', [$ids[0]]);
  $wer = (int) ($erstes['created_by'] ?? 0);
  if (!$wer) return;
  $stand = [];
  foreach ($daten as $d) $stand[(string) $d['date']] = event_answer_tally((int) $d['id']);
  $titel = (string) $erstes['title'];
  $url = event_url($erstes);
  push_to_user($wer, 'vollzaehlig', static function (string $lang) use ($titel, $stand, $url): array {
    $zeilen = [];
    foreach ($stand as $datum => $s) $zeilen[] = fmt_date_lang($datum, $lang) . ': ' . event_answer_tally_text($s, $lang);
    return [
      'title' => str_replace('%1', $titel, push_t($lang, 'push_ev_complete')),
      'body'  => implode(' · ', $zeilen),
      'url'   => $url,
    ];
  });
}

/** Hat diese Person zu jedem Datum der Auswahl nein gesagt? */
function choice_all_no(int $eventId, int $userId): bool {
  $daten = choice_dates($eventId);
  if (!$daten) return false;
  $in = implode(',', array_map(static fn(array $d): int => (int) $d['id'], $daten));
  $nein = (int) row("SELECT COUNT(*) n FROM attendance WHERE user_id = ? AND status = 'no' AND event_id IN ($in)",
                    [$userId])['n'];
  return $nein === count($daten);
}
