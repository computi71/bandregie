<?php
// Druckversion nach Band-Vorlage: pro Set eine A4-Seite.
// Kopf: Titel links (unterstrichen), Logo rechts; darunter die Songs 32 pt fett.
// Passt ein Set nicht auf die Seite, wird die Schrift automatisch verkleinert —
// gerechnet mit knappen Rändern (10/12 mm) und Umbruch langer Titel.

// Sets an den Pausen-Markern trennen (is_break 1 = Pause, 2 = Zugabe-Strich)
$sets = [[]];
foreach ($entries as $entry) {
  if ((int) $entry['is_break'] === 1) {
    $sets[] = [];
  } else {
    $sets[count($sets) - 1][] = $entry;
  }
}
$sets = array_values(array_filter($sets, fn($s) => $s !== []));

// Fürs Papier das Druck-Logo (dunkel auf weiß) bevorzugen, sonst Website-Logo
$logo = ($settings['print_logo_file'] ?? '') ?: ($settings['logo_file'] ?? '');
$watermark = $settings['print_watermark_file'] ?? '';

// Infozeile: ganzer Gig (alle Sets zusammen)
$songCount = count(array_filter($entries, fn($x) => !$x['is_break']));
$totalMin = (int) round(array_sum(array_map(fn($x) => (int) ($x['duration_sec'] ?? 0), $entries)) / 60);
$pauseCount = count(array_filter($entries, fn($x) => (int) $x['is_break'] === 1));
// „zzgl." und nicht „mit": Die Minuten sind Spielzeit, die Pause kommt obendrauf.
// „mit Pause" ließ die Zahl wie die Gesamtdauer des Abends lesen, und danach
// richtet der Veranstalter seinen Ablauf (#243).
$pauseText = $pauseCount === 0 ? ''
  : ($pauseCount === 1 ? t('sl_print_plus_break')
     : str_replace('%1', (string) $pauseCount, t('sl_print_plus_breaks')));

