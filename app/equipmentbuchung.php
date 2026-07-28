<?php
declare(strict_types=1);

/**
 * Gerätekäufe und -abgänge in der Kasse.
 *
 * Der Preis stand bisher nur am Gerät und kam nie in der Kasse an — Bestand
 * und Buch erzählten zwei verschiedene Geschichten. Wer bezahlt hat, ist
 * dabei der entscheidende Punkt: zahlt die Band, ist es eine Ausgabe wie
 * jede andere; zahlt jemand privat, ist es seine Sache und taucht weder im
 * Kontostand noch bei den anderen auf.
 */

/** Wer für ein Gerät bezahlt haben kann. */
const EQ_PAYERS = ['band', 'privat'];

/**
 * Darf jemand für dieses Gerät buchen? Privat buchen kann nur der Besitzer
 * für sich selbst; auf Rechnung der Band bucht, wer die Kasse führt.
 */
function eq_may_book(?array $eq, ?array $user, string $payer): bool {
  if (!$eq || !$user) return false;
  if (!perm_allows($user, 'kasse')) return false;
  if ($payer === 'privat') return (int) ($eq['owner_id'] ?? 0) === (int) $user['id'];
  return perm_allows($user, 'kasse', 'write');
}

/**
 * Darf jemand dieses Gerät abgeben? Den Erlös verbucht die Kassenführung —
 * aber ein Abgang nimmt das Gerät aus dem Bestand, von jeder Packliste und
 * aus dem Rider. Das ist eine Entscheidung über fremdes Eigentum und braucht
 * dasselbe Recht wie jede andere Änderung am Gerät.
 */
function eq_may_dispose(?array $eq, ?array $user, string $payer): bool {
  return eq_may_book($eq, $user, $payer) && eq_may_edit_owner_fields($eq, $user);
}

/** Die Buchungen zu einem Gerät, soweit der Betrachter sie sehen darf. */
function eq_bookings(int $equipmentId, ?array $user): array {
  return rows('SELECT * FROM finances
               WHERE equipment_id = ? AND (private_for IS NULL OR private_for = ?)
               ORDER BY date, id', [$equipmentId, $user['id'] ?? 0]);
}

/**
 * Buchungen zu mehreren Geräten auf einen Schlag — die Inventarseite zeigt
 * sie an jedem Gerät und soll dafür nicht je Gerät nachfragen.
 *
 * @return array<int, array<int, array>> Buchungen, nach Geräte-ID
 */
function eq_bookings_by_equipment(?array $user): array {
  $out = [];
  foreach (rows('SELECT * FROM finances
                 WHERE equipment_id IS NOT NULL AND (private_for IS NULL OR private_for = ?)
                 ORDER BY date, id', [$user['id'] ?? 0]) as $row) {
    $out[(int) $row['equipment_id']][] = $row;
  }
  return $out;
}

/**
 * Kauf oder Abgang eines Geräts buchen.
 *
 * @param string $kind 'kauf' oder 'abgang'
 */
function eq_book(array $eq, array $user, string $payer, string $kind, int $cents, string $date): void {
  // Privat gezahlt heißt: die Zeile steht im Kassenbuch, sieht sie aber nur
  // ihr Besitzer, und sie zählt nicht zum Bandvermögen.
  $private = $payer === 'privat' ? (int) $eq['owner_id'] : null;
  q('INSERT INTO finances (date, type, amount_cents, category, description, member_id, created_by, equipment_id, private_for)
     VALUES (?,?,?,?,?,?,?,?,?)', [
    $date,
    $kind === 'abgang' ? 'einnahme' : 'ausgabe',
    $cents,
    'equipment',
    ($kind === 'abgang' ? t('eqb_sold_prefix') : t('eqb_bought_prefix')) . ' ' . $eq['name'],
    $eq['owner_id'],
    $user['id'],
    $eq['id'],
    $private,
  ]);
}
