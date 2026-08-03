<?php
/**
 * Zeichnet den Bühnenplan als SVG. Erwartet $stageItems; optional $stageEdit
 * (Ziehen erlauben) und $stagePrint (Schwarzweiß für den Ausdruck).
 *
 * Maßstab: 100 Einheiten je Meter, dazu ein Rand für die Beschriftung. Die
 * Positionen bleiben in Prozent — so überlebt ein Plan jede Änderung des
 * Bühnenmaßes und passt auf jede Bühne, auf der die Band spielt.
 *
 * Vorne ist unten: Der Veranstalter schaut auf den Plan wie das Publikum auf
 * die Bühne.
 *
 * Gezeichnet wird nicht mehr ein Kreis mit einem Zeichen darin, sondern jede
 * Art als das, was sie ist. Ein Kreis um alles sagt nichts, und ein Podest, das
 * aussieht wie ein Verstärker, hilft beim Aufbau nicht. Was ein Maß hat, nimmt
 * die Fläche ein, die es wirklich braucht — daran sieht man, ob es passt.
 */
$stageEdit  = $stageEdit ?? false;
$stagePrint = $stagePrint ?? false;
$stroke = $stagePrint ? '#333' : 'currentColor';
$fuell  = $stagePrint ? '#fff' : 'transparent';

