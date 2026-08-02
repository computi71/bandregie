<?php
declare(strict_types=1);

/**
 * QR-Code — so viel davon, wie ein otpauth-Link braucht (#169).
 *
 * Der zweite Faktor wird eingerichtet, indem die Authenticator-App ein
 * Geheimnis übernimmt. Abtippen geht, macht aber niemand gern: 32 Zeichen,
 * und ein Vertipper sieht aus wie ein falscher Code. Also ein QR-Code.
 *
 * Warum selbst geschrieben und keine Bibliothek: Das Projekt verspricht im
 * README, ohne Composer, ohne Lock-Datei und ohne fremdes Skript zu laufen —
 * eine Band soll es in fünf Jahren noch starten können, ohne etwas
 * nachzuziehen. Die QR-Norm ist seit dem Jahr 2000 unverändert, hier ist also
 * nichts zu pflegen; die Arbeit fällt einmal an und dann nie wieder.
 *
 * Was ausdrücklich nicht in Frage kam: einen QR-Dienst im Netz aufrufen. Im
 * Code steht das TOTP-Geheimnis im Klartext. Wer ihn fremd rendern lässt,
 * verschickt genau den zweiten Faktor, der schützen soll.
 *
 * Deshalb kann diese Datei mit Absicht wenig: Byte-Modus, Fehlerkorrektur M,
 * Versionen 1 bis 10. Das reicht für 213 Zeichen — ein otpauth-Link mit
 * langem Bandnamen und langer Adresse liegt bei rund 140. Alles darüber
 * hinaus (Kanji, Zahlenmodus, Version 40) wäre Code, den nie jemand ausführt.
 *
 * Ausgegeben wird SVG und kein Bild: Das braucht keine GD-Erweiterung, bleibt
 * bei jeder Größe scharf und ist reiner Text, der direkt in die Seite kann.
 */

/**
 * Fehlerkorrektur M — rund 15 % des Codes dürfen zerstört sein.
 *
 * L wäre kleiner, M ist die übliche Wahl für Bildschirme: Ein Foto vom
 * Monitor mit Spiegelung und schrägem Winkel kommt damit noch durch.
 */
const QR_EC_M = 0b00;

/**
 * Was jede Version fasst: [Codewörter gesamt, Fehlerkorrektur je Block,
 * [[Anzahl Blöcke, Datenwörter je Block], …]].
 *
 * Aus ISO/IEC 18004, Tabelle 9. Bei den größeren Versionen sind die Blöcke
 * unterschiedlich groß — deshalb die Liste und nicht eine Zahl.
 */
const QR_VERSIONS_M = [
  1  => [26,  10, [[1, 16]]],
  2  => [44,  16, [[1, 28]]],
  3  => [70,  26, [[1, 44]]],
  4  => [100, 18, [[2, 32]]],
  5  => [134, 24, [[2, 43]]],
  6  => [172, 16, [[4, 27]]],
  7  => [196, 18, [[4, 31]]],
  8  => [242, 22, [[2, 38], [2, 39]]],
  9  => [292, 22, [[3, 36], [2, 37]]],
  10 => [346, 26, [[4, 43], [1, 44]]],
];

/** Wo die Ausrichtungsmuster sitzen — Mittelpunkte je Version, Tabelle E.1. */
const QR_ALIGN = [
  1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
  6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
];

/**
 * Ein QR-Code als SVG.
 *
 * $modul ist die Kantenlänge eines Punktes in Pixeln, $rand die Ruhezone in
 * Modulen. Vier ist das Minimum aus der Norm; weniger, und Leser finden den
 * Code auf hellem Grund nicht mehr zuverlässig.
 *
 * Gibt bei zu langem Text eine leere Zeichenkette zurück, nicht etwa einen
 * kaputten Code — die aufrufende Stelle soll dann das Geheimnis zum Abtippen
 * zeigen und nicht ein Bild, das niemand scannen kann.
 */
