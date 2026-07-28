<?php
declare(strict_types=1);

/**
 * Umsatzgrenzen im Blick behalten.
 *
 * Eine Band, die ein gutes Jahr hat, verliert die Kleinunternehmer-Befreiung,
 * ohne es zu merken — und erfährt es aus einem Brief. Die Kasse rechnet
 * deshalb mit, was an Umsatz zusammenkommt, und sagt Bescheid, bevor es so
 * weit ist.
 *
 * Das ist eine Rechenhilfe und keine Steuerberatung. Die Zahlen stehen in den
 * Einstellungen, weil der Gesetzgeber sie ändert und weil nicht jede Band in
 * Deutschland sitzt.
 */

/**
 * Was als Umsatz zählt. Einzahlungen der Mitglieder sind Beiträge und kein
 * Verkauf — sie gehören nicht dazu. Auszahlungen sind ohnehin Ausgaben.
 */
const TAX_TURNOVER_EXCLUDES = ['einlage'];

/**
 * Umsatz eines Jahres: alle Einnahmen der Band ohne die Beiträge und ohne
 * private Buchungen — was jemand für sich selbst bucht, ist nicht der Umsatz
 * der Band.
 */
function tax_turnover(int $year): int {
  $marks = implode(',', array_fill(0, count(TAX_TURNOVER_EXCLUDES), '?'));
  $row = row("SELECT COALESCE(SUM(amount_cents), 0) AS total FROM finances
              WHERE type = 'einnahme' AND private_for IS NULL
                AND YEAR(date) = ? AND category NOT IN ($marks)",
             [$year, ...TAX_TURNOVER_EXCLUDES]);
  return (int) ($row['total'] ?? 0);
}

/**
 * Stand zur Kleinunternehmergrenze — oder null, wenn die Band die Regelung
 * gar nicht nutzt und die Zahlen sie folglich nichts angehen.
 *
 * @return array{
 *   this_year: int, prev_year: int, limit_this: int, limit_prev: int,
 *   share: float, state: string
 * }|null  state: 'ok' | 'close' | 'next_year' | 'over_prev' | 'over_this'
 */
function tax_small_business_status(): ?array {
  if (setting('tax_small_business', '0') !== '1') return null;

  $year = (int) date('Y');
  $limitThis = (int) round((float) setting('tax_limit_this_year', '100000') * 100);
  $limitPrev = (int) round((float) setting('tax_limit_prev_year', '25000') * 100);
  $thisYear = tax_turnover($year);
  $prevYear = tax_turnover($year - 1);

  // Reihenfolge nach Dringlichkeit:
  //  over_this  — die Jahresgrenze ist gerissen, die Befreiung endet sofort
  //  over_prev  — das Vorjahr lag darüber, sie gilt dieses Jahr schon nicht
  //  close      — nahe an der Jahresgrenze. Wer hier steht, ist zwangsläufig
  //               auch über der Vorjahresgrenze; der Text nennt deshalb beides.
  //  next_year  — über der Vorjahresgrenze, aber weit von der Decke: die
  //               Befreiung läuft bis Silvester und endet dann von selbst.
  //               Der häufige Fall, und er kündigt sich nicht von allein an.
  $state = 'ok';
  if ($limitThis > 0 && $thisYear > $limitThis)            $state = 'over_this';
  elseif ($limitPrev > 0 && $prevYear > $limitPrev)        $state = 'over_prev';
  elseif ($limitThis > 0 && $thisYear >= $limitThis * 0.8) $state = 'close';
  elseif ($limitPrev > 0 && $thisYear > $limitPrev)        $state = 'next_year';

  return [
    'this_year'  => $thisYear,
    'prev_year'  => $prevYear,
    'limit_this' => $limitThis,
    'limit_prev' => $limitPrev,
    'share'      => $limitThis > 0 ? min(1.0, $thisYear / $limitThis) : 0.0,
    'state'      => $state,
  ];
}

/**
 * Sind die steuerlichen Werte länger als ein Jahr nicht bestätigt worden?
 * Zahlen altern still — das ist die einzige Stelle, die daran erinnert.
 */
function tax_values_stale(): bool {
  $checked = setting('tax_values_checked', '');
  if ($checked === '') return true;
  return strtotime($checked) < strtotime('-1 year');
}
