<?php
declare(strict_types=1);

/**
 * Neu und geändert: was ein Mitglied noch nicht gesehen hat (#321).
 *
 * Eigene Datei, weil es ein Thema ist und bootstrap.php ohnehin zu viele trägt.
 * Nach außen braucht sie nur q(), row(), rows(), t(), e(), fmt_date() und
 * setting() — sie wird deshalb aus bootstrap.php geladen, sobald die stehen.
 */

/**
 * Was sich merken lässt und woran eine Änderung erkannt wird (#321).
 *
 * Je Art: die Tabelle, die beiden Spalten für „wann" und „von wem", und die
 * Felder, deren Änderung die Band wirklich angeht. Was nicht in der Feldliste
 * steht — eine nachgetragene Rechnungsnummer, ein Feinschliff am öffentlichen
 * Text —, macht keine Marke. Marken, die oft für nichts stehen, liest nach
 * einer Woche niemand mehr. Die Liste ist deshalb absichtlich kürzer als die
 * Liste der Felder, die beim Speichern geschrieben werden.
 *
 * Dateien ändern sich nicht, sie kommen dazu: Sie tragen ihren Zeitstempel seit
 * jeher selbst und haben keine Feldliste. Daran — `wann` heißt `created_at` —
 * erkennt der Rest der Datei, dass diese Sorte nur geboren und nie berührt wird.
 */
const ITEM_KINDS = [
  'event'    => ['tabelle' => 'events', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['type', 'title', 'date', 'time', 'time_meet', 'time_end',
                              'location', 'venue_id', 'status', 'fee', 'setlist_id',
                              'notes', 'responsible_id']],
  'song'     => ['tabelle' => 'songs', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['title', 'artist', 'song_key', 'tempo', 'duration_sec',
                              'status', 'notes', 'lyrics', 'chords']],
  'setlist'  => ['tabelle' => 'setlists', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['name', 'notes']],
  'quote'    => ['tabelle' => 'quotes', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['title', 'customer', 'quote_date', 'play_minutes', 'km',
                              'nights', 'own_pa', 'surcharge_percent', 'discount_mode',
                              'discount_percent', 'discount_cents', 'notes']],
  'contract' => ['tabelle' => 'contracts', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['event_id', 'promoter_id', 'contract_no', 'contract_date',
                              'fee_cents', 'play_from', 'play_to', 'get_in', 'status', 'notes']],
  'file'     => ['tabelle' => 'files', 'wann' => 'created_at', 'wer' => 'uploaded_by',
                 'felder' => []],
  // Ein Kommentar wird geschrieben und nie geändert - wie eine Datei. Daher
  // created_at als Zeitstempel: item_only_born() lässt ihn immer als "neu"
  // gelten, und keine Schreibstelle muss eine Marke setzen (#331).
  'comment'  => ['tabelle' => 'comments', 'wann' => 'created_at', 'wer' => 'user_id',
                 'felder' => []],
  // 'wer' ist updated_by, nicht die eigene user_id der Zeile: In der Sache
  // dieselbe Person, aber über die übliche Spalte braucht die Zusage
  // nirgends sonst eine Ausnahme (#331).
  'attendance' => ['tabelle' => 'attendance', 'wann' => 'updated_at', 'wer' => 'updated_by',
                   'felder' => ['status']],
  'venue'    => ['tabelle' => 'venues', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['name', 'city', 'postcode', 'address', 'notes', 'contact_name',
                              'contact_email', 'contact_phone', 'contact_mobile']],
  'absence'  => ['tabelle' => 'absences', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['date_from', 'date_to', 'note']],
  'task'     => ['tabelle' => 'tasks', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['title', 'notes', 'assigned_to', 'due_date', 'status']],
  // disposed_on gehört zu den verglichenen Feldern: Ein Abgang ist die
  // Änderung am Gerät, die die Band am ehesten angeht.
  'equipment' => ['tabelle' => 'equipment', 'wann' => 'updated_at', 'wer' => 'updated_by',
                  'felder' => ['name', 'category', 'owner_id', 'location', 'is_standard',
                               'notes', 'parent_id', 'slot', 'purchased_on', 'price_cents',
                               'acquired_as', 'article_no', 'quantity', 'disposed_on']],
  'finance'  => ['tabelle' => 'finances', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['date', 'type', 'amount_cents', 'category', 'description',
                              'event_id', 'member_id']],
  'guest'    => ['tabelle' => 'guests', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['name', 'function_name', 'email', 'phone', 'mobile',
                              'street', 'postcode', 'city', 'notes']],
  'photo'    => ['tabelle' => 'photos', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['caption', 'is_public', 'event_id']],
  'media'    => ['tabelle' => 'media_links', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['kind', 'title', 'url']],
  // x und y bewusst nicht dabei: Einen Kasten im Plan zwei Prozent zu
  // verschieben ist Gefummel, kein Ereignis. Ein neuer Name schon.
  'stageitem' => ['tabelle' => 'stage_items', 'wann' => 'updated_at', 'wer' => 'updated_by',
                  'felder' => ['kind', 'label', 'note']],
  'channel'  => ['tabelle' => 'channels', 'wann' => 'updated_at', 'wer' => 'updated_by',
                 'felder' => ['number', 'name', 'source', 'notes']],
  // Eine Mail kommt von außen: Sie wird angelegt und nie geändert, und es
  // gibt niemanden, dessen "eigene Änderung" sie wäre. updated_by bleibt
  // deshalb leer und ist trotzdem da, weil items_unseen() die Spalte liest.
  'post'     => ['tabelle' => 'post_messages', 'wann' => 'created_at', 'wer' => 'updated_by',
                 'felder' => []],
];