function qr_svg(string $text, int $modul = 4, int $rand = 4): string {
  $m = qr_matrix($text);
  if (!$m) return '';
  $n = count($m);
  $kante = ($n + 2 * $rand) * $modul;

  // Alle dunklen Module in einen einzigen Pfad: ein <rect> je Punkt wären bei
  // Version 6 schon über tausend Elemente.
  $pfad = '';
  for ($y = 0; $y < $n; $y++) {
    for ($x = 0; $x < $n; $x++) {
      if (!$m[$y][$x]) continue;
      $pfad .= 'M' . (($x + $rand) * $modul) . ' ' . (($y + $rand) * $modul)
             . 'h' . $modul . 'v' . $modul . 'h-' . $modul . 'z';
    }
  }

  // Weiß ausdrücklich hinterlegen: Auf dunklem Seitenhintergrund wäre der Code
  // sonst hell auf dunkel, und das lesen viele Kameras nicht.
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $kante . '" height="' . $kante . '"'
    . ' viewBox="0 0 ' . $kante . ' ' . $kante . '" shape-rendering="crispEdges" role="img">'
    . '<rect width="' . $kante . '" height="' . $kante . '" fill="#fff"/>'
    . '<path d="' . $pfad . '" fill="#000"/></svg>';
}

/**
 * Die fertige Modulmatrix: Zeilen von 0 und 1, 1 ist dunkel.
 *
 * Leeres Feld, wenn der Text nicht in Version 10 passt.
 */
function qr_matrix(string $text): array {
  $version = qr_version_for($text);
  if ($version === 0) return [];

  $bits = qr_bitstream($text, $version);
  $roh = qr_interleave($bits, $version);

  [$m, $frei] = qr_function_patterns($version);
  qr_place_data($m, $frei, $roh);

  // Acht Masken sind erlaubt, und welche die beste ist, entscheidet die Norm
  // über Strafpunkte: gleichfarbige Flächen und Muster, die nach einem
  // Suchmuster aussehen, machen den Code schwer lesbar.
  $bestePunkte = PHP_INT_MAX;
  $besteMatrix = [];
  for ($maske = 0; $maske < 8; $maske++) {
    $kandidat = $m;
    qr_apply_mask($kandidat, $frei, $maske);
    qr_place_format($kandidat, $maske);
    if ($version >= 7) qr_place_version($kandidat, $version);
    $punkte = qr_penalty($kandidat);
    if ($punkte < $bestePunkte) {
      $bestePunkte = $punkte;
      $besteMatrix = $kandidat;
    }
  }
  return $besteMatrix;
}

/** Die kleinste Version, in die der Text passt. 0, wenn keine reicht. */
function qr_version_for(string $text): int {
  $laenge = strlen($text);
  foreach (QR_VERSIONS_M as $version => [$gesamt, $ec, $bloecke]) {
    $datenwoerter = 0;
    foreach ($bloecke as [$anzahl, $groesse]) $datenwoerter += $anzahl * $groesse;
    // Die Längenangabe ist ab Version 10 sechzehn Bit breit statt acht —
    // ohne diese Fallunterscheidung passen 214 Zeichen scheinbar in einen
    // Code, der nur 213 fasst, und der Leser bekommt Müll.
    $laengenBits = $version < 10 ? 8 : 16;
    if (4 + $laengenBits + 8 * $laenge <= $datenwoerter * 8) return $version;
  }
  return 0;
}

/** Modus, Länge, Nutzdaten, Abschluss und Auffüllen — als Bitkette. */
function qr_bitstream(string $text, int $version): string {
  $datenwoerter = 0;
  foreach (QR_VERSIONS_M[$version][2] as [$anzahl, $groesse]) $datenwoerter += $anzahl * $groesse;
  $kapazitaet = $datenwoerter * 8;

  $bits = '0100'; // Byte-Modus
  $bits .= str_pad(decbin(strlen($text)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
  for ($i = 0; $i < strlen($text); $i++) $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);

  // Abschluss: bis zu vier Nullen, aber nur so viele wie noch Platz ist.
  $bits .= str_repeat('0', min(4, $kapazitaet - strlen($bits)));
  // Auf volle Bytes aufrunden.
  if (strlen($bits) % 8) $bits .= str_repeat('0', 8 - strlen($bits) % 8);
  // Der Rest wird mit zwei festen Bytes im Wechsel gefüllt — so steht es in
  // der Norm, damit die Füllung nicht wie eine gleichförmige Fläche wirkt.
  $fueller = ['11101100', '00010001'];
  for ($i = 0; strlen($bits) < $kapazitaet; $i++) $bits .= $fueller[$i % 2];

  return $bits;
}

// ---------- Fehlerkorrektur ----------

/** Die Logarithmentafeln des GF(256), Primpolynom 0x11D wie in der Norm. */
function qr_gf(): array {
  static $exp = null, $log = null;
  if ($exp === null) {
    $exp = [];
    $log = [];
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
      $exp[$i] = $x;
      $log[$x] = $i;
      $x <<= 1;
      if ($x & 0x100) $x ^= 0x11d;
    }
    // Doppelt angelegt, damit das Multiplizieren ohne Modulo auskommt.
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
  }
  return [$exp, $log];
}

