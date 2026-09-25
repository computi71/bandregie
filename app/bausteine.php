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
 * Der mitgelieferte Satz.
 *
 * Felder: gruppe, wahl, fest, an (Vorauswahl), label, body, hinweis.
 *
 * `wahl` bindet Bausteine zu einem Entweder-oder: Wer die Prozentbeteiligung
 * anhakt, verliert die Festgage. Zwei Gagenregelungen in einem Vertrag sind
 * kein Wunsch, sondern ein Versehen.
 *
 * `fest` heißt: steht immer drin und lässt sich nicht abwählen. Das sind der
 * Kopf, der Vertragsgegenstand und das Unterschriftsfeld — ein Vertrag ohne
 * sie wäre keiner.
 *
 * Die Unterstriche sind Absicht: Was die Anwendung nicht weiß, bleibt als
 * Lücke stehen und wird von Hand ausgefüllt. Eine erfundene Zahl wäre
 * schlimmer als eine sichtbare Lücke.
 */
const CONTRACT_BLOCK_SEED = [

  // ---------- Kopf ----------
  ['bkey' => 'kopf', 'gruppe' => 'kopf', 'fest' => 1, 'an' => 1,
   'label' => 'Vertragskopf',
   'body' => "GASTSPIELVERTRAG\n\nzwischen\n{veranstalter}\n{veranstalter_anschrift}\n— nachfolgend Veranstalter —\n\nund\n{band}\n— nachfolgend Künstler —"],

  // ---------- § Gegenstand des Vertrages ----------
  ['bkey' => 'gegenstand', 'gruppe' => 'rahmen', 'fest' => 1, 'an' => 1,
   'label' => 'Gegenstand',
   'body' => "Der Veranstalter engagiert den Künstler für folgendes Gastspiel:\nVeranstaltungsort: {ort}\nVeranstaltungstag: {datum}\nSpielzeit: {spielzeit}"],

  ['bkey' => 'aufbau', 'gruppe' => 'rahmen', 'an' => 1,
   'label' => 'Aufbau und Soundcheck',
   'body' => 'Der Veranstaltungsraum steht dem Künstler ab {einlass} für Aufbau und Soundcheck zur Verfügung. Bis zum Einlass hat der Künstler ungestörten Zugang zur Bühne.'],

  ['bkey' => 'spieldauer', 'gruppe' => 'rahmen',
   'label' => 'Sets und Pausen',
   'body' => 'Der Künstler spielt ____ Sets von je etwa ____ Minuten mit einer Pause von ____ Minuten. Eine Zugabe von bis zu ____ Minuten ist vereinbart.',
   'hinweis' => 'Nur nötig, wenn die Veranstaltung in Sets geteilt ist. Sonst reicht die Spielzeit im Gegenstand.'],

  // ---------- § Gage und Kosten ----------
  ['bkey' => 'gage_fix', 'gruppe' => 'gage', 'wahl' => 'gage_art', 'an' => 1,
   'label' => 'Festgage',
   'body' => 'Der Veranstalter zahlt dem Künstler für das Gastspiel eine Festgage von {gage}. Damit sind alle Leistungen des Künstlers nach diesem Vertrag abgegolten, soweit nachstehend nichts anderes vereinbart ist.'],

  ['bkey' => 'gage_anteil', 'gruppe' => 'gage', 'wahl' => 'gage_art',
   'label' => 'Beteiligung an den Eintrittsgeldern',
   'body' => 'Der Veranstalter zahlt dem Künstler einen Anteil von ____ % der Eintrittsgelder; maßgeblich sind die Einnahmen nach Abzug der Umsatzsteuer. Der Eintritt beträgt ____ Euro, ermäßigt ____ Euro. Der Veranstalter legt dem Künstler unmittelbar nach der Veranstaltung eine Abrechnung der verkauften Karten vor.'],

  ['bkey' => 'gage_mix', 'gruppe' => 'gage', 'wahl' => 'gage_art',
   'label' => 'Mindestgage plus Beteiligung',
   'body' => 'Der Veranstalter zahlt dem Künstler eine Mindestgage von {gage}. Übersteigt ein Anteil von ____ % der Eintrittsgelder diesen Betrag, erhält der Künstler statt der Mindestgage diesen Anteil. Die Abrechnung der verkauften Karten legt der Veranstalter unmittelbar nach der Veranstaltung vor.'],

  ['bkey' => 'zahlung_bar', 'gruppe' => 'gage', 'wahl' => 'zahlung', 'an' => 1,
   'label' => 'Zahlung: bar am Abend',
   'body' => 'Die Gage wird im Anschluss an den Auftritt in bar gegen Quittung ausgezahlt.'],

  ['bkey' => 'zahlung_rechnung', 'gruppe' => 'gage', 'wahl' => 'zahlung',
   'label' => 'Zahlung: auf Rechnung',
   'body' => 'Der Künstler stellt die Gage nach dem Auftritt in Rechnung. Der Betrag ist innerhalb von 14 Tagen nach Rechnungsdatum ohne Abzug fällig.'],

  ['bkey' => 'abgaben', 'gruppe' => 'gage', 'fest' => 1, 'an' => 1,
   'label' => 'GEMA und Künstlersozialabgabe',
   'body' => 'Gebühren für die öffentliche Wiedergabe von Musik sowie die Künstlersozialabgabe trägt der Veranstalter. Er meldet die Veranstaltung fristgerecht an.',
   'hinweis' => 'Steht fest im Vertrag und lässt sich nicht abwählen: Die Künstlersozialabgabe schuldet nach dem Künstlersozialversicherungsgesetz der Veranstalter. Eine Klausel, die sie der Band aufbürdet, wäre unwirksam — deshalb gibt es dazu keine Gegenvariante.'],

  ['bkey' => 'steuer_auslaender', 'gruppe' => 'gage',
   'label' => 'Steuerabzug bei ausländischen Künstlern',
   'body' => 'Ist der Künstler im Inland nicht steuerlich ansässig, behält der Veranstalter die gesetzliche Abzugsteuer ein und führt sie ab. Den Abzug weist er in der Abrechnung aus und bescheinigt ihn dem Künstler.',
   'hinweis' => 'Nur bei Auftritten über die Grenze nötig — und dann für den Veranstalter Pflicht, nicht Kür.'],

  ['bkey' => 'pa_zuschlag', 'gruppe' => 'gage',
   'label' => 'Vergütung für mitgebrachte Anlage',
   'body' => 'Stellt der Künstler Beschallungs- und Lichtanlage, erhält er dafür zusätzlich ____ Euro.'],

  ['bkey' => 'fahrtkosten', 'gruppe' => 'gage',
   'label' => 'Fahrtkosten',
   'body' => 'Der Veranstalter erstattet dem Künstler die Fahrtkosten mit ____ Euro je gefahrenem Kilometer, gerechnet vom Proberaum des Künstlers zum Veranstaltungsort und zurück.'],

  ['bkey' => 'ausfall_veranstalter', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Absage durch den Veranstalter',
   'body' => 'Sagt der Veranstalter die Veranstaltung ab oder unterbleibt der Auftritt aus einem Grund, den der Veranstalter zu vertreten hat, bleibt die vereinbarte Gage in voller Höhe geschuldet. Aufwendungen, die der Künstler dadurch erspart, werden angerechnet.'],

  ['bkey' => 'ausfall_kuenstler', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Absage durch den Künstler',
   'body' => 'Kann der Künstler aus einem Grund, den er zu vertreten hat, nicht auftreten, entfällt der Anspruch auf die Gage. Er unterrichtet den Veranstalter unverzüglich und bemüht sich um gleichwertigen Ersatz.'],

  ['bkey' => 'krankheit', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Krankheit',
   'body' => 'Erkrankt ein Mitglied des Künstlers, unterrichtet der Künstler den Veranstalter unverzüglich. Ist der Auftritt dadurch nicht möglich, entfallen Auftrittspflicht und Gageanspruch; weitergehende Ansprüche bestehen beiderseits nicht.'],

  ['bkey' => 'hoehere_gewalt', 'gruppe' => 'gage', 'an' => 1,
   'label' => 'Höhere Gewalt',
   'body' => 'Wird die Veranstaltung durch höhere Gewalt unmöglich — etwa durch Naturereignisse, behördliche Anordnung oder Katastrophen —, entfallen die Pflichten beider Seiten ohne Entschädigung. Bereits geleistete Zahlungen werden zurückgewährt; Auslagen, die sich nicht mehr abwenden ließen, trägt jede Seite selbst.',
   'hinweis' => 'Ohne eine eigene Klausel gilt die gesetzliche Regel, und die gibt dem Künstler bei einer behördlich abgesagten Veranstaltung in aller Regel keine Gage. Wer etwas anderes will, muss es hier hineinschreiben.'],

  ['bkey' => 'vertragsstrafe', 'gruppe' => 'gage',
   'label' => 'Vertragsstrafe',
   'body' => 'Löst eine Partei den Vertrag ohne Grund oder verletzt sie ihn schuldhaft, zahlt sie der anderen Partei eine Vertragsstrafe in Höhe der vereinbarten Gage. Weitergehende Schadensersatzansprüche bleiben unberührt; die Vertragsstrafe wird darauf angerechnet.',
   'hinweis' => 'Vorsicht: Eine Vertragsstrafe, die in jedem Vertrag unverändert mitgeschickt wird, ist im Streit oft nicht durchsetzbar — sie trägt im Zweifel nur, wenn beide Seiten sie im Einzelfall wirklich ausgehandelt haben. Die beiden Absageregelungen darüber kommen ohne sie aus.'],

  // ---------- § Pflichten des Veranstalters ----------
  ['bkey' => 'technik_veranstalter', 'gruppe' => 'veranstalter', 'wahl' => 'technik', 'an' => 1,
   'label' => 'Technik: Veranstalter stellt Anlage',
   'body' => 'Der Veranstalter stellt eine bespielbare Bühne mit ausreichender Stromversorgung sowie Beschallungs- und Lichtanlage in einem der Örtlichkeit angemessenen Umfang. Während Aufbau, Soundcheck und Auftritt ist eine Person erreichbar, die die Anlage bedienen kann.'],

  ['bkey' => 'technik_band', 'gruppe' => 'veranstalter', 'wahl' => 'technik',
   'label' => 'Technik: Künstler bringt Anlage mit',
   'body' => 'Der Veranstalter stellt Raum und Stromversorgung. Der Künstler bringt Beschallungs- und Lichtanlage in einem der Örtlichkeit angemessenen Umfang mit. Der Veranstalter teilt dem Künstler spätestens vier Wochen vor dem Gastspiel die erwartete Besucherzahl mit und ermöglicht auf Wunsch eine Besichtigung.'],

  ['bkey' => 'technik_info', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Angaben zur Anlage vorab',
   'body' => 'Der Veranstalter teilt dem Künstler spätestens eine Woche vor dem Gastspiel mit, welche Anlage vor Ort steht und wer sie bedient.'],

  ['bkey' => 'openair', 'gruppe' => 'veranstalter',
   'label' => 'Bühne unter freiem Himmel',
   'body' => 'Findet die Veranstaltung unter freiem Himmel statt, sind Bühne und Mischplatz überdacht und gegen Witterung geschützt. Der Veranstalter sorgt für einen ebenen, tragfähigen Untergrund.'],

  ['bkey' => 'rider', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Bühnenanweisung ist Vertragsbestandteil',
   'body' => 'Die Bühnenanweisung des Künstlers ist Bestandteil dieses Vertrages. Abweichungen stimmt der Veranstalter rechtzeitig mit dem Künstler ab.'],

  ['bkey' => 'haftung', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Haftung und Sicherheit',
   'body' => 'Für die Dauer des Aufenthalts am Veranstaltungsort trägt der Veranstalter die Verantwortung für die Sicherheit des Künstlers und seiner Helfer sowie für die eingebrachten Anlagen und Instrumente. Für Schäden, die der Künstler selbst verursacht, haftet dieser.'],

  ['bkey' => 'garderobe', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Garderobe',
   'body' => 'Der Veranstalter stellt dem Künstler einen abschließbaren, beheizbaren Raum als Garderobe zur Verfügung.'],

  ['bkey' => 'getraenke', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Getränke',
   'body' => 'Der Veranstalter stellt dem Künstler und seinen Helfern während Aufbau, Soundcheck und Auftritt Getränke in angemessenem Umfang kostenlos zur Verfügung.'],

  ['bkey' => 'essen', 'gruppe' => 'veranstalter',
   'label' => 'Warme Mahlzeit',
   'body' => 'Der Veranstalter stellt dem Künstler und seinen Helfern je Veranstaltungstag eine warme Mahlzeit. Anzahl: ____, davon vegetarisch: ____.'],

  ['bkey' => 'uebernachtung', 'gruppe' => 'veranstalter',
   'label' => 'Übernachtung',
   'body' => 'Der Veranstalter trägt die Kosten für Übernachtung und Frühstück von ____ Personen in ____ Einzel- und ____ Doppelzimmern. Das Haus benennt er dem Künstler spätestens eine Woche vor dem Gastspiel.'],

  ['bkey' => 'helfer', 'gruppe' => 'veranstalter',
   'label' => 'Helfer beim Auf- und Abbau',
   'body' => 'Der Veranstalter stellt dem Künstler für Auf- und Abbau jeweils ____ Helfer zur Verfügung.'],

  ['bkey' => 'parken', 'gruppe' => 'veranstalter',
   'label' => 'Anfahrt und Stellplätze',
   'body' => 'Der Veranstalter sorgt dafür, dass der Künstler unmittelbar an der Bühne be- und entladen kann, und weist ihm für die Dauer der Veranstaltung Stellplätze zu.'],

  ['bkey' => 'werbung', 'gruppe' => 'veranstalter',
   'label' => 'Werbung für die Veranstaltung',
   'body' => 'Der Veranstalter bewirbt die Veranstaltung in angemessenem Umfang. Er nennt den Künstler auf allen Ankündigungen in der vom Künstler mitgeteilten Schreibweise.'],

  ['bkey' => 'gaesteliste', 'gruppe' => 'veranstalter',
   'label' => 'Gästeliste',
   'body' => 'Der Künstler darf eine Gästeliste führen. Sie umfasst zwei freie Eintritte je Mitglied und wird dem Veranstalter vor dem Einlass übergeben.'],

  ['bkey' => 'aufnahmen', 'gruppe' => 'veranstalter', 'an' => 1,
   'label' => 'Ton- und Bildaufnahmen',
   'body' => 'Ton-, Bild-, Film- und Videoaufnahmen von Soundcheck und Auftritt bedürfen über den privaten Gebrauch der Gäste hinaus der vorherigen Zustimmung des Künstlers. Aufnahmen, die der Veranstalter für eigene Werbung verwenden will, bedürfen einer gesonderten Vereinbarung.'],

  // ---------- § Pflichten und Rechte des Künstlers ----------
  ['bkey' => 'zeiten', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Zeiten einhalten',
   'body' => 'Der Künstler hält die vereinbarten Zeiten für Aufbau, Soundcheck und Auftritt ein.'],

  ['bkey' => 'programm', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Freiheit des Programms',
   'body' => 'Der Künstler gestaltet sein Programm frei. Besondere Wünsche des Veranstalters bedürfen einer gesonderten Vereinbarung.'],

  ['bkey' => 'ersatz', 'gruppe' => 'kuenstler',
   'label' => 'Ersatz für einzelne Mitglieder',
   'body' => 'Fällt ein einzelnes Mitglied aus, darf der Künstler es durch eine gleichwertige Besetzung ersetzen, ohne dass dem Veranstalter daraus Ansprüche erwachsen.'],

  ['bkey' => 'werbematerial', 'gruppe' => 'kuenstler', 'an' => 1,
   'label' => 'Werbematerial',
   'body' => 'Der Künstler stellt dem Veranstalter Bandinformationen, Pressetext und Fotos zur Ankündigung kostenlos zur Verfügung. Die Rechte daran bleiben beim Künstler; die Nutzung ist auf die Bewerbung dieser Veranstaltung beschränkt.'],

  ['bkey' => 'technik_einwand', 'gruppe' => 'kuenstler',
   'label' => 'Kein Einwand wegen der Technik',
   'body' => 'Hat der Veranstalter die technische Ausstattung nach diesem Vertrag zu stellen, kann er sich nicht darauf berufen, der Künstler sei technisch unzureichend ausgestattet.'],

  // ---------- § Schlussbestimmungen ----------
  ['bkey' => 'salvatorisch', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Salvatorische Klausel',
   'body' => 'Ist eine Bestimmung dieses Vertrages unwirksam oder undurchführbar, bleiben die übrigen Bestimmungen wirksam. Die Parteien ersetzen die unwirksame Bestimmung durch eine zulässige, die dem wirtschaftlich Gewollten am nächsten kommt.'],

  ['bkey' => 'schriftform', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Keine mündlichen Nebenabreden',
   'body' => 'Mündliche Nebenabreden bestehen nicht. Änderungen und Ergänzungen dieses Vertrages bedürfen der Textform.',
   'hinweis' => 'Textform heißt: eine E-Mail genügt. Wer stattdessen Schriftform verlangt, braucht für jede Kleinigkeit eine unterschriebene Papierseite.'],

  ['bkey' => 'kein_arbeitsverhaeltnis', 'gruppe' => 'schluss', 'an' => 1,
   'label' => 'Kein Arbeitsverhältnis',
   'body' => 'Der Künstler wird selbstständig tätig. Ein Arbeitsverhältnis wird durch diesen Vertrag nicht begründet.'],

  ['bkey' => 'gerichtsstand', 'gruppe' => 'schluss',
   'label' => 'Recht und Gerichtsstand',
   'body' => 'Es gilt deutsches Recht. Sind beide Parteien Kaufleute oder juristische Personen, ist Gerichtsstand ____.',
   'hinweis' => 'Ein fester Gerichtsstand lässt sich nur unter Kaufleuten wirksam vereinbaren. Gegenüber Privatleuten und Vereinen bleibt es beim gesetzlichen Gerichtsstand, gleich was im Vertrag steht.'],

  // ---------- Unterschriften ----------
  ['bkey' => 'unterschrift', 'gruppe' => 'fuss', 'fest' => 1, 'an' => 1,
   'label' => 'Unterschriftsfeld',
   'body' => "Ort, Datum: ......................        Ort, Datum: {heute}\n\n\n__________________________                __________________________\nDer Veranstalter                          Der Künstler"],
];

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
  foreach (contract_blocks() as $b) {
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
  $alle = contract_blocks();
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
    foreach ($drin as $i => $text) {
      $punkte[] = count($drin) > 1 ? contract_block_marker($i) . $text : $text;
    }
    $teile[] = '§ ' . $paragraf . ' ' . t($g['title']) . "\n" . implode("\n", $punkte);
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
  $sort = 0;
  foreach (CONTRACT_BLOCK_SEED as $b) {
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
