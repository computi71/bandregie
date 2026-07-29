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

/**
 * Kategorien, die als gewerblich gelten. Spielen ist künstlerisch, T-Shirts
 * verkaufen ist Handel — und das färbt ab.
 */
const TAX_COMMERCIAL = ['merch'];

/**
 * Anteil des gewerblichen Umsatzes. Bei einer Personengesellschaft macht
 * schon ein kleiner Handelsanteil alle Einkünfte gewerblich (§ 15 Abs. 3
 * Nr. 1 EStG). Die Rechtsprechung lässt eine Bagatellgrenze zu, und die ist
 * eng: unter 3 Prozent des Nettoumsatzes *und* unter 24.500 € im Jahr —
 * beides, nicht eines von beiden.
 *
 * @return array{
 *   total: int, commercial: int, share: float,
 *   limit_share: float, limit_abs: int, state: string
 * }|null  state: 'ok' | 'close' | 'over'
 */
function tax_commercial_status(int $year): ?array {
  $total = tax_turnover($year);
  if ($total <= 0) return null;

  $marks = implode(',', array_fill(0, count(TAX_COMMERCIAL), '?'));
  $row = row("SELECT COALESCE(SUM(amount_cents), 0) AS total FROM finances
              WHERE type = 'einnahme' AND private_for IS NULL
                AND YEAR(date) = ? AND category IN ($marks)",
             [$year, ...TAX_COMMERCIAL]);
  $commercial = (int) ($row['total'] ?? 0);

  $limitShare = (float) setting('tax_commercial_share', '3') / 100;
  $limitAbs = (int) round((float) setting('tax_commercial_abs', '24500') * 100);
  $share = $commercial / $total;

  // Gerissen ist die Grenze, sobald *eine* der beiden nicht mehr hält.
  $state = 'ok';
  if ($share > $limitShare || ($limitAbs > 0 && $commercial > $limitAbs)) $state = 'over';
  elseif ($share >= $limitShare * 0.8) $state = 'close';

  return [
    'total' => $total, 'commercial' => $commercial, 'share' => $share,
    'limit_share' => $limitShare, 'limit_abs' => $limitAbs, 'state' => $state,
  ];
}

/**
 * Grenze für geringwertige Wirtschaftsgüter, in Cent. Alles darunter zählt im
 * Jahr des Kaufs vollständig, alles darüber verteilt sich (§ 6 Abs. 2 EStG:
 * 800 € netto).
 */
function tax_gwg_limit_cents(): int {
  return (int) round((float) setting('tax_gwg_limit', '800') * 100);
}

/** Voreingestellte Nutzungsdauer in Jahren, über die sich ein Kauf verteilt. */
function tax_afa_years(): int {
  return max(1, (int) setting('tax_afa_years', '7'));
}

/**
 * Was von einem Kaufpreis in einem bestimmten Jahr zu Buche schlägt.
 *
 * Unterhalb der Grenze ist es der ganze Betrag im Jahr des Kaufs. Darüber
 * verteilt er sich gleichmäßig über die Nutzungsdauer, und zwar ab dem
 * Kaufmonat — wer im Oktober kauft, setzt im ersten Jahr drei Monate an
 * (§ 7 Abs. 1 EStG).
 *
 * Gerechnet wird über die aufgelaufene Summe und nicht Jahr für Jahr: sonst
 * bleiben durch das Runden am Ende ein paar Cent liegen oder es kommen welche
 * hinzu, und die Summe der Jahre wäre nicht der Kaufpreis.
 *
 * Ist das Gerät abgegeben worden, endet die Abschreibung: im Jahr des Abgangs
 * wird der Restwert in einem Zug ausgebucht, danach ist nichts mehr da. Sonst
 * schriebe die Band ein Gerät ab, das ihr nicht mehr gehört — und der Erlös
 * stünde zusätzlich als Einnahme im selben Bericht.
 *
 * @param string|null $disposedOn Datum des Abgangs, falls verkauft
 * @return array{kind: string, this_year: int, remaining: int, first_year: int,
 *               last_year: int, years: int, disposed_year: int|null}|null
 *               null: kein brauchbares Datum
 */
