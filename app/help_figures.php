<?php
declare(strict_types=1);

/**
 * Die Bilder der Hilfeseite (#305).
 *
 * Gezeichnet statt fotografiert, und das mit Absicht: Ein Bildschirmfoto ist in
 * genau einer Sprache richtig, veraltet mit der nächsten Änderung an der
 * Oberfläche und wiegt als Datei mehr als die halbe Seite. Diese Bilder tragen
 * keine Wörter, nehmen die Farben des Programms an und bleiben auch auf einem
 * Telefon lesbar.
 *
 * Sie zeigen nicht die Oberfläche pixelgenau, sondern ihre Form: eine Karte,
 * eine Liste, drei Knöpfe, ein Haken. Genau daran erkennt jemand die Stelle
 * wieder, ohne dass ihm ein veraltetes Foto etwas Falsches verspricht.
 */

/** Ein Bild der Hilfeseite als SVG. Unbekannter Name gibt nichts zurück. */
function help_figure(string $name): string {
  $inhalt = HELP_FIGURES[$name] ?? '';
  if ($inhalt === '') return '';
  return '<svg class="helpfig" viewBox="0 0 320 130" role="img" aria-hidden="true" focusable="false">'
       . $inhalt . '</svg>';
}

/**
 * Bausteine, aus denen fast jedes Bild besteht. Sie halten die Bilder
 * untereinander gleich — eine Karte sieht überall aus wie eine Karte.
 */
function hf_card(int $x, int $y, int $w, int $h): string {
  return sprintf('<rect class="hf-card" x="%d" y="%d" width="%d" height="%d" rx="8"/>', $x, $y, $w, $h);
}
function hf_line(int $x, int $y, int $w, int $h = 6, string $klasse = 'hf-line'): string {
  return sprintf('<rect class="%s" x="%d" y="%d" width="%d" height="%d" rx="3"/>', $klasse, $x, $y, $w, $h);
}
function hf_pill(int $x, int $y, int $w, string $klasse): string {
  return sprintf('<rect class="%s" x="%d" y="%d" width="%d" height="14" rx="7"/>', $klasse, $x, $y, $w);
}
function hf_dot(int $cx, int $cy, int $r, string $klasse): string {
  return sprintf('<circle class="%s" cx="%d" cy="%d" r="%d"/>', $klasse, $cx, $cy, $r);
}
function hf_check(int $x, int $y): string {
  return sprintf('<path class="hf-ok-stroke" d="M%d %d l4 4 l7 -9"/>', $x, $y);
}
function hf_arrow(int $x1, int $y1, int $x2, int $y2): string {
  return sprintf('<path class="hf-edge" d="M%d %d H%d" marker-end="url(#hf-tip)"/>', $x1, $y1, $x2, $y2);
}

/**
 * Der Pfeilkopf steht einmal im Dokument und wird von allen Bildern benutzt.
 * Ohne ihn zeichnet ein Pfeil nur einen Strich.
 */
function help_figure_defs(): string {
  return '<svg width="0" height="0" style="position:absolute" aria-hidden="true">'
       . '<defs><marker id="hf-tip" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto">'
       . '<path d="M0 0 L10 5 L0 10 z" class="hf-edge-fill"/></marker></defs></svg>';
}