/** Das Generatorpolynom für $grad Prüfwörter. */
function qr_generator(int $grad): array {
  [$exp, $log] = qr_gf();
  $g = [1];
  for ($i = 0; $i < $grad; $i++) {
    $neu = array_fill(0, count($g) + 1, 0);
    foreach ($g as $j => $koeff) {
      $neu[$j] ^= $koeff;
      if ($koeff !== 0) $neu[$j + 1] ^= $exp[$log[$koeff] + $i];
    }
    $g = $neu;
  }
  return $g;
}

/** Die Prüfwörter zu einem Datenblock — Polynomdivision im GF(256). */
function qr_ecc(array $daten, int $anzahl): array {
  [$exp, $log] = qr_gf();
  $g = qr_generator($anzahl);
  $rest = array_merge($daten, array_fill(0, $anzahl, 0));
  for ($i = 0; $i < count($daten); $i++) {
    $fuehrend = $rest[$i];
    if ($fuehrend === 0) continue;
    foreach ($g as $j => $koeff) {
      if ($koeff !== 0) $rest[$i + $j] ^= $exp[$log[$koeff] + $log[$fuehrend]];
    }
  }
  return array_slice($rest, count($daten));
}

/**
 * Blöcke bilden, Prüfwörter rechnen und beides verschränken.
 *
 * Verschränkt wird, damit ein Kratzer quer über den Code nicht einen einzigen
 * Block vollständig zerstört, sondern sich auf alle verteilt — jeder Block
 * verkraftet seinen Anteil, keiner fällt ganz aus.
 */
function qr_interleave(string $bits, int $version): string {
  [, $ecAnzahl, $blockPlan] = QR_VERSIONS_M[$version];

  $woerter = [];
  foreach (str_split($bits, 8) as $byte) $woerter[] = bindec($byte);

  $datenBloecke = [];
  $ecBloecke = [];
  $pos = 0;
  foreach ($blockPlan as [$anzahl, $groesse]) {
    for ($i = 0; $i < $anzahl; $i++) {
      $block = array_slice($woerter, $pos, $groesse);
      $pos += $groesse;
      $datenBloecke[] = $block;
      $ecBloecke[] = qr_ecc($block, $ecAnzahl);
    }
  }

  $aus = [];
  $maxDaten = max(array_map('count', $datenBloecke));
  for ($i = 0; $i < $maxDaten; $i++) {
    foreach ($datenBloecke as $block) if (isset($block[$i])) $aus[] = $block[$i];
  }
  for ($i = 0; $i < $ecAnzahl; $i++) {
    foreach ($ecBloecke as $block) $aus[] = $block[$i];
  }

  $roh = '';
  foreach ($aus as $wort) $roh .= str_pad(decbin($wort), 8, '0', STR_PAD_LEFT);
  return $roh;
}

// ---------- Die Matrix ----------

/**
 * Suchmuster, Ausrichtungsmuster, Taktlinien — und die Karte der Module, die
 * danach noch für Daten frei sind.
 */
