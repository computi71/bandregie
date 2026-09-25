<?php
declare(strict_types=1);

/**
 * Vertragsbausteine (#359)
 *
 * Ein Gastspielvertrag besteht aus Punkten, von denen bei jedem Auftritt
 * andere passen: Mal bringt die Band die Anlage mit, mal steht sie da; mal
 * gibt es Hotel, mal fährt man nachts heim. Der Mustervertrag löst das mit
 * „Unzutreffende Punkte sind zu streichen" — wer das im Textfeld macht,
 * streicht früher oder später den falschen Absatz.
 *
 * Deshalb liegt jeder Punkt als eigener Baustein in der Datenbank, und der
 * Vertrag setzt sich aus den angehakten zusammen. Die Paragraphen- und
 * Buchstabenzählung entsteht dabei — sonst hinterlässt ein abgewählter Punkt
 * eine Lücke in der Nummerierung, und genau darauf schaut ein Jurist zuerst.
 *
 * Die Bausteine gehören der Band: Sie kann jeden Wortlaut ändern. Was hier
 * steht, ist der Satz, mit dem eine neue Anlage startet.
 *
 * ACHTUNG, und das ist keine Floskel: Das ist eine Schreibhilfe, kein
 * Rechtsrat. Drei Stellen sind beim Formulieren aufgefallen und stehen
 * deshalb als Hinweis am Baustein:
 *
 *  - Die Künstlersozialabgabe schuldet der Veranstalter. Sie auf die Band
 *    abzuwälzen ginge nicht, deshalb gibt es dazu keine Gegenvariante.
 *  - Eine pauschale Vertragsstrafe trägt in einem Formular, das man immer
 *    wieder verschickt, im Zweifel nicht.
 *  - Ohne eigene Klausel gibt es bei höherer Gewalt keine Gage. Wer etwas
 *    anderes will, muss es hineinschreiben.
 */

/**
 * Die Abschnitte in der Reihenfolge, in der sie im Vertrag stehen.
 *
 * `num` sagt, ob der Abschnitt einen Paragraphen bekommt: Kopf und
 * Unterschriftsfeld sind Rahmen, keine Regelung.
 */
const CONTRACT_BLOCK_GROUPS = [
  'kopf'         => ['num' => false, 'title' => ''],
  'rahmen'       => ['num' => true,  'title' => 'cb_g_rahmen'],
  'gage'         => ['num' => true,  'title' => 'cb_g_gage'],
  'veranstalter' => ['num' => true,  'title' => 'cb_g_veranstalter'],
  'kuenstler'    => ['num' => true,  'title' => 'cb_g_kuenstler'],
  'schluss'      => ['num' => true,  'title' => 'cb_g_schluss'],
  'fuss'         => ['num' => false, 'title' => ''],
];


/**
 * Der mitgelieferte Bausteinsatz einer Sprache (#360).
 *
 * Je Sprache eine Datei unter app/bausteine/. Das sind keine Übersetzungen
 * voneinander: Ein Gastspielvertrag folgt dem Recht des Landes, in dem
 * gespielt wird. Der französische Satz ist deshalb ein Abtretungsvertrag und
 * kein Engagement, der niederländische kennt die Gageverklaring, der
 * italienische die Agibilità — Dinge, die es nebenan nicht gibt.
 *
 * Gibt es für eine Sprache keinen Satz, gilt der deutsche. Das ist die
 * ehrlichere Antwort als ein leerer Vertrag: Die Band sieht, was dasteht, und
 * kann es überschreiben.
 */
function contract_block_seed_set(string $lang): array {
  $datei = __DIR__ . '/bausteine/' . $lang . '.php';
  if (!preg_match('~^[a-z]{2}$~', $lang) || !is_file($datei)) {
    $datei = __DIR__ . '/bausteine/de.php';
  }
  return require $datei;
}

/**
 * Alle Bausteine in Vertragsreihenfolge.
 *
 * Innerhalb eines Abschnitts entscheidet `sort`; bei gleichem Wert die id,
 * damit ein selbst angelegter Baustein nicht bei jedem Aufruf woanders steht.
 */
