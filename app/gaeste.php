<?php
/**
 * Gäste (#294): Leute, die für einen Abend dazukommen — Tontechnik,
 * Bläsersatz, eine Aushilfe — ohne Mitglied zu sein.
 *
 * Kein Login, keine Rechte: Der einzige Schlüssel ist das Token einer Buchung,
 * und es gilt für genau einen Termin und nur bis zum Mittag danach. Alles, was
 * ein Gast zu sehen bekommt, hängt deshalb an der Buchung und nicht an einem
 * Konto — und was er dort sieht, entscheidet die Band je Buchung.
 *
 * Eigene Datei, seit hier mehr steht als die Buchung selbst: Die bootstrap
 * trägt die halbe Anwendung, und ein Bereich, der eine eigene Seite nach außen
 * öffnet, soll an einer Stelle nachzulesen sein.
 */

const GUEST_STATUSES = ['angefragt', 'zugesagt', 'abgesagt', 'storniert'];

function guest_status_label(string $status): string {
  return t('guest_st_' . (in_array($status, GUEST_STATUSES, true) ? $status : 'angefragt'));
}

/**
 * Was der Link dieser Buchung öffnet (#449).
 *
 * Drei Dinge entscheidet die Band je Buchung: Rider, Setliste samt Texten und
 * die Liederliste zum Ankreuzen. Alles andere — Datum, Ort, Treffzeit, seine
 * Aufgabe — steht immer da; das ist die Einladung selbst.
 *
 * Ein Name, den es nicht gibt, ist zu. Standardmäßig „nein" heißt hier: Wer
 * sich vertippt, zeigt zu wenig und nicht versehentlich zu viel.
 */
const GUEST_PARTS = ['rider' => 'show_rider', 'setlist' => 'show_setlist', 'songs' => 'show_songs'];

function guest_may_see(array $b, string $teil): bool {
  $spalte = GUEST_PARTS[$teil] ?? '';
  return $spalte !== '' && (int) ($b[$spalte] ?? 0) === 1;
}

/**
 * Die Lieder, die ein Gast zu sehen bekommt (#450).
 *
 * Dieselbe Auswahl, die auch die Setliste anbietet: was gespielt wird und was
 * geprobt wird. Ein Vorschlag, über den die Band noch abstimmt, ist keine
 * Frage an einen Gast.
 */
function guest_songs_list(): array {
  return rows("SELECT id, title, artist, song_key, tempo, duration_sec FROM songs
                WHERE status IN ('aktiv', 'in_arbeit') ORDER BY title");
}

/** Was er bisher angekreuzt hat: Liednummer => 'yes' | 'maybe' | 'no'. */
function guest_song_answers(int $guestId): array {
  $out = [];
  foreach (rows('SELECT song_id, status FROM guest_songs WHERE guest_id = ?', [$guestId]) as $r) {
    $out[(int) $r['song_id']] = (string) $r['status'];
  }
  return $out;
}

/**
 * Eine Antwort festhalten.
 *
 * Geprüft wird beides, der Stand und das Lied: Die Liste kommt zwar aus
 * guest_songs_list(), aber die Nummer steht in der Adresse, und dort kann
 * jeder etwas anderes hineinschreiben.
 *
 * @return bool false, wenn der Stand erfunden oder das Lied nicht zur Wahl
 *              steht — dann ist nichts geschrieben worden
 */
function guest_song_set(int $guestId, int $songId, string $status): bool {
  if (!in_array($status, ['yes', 'maybe', 'no'], true)) return false;
  if (!row("SELECT 1 FROM songs WHERE id = ? AND status IN ('aktiv', 'in_arbeit')", [$songId])) return false;
  q('INSERT INTO guest_songs (guest_id, song_id, status, updated_at) VALUES (?,?,?,NOW(3))
     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)',
    [$guestId, $songId, $status]);
  return true;
}

/**
 * Sein Stand in Zahlen.
 *
 * Gezählt wird nur, was er gerade vor sich hat: Eine Antwort zu einem Lied,
 * das die Band inzwischen archiviert hat, bleibt stehen — sie gilt wieder,
 * wenn das Lied zurückkommt —, zählt aber nirgends mit. Sonst stünde auf
 * seiner Seite „50 von 48" und er suchte zwei Lieder, die es nicht mehr gibt.
 *
 * @return array{yes:int, maybe:int, no:int, answered:int, total:int}
 */