function qr_function_patterns(int $version): array {
  $n = 17 + 4 * $version;
  $m = array_fill(0, $n, array_fill(0, $n, 0));
  $frei = array_fill(0, $n, array_fill(0, $n, true));

  $belege = function (int $y, int $x, int $wert) use (&$m, &$frei, $n): void {
    if ($y < 0 || $x < 0 || $y >= $n || $x >= $n) return;
    $m[$y][$x] = $wert;
    $frei[$y][$x] = false;
  };

  // Die drei Suchmuster mit ihrem hellen Rand.
  foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$oy, $ox]) {
    for ($y = -1; $y <= 7; $y++) {
      for ($x = -1; $x <= 7; $x++) {
        $imRing = ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6))
               || ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6));
        $imKern = $y >= 2 && $y <= 4 && $x >= 2 && $x <= 4;
        $belege($oy + $y, $ox + $x, ($imRing || $imKern) ? 1 : 0);
      }
    }
  }

  // Ausrichtungsmuster, aber nicht dort, wo schon ein Suchmuster sitzt.
  $mitten = QR_ALIGN[$version];
  foreach ($mitten as $cy) {
    foreach ($mitten as $cx) {
      $beiSucher = ($cy <= 8 && $cx <= 8) || ($cy <= 8 && $cx >= $n - 9) || ($cy >= $n - 9 && $cx <= 8);
      if ($beiSucher) continue;
      for ($y = -2; $y <= 2; $y++) {
        for ($x = -2; $x <= 2; $x++) {
          $dunkel = max(abs($y), abs($x)) !== 1;
          $belege($cy + $y, $cx + $x, $dunkel ? 1 : 0);
        }
      }
    }
  }

  // Taktlinien: das Lineal, an dem der Leser die Modulbreite abzählt.
  for ($i = 8; $i < $n - 8; $i++) {
    $belege(6, $i, $i % 2 === 0 ? 1 : 0);
    $belege($i, 6, $i % 2 === 0 ? 1 : 0);
  }

  // Der Platz für die Formatangabe bleibt frei von Daten; er wird nach dem
  // Maskieren beschrieben. Das eine feste dunkle Modul gehört dazu.
  for ($i = 0; $i <= 8; $i++) {
    if ($i !== 6) { $belege(8, $i, 0); $belege($i, 8, 0); }
  }
  for ($i = 0; $i < 8; $i++) {
    $belege(8, $n - 1 - $i, 0);
    $belege($n - 1 - $i, 8, 0);
  }
  $belege($n - 8, 8, 1);

  // Ab Version 7 steht die Versionsnummer zweimal im Code.
  if ($version >= 7) {
    for ($i = 0; $i < 6; $i++) {
      for ($j = 0; $j < 3; $j++) {
        $belege($n - 11 + $j, $i, 0);
        $belege($i, $n - 11 + $j, 0);
      }
    }
  }

  return [$m, $frei];
}

/**
 * Die Bits im Zickzack einfüllen: von unten rechts, spaltenweise zu zweit,
 * abwechselnd aufwärts und abwärts. Spalte 6 ist die senkrechte Taktlinie und
 * wird übersprungen.
 */
function qr_place_data(array &$m, array $frei, string $bits): void {
  $n = count($m);
  $idx = 0;
  $aufwaerts = true;
  for ($col = $n - 1; $col > 0; $col -= 2) {
    if ($col === 6) $col--;
    for ($i = 0; $i < $n; $i++) {
      $row = $aufwaerts ? $n - 1 - $i : $i;
      foreach ([$col, $col - 1] as $c) {
        if (!$frei[$row][$c]) continue;
        // Reichen die Bits nicht bis in die letzte Ecke, bleibt dort hell —
        // die Norm nennt das Restbits, sie tragen keine Information.
        $m[$row][$c] = $idx < strlen($bits) ? (int) $bits[$idx] : 0;
        $idx++;
      }
    }
    $aufwaerts = !$aufwaerts;
  }
}