function contract_blocks(bool $auchStille = false): array {
  $wo = $auchStille ? '' : 'WHERE active = 1';
  $satz = rows("SELECT * FROM contract_blocks $wo ORDER BY sort, id");
  $rang = array_flip(array_keys(CONTRACT_BLOCK_GROUPS));
  usort($satz, static fn(array $a, array $b): int =>
    ($rang[$a['gruppe']] ?? 99) <=> ($rang[$b['gruppe']] ?? 99)
      ?: (int) $a['sort'] <=> (int) $b['sort']
      ?: (int) $a['id'] <=> (int) $b['id']);
  return $satz;
}

/**
 * Die Häkchenliste eines Vertrages: alles Angebotene, dazu das, was dieser
 * Vertrag benutzt.
 *
 * Ein stillgelegter Baustein steht sonst im Wortlaut, ohne dass die Liste
 * darüber ihn kennt — und wer ihn loswerden will, findet kein Häkchen dafür.
 */
function contract_blocks_fuer_vertrag(array $blockIds): array {
  $benutzt = array_flip($blockIds);
  return array_values(array_filter(contract_blocks(true),
    static fn(array $b): bool => (bool) $b['active'] || isset($benutzt[(int) $b['id']])));
}

/** Welche Bausteine sind bei diesem Vertrag angehakt? */
function contract_block_ids(int $contractId): array {
  return array_map('intval', array_column(
    rows('SELECT block_id FROM contract_block_use WHERE contract_id = ?', [$contractId]), 'block_id'));
}

/** Womit ein neuer Vertrag anfängt. */
function contract_blocks_default(): array {
  $ids = [];
  foreach (contract_blocks() as $b) {
    if ($b['default_on'] || $b['fest']) $ids[] = (int) $b['id'];
  }
  return $ids;
}

/**
 * Die Auswahl eines Vertrages festhalten.
 *
 * Feste Bausteine kommen immer dazu, abgewählte fliegen raus — was das
 * Formular schickt, ist ein Vorschlag, keine Anweisung: Ein Häkchen, das im
 * HTML fehlt, darf den Vertragskopf nicht mitnehmen.
 */
function contract_blocks_set(int $contractId, array $ids): void {
  $erlaubt = [];
  $wahlBelegt = [];
  // Auch die stillgelegten, damit ein Vertrag einen behalten kann, den er
  // schon benutzt. Angehakt werden muss er trotzdem — neu dazu kommt ein
  // stillgelegter Punkt nirgends, weil er in keiner Liste mehr angeboten wird.
  foreach (contract_blocks(true) as $b) {
    $id = (int) $b['id'];
    if ($b['fest']) { $erlaubt[$id] = true; continue; }
    if (!in_array($id, $ids, true)) continue;
    // Bei einem Entweder-oder gewinnt der erste — zwei Gagenregelungen in
    // einem Vertrag wären ein Versehen, kein Wunsch.
    $wahl = (string) $b['wahl'];
    if ($wahl !== '') {
      if (isset($wahlBelegt[$wahl])) continue;
      $wahlBelegt[$wahl] = true;
    }
    $erlaubt[$id] = true;
  }
  q('DELETE FROM contract_block_use WHERE contract_id = ?', [$contractId]);
  foreach (array_keys($erlaubt) as $id) {
    q('INSERT INTO contract_block_use (contract_id, block_id) VALUES (?, ?)', [$contractId, $id]);
  }
}

/**
 * Die Marke eines Punktes innerhalb eines Paragraphen: a), b), c) …
 *
 * Über z hinaus wird gezählt statt weiterbuchstabiert. Das passiert nur, wenn
 * eine Band sich 27 Punkte in einen Abschnitt schreibt — aber dann soll dort
 * nicht „{) " stehen.
 */
function contract_block_marker(int $i): string {
  return $i < 26 ? chr(97 + $i) . ') ' : ($i + 1) . ') ';
}

/**
 * Aus den angehakten Bausteinen wird der Wortlaut.
 *
 * Paragraphen und Buchstaben entstehen hier und stehen nicht im Baustein:
 * Sonst hinterlässt jeder abgewählte Punkt eine Lücke in der Zählung, und ein
 * Vertrag, der von § 2 auf § 4 springt, sieht nach Schlamperei aus.
 */
function contract_compose(array $vertrag, array $blockIds): string {
  return strtr(contract_blocks_rohtext($blockIds), contract_values($vertrag));
}

/**
 * Derselbe Text, aber mit den Platzhaltern in geschweiften Klammern.
 *
 * So steht er in den Einstellungen: Dort gibt es keinen Termin, aus dem sich
 * ein Ort oder eine Gage einsetzen ließe, und eine Vorlage voller Punktreihen
 * ließe nicht erkennen, wo später etwas hinkommt.
 */