/** Wird diese Sorte nur angelegt und nie geändert? */
function item_only_born(string $kind): bool {
  return (ITEM_KINDS[$kind]['wann'] ?? '') === 'created_at';
}

/**
 * Ein neuer Eintrag: ab jetzt für alle anderen „neu".
 *
 * Der Änderungszeitpunkt ist derselbe wie der Anlagezeitpunkt — genau daran
 * unterscheidet die Anzeige später „neu" von „geändert", ohne dafür eine
 * weitere Spalte zu brauchen.
 */
function item_new(string $kind, int $id, ?int $wer): void {
  if (!isset(ITEM_KINDS[$kind]) || item_only_born($kind)) return;
  $tabelle = ITEM_KINDS[$kind]['tabelle'];
  q("UPDATE `$tabelle` SET updated_at = created_at, updated_by = ? WHERE id = ?", [$wer, $id]);
}

/**
 * Hat sich etwas geändert, das die Band angeht? Dann trägt der Eintrag den
 * Zeitpunkt und den Urheber, und für alle anderen steht er als „geändert" da,
 * bis sie ihn angesehen haben.
 *
 * $vorher ist die Zeile, wie sie vor dem Schreiben aussah; der Aufrufer holt
 * sie sich, bevor er schreibt. `null` heißt „markieren ohne zu vergleichen" —
 * das braucht, was sich ändert, ohne eine eigene Spalte anzufassen: Eine
 * umgestellte Setliste ändert ihre Lieder, nicht sich selbst, und ist für die
 * Band trotzdem eine andere.
 *
 * Gibt zurück, ob eine Marke gesetzt wurde; das ist die Antwort auf „warum
 * steht da nichts?".
 */