/** Die acht Masken der Norm. Sie gelten nur für Datenmodule. */
function qr_mask_bit(int $maske, int $y, int $x): bool {
  switch ($maske) {
    case 0: return ($y + $x) % 2 === 0;
    case 1: return $y % 2 === 0;
    case 2: return $x % 3 === 0;
    case 3: return ($y + $x) % 3 === 0;
    case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
    case 5: return ($y * $x) % 2 + ($y * $x) % 3 === 0;
    case 6: return (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0;
    default: return ((($y + $x) % 2) + ($y * $x) % 3) % 2 === 0;
  }
}

function qr_apply_mask(array &$m, array $frei, int $maske): void {
  $n = count($m);
  for ($y = 0; $y < $n; $y++) {
    for ($x = 0; $x < $n; $x++) {
      if ($frei[$y][$x] && qr_mask_bit($maske, $y, $x)) $m[$y][$x] ^= 1;
    }
  }
}

/**
 * Formatangabe: Fehlerkorrekturstufe und Maskennummer, mit BCH-Prüfbits
 * gesichert und gegen ein festes Muster verrechnet, damit nie alles hell ist.
 */
function qr_place_format(array &$m, int $maske): void {
  $n = count($m);
  $wert = (QR_EC_M << 3) | $maske;
  $rest = $wert << 10;
  for ($i = 4; $i >= 0; $i--) {
    if ($rest & (1 << ($i + 10))) $rest ^= 0x537 << $i;
  }
  $bits = (($wert << 10) | $rest) ^ 0x5412;

  // Zeile und Spalte sind hier leicht zu vertauschen, und das Ergebnis sieht
  // täuschend richtig aus — die Fläche ist symmetrisch, die Bitfolge nicht.
  // Die erste Kopie läuft Spalte 8 hinunter und dann Zeile 8 nach links.
  for ($i = 0; $i < 15; $i++) {
    $bit = ($bits >> $i) & 1;
    if ($i < 6)       $m[$i][8] = $bit;
    elseif ($i === 6) $m[7][8] = $bit;
    elseif ($i === 7) $m[8][8] = $bit;
    elseif ($i === 8) $m[8][7] = $bit;
    else              $m[8][14 - $i] = $bit;
    // Zweite Kopie: verteilt auf die beiden anderen Ecken, damit ein
    // beschädigtes Suchmuster die Formatangabe nicht mitnimmt.
    if ($i < 8) $m[8][$n - 1 - $i] = $bit;
    else        $m[$n - 15 + $i][8] = $bit;
  }
}

/** Versionsangabe für Version 7 und größer, zweimal im Code. */
function qr_place_version(array &$m, int $version): void {
  $n = count($m);
  $rest = $version << 12;
  for ($i = 5; $i >= 0; $i--) {
    if ($rest & (1 << ($i + 12))) $rest ^= 0x1f25 << $i;
  }
  $bits = ($version << 12) | $rest;

  for ($i = 0; $i < 18; $i++) {
    $bit = ($bits >> $i) & 1;
    $m[$n - 11 + $i % 3][intdiv($i, 3)] = $bit;
    $m[intdiv($i, 3)][$n - 11 + $i % 3] = $bit;
  }
}

/**
 * Strafpunkte nach den vier Regeln der Norm. Je weniger, desto besser liest
 * sich der Code — gleichförmige Flächen und Muster, die nach einem Suchmuster
 * aussehen, sind das, was Leser stolpern lässt.
 */
function qr_penalty(array $m): int {
  $n = count($m);
  $punkte = 0;

  // Regel 1: Reihen ab fünf gleichen Modulen, waagerecht wie senkrecht.
  for ($durchgang = 0; $durchgang < 2; $durchgang++) {
    for ($a = 0; $a < $n; $a++) {
      $lauf = 1;
      for ($b = 1; $b < $n; $b++) {
        $jetzt = $durchgang === 0 ? $m[$a][$b] : $m[$b][$a];
        $vorher = $durchgang === 0 ? $m[$a][$b - 1] : $m[$b - 1][$a];
        if ($jetzt === $vorher) {
          $lauf++;
        } else {
          if ($lauf >= 5) $punkte += 3 + ($lauf - 5);
          $lauf = 1;
        }
      }
      if ($lauf >= 5) $punkte += 3 + ($lauf - 5);
    }
  }

  // Regel 2: jedes einfarbige Zweiergeviert.
  for ($y = 0; $y < $n - 1; $y++) {
    for ($x = 0; $x < $n - 1; $x++) {
      $v = $m[$y][$x];
      if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) $punkte += 3;
    }
  }

  // Regel 3: Folgen, die einem Suchmuster ähneln — teuer, denn sie schicken
  // den Leser an die falsche Stelle.
  $muster = [[1,0,1,1,1,0,1,0,0,0,0], [0,0,0,0,1,0,1,1,1,0,1]];
  for ($a = 0; $a < $n; $a++) {
    for ($b = 0; $b <= $n - 11; $b++) {
      foreach ($muster as $p) {
        $waagerecht = true;
        $senkrecht = true;
        for ($k = 0; $k < 11; $k++) {
          if ($m[$a][$b + $k] !== $p[$k]) $waagerecht = false;
          if ($m[$b + $k][$a] !== $p[$k]) $senkrecht = false;
        }
        if ($waagerecht) $punkte += 40;
        if ($senkrecht) $punkte += 40;
      }
    }
  }

  // Regel 4: Abweichung vom halb-halb-Verhältnis zwischen hell und dunkel.
  $dunkel = 0;
  foreach ($m as $zeile) $dunkel += array_sum($zeile);
  $anteil = intdiv($dunkel * 100, $n * $n);
  $punkte += intdiv(abs($anteil - 50), 5) * 10;

  return $punkte;
}