function contract_blocks_rohtext(array $blockIds): string {
  $gewaehlt = array_flip($blockIds);
  // Auch die stillgelegten: Wer einen Punkt abschaltet, nimmt ihn aus der
  // Auswahl für neue Verträge — er zieht ihn nicht aus den Verträgen heraus,
  // die ihn schon angehakt haben. Genau das verspricht der Hinweis am
  // Schalter, und ein Entwurf, der beim nächsten Neubilden still eine Klausel
  // verliert, bricht dieses Versprechen.
  $alle = contract_blocks(true);
  $teile = [];
  $paragraf = 0;

  foreach (CONTRACT_BLOCK_GROUPS as $gruppe => $g) {
    $drin = [];
    foreach ($alle as $b) {
      if ($b['gruppe'] !== $gruppe) continue;
      if (!$b['fest'] && !isset($gewaehlt[(int) $b['id']])) continue;
      $drin[] = (string) $b['body'];
    }
    if (!$drin) continue;

    if (!$g['num']) { foreach ($drin as $text) $teile[] = $text; continue; }

    $paragraf++;
    $punkte = [];
    // Ein einzelner Punkt braucht kein „a)" — das sieht nach abgeschnitten aus.
    // Bei mehreren rücken die Folgezeilen unter den Buchstaben ein, sonst
    // steht ein mehrzeiliger Punkt am Rand und sieht aus wie ein neuer.
    foreach ($drin as $i => $text) {
      $punkte[] = count($drin) > 1
        ? contract_block_marker($i) . str_replace("\n", "\n   ", $text)
        : $text;
    }
    // Die Überschrift kommt in der Sprache der Band, nicht in der des gerade
    // Angemeldeten. Sonst bekäme derselbe Bausteinsatz je nachdem, wer den
    // Vertrag anlegt, andere Paragraphenüberschriften — und der Veranstalter
    // sähe an einem Blatt, wer in der Band welche Sprache eingestellt hat.
    $teile[] = '§ ' . $paragraf . ' ' . push_t(default_lang(), $g['title'])
      . "\n" . implode("\n", $punkte);
  }

  return implode("\n\n", $teile);
}

/** Setzt sich der Vertrag aus Bausteinen zusammen — oder aus eigenem Text? */
function contract_blocks_aktiv(): bool {
  return trim((string) setting('contract_text')) === '';
}

/**
 * Den mitgelieferten Satz in die Datenbank bringen.
 *
 * Läuft bei jedem Schemalauf, ändert aber nie einen Wortlaut: Was die Band an
 * einem Baustein geschrieben hat, gehört ihr. Neu dazukommende Bausteine
 * landen abgewählt in der Liste — ein Update darf einem laufenden Vertrag
 * keine Klausel unterschieben.
 */
function contract_blocks_seed(): void {
  // Welcher Satz gehört dieser Anlage? Einmal entschieden und dann
  // festgehalten: Eine Band, die später die Oberflächensprache umstellt, will
  // keinen zweiten Satz Klauseln aus einem anderen Rechtsraum in ihre Liste
  // geschoben bekommen (#360).
  $sprache = (string) setting('contract_blocks_lang');
  if ($sprache === '') {
    $sprache = default_lang();
    set_setting('contract_blocks_lang', $sprache);
  }

  $sort = 0;
  foreach (contract_block_seed_set($sprache) as $b) {
    $sort += 10;
    // label, body, hinweis und default_on stehen bewusst nicht im UPDATE-Teil:
    // Sie gehören ab dem ersten Einspielen der Band. Nur Einordnung und
    // Entweder-oder zieht ein Update nach, weil die zur Mechanik gehören.
    q('INSERT INTO contract_blocks (bkey, gruppe, sort, wahl, label, body, hinweis, fest, default_on)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE gruppe = VALUES(gruppe), sort = VALUES(sort),
                               wahl = VALUES(wahl), fest = VALUES(fest)', [
      $b['bkey'], $b['gruppe'], $sort, $b['wahl'] ?? '',
      $b['label'], $b['body'], $b['hinweis'] ?? '',
      (int) ($b['fest'] ?? 0), (int) ($b['an'] ?? 0),
    ]);
  }
}