[$stageB, $stageT] = stage_size();
$rand = 60;                       // Platz für „hinten", „vorne" und das Maß
$flB  = $stageB * 100;            // Bühnenfläche in Zeichnungseinheiten
$flT  = $stageT * 100;
$vbB  = $flB + 2 * $rand;
$vbT  = $flT + 2 * $rand;
?>
<svg class="stage-plot<?= $stagePrint ? ' stage-print' : '' ?>" viewBox="0 0 <?= $vbB ?> <?= $vbT ?>" role="img"
     aria-label="<?= e(t('stage_plot')) ?>"<?= $stageEdit ? ' data-stageedit' : '' ?>
     data-ox="<?= $rand ?>" data-oy="<?= $rand ?>" data-sw="<?= $flB ?>" data-sh="<?= $flT ?>"
     data-vw="<?= $vbB ?>" data-vh="<?= $vbT ?>">
  <?php // Das Meterraster gibt dem Plan seinen Maßstab. Zurückhaltend, damit es
        // die Symbole nicht erschlägt. ?>
  <g opacity="0.16" stroke="<?= $stroke ?>" stroke-width="1">
    <?php for ($m = 1; $m < $stageB; $m++): ?>
      <line x1="<?= $rand + $m * 100 ?>" y1="<?= $rand ?>" x2="<?= $rand + $m * 100 ?>" y2="<?= $rand + $flT ?>"/>
    <?php endfor; ?>
    <?php for ($m = 1; $m < $stageT; $m++): ?>
      <line x1="<?= $rand ?>" y1="<?= $rand + $m * 100 ?>" x2="<?= $rand + $flB ?>" y2="<?= $rand + $m * 100 ?>"/>
    <?php endfor; ?>
  </g>
  <rect x="<?= $rand ?>" y="<?= $rand ?>" width="<?= $flB ?>" height="<?= $flT ?>" rx="6"
        fill="none" stroke="<?= $stroke ?>" stroke-width="3" opacity="0.55"/>

  <text x="<?= $rand + $flB / 2 ?>" y="<?= $rand - 20 ?>" text-anchor="middle" font-size="26" fill="<?= $stroke ?>" opacity="0.55"><?= e(t('stage_back')) ?></text>
  <text x="<?= $rand + $flB / 2 ?>" y="<?= $rand + $flT + 40 ?>" text-anchor="middle" font-size="26" fill="<?= $stroke ?>" opacity="0.55"><?= e(t('stage_front')) ?></text>
  <?php // Das Maß gehört auf den Plan: Er wird verschickt, und der Empfänger
        // muss wissen, von welcher Bühne die Band ausgegangen ist. ?>
  <text x="<?= $rand ?>" y="<?= $rand - 20 ?>" font-size="22" fill="<?= $stroke ?>" opacity="0.5"><?= $stageB ?> × <?= $stageT ?> m</text>

  <?php // Podeste zuerst: Sie sind der Boden, auf dem die anderen stehen. Läge
        // ein Podest über dem Schlagzeuger, wäre der Plan falsch herum gelesen. ?>
  <?php foreach ([true, false] as $stageBoden): ?>
    <?php foreach ($stageItems as $it): ?>
      <?php
        if ((($it['kind'] ?? '') === 'podest') !== $stageBoden) continue;
        [$bCm, $tCm] = stage_footprint($it);
        $ix = $rand + ((int) $it['x'] / 100) * $flB;
        $iy = $rand + ((int) $it['y'] / 100) * $flT;
        $b = $bCm; $tf = $tCm;   // 1 cm = 1 Einheit, weil 1 m = 100 Einheiten
        $sym = STAGE_KINDS[$it['kind']] ?? '▫';
        // Die Beschriftung sitzt unter dem Grundriss, nicht im Symbol — sonst
        // steht sie bei kleinen Dingen darüber und bei großen mittendrin.
        // Beschriftung unter dem Grundriss, bei maßlosen Dingen unter dem
        // Zeichen. Schrift 15 statt 22: Bei 22 bestand der Plan aus Text mit
        // ein paar Formen dazwischen, und links klebten fünf Beschriftungen
        // ineinander.
        $unten = $tf > 0 ? $tf / 2 + 17 : 26;
        // Höhe des Symbols über seinem Mittelpunkt; der Musiker-Zweig setzt sie
        // je nach Figur oder Foto neu. Steht hier, damit sie nicht vom
        // vorherigen Eintrag übrig ist.
        $kopf = 16;
        // Manche Symbole setzen ihre Notiz selbst — dann darf der gemeinsame
        // Block sie nicht wiederholen. Auch das muss je Eintrag zurückgesetzt
        // werden, sonst verschwindet die Notiz des nächsten Dings.
        $notizVerbraucht = false;
      ?>
      <g class="stage-item" data-id="<?= (int) $it['id'] ?>" transform="translate(<?= round($ix, 1) ?>,<?= round($iy, 1) ?>)">
        <?php if ($it['kind'] === 'podest'): ?>
          <?php // Nur die Fläche, keine Diagonale: Auf dem Podest steht etwas,
                // und eine Linie quer darüber macht aus dem Boden ein Muster. ?>
          <rect x="<?= round(-$b / 2, 1) ?>" y="<?= round(-$tf / 2, 1) ?>" width="<?= round($b, 1) ?>" height="<?= round($tf, 1) ?>"
                fill="currentColor" fill-opacity="<?= $stagePrint ? '0.06' : '0.10' ?>"
                stroke="<?= $stroke ?>" stroke-width="2"/>
          <?php // Das Maß in die Ecke, nicht in die Mitte: In der Mitte steht der
                // Schlagzeuger, und zwei Texte übereinander liest niemand.
                //
                // Die Notiz gehört in dieselbe Zeile und nicht unter das Podest:
                // Dort steht schon die Beschriftung des Schlagzeugs, das darauf
                // aufgebaut ist, und zwei Zeilen aus zwei Dingen lesen sich wie
                // eine. Deshalb wird sie hier verbraucht — $notizVerbraucht sorgt
                // dafür, dass der gemeinsame Block sie nicht ein zweites Mal setzt. ?>
          <?php $notizVerbraucht = ($it['note'] ?? '') !== ''; ?>
          <text x="<?= round(-$b / 2 + 6, 1) ?>" y="<?= round(-$tf / 2 + 17, 1) ?>" font-size="14" fill="<?= $stroke ?>" opacity="0.6"><?= rtrim(rtrim(number_format($bCm / 100, 2, ',', ''), '0'), ',') ?> × <?= rtrim(rtrim(number_format($tCm / 100, 2, ',', ''), '0'), ',') ?> m<?= $notizVerbraucht ? ' · ' . e($it['note']) : '' ?></text>

        <?php elseif ($it['kind'] === 'musiker'): ?>
          <?php
            // Foto, wenn das Mitglied es so gewählt hat und eines da ist —
            // sonst die gewählte Figur. Ein Gesicht erkennt die Band schneller
            // als jedes Strichmännchen.
            $mg = !empty($it['user_id'])
              ? row('SELECT stage_figure, avatar_file FROM users WHERE id = ?', [(int) $it['user_id']])
              : null;
            $figur = STAGE_FIGURES[$mg['stage_figure'] ?? ''] ?? STAGE_FIGURES[''];
            $foto  = ($mg['stage_figure'] ?? '') === 'avatar' && !empty($mg['avatar_file']);
            // Wie hoch das Symbol über seinen Mittelpunkt reicht. Danach richtet
            // sich der Abstand der Beschriftung: Ein Foto ist doppelt so hoch wie
            // ein Zeichen, und bei festem Abstand stand das Instrument im Gesicht.
            $kopf = $foto ? 32 : 16;
          ?>
          <?php if ($foto): ?>
            <clipPath id="stagepic-<?= (int) $it['id'] ?>"><circle r="30"/></clipPath>
            <image href="/uploads/<?= e($mg['avatar_file']) ?>" x="-30" y="-30" width="60" height="60"
                   preserveAspectRatio="xMidYMid slice" clip-path="url(#stagepic-<?= (int) $it['id'] ?>)"/>
            <circle r="30" fill="none" stroke="<?= $stroke ?>" stroke-width="2" opacity="0.5"/>
          <?php else: ?>
            <?php // fill="transparent": Ein unsichtbarer Griff, damit sich die
                  // Figur beim Ziehen auch dort anfassen lässt, wo das Zeichen
                  // gerade Luft hat. ?>
            <circle r="22" fill="transparent"/>
            <text text-anchor="middle" y="12" font-size="36"><?= $figur ?></text>
          <?php endif; ?>

        <?php elseif ($it['kind'] === 'schlagzeug'): ?>
          <?php // Grundriss plus die zwei Trommeln, an denen man ein Schlagzeug
                // erkennt: die große Trommel vorn zum Publikum, eine Standtom
                // daneben. Mehr Kreise wären Zierde, nicht Information. ?>
          <rect x="<?= round(-$b / 2, 1) ?>" y="<?= round(-$tf / 2, 1) ?>" width="<?= round($b, 1) ?>" height="<?= round($tf, 1) ?>" rx="6"
                fill="none" stroke="<?= $stroke ?>" stroke-width="1.4" stroke-dasharray="7 6" opacity="0.5"/>
          <?php // Große Trommel vorn zum Publikum, Snare in der Mitte, zwei
                // Toms daneben — so weit erkennt es jeder, und weiter geht die
                // Genauigkeit eines Grundrisses ohnehin nicht. ?>
          <circle cx="0" cy="<?= round($tf / 5, 1) ?>" r="<?= round(min($b, $tf) / 4.6, 1) ?>" fill="currentColor" fill-opacity="0.07" stroke="<?= $stroke ?>" stroke-width="2"/>
          <circle cx="<?= round(-$b / 4.5, 1) ?>" cy="<?= round($tf / 12, 1) ?>" r="<?= round(min($b, $tf) / 9, 1) ?>" fill="none" stroke="<?= $stroke ?>" stroke-width="1.4" opacity="0.85"/>
          <circle cx="<?= round($b / 5.5, 1) ?>" cy="<?= round(-$tf / 5, 1) ?>" r="<?= round(min($b, $tf) / 11, 1) ?>" fill="none" stroke="<?= $stroke ?>" stroke-width="1.4" opacity="0.85"/>
          <circle cx="<?= round($b / 3, 1) ?>" cy="<?= round(-$tf / 14, 1) ?>" r="<?= round(min($b, $tf) / 10, 1) ?>" fill="none" stroke="<?= $stroke ?>" stroke-width="1.4" opacity="0.85"/>

        <?php elseif ($it['kind'] === 'amp'): ?>
          <?php // Verstärker: Kiste mit Lautsprecher, Front nach vorn. ?>
          <rect x="<?= round(-$b / 2, 1) ?>" y="<?= round(-$tf / 2, 1) ?>" width="<?= round($b, 1) ?>" height="<?= round($tf, 1) ?>" rx="3"
                fill="<?= $fuell ?>" stroke="<?= $stroke ?>" stroke-width="2"/>
          <circle cy="<?= round($tf / 6, 1) ?>" r="<?= round(min($b, $tf) / 3, 1) ?>" fill="none" stroke="<?= $stroke ?>" stroke-width="2" opacity="0.7"/>

        <?php elseif ($it['kind'] === 'monitor'): ?>
          <?php // Der Monitor ist ein Keil: hinten hoch, vorne flach, schräg
                // zum Musiker. Diese Form erkennt jeder Techniker sofort. ?>
          <polygon points="<?= round(-$b / 2, 1) ?>,<?= round($tf / 2, 1) ?> <?= round($b / 2, 1) ?>,<?= round($tf / 2, 1) ?> <?= round($b / 2.6, 1) ?>,<?= round(-$tf / 2, 1) ?> <?= round(-$b / 2.6, 1) ?>,<?= round(-$tf / 2, 1) ?>"
                   fill="<?= $fuell ?>" stroke="<?= $stroke ?>" stroke-width="2"/>
          <circle cy="0" r="<?= round(min($b, $tf) / 3.4, 1) ?>" fill="none" stroke="<?= $stroke ?>" stroke-width="1.6" opacity="0.7"/>

        <?php elseif ($it['kind'] === 'keyboard'): ?>
          <rect x="<?= round(-$b / 2, 1) ?>" y="<?= round(-$tf / 2, 1) ?>" width="<?= round($b, 1) ?>" height="<?= round($tf, 1) ?>" rx="3"
                fill="<?= $fuell ?>" stroke="<?= $stroke ?>" stroke-width="2"/>
          <?php // Ein paar schwarze Tasten genügen als Zeichen. ?>
          <?php foreach ([1, 2, 4, 5, 6] as $k): ?>
            <line x1="<?= round(-$b / 2 + $k * ($b / 7), 1) ?>" y1="<?= round(-$tf / 2 + 3, 1) ?>"
                  x2="<?= round(-$b / 2 + $k * ($b / 7), 1) ?>" y2="0" stroke="<?= $stroke ?>" stroke-width="3" opacity="0.6"/>
          <?php endforeach; ?>

        <?php elseif ($it['kind'] === 'di' || $it['kind'] === 'stagebox'): ?>
          <?php
            // DI-Box und Stagebox sind beides Anschlusskisten, nur in anderer
            // Größe: Klinke rund, XLR als Dreipunkt. Weil die Dinger klein sind,
            // wächst die Zeichnung nicht mit dem Grundriss mit — eine DI-Box
            // wäre sonst ein Punkt, den niemand mehr anfassen kann.
            $kb = max($b, 48); $kt = max($tf, 34);
            // Die Beschriftung muss unter das Gezeichnete, nicht unter den
            // Grundriss: Bei einer DI-Box ist die Zeichnung größer als das Maß,
            // und der Name stand sonst mitten in der Kiste.
            $unten = max($unten, $kt / 2 + 17);
          ?>
          <rect x="<?= round(-$kb / 2, 1) ?>" y="<?= round(-$kt / 2, 1) ?>" width="<?= round($kb, 1) ?>" height="<?= round($kt, 1) ?>" rx="4"
                fill="<?= $fuell ?>" stroke="<?= $stroke ?>" stroke-width="2"/>
          <?php // Klinke links: Ring mit Punkt. ?>
          <circle cx="<?= round(-$kb / 4, 1) ?>" cy="0" r="7" fill="none" stroke="<?= $stroke ?>" stroke-width="1.6"/>
          <circle cx="<?= round(-$kb / 4, 1) ?>" cy="0" r="2" fill="<?= $stroke ?>"/>
          <?php // XLR rechts: Ring mit drei Stiften. ?>
          <circle cx="<?= round($kb / 4, 1) ?>" cy="0" r="8" fill="none" stroke="<?= $stroke ?>" stroke-width="1.6"/>
          <circle cx="<?= round($kb / 4 - 3, 1) ?>" cy="-2.5" r="1.7" fill="<?= $stroke ?>"/>
          <circle cx="<?= round($kb / 4 + 3, 1) ?>" cy="-2.5" r="1.7" fill="<?= $stroke ?>"/>
          <circle cx="<?= round($kb / 4, 1) ?>" cy="3" r="1.7" fill="<?= $stroke ?>"/>
          <?php // An der Stagebox liegt immer Strom — der Blitz hängt deshalb
                // fest daran und muss nicht als zweiter Eintrag gepflegt werden. ?>
          <?php if ($it['kind'] === 'stagebox'): ?>
            <text x="<?= round($kb / 2 + 2, 1) ?>" y="<?= round(-$kt / 2 + 16, 1) ?>" font-size="24">⚡</text>
          <?php endif; ?>

        <?php elseif ($it['kind'] === 'strom'): ?>
          <?php // Nur der Blitz. Ein Kasten drumherum sagt nichts dazu. ?>
          <circle r="20" fill="transparent"/>
          <text text-anchor="middle" y="11" font-size="32">⚡</text>

        <?php else: ?>
          <rect x="-16" y="-16" width="32" height="32" rx="5" fill="<?= $fuell ?>" stroke="<?= $stroke ?>" stroke-width="2" opacity="0.8"/>
          <text text-anchor="middle" y="8" font-size="22"><?= $sym ?></text>
        <?php endif; ?>

        <?php // Bei Menschen steht der Name über der Figur: Unter ihr liegt das,
              // worauf sie steht — beim Schlagzeuger das Schlagzeug samt eigener
              // Beschriftung, und zwei Texte übereinander liest niemand. ?>
        <?php
          $obenDrueber = ($it['kind'] ?? '') === 'musiker';
          $hatNote = ($it['note'] ?? '') !== '' && !$notizVerbraucht;
          // Über der Figur stapeln sich Name und Instrument nach oben, mit
          // Abstand von der Symbolhöhe statt einem festen Wert. Ohne Instrument
          // rückt der Name näher heran, sonst schwebt er.
          $noteY  = $obenDrueber ? -($kopf + 8) : round($unten + 16);
          $labelY = $obenDrueber ? -($kopf + ($hatNote ? 24 : 8)) : round($unten);
        ?>
        <?php if (($it['label'] ?? '') !== ''): ?>
          <text text-anchor="middle" y="<?= $labelY ?>" font-size="15" fill="<?= $stroke ?>"><?= e($it['label']) ?></text>
        <?php endif; ?>
        <?php if ($hatNote): ?>
          <text text-anchor="middle" y="<?= $noteY ?>" font-size="12" fill="<?= $stroke ?>" opacity="0.65"><?= e($it['note']) ?></text>
        <?php endif; ?>
      </g>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if (!$stageItems): ?>
    <text x="<?= $rand + $flB / 2 ?>" y="<?= $rand + $flT / 2 ?>" text-anchor="middle" font-size="24" fill="<?= $stroke ?>" opacity="0.6"><?= e(t('stage_empty')) ?></text>
  <?php endif; ?>
</svg>