// Startwert für die Schriftgröße pro Set. Das letzte Wort hat der Browser
// (print-fit.js misst und passt an) — diese Rechnung gilt, wenn kein JavaScript
// läuft, und soll deshalb eher etwas zu klein als zu groß liegen.
//
// Bedruckbar: 296 mm Blatt − 12 mm oben − 10 mm unten − Kopfblock (Logo bis
// 22 mm, Infozeile, 8 mm Abstand ≈ 45 mm) ≈ 230 mm. Eine Liedzeile braucht
// F pt × 0,3528 mm/pt × 1,18 ≈ 0,42×F mm. Eine nackte Trennlinie ist KEINE
// Textzeile: sie braucht rund 3 mm, unabhängig von der Schrift. Trägt sie ein
// Wort (Zugabe, Ansage), ist sie so hoch wie das Wort — 45 % der Schrift, also
// 0,45×F pt × 1,2 × 0,3528 ≈ 0,19×F mm, plus 2,5 mm Abstände (#253).
//
// Und der Umbruch hängt an der Schrift, nicht an einer festen Zeichenzahl: In
// die 182 mm Textbreite passen bei 32 pt etwa 28 Zeichen, also grob 900/F. Bei
// 25 pt sind das 36 — „Du hast den Farbfilm vergessen" passt dort längst in eine
// Zeile, und genau dafür hatte die alte Rechnung eine Zeile verschenkt (#252).
$fontFor = function (array $set): int {
  $linien = 0;      // nackte Trennlinien
  $beschriftet = 0; // Trennlinien mit Wort darin
  $titel = [];
  foreach ($set as $entry) {
    $art = (int) $entry['is_break'];
    if ($art === 2) { $beschriftet++; continue; }
    if ($art === 3) { (string) $entry['item_note'] !== '' ? $beschriftet++ : $linien++; continue; }
    $titel[] = mb_strlen((string) $entry['title']);
  }
  $passt = function (int $f) use ($linien, $beschriftet, $titel): bool {
    $zeichen = max(12, intdiv(900, $f));
    $mm = $linien * 3.0 + $beschriftet * (0.19 * $f + 2.5);
    foreach ($titel as $len) $mm += 0.42 * $f * (int) ceil($len / $zeichen);
    return $mm <= 230.0;
  };
  for ($f = 32; $f > 14; $f--) if ($passt($f)) return $f;
  return 14;
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Setlist · <?= e($setlist['name']) ?></title>
  <style>
    /* Ränder fest ins Blatt eingebaut (Padding) statt über @page — so stimmen sie
       unabhängig von der Rand-Einstellung im Druckdialog des Browsers. */
    @page { size: A4 portrait; margin: 0; }
    body { font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; }
    .sheet { box-sizing: border-box; width: 210mm; height: 296mm; padding: 12mm 14mm 10mm;
             break-after: page; position: relative; overflow: hidden; }
    .sheet:last-child { break-after: auto; }
    .head-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; }
    /* Der Titel bleibt auf einer Zeile: „Setlist Mittwochs Konzert Zündstoff –
       19. August 2026" brach neben dem Logo um und stand zweizeilig unter dem
       Bandnamen. Ein Kopf, der über zwei Zeilen läuft, drückt die Songs nach
       unten — und auf einem Blatt, das ohnehin knapp ist, kostet das eine Zeile
       Repertoire. Nur der Titel selbst wird schmaler, wenn der Platz nicht
       reicht; umgebrochen wird er nicht (#240). */
    .head {
      font-weight: 700;
      text-decoration: underline;
      font-size: 14pt;
      white-space: nowrap;
      /* Damit der Flex-Kasten den Titel schrumpfen lässt statt ihn zu brechen */
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    /* Lange Namen: eine Stufe kleiner, statt abgeschnitten zu werden. */
    .head.head-long { font-size: 12pt; }
    .head.head-verylong { font-size: 10.5pt; }
    .sub { font-size: 12pt; margin-top: 4mm; line-height: 1.35; }
    .logo img { max-height: 22mm; max-width: 75mm; }
    .logo .bandname { font-size: 22pt; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .songs { margin-top: 8mm; position: relative; z-index: 1; }
    .song { font-weight: 700; line-height: 1.18; }
    .song .note { font-weight: 400; font-size: 55%; }
    /* Waehlbare Zusatzfelder (#255): Sie stehen immer im HTML und werden ueber
       Klassen am body eingeschaltet — so wirkt ein Haken sofort, ohne die Seite
       neu zu laden, und der Druck nimmt genau das mit, was zu sehen ist. */
    .feld { display: none; font-weight: 400; font-size: 50%; }
    .feld::before { content: ' · '; }
    body.mit-interpret .feld-interpret,
    body.mit-jahr .feld-jahr,
    body.mit-bpm .feld-bpm,
    body.mit-zeit .feld-zeit { display: inline; }
    .notiz-kurz { display: none; }
    body.mit-notiz-kurz .notiz-kurz { display: inline; }
    .notiz-lang { display: none; font-weight: 400; font-size: 50%; white-space: pre-line; }
    body.mit-notiz-lang .notiz-lang { display: block; }
    /* Die Auswahl selbst: nur am Bildschirm, im Druck ist die Leiste ohnehin fort. */
    .felder { display: inline-flex; flex-wrap: wrap; gap: 0.15rem 0.9rem; align-items: center; margin-left: 0.9rem; }
    .felder > label { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.9rem; }
    .felder strong { font-weight: 700; font-size: 0.9rem; }
    /* Zugabe: das Wort steht IN der Linie, wie es auf dem Papier gezogen wird —
       ein kurzes Stück voraus, dann das Wort, dann die Linie bis zum Rand. Durch-
       gezogen und dicker als die Sprechpause: Die trennt innerhalb des Sets, die
       Zugabe trennt, was nur bei Zugabe gespielt wird (#253). */
    .encore-rule { display: flex; align-items: center; gap: 2mm; margin: 1.5mm 0; border: 0; }
    .encore-rule::before { content: ''; flex: 0 0 8mm; border-top: 0.6mm solid #000; }
    .encore-rule::after { content: ''; flex: 1; border-top: 0.6mm solid #000; }
    .encore-rule span { font-size: 45%; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
    /* Sprechpause: gestrichelt und dünner als der Zugabe-Strich — auf einen Blick
       zu unterscheiden, auch aus zwei Metern auf einem Notenpult (#241). */
    .block-line { border: 0; border-top: 0.35mm dashed #000; margin: 1.5mm 0; }
    /* Mit Anweisung: der Text sitzt in der Linie, links ausgerichtet, damit das
       Auge ihn beim Abwärtslesen findet. */
    .block-rule { display: flex; align-items: center; gap: 2mm; margin: 1.5mm 0; }
    .block-rule::after { content: ''; flex: 1; border-top: 0.35mm dashed #000; }
    .block-rule span { font-size: 45%; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    /* Eine Ansage gilt für das, was UNTER der Linie kommt — deshalb sitzt sie
       optisch näher daran als an den Liedern darüber (#241). */
    .block-rule { margin: 2mm 0 0.5mm; }
    /* Die Klammer vom Zettel: Titel links, geschweifte Klammer rechts daneben,
       Anweisung dahinter. Gezeichnet aus einem Rahmen mit runden Ecken — das
       kommt der Handschrift näher als eine Glyphe, die je Drucker anders sitzt
       und bei zwei Zeilen zu groß wäre (#242). */
    .braced { display: flex; align-items: stretch; gap: 2mm; }
    .braced-songs { flex: 0 1 auto; }
    .brace {
      flex: 0 0 2.5mm;
      border: 0.5mm solid #000;
      border-left: 0;
      border-radius: 0 3mm 3mm 0;
      margin: 0.8mm 0;
    }
    .brace-note {
      align-self: center;
      font-size: 45%;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      white-space: nowrap;
    }
    /* position:fixed wiederholt das Wasserzeichen beim Druck auf jeder Seite */
    /* Wasserzeichen liegt in jedem Blatt; pointer-events: none, damit es keine Klicks schluckt */
    .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 0; pointer-events: none; }
    .watermark img { width: 72%; opacity: 0.07; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .toolbar { margin: 0.6rem 0 1rem; position: relative; z-index: 2; }
    @media print { .toolbar { display: none; } }
    @media screen {
      body { background: #777; padding: 1rem; }
      .sheet { margin: 0 auto 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.4); background: #fff; }
    }
  </style>
</head>
<body>
  <?php // Gemessen statt geschätzt: print-fit.js vergrößert die Schrift, solange
          // die Liste auf das Blatt passt. Ohne JavaScript bleibt der Startwert
          // von oben stehen — dann ist der Ausdruck kleiner, aber nie zu groß. ?>
  <script src="<?= e(asset('/assets/print-fit.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/print-fields.js')) ?>" defer></script>
  <div class="toolbar">
    <button data-print>🖨 Drucken</button>
    <span class="felder">
      <strong><?= e(t('sl_print_fields')) ?></strong>
      <label><input type="checkbox" data-feld="interpret"> <?= e(t('songs_col_original')) ?></label>
      <label><input type="checkbox" data-feld="jahr"> <?= e(t('song_year')) ?></label>
      <label><input type="checkbox" data-feld="bpm"> BPM</label>
      <label><input type="checkbox" data-feld="zeit"> <?= e(t('sl_print_f_time')) ?></label>
      <label><?= e(t('notes')) ?>
        <select data-notiz>
          <option value="">— <?= e(t('sl_print_f_off')) ?> —</option>
          <option value="kurz" selected><?= e(t('sl_print_f_first')) ?></option>
          <option value="lang"><?= e(t('sl_print_f_full')) ?></option>
        </select>
      </label>
    </span>
  </div>
  <?php foreach ($sets as $set): ?>
    <div class="sheet">
      <?php if ($watermark): ?>
        <div class="watermark"><img src="/uploads/<?= e($watermark) ?>" alt=""></div>
      <?php endif; ?>
      <div class="head-row">
        <div>
          <?php // Je länger der Name, desto kleiner die Zeile — gerechnet, nicht
              // geraten: 14 pt tragen etwa 46 Zeichen neben dem Logo. ?>
        <?php $headText = 'Setlist ' . $setlist['name']; ?>
        <?php $headClass = mb_strlen($headText) > 62 ? ' head-verylong' : (mb_strlen($headText) > 46 ? ' head-long' : ''); ?>
        <div class="head<?= $headClass ?>"><?= e($headText) ?></div>
          <?php // Eine Zeile, nicht zwei: „38 Songs = 130 Min zzgl. Pause" liest
                // sich in einem Blick, und der Kopf bleibt flach — jede Zeile hier
                // fehlt unten beim Repertoire. ?>
          <div class="sub"><?= e(str_replace('%1', (string) $songCount, t('sl_print_songs'))) ?><?php
            if ($totalMin > 0): ?> = <?= e(str_replace('%1', (string) $totalMin, t('sl_print_min'))) ?><?php endif; ?><?php
            if ($pauseText !== ''): ?> <?= e($pauseText) ?><?php endif; ?></div>
        </div>
        <div class="logo">
          <?php if ($logo): ?>
            <img src="/uploads/<?= e($logo) ?>" alt="<?= e($settings['band_name']) ?>">
          <?php else: ?>
            <span class="bandname"><?= e($settings['band_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="songs" style="font-size: <?= $fontFor($set) ?>pt">
        <?php
          // Zeilen mit derselben Klammernummer zu Gruppen zusammenfassen — die
          // gezeichnete Klammer vom Zettel (#242). Alles ohne Nummer bleibt für
          // sich; die Reihenfolge bleibt in jedem Fall die der Setliste.
          $gruppen = [];
          foreach ($set as $entry) {
            $nr = $entry['bracket'] !== null ? (int) $entry['bracket'] : 0;
            $letzte = $gruppen ? array_key_last($gruppen) : null;
            if ($nr > 0 && $letzte !== null && $gruppen[$letzte]['nr'] === $nr) {
              $gruppen[$letzte]['zeilen'][] = $entry;
            } else {
              $gruppen[] = ['nr' => $nr, 'zeilen' => [$entry]];
            }
          }
        ?>
        <?php foreach ($gruppen as $gruppe): ?>
        <?php // Eine Klammer nur, wo sie etwas umfasst — über einer Zeile wäre sie Zierrat. ?>
        <?php $geklammert = $gruppe['nr'] > 0 && count($gruppe['zeilen']) > 1; ?>
        <?php $klammerText = $geklammert ? (string) $gruppe['zeilen'][0]['bracket_note'] : ''; ?>
        <?php if ($geklammert): ?><div class="braced"><div class="braced-songs"><?php endif; ?>
        <?php foreach ($gruppe['zeilen'] as $entry): ?>
          <?php if ((int) $entry['is_break'] === 2): ?>
            <div class="encore-rule"><span><?= e(t('sl_encore_word')) ?></span></div>
          <?php elseif ((int) $entry['is_break'] === 3): ?>
            <?php // Sprechpause wie der Strich auf dem Papier — gestrichelt, damit
                  // sie sich vom durchgezogenen Zugabe-Strich unterscheidet. Steht
                  // eine Anweisung dabei, steht sie in der Linie (#241). ?>
            <?php if ((string) $entry['item_note'] !== ''): ?>
              <div class="block-rule"><span><?= e($entry['item_note']) ?></span></div>
            <?php else: ?>
              <hr class="block-line">
            <?php endif; ?>
          <?php else: ?>
            <?php // Die Anweisung der Zeile gilt für diesen Abend und steht immer da.
                  // Die Notiz am Lied gilt immer — was davon mitkommt, entscheidet
                  // die Auswahl in der Leiste (#255). ?>
            <?php $zuruf = (string) $entry['item_note']; ?>
            <?php $notizKurz = song_note_cue((string) $entry['notes']); ?>
            <?php $notizLang = trim((string) $entry['notes']); ?>
            <div class="song">
              <?= e($entry['title']) ?>
              <?php if ($zuruf !== ''): ?><span class="note">(<?= e($zuruf) ?>)</span><?php endif; ?>
              <?php if ($notizKurz !== '' && $notizKurz !== $zuruf): ?><span class="note notiz-kurz">(<?= e($notizKurz) ?>)</span><?php endif; ?>
              <?php if ($entry['artist']): ?><span class="feld feld-interpret"><?= e($entry['artist']) ?></span><?php endif; ?>
              <?php if ($entry['release_year']): ?><span class="feld feld-jahr"><?= (int) $entry['release_year'] ?></span><?php endif; ?>
              <?php if ($entry['tempo']): ?><span class="feld feld-bpm"><?= e($entry['tempo']) ?></span><?php endif; ?>
              <?php if ($entry['duration_sec']): ?><span class="feld feld-zeit"><?= fmt_duration((int) $entry['duration_sec']) ?></span><?php endif; ?>
              <?php if ($notizLang !== ''): ?><span class="notiz-lang"><?= e($notizLang) ?></span><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($geklammert): ?>
          </div>
          <?php // Die Klammer selbst: rechts neben den Titeln, mit der Anweisung
                // daneben — so wie sie auf dem Zettel gezeichnet ist. ?>
          <div class="brace" aria-hidden="true"></div>
          <?php if ($klammerText !== ''): ?><div class="brace-note"><?= e($klammerText) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<script src="<?= e(asset('/assets/actions.js')) ?>" defer></script>
</body>
</html>
