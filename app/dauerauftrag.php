<?php
declare(strict_types=1);

/**
 * Daueraufträge: Buchungen, die sich selbst eintragen.
 *
 * Proberaummiete, GEMA, Versicherung — jeden Monat derselbe Betrag, den
 * bisher jemand von Hand tippen musste oder vergaß. Ein Dauerauftrag gehört
 * entweder der Bandkasse oder einem Mitglied; ein eigener geht nur den an,
 * der ihn angelegt hat.
 */

/** Wie oft ein Dauerauftrag bucht. Schlüssel steht in der Datenbank. */
const ORDER_INTERVALS = ['monthly' => '+1 month', 'quarterly' => '+3 months', 'yearly' => '+1 year'];

/**
 * Daueraufträge, die jemand sehen darf: alles, was die Band angeht — auch die
 * Einzahlungen der anderen —, dazu die eigenen privaten. Wer die Kasse gar
 * nicht sehen darf, bekommt nichts.
 */
function orders_for(?array $user): array {
  if (!perm_allows($user, 'kasse')) return [];
  return rows('SELECT s.*, u.name AS owner_name FROM standing_orders s
               LEFT JOIN users u ON u.id = s.owner_id
               WHERE s.private = 0 OR s.owner_id = ?
               ORDER BY s.owner_id IS NULL DESC, s.private, s.next_date', [$user['id'] ?? 0]);
}

/**
 * Darf jemand diesen Dauerauftrag ändern? Einen privaten ändert nur sein
 * Besitzer — auch ein Admin verwaltet keine fremden Privatbuchungen. Alles
 * andere ist Bandsache und damit Sache der Kassenführung; eine Einzahlung
 * ändert außerdem, wer sie eingerichtet hat.
 */
function may_edit_order(?array $user, ?array $order): bool {
  if (!$user || !$order) return false;
  if ((int) $order['owner_id'] === (int) $user['id'] && $order['owner_id'] !== null) return true;
  if ((int) $order['private']) return false;
  return perm_allows($user, 'kasse', 'write');
}

/** Nächstes Fälligkeitsdatum nach einem gebuchten Termin. */
function order_next_date(string $date, string $interval): string {
  $step = ORDER_INTERVALS[$interval] ?? '+1 month';
  return date('Y-m-d', strtotime($date . ' ' . $step));
}

/**
 * Alle fälligen Daueraufträge buchen — auch rückwirkend, wenn die Seite eine
 * Weile nicht geöffnet wurde. Gebucht wird bis heute, nie in die Zukunft.
 *
 * @return int Zahl der erzeugten Buchungen
 */
function orders_run(): int {
  $today = date('Y-m-d');
  $made = 0;
  foreach (rows('SELECT * FROM standing_orders WHERE paused = 0 AND next_date <= ?', [$today]) as $order) {
    $date = $order['next_date'];
    // Ein Lauf holt alle versäumten Termine nach; die Schranke verhindert,
    // dass ein kaputtes Datum eine Endlosschleife dreht.
    for ($guard = 0; $guard < 120 && $date <= $today; $guard++) {
      if ($order['end_date'] !== null && $date > $order['end_date']) break;
      // Ein privater Auftrag bucht privat: die Zeile steht im Kassenbuch,
      // sieht sie aber nur ihr Besitzer, und sie zählt nicht zum Bandvermögen.
      // Eine Einzahlung trägt denselben Namen, geht aber alle an.
      q('INSERT INTO finances (date, type, amount_cents, category, description, member_id, created_by, standing_order_id, private_for)
         VALUES (?,?,?,?,?,?,?,?,?)', [
        $date, $order['type'], $order['amount_cents'], $order['category'],
        $order['description'], $order['owner_id'], $order['created_by'], $order['id'],
        (int) $order['private'] ? $order['owner_id'] : null,
      ]);
      $made++;
      $date = order_next_date($date, $order['interval_kind']);
    }
    q('UPDATE standing_orders SET next_date = ? WHERE id = ?', [$date, $order['id']]);
  }
  return $made;
}