function guest_song_counts(int $guestId): array {
  $z = row("SELECT
              SUM(gs.status = 'yes') AS ja, SUM(gs.status = 'maybe') AS vielleicht,
              SUM(gs.status = 'no') AS nein, COUNT(*) AS beantwortet
            FROM guest_songs gs JOIN songs s ON s.id = gs.song_id
            WHERE gs.guest_id = ? AND s.status IN ('aktiv', 'in_arbeit')", [$guestId]);
  return [
    'yes' => (int) ($z['ja'] ?? 0), 'maybe' => (int) ($z['vielleicht'] ?? 0),
    'no' => (int) ($z['nein'] ?? 0), 'answered' => (int) ($z['beantwortet'] ?? 0),
    'total' => (int) (row("SELECT COUNT(*) AS n FROM songs WHERE status IN ('aktiv', 'in_arbeit')")['n'] ?? 0),
  ];
}

/**
 * Ort und Navi-Ziel eines Gast-Termins: der hinterlegte Veranstaltungsort mit
 * Anschrift, sonst der Freitext. Einmal hier, weil Einladungsmail und
 * Gastseite dasselbe sagen müssen.
 */
function guest_event_place(array $b): array {
  $venue = !empty($b['venue_id']) ? row('SELECT * FROM venues WHERE id = ?', [$b['venue_id']]) : null;
  if ($venue) {
    return ['ort' => event_place(['venue_name' => $venue['name'], 'venue_address' => $venue['address'],
                                  'venue_postcode' => $venue['postcode'], 'venue_city' => $venue['city']]),
            'navi' => venue_dest($venue)];
  }
  $frei = trim((string) ($b['location'] ?? ''));
  return ['ort' => $frei, 'navi' => navi_dest($frei)];
}

/**
 * Bis wann ein Gast nach seinem Termin noch hineinsieht: Mittag des Folgetags.
 * Ein Gig endet nach Mitternacht, abgebaut wird nachts — um null Uhr wäre der
 * Zugang genau dann weg, wenn jemand noch die Kanalliste braucht.
 */
function guest_access_until(string $eventDate): string {
  return date('Y-m-d 12:00:00', strtotime($eventDate . ' +1 day'));
}

/** Die Buchung zu einem Token — nur, wenn sie noch gilt. Sonst null. */
function guest_booking_by_token(string $token): ?array {
  if (!preg_match('~^[a-f0-9]{64}$~', $token)) return null;
  $b = row('SELECT b.*, g.name AS guest_name, g.email AS guest_email, e.title, e.date, e.time, e.time_meet, e.time_end,
                   e.location, e.support_act, e.setlist_id, e.venue_id, e.status AS event_status
            FROM guest_bookings b JOIN guests g ON g.id = b.guest_id JOIN events e ON e.id = b.event_id
            WHERE b.token = ?', [$token]);
  if (!$b) return null;
  // Absage und Stornierung nehmen den Schlüssel sofort, das Datum am Folgetag.
  if (in_array($b['status'], ['abgesagt', 'storniert'], true)) return null;
  if (strtotime($b['access_until']) < time()) return null;
  return $b;
}

/**
 * Bewertungen je Buchung: Schnitt, Anzahl, eigene Sterne und die Zeilen dazu.
 * Eine Abfrage für alle Buchungen einer Seite.
 */
function guest_ratings_map(array $bookingIds, int $meId): array {
  if (!$bookingIds) return [];
  $ph = implode(',', array_fill(0, count($bookingIds), '?'));
  $out = [];
  foreach (rows("SELECT r.*, u.name FROM guest_ratings r LEFT JOIN users u ON u.id = r.user_id
                 WHERE r.booking_id IN ($ph) ORDER BY r.created_at", $bookingIds) as $r) {
    $b = (int) $r['booking_id'];
    $out[$b] ??= ['sum' => 0, 'n' => 0, 'mine' => 0, 'rows' => []];
    $out[$b]['sum'] += (int) $r['stars'];
    $out[$b]['n']++;
    if ((int) $r['user_id'] === $meId) $out[$b]['mine'] = (int) $r['stars'];
    $out[$b]['rows'][] = $r;
  }
  foreach ($out as &$o) $o['avg'] = $o['n'] ? round($o['sum'] / $o['n'], 1) : 0;
  unset($o);
  return $out;
}

/** Sterne als Text, „★★★★☆", für Liste und Karte. */
function guest_stars(float $avg): string {
  $voll = (int) round($avg);
  return str_repeat('★', max(0, min(5, $voll))) . str_repeat('☆', 5 - max(0, min(5, $voll)));
}

/**
 * Die Einladung eines Gasts als Nachricht fürs Handy (#299): Links, die auf
 * dem Gerät des Mitglieds WhatsApp oder die SMS-App mit fertigem Text öffnen.
 * Verschickt wird dort erst auf Tippen — der Server redet mit niemandem, es
 * kostet nichts, und es braucht kein Konto bei irgendwem. Ohne Nummer leer.
 */
function guest_share_links(array $b): array {
  return share_links((string) ($b['guest_phone'] ?? ''),
    sprintf(t('guest_share_msg'), trim((string) $b['guest_name']), setting('band_name'),
            fmt_date($b['date']), $b['title'], absolute_url('/gast/' . $b['token'])));
}

/** Buchungen je Termin, für die Karten: Gastname, Funktion, Status. */
function guest_bookings_map(array $eventIds): array {
  if (!$eventIds) return [];
  $ph = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT b.*, g.name AS guest_name, g.email AS guest_email, g.phone AS guest_phone, e.title, e.date FROM guest_bookings b
                 JOIN events e ON e.id = b.event_id
                 JOIN guests g ON g.id = b.guest_id WHERE b.event_id IN ($ph) ORDER BY g.name", $eventIds) as $b) {
    $out[(int) $b['event_id']][] = $b;
  }
  return $out;
}

/**
 * Die Einladung an einen Gast: ein Link, der zuerst die Antwort entgegennimmt
 * und danach — bei Zusage — sein Zugang für diesen einen Termin ist. Landet
 * wie die Mitglieder-Einladung in mail_log (#293), Art „gast".
 */
function guest_invite_mail(array $b): bool {
  $band = setting('band_name');
  $ort = guest_event_place($b)['ort'];
  $body = 'Hallo ' . trim((string) $b['guest_name']) . ",\n\n"
    . "$band fragt dich für einen Termin an:\n\n"
    . '  ' . fmt_date_lang($b['date'], 'de') . ($b['time'] ? ' · ' . $b['time'] . ' Uhr' : '') . "\n"
    . '  ' . $b['title'] . "\n"
    . ($ort !== '' ? "  $ort\n" : '')
    . ($b['function_name'] !== '' ? '  Deine Aufgabe: ' . $b['function_name'] . "\n" : '')
    . "\nBitte sag hier zu oder ab:\n" . absolute_url('/gast/' . $b['token']) . "\n\n"
    . "Nach deiner Zusage ist derselbe Link dein Zugang zu allem, was du für den Abend brauchst — "
    . "Ablauf, Rider, Setliste. Er ist persönlich und gilt bis zum Mittag nach dem Termin.\n\n"
    . "Viele Grüße\n$band";
  // Deutsch festgenagelt (#349): Der Rumpf dieser Mail ist deutsch geschrieben,
  // also hat das Datum darin nichts in der Browsersprache dessen zu suchen, der
  // gerade einlädt. Vorher stand im Betreff „Anfrage für Thu, 03.08.2028“,
  // sobald ein Admin die Oberfläche auf Englisch gestellt hatte.
  $ok = band_mail_send((string) $b['guest_email'], "$band: Anfrage für " . fmt_date_lang($b['date'], 'de'),
                       $body, 'gast', null, 'Buchung ' . (int) $b['id']);
  if ($ok) q('UPDATE guest_bookings SET invited_at = NOW() WHERE id = ?', [$b['id']]);
  return $ok;
}
