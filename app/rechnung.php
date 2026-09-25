<?php
/**
 * Die Rechnung an den Veranstalter (#356).
 *
 * Nicht zu verwechseln mit `invoices`: Das sind die Belege, die bei der Band
 * eingehen. Hier geht etwas hinaus, und dafür gelten andere Regeln — eine
 * fortlaufende Nummer, Pflichtangaben und ein Wortlaut, der sich nach dem
 * Verschicken nicht mehr ändern darf.
 */

/** Was eine Rechnung durchläuft. */
const INVOICE_STATUSES = ['entwurf', 'verschickt', 'bezahlt'];

/**
 * Die nächste Rechnungsnummer — lückenlos und nur einmal vergeben.
 *
 * Die Zahl steht als Einstellung je Jahr und wird in EINER Anweisung erhöht
 * und gelesen: LAST_INSERT_ID(x) merkt sich x für diese Verbindung, und das
 * nachfolgende SELECT liefert genau den Wert, den dieses UPDATE gesetzt hat —
 * auch wenn im selben Augenblick jemand anderes dieselbe Zeile erhöht.
 *
 * Lesen-dann-Schreiben wäre die naheliegende Lösung und die falsche: Zwei
 * gleichzeitige Rechnungen bekämen dieselbe Nummer, und das fällt erst auf,
 * wenn das Finanzamt fragt.
 *
 * Vergeben wird beim Anlegen, nicht beim Verschicken. Eine verworfene Rechnung
 * hinterlässt damit eine Nummer, die es nicht mehr gibt — genau deshalb bleibt
 * sie als Storno stehen, statt gelöscht zu werden.
 */
function invoice_next_no(): string {
  $jahr = date('Y');
  $schluessel = 'invoice_counter_' . $jahr;
  q("INSERT INTO settings (`key`, value) VALUES (?, '0') ON DUPLICATE KEY UPDATE value = value", [$schluessel]);
  q("UPDATE settings SET value = LAST_INSERT_ID(CAST(value AS UNSIGNED) + 1) WHERE `key` = ?", [$schluessel]);
  $nr = (int) ($GLOBALS['db']->query('SELECT LAST_INSERT_ID()')->fetchColumn());
  settings_forget();
  $praefix = trim(setting('invoice_prefix', 'R')) ?: 'R';
  return sprintf('%s%s-%03d', $praefix, $jahr, $nr);
}

/** Die Posten einer Rechnung. */
function invoice_items(int $invoiceId): array {
  return rows('SELECT * FROM sales_invoice_items WHERE invoice_id = ? ORDER BY sort, id', [$invoiceId]);
}

/**
 * Netto, Steuer und Brutto aus den Posten.
 *
 * Gerechnet wird in Cent, und der Steuerbetrag entsteht aus der Nettosumme —
 * nicht je Posten. Wer je Posten rundet und dann addiert, kommt bei zehn
 * Positionen um ein paar Cent daneben, und eine Rechnung, die um einen Cent
 * abweicht, glaubt niemand.
 */
function invoice_totals(array $rechnung, array $posten): array {
  $netto = 0;
  foreach ($posten as $p) $netto += (int) $p['amount_cents'];
  $satz = (int) $rechnung['vat_percent'];
  $steuer = (int) round($netto * $satz / 100);
  return ['net' => $netto, 'vat' => $steuer, 'total' => $netto + $steuer];
}

/**
 * Eine Rechnung aus einem Vertrag bilden.
 *
 * Alles, was schon feststeht, kommt von dort: Veranstalter, Termin, Gage. Und
 * die Steuerlage wird eingefroren — ändert die Band im nächsten Jahr ihren
 * Status, darf das nicht rückwirkend umschreiben, was verschickt wurde.
 *
 * Gibt die Nummer der neuen Rechnung zurück, oder 0, wenn es schon eine gibt.
 */
function invoice_from_contract(int $contractId, int $wer): int {
  $v = row('SELECT * FROM contracts WHERE id = ?', [$contractId]);
  if (!$v) return 0;
  $schon = row('SELECT id FROM sales_invoices WHERE contract_id = ?', [$contractId]);
  if ($schon) return (int) $schon['id'];

  $klein = setting('tax_small_business', '0') === '1';
  $satz = $klein ? 0 : (int) setting('tax_vat_rate', '19');
  $netto = (int) $v['fee_cents'];
  $steuer = (int) round($netto * $satz / 100);

  q('INSERT INTO sales_invoices (event_id, contract_id, promoter_id, invoice_no, invoice_date,
                                 net_cents, vat_percent, vat_cents, total_cents, small_business, created_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
    $v['event_id'], $contractId, $v['promoter_id'], invoice_next_no(), date('Y-m-d'),
    $netto, $satz, $steuer, $netto + $steuer, $klein ? 1 : 0, $wer,
  ]);
  $id = (int) $GLOBALS['db']->lastInsertId();

  // Ein Posten aus dem Vertrag. Weitere trägt die Band von Hand nach — was
  // über die Gage hinausgeht (Fahrt, Technik), steht nicht im Vertrag.
  $ev = $v['event_id'] ? row('SELECT title, date FROM events WHERE id = ?', [$v['event_id']]) : null;
  $bezeichnung = $ev
    ? str_replace(['%1', '%2'], [(string) $ev['title'], fmt_date((string) $ev['date'])], t('inv_line_gig'))
    : t('inv_line_gig_plain');
  q('INSERT INTO sales_invoice_items (invoice_id, label, amount_cents, sort) VALUES (?,?,?,1)',
    [$id, mb_substr($bezeichnung, 0, 190), $netto]);

  item_new('invoice', $id, $wer);
  return $id;
}

/** Steht zu diesem Termin schon eine Rechnung? */
function invoice_by_event(array $eventIds): array {
  if (!$eventIds) return [];
  $marken = implode(',', array_fill(0, count($eventIds), '?'));
  $karte = [];
  foreach (rows("SELECT id, event_id, status, paid_cash FROM sales_invoices
                 WHERE event_id IN ($marken) ORDER BY id", $eventIds) as $z) {
    $karte[(int) $z['event_id']] = $z;
  }
  return $karte;
}

function invoice_status_label(string $status): string {
  return t('invstatus_' . $status) !== 'invstatus_' . $status ? t('invstatus_' . $status) : $status;
}