function item_touch(string $kind, int $id, ?array $vorher, ?int $wer): bool {
  if (!isset(ITEM_KINDS[$kind]) || item_only_born($kind)) return false;
  $art = ITEM_KINDS[$kind];
  if ($vorher !== null) {
    $nachher = row('SELECT * FROM `' . $art['tabelle'] . '` WHERE id = ?', [$id]);
    if (!$nachher) return false;
    $anders = false;
    foreach ($art['felder'] as $feld) {
      // Lose verglichen wäre "0" gleich "" und eine abgeräumte Gage keine
      // Änderung. Beide Werte kommen aus der Datenbank, also als Text.
      if ((string) ($vorher[$feld] ?? '') !== (string) ($nachher[$feld] ?? '')) { $anders = true; break; }
    }
    if (!$anders) return false;
  }
  q('UPDATE `' . $art['tabelle'] . '` SET updated_at = NOW(3), updated_by = ? WHERE id = ?', [$wer, $id]);
  return true;
}

/**
 * Lesen, schreiben lassen, vergleichen — der Dreischritt, den jede Schreibroute
 * sonst selbst hinschreiben müsste. Dass die Zeile VOR dem Schreiben geholt
 * wird, ist die Hälfte, die man vergisst; hier kann man sie nicht vergessen.
 */
function item_update(string $kind, int $id, callable $schreiben, ?int $wer): bool {
  $tabelle = ITEM_KINDS[$kind]['tabelle'] ?? '';
  $vorher = $tabelle !== '' ? row("SELECT * FROM `$tabelle` WHERE id = ?", [$id]) : null;
  $schreiben();
  return item_touch($kind, $id, $vorher ?? [], $wer);
}

/**
 * Der Stichtag, ab dem markiert wird — je Sorte einer.
 *
 * Warum je Sorte: Kommt eine Sorte später dazu (#331), hätte sie sonst den
 * alten, gemeinsamen Stichtag geerbt — und beim ersten Aufruf nach dem Update
 * stünde alles als „neu" da, was seitdem entstanden ist. Für Kommentare und
 * das Postfach wäre das der halbe Bestand gewesen: Beide tragen created_at als
 * Zeitstempel, es gibt also keine leere Spalte, hinter der sich Altes
 * versteckt.
 *
 * Fehlt der eigene Stichtag, gilt der gemeinsame. Das hält jede Sorte am
 * Laufen, die es schon vor #331 gab.
 */
function marks_since(string $kind = ''): string {
  static $werte = [];
  return $werte[$kind] ??= setting('marks_since_' . $kind) ?: setting('marks_since', '1000-01-01');
}

/**
 * Was dieses Konto in dieser Sorte noch nicht gesehen hat: Nummer => [neu, wer,
 * wann]. Eigene Änderungen zählen nie — man hat sie ja gerade gemacht.
 *
 * Zwei Grenzen wie beim Chat: Wer noch nie hingesehen hat, bekommt alles ab
 * seinem Beitrittstag; wer hingesehen hat, nur was danach kam. Und was vor dem
 * Stichtag entstand, bleibt außen vor.
 */
function items_unseen(?array $user, string $kind): array {
  $uid = (int) ($user['id'] ?? 0);
  if (!$uid || !isset(ITEM_KINDS[$kind])) return [];
  $art = ITEM_KINDS[$kind];
  [$tabelle, $wann, $wer] = [$art['tabelle'], $art['wann'], $art['wer']];
  // Die beiden Grenzen sind bewusst verschieden: Ab dem Beitritt zählt alles
  // mit, was in derselben Sekunde oder später entstand — wer dazukommt, während
  // jemand tippt, soll es sehen. Nach dem Ansehen zählt nur, was danach kam,
  // sonst stünde das gerade Gesehene gleich wieder als neu da.
  $zeilen = rows("SELECT i.id, (i.`$wann` <= i.created_at) AS ist_neu, i.`$wann` AS wann, w.name AS wer
                    FROM `$tabelle` i
                    LEFT JOIN seen_marks s ON s.kind = ? AND s.item_id = i.id AND s.user_id = ?
                    LEFT JOIN users w ON w.id = i.`$wer`
                    JOIN users me ON me.id = ?
                   WHERE i.`$wann` IS NOT NULL
                     AND i.`$wann` >= ?
                     AND (i.`$wer` IS NULL OR i.`$wer` <> ?)
                     AND ((s.seen_at IS NULL     AND i.`$wann` >= me.created_at)
                       OR (s.seen_at IS NOT NULL AND i.`$wann` >  s.seen_at))",
                 [$kind, $uid, $uid, marks_since($kind), $uid]);
  $offen = [];
  foreach ($zeilen as $z) {
    $offen[(int) $z['id']] = [
      'neu'  => item_only_born($kind) || (bool) $z['ist_neu'],
      'wer'  => $z['wer'],
      'wann' => $z['wann'],
    ];
  }
  return $offen;
}