function tax_depreciation(int $cents, ?string $purchasedOn, int $year, ?string $disposedOn = null): ?array {
  $ts = $purchasedOn ? strtotime($purchasedOn) : false;
  if ($cents <= 0 || $ts === false) return null;

  $buyYear = (int) date('Y', $ts);
  $buyMonth = (int) date('n', $ts);
  $years = tax_afa_years();
  $goneTs = $disposedOn ? strtotime($disposedOn) : false;
  $goneYear = $goneTs === false ? null : (int) date('Y', $goneTs);

  if ($cents <= tax_gwg_limit_cents()) {
    return [
      'kind' => 'gwg', 'years' => 1,
      'this_year' => $year === $buyYear ? $cents : 0,
      'remaining' => $year < $buyYear ? $cents : 0,
      'first_year' => $buyYear, 'last_year' => $buyYear,
      'disposed_year' => $goneYear,
    ];
  }

  $months = $years * 12;
  // Abgeschriebene Monate bis zum Ende des Jahres $y
  $until = function (int $y) use ($buyYear, $buyMonth, $months): int {
    if ($y < $buyYear) return 0;
    return max(0, min($months, ($y - $buyYear) * 12 + (13 - $buyMonth)));
  };
  $cum = fn(int $m): int => intdiv($cents * $m, $months);

  $thisYear = $cum($until($year)) - $cum($until($year - 1));
  $remaining = $cents - $cum($until($year));
  $lastYear = (int) date('Y', (int) mktime(0, 0, 0, $buyMonth + $months - 1, 1, $buyYear));

  if ($goneYear !== null && $goneYear <= $lastYear) {
    if ($year > $goneYear) {
      $thisYear = 0;
      $remaining = 0;
    } elseif ($year === $goneYear) {
      $thisYear = $cents - $cum($until($year - 1));   // der ganze Rest auf einmal
      $remaining = 0;
    }
    $lastYear = min($lastYear, $goneYear);
  }

  return [
    'kind' => 'afa', 'years' => $years,
    'this_year' => $thisYear,
    'remaining' => $remaining,
    'first_year' => $buyYear,
    'last_year' => $lastYear,
    'disposed_year' => $goneYear,
  ];
}

/**
 * Der Jahresbericht: was hereinkam, was hinausging, und was von den
 * Anschaffungen in dieses Jahr fällt.
 *
 * Ein Gerätekauf steht nicht bei den Ausgaben — er kommt über die
 * Abschreibung herein, sonst stünde er doppelt im Ergebnis. Der Verkauf eines
 * Geräts ist dagegen eine Einnahme wie jede andere; zugleich beendet er die
 * Abschreibung, damit nicht beides zusammen doppelt zählt.
 *
 * @param int|null $memberId gesetzt: die privaten Buchungen dieses Mitglieds.
 *                           null: die Zahlen der Band
 * @return array{year: int, income: array<string,int>, expense: array<string,int>,
 *               entries: array, equipment: array, sum_income: int,
 *               sum_expense: int, sum_afa: int, result: int}
 */
