<?php
// Die Hilfe gegen den Funktionsstand halten (#369).
//
// Die Hilfeseite zählt über PERM_MODULES und gibt t('inav_…') und t('help_…')
// ungeprüft aus. Fehlt ein Text, liefert t() seinen eigenen Schlüssel zurück —
// und dann stand dort monatelang
//
//     🧾 inav_rechnungen
//     help_rechnungen
//
// auf jeder laufenden Anlage. Aufgefallen ist es, weil jemand gefragt hat, ob
// die Hilfe noch stimmt. Darauf soll sich das nicht wieder verlassen müssen.
//
// Geprüft wird, was mechanisch prüfbar ist: dass jeder Bereich einen Namen und
// eine Erklärung hat, dass jeder Bereich mit einer Seite auch im Menü steht,
// und dass kein Hilfetext einen Absatz überspringt — denn die Seite bricht bei
// der ersten Lücke ab und verschluckt, was danach kommt.
//
// Ob der Text INHALTLICH stimmt, sieht kein Skript. Das bleibt Handarbeit bei
// jedem Review.
//
//   sudo -u www-data php bin/hilfe-pruefen.php

require __DIR__ . '/../app/bootstrap.php';
// help_sections() und help_section_name() leben hier und nicht im bootstrap:
// Sie werden nur von der Hilfeseite gebraucht.
require_once __DIR__ . '/../app/help.php';

$fehler = 0;
$pruef = function (string $was, bool $ok, string $mehr = '') use (&$fehler): void {
  if (!$ok) $fehler++;
  echo ($ok ? '  ok   ' : '  FEHL '), $was, $mehr === '' ? '' : "  — $mehr", PHP_EOL;
};

// Die Hilfeseite bietet vier Zusatzabsätze an. Steht die Zahl hier anders als
// dort, prüft dieses Skript an der Seite vorbei.
const HILFE_ABSAETZE = ['_2', '_3', '_4', '_5'];

echo '— Name und Erklärung je Bereich —', PHP_EOL;
$ohneName = $ohneText = [];
foreach (PERM_MODULES as $modul => $pfade) {
  if (t('inav_' . $modul) === 'inav_' . $modul) $ohneName[] = $modul;
  if (t('help_' . $modul) === 'help_' . $modul) $ohneText[] = $modul;
}
$pruef('jeder Bereich hat einen Namen', !$ohneName, implode(' ', $ohneName));
$pruef('jeder Bereich hat eine Erklärung', !$ohneText, implode(' ', $ohneText));

echo PHP_EOL, '— Lücken in den Absätzen —', PHP_EOL;
// help_x_2 fehlt, help_x_3 ist da: Die Seite hört beim ersten Loch auf, also
// wäre der dritte Absatz geschrieben und unsichtbar.
$loecher = [];
foreach (PERM_MODULES as $modul => $pfade) {
  $da = [];
  foreach (HILFE_ABSAETZE as $nr) {
    $k = 'help_' . $modul . $nr;
    $da[] = t($k) !== $k;
  }
  $ersteLuecke = array_search(false, $da, true);
  if ($ersteLuecke !== false && in_array(true, array_slice($da, (int) $ersteLuecke), true)) {
    $loecher[] = $modul;
  }
}
$pruef('kein Bereich überspringt einen Absatz', !$loecher, implode(' ', $loecher));

echo PHP_EOL, '— Erreichbarkeit —', PHP_EOL;
// Ein Bereich mit einer Seite, den das Menü nicht nennt, findet nur, wer die
// Adresse kennt. Genau so lagen die Rechnungen monatelang da.
$navigation = (string) @file_get_contents(BASE_DIR . '/app/views/_header.php');
$nichtImMenue = [];
foreach (PERM_MODULES as $modul => $pfade) {
  foreach ($pfade as $pfad) {
    if ($pfad === '') continue;                       // mailversand hat keine Seite
    if (str_contains($navigation, "'" . $pfad . "'")) continue 2;
  }
  if ($pfade) $nichtImMenue[] = $modul;
}
$pruef('jeder Bereich mit einer Seite steht im Menü', !$nichtImMenue, implode(' ', $nichtImMenue));