/** Dieser Eintrag ist gesehen. */
function item_mark_seen(?array $user, string $kind, int $id): void {
  items_mark_seen($user, $kind, [$id]);
}

/**
 * Gleich mehrere Einträge einer Sorte als gesehen vermerken — eine Anweisung
 * statt einer je Zeile. Die Terminliste zeigt auf Wunsch die ganze Geschichte
 * der Band; eine Einfügung je Karte wären dort hunderte.
 */
function items_mark_seen(?array $user, string $kind, array $ids): void {
  $uid = (int) ($user['id'] ?? 0);
  $ids = array_values(array_unique(array_map('intval', $ids)));
  if (!$uid || !$ids || !isset(ITEM_KINDS[$kind])) return;
  $werte = [];
  foreach ($ids as $id) { $werte[] = $uid; $werte[] = $kind; $werte[] = $id; }
  q('INSERT INTO seen_marks (user_id, kind, item_id, seen_at) VALUES '
    . implode(',', array_fill(0, count($ids), '(?,?,?,NOW(3))'))
    . ' ON DUPLICATE KEY UPDATE seen_at = NOW(3)', $werte);
}

/**
 * Eine ganze Sammlung wurde ersetzt. Stagerider und Kanalbelegung werden am
 * Stück gespeichert, nicht Zeile für Zeile — erst fliegen alle Zeilen raus,
 * dann kommen die neuen. Die alten Marken müssen mit, sonst zeigen sie auf
 * Nummern, die neu vergeben werden.
 *
 * updated_at = created_at, weil jede Zeile wirklich neu ist: Genau daran
 * unterscheidet die Anzeige „neu" von „geändert".
 */
function items_replaced(string $kind, ?int $wer): void {
  if (!isset(ITEM_KINDS[$kind])) return;
  q('DELETE FROM seen_marks WHERE kind = ?', [$kind]);
  q('UPDATE `' . ITEM_KINDS[$kind]['tabelle'] . '` SET updated_at = created_at, updated_by = ?', [$wer]);
}

/**
 * Was gelöscht wird, hinterlässt keine Marken. Sonst zeigt eine Zeile auf eine
 * Nummer, die es nicht mehr gibt — und trifft irgendwann einen neuen Eintrag,
 * der dieselbe Nummer bekommen hat, und gilt dort als längst gesehen.
 */
function item_forget(string $kind, int $id): void {
  if (!isset(ITEM_KINDS[$kind])) return;
  q('DELETE FROM seen_marks WHERE kind = ? AND item_id = ?', [$kind, $id]);
}

/**
 * Die Marke als fertiges Stück Seite — einmal hier, damit sie überall gleich
 * aussieht und überall gleich maskiert ist.
 */
function item_mark_html(array $unseen, int $id): string {
  $m = $unseen[$id] ?? null;
  if (!$m) return '';
  $text = $m['neu'] ? t('mark_new') : t('mark_changed');
  if (!$m['neu'] && $m['wer']) $text .= ' · ' . $m['wer'];
  if ($m['wann']) $text .= ' · ' . fmt_date(substr((string) $m['wann'], 0, 10));
  return '<span class="badge neu">' . e($text) . '</span>';
}