function tax_report(int $year, ?int $memberId): array {
  $scope = $memberId === null ? 'f.private_for IS NULL' : 'f.private_for = ?';
  $args = $memberId === null ? [] : [$memberId];

  $entries = rows("SELECT f.*, eq.name AS equipment_name, e.title AS event_title
                   FROM finances f
                   LEFT JOIN equipment eq ON eq.id = f.equipment_id
                   LEFT JOIN events e ON e.id = f.event_id
                   WHERE $scope AND YEAR(f.date) = ?
                   ORDER BY f.date, f.id", [...$args, $year]);

  $income = $expense = []; $sumIn = $sumOut = 0; $plain = [];
  foreach ($entries as $en) {
    $cents = (int) $en['amount_cents'];
    // Der Kauf steckt in der Abschreibung, nicht in den Ausgaben.
    if ($en['equipment_id'] !== null && $en['type'] === 'ausgabe') continue;
    $plain[] = $en;
    if ($en['type'] === 'einnahme') {
      $income[$en['category']] = ($income[$en['category']] ?? 0) + $cents;
      $sumIn += $cents;
    } else {
      $expense[$en['category']] = ($expense[$en['category']] ?? 0) + $cents;
      $sumOut += $cents;
    }
  }

  // Käufe früherer Jahre schreiben weiter ab — deshalb alle bis Silvester,
  // nicht nur die des Jahres.
  $purchases = rows("SELECT f.*, eq.name AS equipment_name
                     FROM finances f
                     LEFT JOIN equipment eq ON eq.id = f.equipment_id
                     WHERE $scope AND f.equipment_id IS NOT NULL AND f.type = 'ausgabe'
                       AND f.date <= ?
                     ORDER BY f.date, f.id", [...$args, $year . '-12-31']);

  $goneOn = tax_disposals($memberId);

  $equipment = []; $sumAfa = 0;
  foreach ($purchases as $p) {
    $dep = tax_depreciation((int) $p['amount_cents'], $p['date'], $year,
                            $goneOn[(int) $p['equipment_id']] ?? null);
    if ($dep === null || ($dep['this_year'] === 0 && $dep['first_year'] !== $year)) continue;
    $equipment[] = [
      'name' => $p['equipment_name'] ?? $p['description'],
      'date' => $p['date'],
      'cents' => (int) $p['amount_cents'],
      // Für das Belegpaket: die Rechnung hängt am Gerät oder an der Buchung,
      // und die Buchung kann aus einem früheren Jahr stammen.
      'equipment_id' => (int) $p['equipment_id'],
      'finance_id' => (int) $p['id'],
    ] + $dep;
    $sumAfa += $dep['this_year'];
  }

  return [
    'year' => $year,
    'income' => $income, 'expense' => $expense,
    'entries' => $plain, 'equipment' => $equipment,
    'sum_income' => $sumIn, 'sum_expense' => $sumOut, 'sum_afa' => $sumAfa,
    'result' => $sumIn - $sumOut - $sumAfa,
  ];
}

/**
 * Wann ein Gerät abgegeben wurde: die Einnahmezeile zum Gerät. Der frühste
 * Abgang zählt, falls jemand mehrfach gebucht hat.
 *
 * @return array<int, string> Datum des Abgangs, nach Geräte-ID
 */
function tax_disposals(?int $memberId): array {
  $scope = $memberId === null ? 'private_for IS NULL' : 'private_for = ?';
  $args = $memberId === null ? [] : [$memberId];
  $out = [];
  foreach (rows("SELECT equipment_id, MIN(date) AS gone FROM finances
                 WHERE $scope AND equipment_id IS NOT NULL AND type = 'einnahme'
                 GROUP BY equipment_id", $args) as $g) {
    $out[(int) $g['equipment_id']] = (string) $g['gone'];
  }
  return $out;
}

/**
 * Jahre, für die sich ein Bericht lohnt: die mit Buchungen, und dazu die
 * Jahre, in denen noch etwas abzuschreiben ist.
 *
 * @return int[] absteigend
 */
function tax_report_years(?int $memberId): array {
  $scope = $memberId === null ? 'private_for IS NULL' : 'private_for = ?';
  $args = $memberId === null ? [] : [$memberId];
  $years = array_map('intval', array_column(
    rows("SELECT DISTINCT YEAR(date) AS y FROM finances WHERE $scope ORDER BY y DESC", $args), 'y'));
  // Ein Gerät aus 2023 beschäftigt die Erklärung 2029 noch, auch wenn in dem
  // Jahr keine einzige Zeile gebucht wurde. Über das laufende Jahr hinaus
  // aber nicht: für ein Jahr, das noch läuft, gibt es nichts zu erklären.
  $now = (int) date('Y');
  $goneOn = tax_disposals($memberId);
  foreach (rows("SELECT amount_cents, date, equipment_id FROM finances
                 WHERE $scope AND equipment_id IS NOT NULL AND type = 'ausgabe'", $args) as $p) {
    $dep = tax_depreciation((int) $p['amount_cents'], $p['date'], $now,
                            $goneOn[(int) $p['equipment_id']] ?? null);
    if (!$dep) continue;
    for ($y = $dep['first_year']; $y <= min($dep['last_year'], $now); $y++) $years[] = $y;
  }
  $years = array_values(array_unique($years));
  rsort($years);
  return $years;
}

/**
 * Umfang, Jahr und Bericht aus einer Anfrage. Drei Wege zeigen dieselben
 * Zahlen — die Seite, der Druck und die Tabelle —, und sie sollen sich nicht
 * in Kleinigkeiten unterscheiden.
 *
 * @param array $query $_GET: 'umfang' ('band' oder eigene Zahlen), 'jahr'
 */
function tax_report_for(array $user, array $query): array {
  // Die Zahlen der Band sieht nur, wer die Kasse führt.
  $band = ($query['umfang'] ?? '') === 'band' && perm_allows($user, 'kasse', 'write');
  $memberId = $band ? null : (int) $user['id'];
  $years = tax_report_years($memberId);
  $wanted = (int) ($query['jahr'] ?? 0);
  $year = in_array($wanted, $years, true) ? $wanted : (int) ($years[0] ?? date('Y'));
  return [
    'scope' => $band ? 'band' : 'eigen',
    'years' => $years,
    'year'  => $year,
    'report' => tax_report($year, $memberId),
  ];
}

/**
 * Die Zeilen der Steuertabelle — dieselben für die Tabelle allein und für die
 * im Paket. Zwei Fassungen derselben Zahlen gingen irgendwann auseinander.
 *
 * @return array{0: string[], 1: array<int, array<int, string>>} Kopfzeile, Zeilen
 */
function tax_export_table(array $report): array {
  $rows = [];
  foreach ($report['entries'] as $en) {
    $rows[] = [
      $en['date'],
      t('fin_' . ($en['type'] === 'einnahme' ? 'income' : 'expense')),
      fin_category_label($en['category']),
      $en['description'],
      number_format($en['amount_cents'] / 100, 2, '.', ''),
      '',
    ];
  }
  foreach ($report['equipment'] as $eq) {
    $rows[] = [
      $eq['date'],
      t('taxr_afa'),
      fin_category_label('equipment'),
      $eq['name'],
      number_format($eq['this_year'] / 100, 2, '.', ''),
      number_format($eq['cents'] / 100, 2, '.', ''),
    ];
  }
  return [[
    t('date'), t('ev_type'), t('fin_category'), t('fin_description'),
    t('taxr_amount_year'), t('taxr_purchase_price'),
  ], $rows];
}

/**
 * Die Belege zu einem Bericht: die Anhänge der Buchungen des Jahres und die
 * Rechnungen der Geräte, die noch abschreiben — deren Papier liegt im Jahr des
 * Kaufs und nicht im Jahr der Erklärung.
 *
 * Dieselbe Datei kommt nur einmal vor: eine Rechnung über mehrere Geräte hängt
 * an jedem von ihnen (#67), ist aber ein Blatt.
 *
 * @return array<int, array{name: string, file: array}> Zielname im Paket und Dateizeile
 */
function tax_report_receipts(array $report): array {
  $financeIds = array_map('intval', array_column($report['entries'], 'id'));
  foreach ($report['equipment'] as $eq) $financeIds[] = (int) $eq['finance_id'];
  $eqIds = array_filter(array_map('intval', array_column($report['equipment'], 'equipment_id')));
  if (!$financeIds && !$eqIds) return [];

  // Datum und Bezeichnung je Art, damit im Paket nicht nur Nummern stehen:
  // an einer Buchung hängt ihr eigenes Datum, an einem Gerät das des Kaufs.
  $about = [];
  foreach ($report['entries'] as $en) {
    $about['finance'][(int) $en['id']] = ['date' => (string) $en['date'], 'label' => (string) $en['description']];
  }
  foreach ($report['equipment'] as $eq) {
    $about['finance'][(int) $eq['finance_id']] = ['date' => (string) $eq['date'], 'label' => (string) $eq['name']];
    $about['equipment'][(int) $eq['equipment_id']] = ['date' => (string) $eq['date'], 'label' => (string) $eq['name']];
  }

  $where = []; $args = [];
  if ($financeIds) {
    $where[] = "(entity_type = 'finance' AND entity_id IN (" . implode(',', array_fill(0, count($financeIds), '?')) . '))';
    $args = [...$args, ...$financeIds];
  }
  if ($eqIds) {
    $where[] = "(entity_type = 'equipment' AND entity_id IN (" . implode(',', array_fill(0, count($eqIds), '?')) . '))';
    $args = [...$args, ...$eqIds];
  }

  $out = []; $seen = [];
  foreach (rows('SELECT * FROM files WHERE ' . implode(' OR ', $where) . ' ORDER BY id', $args) as $f) {
    if (isset($seen[$f['filename']])) continue;
    $seen[$f['filename']] = true;
    $meta = $about[$f['entity_type']][(int) $f['entity_id']] ?? ['date' => '', 'label' => ''];
    $out[] = ['name' => tax_receipt_name($meta['label'], $f, $meta['date']), 'file' => $f];
  }
  return $out;
}

/**
 * Ein Name, den man ohne die Anwendung lesen kann: Datum, worum es geht, und
 * der ursprüngliche Dateiname. Alles andere fliegt raus — ein Beleg soll auf
 * jedem Rechner auspackbar sein.
 */
function tax_receipt_name(string $label, array $file, string $date): string {
  $clean = function (string $s): string {
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    $s = preg_replace('~[^A-Za-z0-9._-]+~', '-', $s) ?? $s;
    return trim($s, '-.');
  };
  $parts = array_filter([$date, $clean($label), $clean((string) $file['original_name'])]);
  return implode('_', $parts);
}

/**
 * Das ganze Paket als ZIP: die Tabelle, die Belege und ein Blatt, das sagt,
 * womit gerechnet wurde. Ohne die ZIP-Erweiterung gibt es kein Paket — dann
 * bleibt es bei der Tabelle, und die Seite sagt es.
 *
 * @return string|null Pfad zur fertigen Datei; null, wenn ZIP fehlt
 */
function tax_report_package(array $view, string $owner): ?string {
  if (!class_exists('ZipArchive')) return null;
  require_once BASE_DIR . '/app/export.php';

  [$head, $tableRows] = tax_export_table($view['report']);
  [$ext, $table] = export_table_bytes($head, $tableRows);

  $tmp = tempnam(sys_get_temp_dir(), 'steuer');
  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { @unlink($tmp); return null; }

  $base = 'steuer-' . $view['year'] . '-' . $view['scope'];
  $zip->addFromString($base . '.' . $ext, $table);
  $zip->addFromString('hinweis.txt', tax_package_note($view, $owner));
  foreach (tax_report_receipts($view['report']) as $receipt) {
    $path = FILES_DIR . '/' . $receipt['file']['filename'];
    if (is_file($path)) $zip->addFile($path, 'belege/' . $receipt['name']);
  }
  $zip->close();
  return $tmp;
}

/** Das Beiblatt: wessen Zahlen, welches Jahr, womit gerechnet, und der Vorbehalt. */
function tax_package_note(array $view, string $owner): string {
  $r = $view['report'];
  $lines = [
    t('taxr_title') . ' ' . $view['year'],
    $owner . ' · ' . fmt_date(date('Y-m-d')),
    '',
    t('fin_income') . ': ' . fmt_money($r['sum_income']),
    t('fin_expense') . ': ' . fmt_money($r['sum_expense']),
    t('taxr_afa') . ': ' . fmt_money($r['sum_afa']),
    t('taxr_sum') . ': ' . fmt_money($r['result']),
    '',
    t('taxr_applied') . ':',
    '  ' . t('set_tax_gwg') . ': ' . fmt_money(tax_gwg_limit_cents()),
    '  ' . t('set_tax_afa_years') . ': ' . tax_afa_years(),
    '  ' . t('taxr_small') . ': ' . (setting('tax_small_business', '0') === '1' ? t('taxr_small_on') : t('taxr_small_off')),
    '',
    t('taxr_gross_hint'),
    '',
    t('tax_no_advice'),
  ];
  return implode("\r\n", $lines) . "\r\n";
}