const HELP_FIGURES = [

  // Termine: eine Karte mit Datum, Ort und den drei Antworten darunter.
  'termine' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <rect class="hf-accent" x="8" y="12" width="6" height="106" rx="3"/>
     <rect class="hf-line" x="28" y="26" width="120" height="9" rx="4"/>
     <rect class="hf-line-soft" x="28" y="44" width="180" height="6" rx="3"/>
     <rect class="hf-line-soft" x="28" y="58" width="140" height="6" rx="3"/>
     <rect class="hf-ok" x="28" y="84" width="58" height="18" rx="9"/>
     <rect class="hf-maybe" x="94" y="84" width="58" height="18" rx="9"/>
     <rect class="hf-no" x="160" y="84" width="58" height="18" rx="9"/>
     <circle class="hf-accent" cx="278" cy="40" r="16"/>',

  // Songs: eine Liste, daneben Sterne.
  'songs' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <rect class="hf-line" x="26" y="28" width="110" height="8" rx="4"/>
     <rect class="hf-line-soft" x="26" y="56" width="140" height="8" rx="4"/>
     <rect class="hf-line-soft" x="26" y="84" width="96" height="8" rx="4"/>
     <circle class="hf-accent" cx="212" cy="32" r="6"/><circle class="hf-accent" cx="230" cy="32" r="6"/>
     <circle class="hf-accent" cx="248" cy="32" r="6"/><circle class="hf-line-soft" cx="266" cy="32" r="6"/>
     <circle class="hf-accent" cx="212" cy="60" r="6"/><circle class="hf-accent" cx="230" cy="60" r="6"/>
     <circle class="hf-line-soft" cx="248" cy="60" r="6"/><circle class="hf-line-soft" cx="266" cy="60" r="6"/>
     <circle class="hf-accent" cx="212" cy="88" r="6"/><circle class="hf-accent" cx="230" cy="88" r="6"/>
     <circle class="hf-accent" cx="248" cy="88" r="6"/><circle class="hf-accent" cx="266" cy="88" r="6"/>',

  // Setlists: aus der Liste wird ein Blatt.
  'setlists' =>
    '<rect class="hf-card" x="8" y="14" width="120" height="102" rx="8"/>
     <rect class="hf-line-soft" x="22" y="28" width="80" height="6" rx="3"/>
     <rect class="hf-line-soft" x="22" y="44" width="92" height="6" rx="3"/>
     <rect class="hf-line-soft" x="22" y="60" width="70" height="6" rx="3"/>
     <rect class="hf-accent" x="22" y="76" width="50" height="6" rx="3"/>
     <rect class="hf-line-soft" x="22" y="92" width="86" height="6" rx="3"/>
     <path class="hf-edge" d="M140 65 H176" marker-end="url(#hf-tip)"/>
     <rect class="hf-sheet" x="190" y="8" width="112" height="114" rx="6"/>
     <rect class="hf-ink" x="204" y="26" width="52" height="10" rx="4"/>
     <rect class="hf-ink" x="204" y="48" width="78" height="10" rx="4"/>
     <rect class="hf-ink" x="204" y="68" width="66" height="10" rx="4"/>
     <rect class="hf-ink" x="204" y="88" width="84" height="10" rx="4"/>',

  // Orte: eine Stecknadel auf einer Karte, daneben die Anschrift.
  'orte' =>
    '<rect class="hf-card" x="8" y="12" width="140" height="106" rx="10"/>
     <path class="hf-edge" d="M16 84 C56 60 74 96 140 56"/>
     <path class="hf-edge" d="M46 118 L70 20"/>
     <path class="hf-accent-fill" d="M92 44 a14 14 0 1 0 -28 0 c0 12 14 30 14 30 s14 -18 14 -30 z"/>
     <circle class="hf-card-fill" cx="78" cy="44" r="5"/>
     <rect class="hf-card" x="164" y="28" width="148" height="74" rx="8"/>
     <rect class="hf-line" x="180" y="44" width="80" height="8" rx="4"/>
     <rect class="hf-line-soft" x="180" y="62" width="112" height="6" rx="3"/>
     <rect class="hf-line-soft" x="180" y="76" width="90" height="6" rx="3"/>',

  // Abwesenheiten: ein Kalenderband mit einem gesperrten Abschnitt.
  'abwesenheiten' =>
    '<rect class="hf-card" x="8" y="26" width="304" height="78" rx="10"/>
     <rect class="hf-line-soft" x="24" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-line-soft" x="58" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-no" x="92" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-no" x="126" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-no" x="160" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-line-soft" x="194" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-line-soft" x="228" y="44" width="26" height="42" rx="5"/>
     <rect class="hf-line-soft" x="262" y="44" width="26" height="42" rx="5"/>',

  // Aufgaben: eine Liste zum Abhaken.
  'aufgaben' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <rect class="hf-box" x="28" y="28" width="16" height="16" rx="4"/>
     <path class="hf-ok-stroke" d="M31 36 l4 4 l7 -9"/>
     <rect class="hf-line-soft" x="56" y="32" width="150" height="7" rx="3"/>
     <rect class="hf-box" x="28" y="58" width="16" height="16" rx="4"/>
     <path class="hf-ok-stroke" d="M31 66 l4 4 l7 -9"/>
     <rect class="hf-line-soft" x="56" y="62" width="118" height="7" rx="3"/>
     <rect class="hf-box" x="28" y="88" width="16" height="16" rx="4"/>
     <rect class="hf-line" x="56" y="92" width="176" height="7" rx="3"/>',

  // Themen: zwei Sprechblasen.
  'themen' =>
    '<path class="hf-card-fill hf-stroke" d="M16 20 h180 a8 8 0 0 1 8 8 v40 a8 8 0 0 1 -8 8 h-140 l-24 18 v-18 h-16 a8 8 0 0 1 -8 -8 v-40 a8 8 0 0 1 8 -8 z"/>
     <rect class="hf-line-soft" x="34" y="36" width="130" height="6" rx="3"/>
     <rect class="hf-line-soft" x="34" y="52" width="94" height="6" rx="3"/>
     <path class="hf-accent-soft hf-stroke" d="M136 74 h160 a8 8 0 0 1 8 8 v28 a8 8 0 0 1 -8 8 h-140 l-20 10 v-10 z"/>
     <rect class="hf-line-soft" x="154" y="88" width="110" height="6" rx="3"/>',

  // Kasse: ein Balken hinein, ein Balken hinaus, darunter der Saldo.
  'kasse' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <rect class="hf-ok" x="28" y="30" width="120" height="14" rx="7"/>
     <rect class="hf-ok" x="28" y="52" width="76" height="14" rx="7"/>
     <rect class="hf-no" x="172" y="30" width="60" height="14" rx="7"/>
     <rect class="hf-no" x="172" y="52" width="94" height="14" rx="7"/>
     <path class="hf-edge" d="M28 82 H292"/>
     <rect class="hf-accent" x="28" y="92" width="150" height="14" rx="7"/>',

  // Equipment: eine Kiste mit Etikett.
  'equipment' =>
    '<rect class="hf-card" x="24" y="30" width="130" height="80" rx="8"/>
     <path class="hf-edge" d="M24 54 H154"/>
     <rect class="hf-accent" x="70" y="40" width="38" height="8" rx="4"/>
     <rect class="hf-line-soft" x="40" y="70" width="90" height="6" rx="3"/>
     <rect class="hf-line-soft" x="40" y="86" width="62" height="6" rx="3"/>
     <rect class="hf-card" x="178" y="18" width="118" height="42" rx="8"/>
     <rect class="hf-line" x="192" y="34" width="60" height="8" rx="4"/>
     <rect class="hf-card" x="178" y="72" width="118" height="42" rx="8"/>
     <rect class="hf-line-soft" x="192" y="88" width="84" height="8" rx="4"/>',

  // Stagerider: die Bühne von oben mit Schlagzeug, Musikern und Monitoren.
  'rider' =>
    '<rect class="hf-card" x="16" y="14" width="288" height="102" rx="8"/>
     <rect class="hf-line-soft" x="136" y="26" width="52" height="30" rx="5"/>
     <circle class="hf-accent" cx="70" cy="52" r="9"/>
     <circle class="hf-accent" cx="250" cy="52" r="9"/>
     <circle class="hf-accent" cx="160" cy="80" r="9"/>
     <path class="hf-edge" d="M46 100 h34 l-8 -12 h-18 z"/>
     <path class="hf-edge" d="M240 100 h34 l-8 -12 h-18 z"/>
     <path class="hf-edge" d="M143 104 h34 l-8 -12 h-18 z"/>',

  // Fotos: eine Kachelwand.
  'fotos' =>
    '<rect class="hf-card" x="16" y="16" width="86" height="46" rx="6"/>
     <rect class="hf-card" x="116" y="16" width="86" height="46" rx="6"/>
     <rect class="hf-card" x="216" y="16" width="86" height="46" rx="6"/>
     <rect class="hf-card" x="16" y="72" width="86" height="46" rx="6"/>
     <rect class="hf-card" x="116" y="72" width="86" height="46" rx="6"/>
     <rect class="hf-card" x="216" y="72" width="86" height="46" rx="6"/>
     <circle class="hf-accent" cx="46" cy="34" r="7"/>
     <path class="hf-line-soft-stroke" d="M22 56 l22 -16 l16 12 l14 -9 l24 20"/>
     <circle class="hf-accent" cx="146" cy="90" r="7"/>
     <path class="hf-line-soft-stroke" d="M122 112 l22 -16 l16 12 l14 -9 l24 20"/>',

  // Postfach: ein Brief fällt in den Eingang.
  'post' =>
    '<rect class="hf-card" x="60" y="20" width="130" height="80" rx="8"/>
     <path class="hf-edge" d="M60 28 L125 68 L190 28"/>
     <rect class="hf-card" x="206" y="52" width="94" height="58" rx="8"/>
     <rect class="hf-line-soft" x="220" y="70" width="66" height="6" rx="3"/>
     <rect class="hf-line-soft" x="220" y="84" width="48" height="6" rx="3"/>
     <path class="hf-edge" d="M192 60 H246" marker-end="url(#hf-tip)"/>
     <circle class="hf-accent" cx="292" cy="46" r="10"/>',

  // Musik & Videos: ein Abspielknopf.
  'musik' =>
    '<rect class="hf-card" x="16" y="16" width="176" height="98" rx="10"/>
     <circle class="hf-accent" cx="104" cy="65" r="26"/>
     <path class="hf-card-fill" d="M96 53 l22 12 l-22 12 z"/>
     <rect class="hf-card" x="208" y="16" width="96" height="44" rx="8"/>
     <rect class="hf-line-soft" x="222" y="32" width="60" height="7" rx="3"/>
     <rect class="hf-card" x="208" y="70" width="96" height="44" rx="8"/>
     <rect class="hf-line-soft" x="222" y="86" width="44" height="7" rx="3"/>',

  // Downloads: eine Datei mit Pfeil nach unten.
  'downloads' =>
    '<rect class="hf-card" x="110" y="12" width="100" height="74" rx="8"/>
     <rect class="hf-line-soft" x="126" y="30" width="60" height="6" rx="3"/>
     <rect class="hf-line-soft" x="126" y="44" width="44" height="6" rx="3"/>
     <path class="hf-accent-stroke" d="M160 60 V104"/>
     <path class="hf-accent-fill" d="M146 96 l14 18 l14 -18 z"/>
     <path class="hf-edge" d="M78 118 H242"/>',

  // Mitglieder: drei Personen, daneben ihre Haken.
  'mitglieder' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <circle class="hf-accent" cx="42" cy="34" r="9"/>
     <path class="hf-accent-fill" d="M28 52 a14 12 0 0 1 28 0 z"/>
     <rect class="hf-line-soft" x="70" y="30" width="96" height="7" rx="3"/>
     <rect class="hf-box" x="196" y="26" width="14" height="14" rx="4"/><path class="hf-ok-stroke" d="M199 33 l3 4 l6 -8"/>
     <rect class="hf-box" x="226" y="26" width="14" height="14" rx="4"/><path class="hf-ok-stroke" d="M229 33 l3 4 l6 -8"/>
     <rect class="hf-box" x="256" y="26" width="14" height="14" rx="4"/>
     <circle class="hf-line" cx="42" cy="82" r="9"/>
     <path class="hf-line-fill" d="M28 100 a14 12 0 0 1 28 0 z"/>
     <rect class="hf-line-soft" x="70" y="78" width="72" height="7" rx="3"/>
     <rect class="hf-box" x="196" y="74" width="14" height="14" rx="4"/><path class="hf-ok-stroke" d="M199 81 l3 4 l6 -8"/>
     <rect class="hf-box" x="226" y="74" width="14" height="14" rx="4"/>
     <rect class="hf-box" x="256" y="74" width="14" height="14" rx="4"/>',

  // Gäste: eine Einladung, ein Schlüssel, eine Uhr, die abläuft.
  'gaeste' =>
    '<rect class="hf-card" x="12" y="34" width="88" height="58" rx="8"/>
     <path class="hf-edge" d="M12 40 L56 70 L100 40"/>
     <path class="hf-edge" d="M104 62 H144" marker-end="url(#hf-tip)"/>
     <rect class="hf-accent-soft hf-stroke" x="154" y="34" width="72" height="58" rx="8"/>
     <circle class="hf-accent" cx="180" cy="63" r="9"/>
     <path class="hf-accent-stroke" d="M188 63 H212"/>
     <path class="hf-edge" d="M230 62 H266" marker-end="url(#hf-tip)"/>
     <circle class="hf-card-fill hf-stroke" cx="288" cy="63" r="22"/>
     <path class="hf-no-stroke" d="M288 48 V63 l11 8"/>',

  // E-Mail-Versand: ein Brief verlässt das Haus.
  'mailversand' =>
    '<rect class="hf-card" x="20" y="34" width="110" height="66" rx="8"/>
     <path class="hf-edge" d="M20 40 L75 76 L130 40"/>
     <path class="hf-accent-stroke" d="M142 67 H262"/>
     <path class="hf-accent-fill" d="M256 55 l22 12 l-22 12 z"/>
     <circle class="hf-ok" cx="292" cy="34" r="12"/>
     <path class="hf-card-fill-stroke" d="M286 34 l4 4 l8 -9"/>',

  // Angebote: die erste Stunde als hoher Balken, die weiteren niedriger.
  'angebote' =>
    '<rect class="hf-card" x="8" y="12" width="304" height="106" rx="10"/>
     <path class="hf-edge" d="M36 100 H286"/>
     <rect class="hf-accent" x="48" y="34" width="44" height="66" rx="5"/>
     <rect class="hf-accent-soft" x="104" y="60" width="44" height="40" rx="5"/>
     <rect class="hf-accent-soft" x="160" y="60" width="44" height="40" rx="5"/>
     <rect class="hf-line-soft" x="216" y="76" width="44" height="24" rx="5"/>
     <rect class="hf-line" x="48" y="20" width="60" height="6" rx="3"/>',

  // Steuerübersicht: ein Blatt mit Summenstrich.
  'steuer' =>
    '<rect class="hf-sheet" x="80" y="8" width="160" height="114" rx="6"/>
     <rect class="hf-ink" x="96" y="24" width="64" height="9" rx="4"/>
     <rect class="hf-line-soft" x="96" y="46" width="128" height="6" rx="3"/>
     <rect class="hf-line-soft" x="96" y="60" width="104" height="6" rx="3"/>
     <rect class="hf-line-soft" x="96" y="74" width="118" height="6" rx="3"/>
     <path class="hf-edge" d="M96 90 H224"/>
     <rect class="hf-accent" x="150" y="98" width="74" height="10" rx="5"/>',

  // Mitteilungen: eine Glocke mit Zähler.
  'push' =>
    '<path class="hf-accent-fill" d="M160 26 a34 34 0 0 1 34 34 v22 l10 14 h-88 l10 -14 v-22 a34 34 0 0 1 34 -34 z"/>
     <path class="hf-accent-fill" d="M148 100 a12 12 0 0 0 24 0 z"/>
     <circle class="hf-no" cx="200" cy="34" r="14"/>
     <rect class="hf-card" x="36" y="44" width="56" height="40" rx="8"/>
     <rect class="hf-card" x="228" y="44" width="56" height="40" rx="8"/>',

  // Zweiter Faktor: Kennwort plus Zahl vom Handy.
  'totp' =>
    '<rect class="hf-card" x="16" y="30" width="118" height="70" rx="8"/>
     <rect class="hf-line-soft" x="32" y="48" width="86" height="8" rx="4"/>
     <rect class="hf-line-soft" x="32" y="70" width="60" height="8" rx="4"/>
     <path class="hf-edge" d="M146 66 H180" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="196" y="14" width="76" height="102" rx="12"/>
     <rect class="hf-accent" x="212" y="46" width="44" height="14" rx="4"/>
     <rect class="hf-line-soft" x="212" y="70" width="44" height="6" rx="3"/>
     <circle class="hf-line" cx="234" cy="104" r="5"/>',

  // Passkey: das Gerät öffnet sich mit dem Finger.
  'passkey' =>
    '<rect class="hf-card" x="120" y="12" width="80" height="106" rx="14"/>
     <circle class="hf-accent" cx="160" cy="56" r="20"/>
     <path class="hf-card-fill-stroke" d="M152 56 a8 10 0 0 1 16 0 M156 56 a4 8 0 0 1 8 0 M160 50 v14"/>
     <rect class="hf-line-soft" x="136" y="88" width="48" height="6" rx="3"/>
     <rect class="hf-card" x="16" y="42" width="80" height="46" rx="8"/>
     <rect class="hf-card" x="224" y="42" width="80" height="46" rx="8"/>
     <path class="hf-edge" d="M100 65 H114" marker-end="url(#hf-tip)"/>
     <path class="hf-edge" d="M206 65 H220" marker-end="url(#hf-tip)"/>',

  // Auf dem Handy installieren: aus der Seite wird ein Symbol.
  'app' =>
    '<rect class="hf-card" x="16" y="18" width="120" height="94" rx="10"/>
     <rect class="hf-line-soft" x="30" y="34" width="72" height="7" rx="3"/>
     <rect class="hf-line-soft" x="30" y="52" width="92" height="6" rx="3"/>
     <rect class="hf-accent" x="30" y="86" width="52" height="12" rx="6"/>
     <path class="hf-edge" d="M150 65 H186" marker-end="url(#hf-tip)"/>
     <rect class="hf-card" x="202" y="14" width="76" height="102" rx="12"/>
     <rect class="hf-accent" x="216" y="34" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="246" y="34" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="216" y="66" width="22" height="22" rx="6"/>
     <rect class="hf-line-soft" x="246" y="66" width="22" height="22" rx="6"/>',
];