echo PHP_EOL, '— Die Aufgabenliste —', PHP_EOL;
// „Ich möchte …" springt zu einem Abschnitt. Fehlt der, führt der Sprung ins
// Leere und die Seite rührt sich nicht. Genau das passierte beim Rechenblatt:
// Der Abschnitt verschwand im einstufigen Weg, der Verweis blieb (#370).
//
// Geprüft wird je Mitglied und nicht gegen ein ausgedachtes Konto. Beides
// hängt an denselben Rechten: Wer die Kasse nicht sehen darf, bekommt weder
// die Aufgabe noch den Abschnitt. Ein erfundener Admin hätte hier Lücken
// gemeldet, die auf der Seite niemand zu sehen bekommt — der erste Lauf
// dieser Prüfung tat das prompt.
$ziellos = [];
foreach (rows('SELECT id, role FROM users') as $konto) {
  $abschnitte = array_keys(help_sections($konto));
  foreach (HELP_TASKS as [$modul, $textKey, $anker]) {
    if ($modul !== '' && !perm_allows($konto, $modul)) continue;   // wird nicht gezeigt
    $ziel = preg_replace('~^hilfe-~', '', $anker);
    if (!in_array($ziel, $abschnitte, true)) $ziellos[] = $anker . ' (Konto ' . $konto['id'] . ')';
  }
}
foreach (array_column(HELP_TASKS, 1) as $textKey) {
  if (t($textKey) === $textKey) $ziellos[] = $textKey . ' (ohne Text)';
}
$pruef('jede Aufgabe führt zu einem Abschnitt, den es gibt',
  !$ziellos, implode(' ', array_unique($ziellos)));

// Umgekehrt: Was die Band täglich tut, soll in der Liste stehen. Vertrag und
// Rechnung fehlten dort, obwohl beides zum Weg von der Anfrage zum Geld gehört.
$inListe = array_column(HELP_TASKS, 0);
$sollte = array_intersect(['termine', 'vertraege', 'rechnungen', 'kasse'], array_keys(PERM_MODULES));
$fehltInListe = array_diff($sollte, $inListe);
$pruef('der Weg von der Anfrage bis zum Geld steht in der Liste',
  !$fehltInListe, implode(' ', $fehltInListe));

echo PHP_EOL, '— Übersetzungen —', PHP_EOL;
$ohneUebersetzung = [];
foreach (PERM_MODULES as $modul => $pfade) {
  foreach (array_merge(['inav_' . $modul, 'help_' . $modul],
           array_map(static fn(string $n): string => 'help_' . $modul . $n, HILFE_ABSAETZE)) as $k) {
    if (t($k) === $k) continue;                       // gibt es auf Deutsch nicht, schon gemeldet
    foreach (LANGS as $lang => $unused) {
      if ($lang === 'de') continue;
      if (!row('SELECT value FROM translations WHERE lang = ? AND tkey = ?', [$lang, $k])) {
        $ohneUebersetzung[] = "$lang/$k";
      }
    }
  }
}
foreach (array_column(HELP_TASKS, 1) as $k) {
  foreach (LANGS as $lang => $unused) {
    if ($lang === 'de') continue;
    if (!row('SELECT value FROM translations WHERE lang = ? AND tkey = ?', [$lang, $k])) {
      $ohneUebersetzung[] = "$lang/$k";
    }
  }
}
$pruef('jeder Hilfetext steht in allen Sprachen', !$ohneUebersetzung,
  count($ohneUebersetzung) . ' Lücken: ' . implode(' ', array_slice($ohneUebersetzung, 0, 6)));

echo PHP_EOL, $fehler === 0 ? 'Alles grün.' : "$fehler Punkte offen.", PHP_EOL;
exit($fehler === 0 ? 0 : 1);
