<?php
declare(strict_types=1);

// Demodaten: füllen eine frische Installation mit einer erfundenen Band, damit
// man alle Funktionen ausprobieren kann. Jede angelegte Zeile wird in demo_rows
// vermerkt — beim Entfernen wird ausschließlich das wieder gelöscht, echte
// Daten bleiben unangetastet.

// Die Demo legt Daueraufträge an und lässt sie gleich buchen. Selbst geholt,
// damit die Datei nicht davon abhängt, wer sie einbindet.
require_once __DIR__ . '/dauerauftrag.php';

/**
 * Ist die Installation schon in Gebrauch? Sobald ein Mitglied oder ein Termin
 * darin steht, den nicht die Demo angelegt hat, gehört sie einer Band — und
 * dann darf man ihr nicht mehr mit einem Klick eine zweite hineinschütten.
 *
 * Das erste Admin-Konto zählt nicht: das legt die Installation selbst an.
 */
function demo_in_real_use(): bool {
  foreach (['users', 'events'] as $table) {
    $extra = $table === 'users' ? " AND t.role <> 'admin'" : '';
    $row = row("SELECT COUNT(*) AS n FROM `$table` t
                WHERE NOT EXISTS (SELECT 1 FROM demo_rows d WHERE d.table_name = ? AND d.row_id = t.id)$extra",
               [$table]);
    if ((int) ($row['n'] ?? 0) > 0) return true;
  }
  return false;
}

function demo_installed(): bool {
  return (int) (row('SELECT COUNT(*) AS n FROM demo_rows')['n'] ?? 0) > 0;
}

/** Merkt sich eine angelegte Zeile und gibt ihre ID zurück. */
function demo_track(string $table, int $id): int {
  q('INSERT INTO demo_rows (table_name, row_id) VALUES (?,?)', [$table, $id]);
  return $id;
}

/** Legt eine Zeile an und vermerkt sie als Demodatum. */
function demo_insert(string $table, array $data): int {
  global $db;
  $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
  $marks = implode(',', array_fill(0, count($data), '?'));
  q("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($data));
  return demo_track($table, (int) $db->lastInsertId());
}

function demo_install(): void {
  global $db;
  if (demo_installed()) return;
  // Alles oder nichts — bricht etwas ab, bleibt keine halbe Demoband zurück.
  $db->beginTransaction();
  try {
    demo_install_rows();
    $db->commit();
  } catch (Throwable $e) {
    $db->rollBack();
    throw $e;
  }
}

function demo_install_rows(): void {
  $d = fn(string $mod): string => date('Y-m-d', strtotime($mod));

  // --- Mitglieder. Zufallspasswörter, aber gemerkt: ohne sie sieht man die
  // Demo nur als Admin und nie als Mitglied oder Aushilfe — also gerade das
  // nicht, was die Rechte ausmachen. Sie landen einmalig in einer Datei
  // außerhalb des Webroots.
  $logins = [];
  $pw = function (string $email) use (&$logins): string {
    $plain = bin2hex(random_bytes(6));
    $logins[$email] = $plain;
    return password_hash($plain, PASSWORD_DEFAULT);
  };
  $members = [];
  foreach ([
    ['Lisa', 'Berg', 'Lisa', 'lisa@example.com', 'Vocals'],
    ['Tom', 'Krause', 'Tommy', 'tom@example.com', 'Guitar'],
    ['Ines', 'Adler', '', 'ines@example.com', 'Bass'],
    ['Ben', 'Rauch', 'Benny', 'ben@example.com', 'Drums'],
  ] as [$first, $last, $stage, $mail, $instr]) {
    $members[] = demo_insert('users', [
      'name' => "$first $last", 'first_name' => $first, 'last_name' => $last,
      'stage_name' => $stage, 'email' => $mail,
      'password_hash' => $pw($mail), 'role' => 'member', 'instrument' => $instr,
      // Kein erzwungener Wechsel: das Passwort steht in der Datei und soll
      // zum Ausprobieren taugen. Mit der Demo verschwindet das Konto wieder.
      'must_change_pw' => 0,
    ]);
    // Ohne Rechte könnte die Demoband nichts öffnen
    perm_apply_template(end($members), 'member');
  }

  // --- Veranstaltungsorte
  $venue1 = demo_insert('venues', [
    'name' => 'Sampleton Town Hall', 'city' => 'Sampleton',
    'address' => "3 Hall Lane
12345 Sampleton",
    'contact_name' => 'Ms Sommer', 'contact_email' => 'buehne@example.com',
    'contact_phone' => '0123 456789',
    'notes' => 'Stage 8 × 6 m, power 2 × 32 A, parking right behind the hall.',
  ]);
  $venue2 = demo_insert('venues', [
    'name' => 'Exampleton Barn', 'city' => 'Exampleton',
    'address' => "1 Village Road
12346 Exampleton",
    'contact_name' => 'Mr Winter', 'contact_email' => 'scheune@example.com',
    'contact_phone' => '0123 987654',
    'notes' => 'Small stage, house PA available. Access along the field track.',
  ]);

  // --- Songs (frei erfundene Titel, damit keine echten Rechte berührt werden)
  // Ein paar bekommen frei erfundene Beispieltexte und einen Notizzettel — kein
  // fremder Songtext, alles Platzhalter —, damit Teleprompter, Abschnitts-Farben
  // und die Akkord-Ansicht (feste Zeichenbreite) sofort etwas zeigen. Die
  // Notdocs stehen bündig links, damit die Akkorde über den Silben bleiben.
  $demoLyrics = [
    'Summer Rain' => <<<'TXT'
[Strophe]
Grey clouds gather over the town
The first drops fall on empty streets
We take the long way, umbrella down
And count the puddles under our feet

[Refrain]
Summer rain, let it pour
Wash the dust from every door
Summer rain, fall on me
This is where I want to be

[Strophe]
The neon flickers, taxis hiss
A busker plays beneath the eaves
We share a laugh, we share a wish
And dance the way the weather leaves

[Bridge]
And when it clears, the sky turns gold
We're soaked right through, but we don't mind

[Solo]

[Refrain]
Summer rain, fall on me
This is where I want to be

[Outro]
Let it pour, let it pour
TXT,
    'Neon Light' => <<<'TXT'
[Strophe]
City hums a lower key
Signs are buzzing, half asleep
We walk where no one else will be
Promises we mean to keep

[Refrain]
Neon light, neon light
Colour up the empty night
Neon light, burning slow
Show us all the way to go

[Bridge]
Turn it down, turn it low
Let the quiet have its show

[Refrain]
Neon light, burning slow
Show us all the way to go
TXT,
    'Last Train' => <<<'TXT'
[Strophe]
Platform empty, midnight cold
One more coffee, one more mile
The last train's here, the story's told
We ride it home in single file

[Refrain]
Last train, take me on
Before the night is gone
Last train, one more time
Down the old familiar line
TXT,
  ];
  $demoChords = [
    'Summer Rain' => <<<'TXT'
[Intro]
G  D  Em  C

[Strophe]
G                 D
Grey clouds gather over the town
Em                 C
The first drops fall on empty streets

[Refrain]
C         G        D
Summer rain, let it pour
C         G           D
Wash the dust from every door

[Bridge]
Em  C  G  D    (quiet, 2x)

[Solo]
G  D  Em  C    (over the verse)
TXT,
    'Neon Light' => <<<'TXT'
[Strophe]
Am        F
City hums a lower key
C         G
Signs are buzzing, half asleep

[Refrain]
F      C      G
Neon light, neon light
F      C          G
Colour up the empty night
TXT,
  ];
  $songs = [];
  foreach ([
    ['Summer Rain', 'Own composition', 'G', '128 BPM', 214, 'aktiv'],
    ['Neon Light', 'Own composition', 'Am', '140 BPM', 186, 'aktiv'],
    ['Last Train', 'Own composition', 'D', '96 BPM', 245, 'aktiv'],
    ['New Shores', 'Own composition', 'C', '132 BPM', 198, 'aktiv'],
    ['Cold Coffee', 'Own composition', 'Em', '150 BPM', 172, 'aktiv'],
    ['Between the Lines', 'Own composition', 'F', '88 BPM', 262, 'aktiv'],
    ['Tailwind', 'Own composition', 'A', '160 BPM', 205, 'in_arbeit'],
    ['Night Shift', 'Own composition', 'Bm', '118 BPM', 228, 'in_arbeit'],
    ['Old Road', 'Own composition', 'G', '104 BPM', 190, 'vorschlag'],
    ['Untitled', 'Own composition', 'C', '', 0, 'vorschlag'],
  ] as [$title, $artist, $key, $tempo, $sec, $status]) {
    $songs[] = demo_insert('songs', [
      'title' => $title, 'artist' => $artist, 'song_key' => $key,
      'tempo' => $tempo, 'duration_sec' => $sec, 'status' => $status,
      'notes' => '',
      'lyrics' => $demoLyrics[$title] ?? '',
      'chords' => $demoChords[$title] ?? '',
    ]);
  }

  // --- Setlists: eine gespielte (wird durch den vergangenen Gig fixiert)
  //     und eine für den nächsten Auftritt, mit Pause und Zugabe
  $slPast = demo_insert('setlists', ['name' => 'Summer festival — as played', 'notes' => 'Running order as agreed.']);
  $pos = 1;
  foreach (array_slice($songs, 0, 6) as $sid) {
    demo_insert('setlist_songs', ['setlist_id' => $slPast, 'song_id' => $sid, 'is_break' => 0, 'position' => $pos++]);
  }

  $slNext = demo_insert('setlists', ['name' => 'Next show', 'notes' => '']);
  $pos = 1;
  foreach (array_slice($songs, 0, 4) as $sid) {
    demo_insert('setlist_songs', ['setlist_id' => $slNext, 'song_id' => $sid, 'is_break' => 0, 'position' => $pos++]);
  }
  demo_insert('setlist_songs', ['setlist_id' => $slNext, 'song_id' => null, 'is_break' => 1, 'position' => $pos++]);
  foreach (array_slice($songs, 4, 2) as $sid) {
    demo_insert('setlist_songs', ['setlist_id' => $slNext, 'song_id' => $sid, 'is_break' => 0, 'position' => $pos++]);
  }
  demo_insert('setlist_songs', ['setlist_id' => $slNext, 'song_id' => null, 'is_break' => 2, 'position' => $pos++]);
  demo_insert('setlist_songs', ['setlist_id' => $slNext, 'song_id' => $songs[2], 'is_break' => 0, 'position' => $pos++]);

  // --- Termine: vergangener Gig (fixiert), kommender Gig, Probe, Besprechung
  $evPast = demo_insert('events', [
    'type' => 'gig', 'title' => 'Summer Festival Sampleton', 'date' => $d('-10 weeks'),
    'time' => '20:00', 'time_meet' => '17:00', 'time_end' => '23:00',
    'venue_id' => $venue1, 'location' => '', 'notes' => 'Went well, they asked for an encore.',
    'is_public' => 1, 'setlist_id' => $slPast, 'status' => 'bestaetigt',
    'fee' => '900 €', 'invoice_no' => 'R-2026-014', 'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);
  $evNext = demo_insert('events', [
    'type' => 'gig', 'title' => 'Exampleton Barn', 'date' => $d('+6 weeks'),
    'time' => '21:00', 'time_meet' => '18:00', 'time_end' => '00:30',
    'venue_id' => $venue2, 'location' => '', 'notes' => 'Bring our own PA, backline is provided.',
    'is_public' => 1, 'setlist_id' => $slNext, 'status' => 'bestaetigt',
    'fee' => '750 €', 'invoice_no' => '', 'public_title' => '', 'public_link' => '',
    'public_info' => 'Doors at 8 pm',
  ]);
  demo_insert('events', [
    'type' => 'probe', 'title' => 'Rehearsal before the show', 'date' => $d('+2 weeks'),
    'time' => '19:00', 'time_meet' => '', 'time_end' => '22:00', 'venue_id' => null,
    'location' => 'Rehearsal room', 'notes' => 'Run through the new songs.', 'is_public' => 0,
    'setlist_id' => null, 'status' => 'bestaetigt', 'fee' => '', 'invoice_no' => '',
    'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);
  demo_insert('events', [
    'type' => 'besprechung', 'title' => 'Band meeting', 'date' => $d('+3 days'),
    'time' => '19:30', 'time_meet' => '', 'time_end' => '21:00', 'venue_id' => null,
    'location' => 'Rehearsal room', 'notes' => 'Autumn planning, merch, photo session.',
    'is_public' => 0, 'setlist_id' => null, 'status' => 'bestaetigt', 'fee' => '',
    'invoice_no' => '', 'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);

  // --- Rückmeldungen und ein Kommentar am kommenden Gig
  foreach ([[0, 'yes'], [1, 'yes'], [2, 'maybe'], [3, 'no']] as [$i, $status]) {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)', [$evNext, $members[$i], $status]);
  }
  demo_insert('comments', [
    'event_id' => $evNext, 'user_id' => $members[1],
    'text' => 'I will bring the big amp, there is room in the car for two cabs.',
  ]);

  // --- Aufgaben
  demo_insert('tasks', ['title' => 'Technik-Rider an den Veranstalter schicken', 'notes' => '',
    'assigned_to' => $members[0], 'due_date' => $d('+10 days'), 'status' => 'offen']);
  demo_insert('tasks', ['title' => 'Pick new photos for the website', 'notes' => 'Preferably from the summer festival.',
    'assigned_to' => $members[2], 'due_date' => $d('+3 weeks'), 'status' => 'offen']);
  demo_insert('tasks', ['title' => 'Book the trailer in for its test', 'notes' => '',
    'assigned_to' => null, 'due_date' => $d('-1 week'), 'status' => 'erledigt']);

  // --- Abwesenheit (erzeugt die Warnung beim Termin, falls sie zusammenfällt)
  demo_insert('absences', ['user_id' => $members[3], 'date_from' => $d('+13 days'),
    'date_to' => $d('+20 days'), 'note' => 'Urlaub']);

  // --- Kasse
  $adminId = (int) (row("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")['id'] ?? 0) ?: null;
  foreach ([
    [$d('-10 weeks'), 'einnahme', 90000, 'gage', 'Summer Festival Sampleton', $evPast],
    [$d('-10 weeks'), 'ausgabe', 60000, 'ausschuettung', 'Payout summer festival', $evPast],
    [$d('-9 weeks'), 'einnahme', 4500, 'merch', 'T-shirt sales', null],
    [$d('-8 weeks'), 'ausgabe', 12000, 'equipment', 'New microphone cables', null],
    [$d('-6 weeks'), 'ausgabe', 15000, 'proberaum', 'Rehearsal room rent (quarterly)', null],
    [$d('-2 weeks'), 'ausgabe', 3200, 'verpflegung', 'Drinks for the rehearsal', null],
  ] as [$date, $type, $cents, $cat, $desc, $ev]) {
    demo_insert('finances', ['date' => $date, 'type' => $type, 'amount_cents' => $cents,
      'category' => $cat, 'description' => $desc, 'event_id' => $ev, 'member_id' => null,
      'created_by' => $adminId]);
  }

  // --- Kanalbelegung (die Inputliste im Stagerider)
  //
  // Der Port ist, was den Veranstalter angeht: da kommt das Kabel rein. Die
  // Kanalnummer ist unsere. Beim Playback zeigt „A11–A12" ein Stereopaar —
  // ein Eingang, zwei Buchsen, ungerade und gerade.
  foreach ([
    [1,  'A1',        'Kick drum', 'Large diaphragm, dynamic', ''],
    [2,  'A2',        'Snare', 'Dynamic, clip-on', ''],
    [3,  'A3',        'HiHat', 'Small diaphragm condenser', 'Phantom power +48 V'],
    [4,  'A4',        'Tom', 'Clip-on condenser', 'Phantom power +48 V'],
    [5,  'A5',        'Floor tom', 'Clip-on condenser', 'Phantom power +48 V'],
    [6,  'A6',        'Overhead left', 'Condenser', 'Phantom power +48 V'],
    [7,  'A7',        'Overhead right', 'Condenser', 'Phantom power +48 V'],
    [8,  'A8',        'Bass', 'DI box', ''],
    [9,  'A9',        'Guitar', 'Dynamic at the amp', ''],
    [10, 'A10',       'Vocals Lisa', 'Vocal microphone', ''],
    [11, 'A13',       'Vocals Tom', 'Vocal microphone', ''],
    [12, 'A11–A12',   'Playback', 'Stereo DI from the laptop', 'only on two songs'],
  ] as [$number, $patch, $name, $source, $chNotes]) {
    demo_insert('channels', ['number' => $number, 'patch' => $patch, 'name' => $name,
                             'source' => $source, 'notes' => $chNotes]);
  }

  // --- Equipment mit Fristen
  $eqTrailer = demo_insert('equipment', ['name' => 'Band trailer', 'category' => 'transport',
    'owner_id' => null, 'location' => 'Yard at the rehearsal room', 'is_standard' => 1,
    'notes' => 'Registration in the glovebox, spare wheel rear left.']);
  demo_insert('equipment_deadlines', ['equipment_id' => $eqTrailer, 'title' => 'Roadworthiness test',
    'due_date' => $d('+5 weeks'), 'interval_months' => 24, 'notes' => '']);
  demo_insert('equipment_deadlines', ['equipment_id' => $eqTrailer, 'title' => 'Insurance',
    'due_date' => $d('+4 months'), 'interval_months' => 12, 'notes' => '']);
  $eqPa = demo_insert('equipment', ['name' => 'PA system (2 tops, 2 subs)', 'category' => 'pa',
    'owner_id' => null, 'location' => 'Rehearsal room', 'is_standard' => 1,
    'notes' => 'Enough for about 300 people.']);
  demo_insert('equipment', ['name' => 'Light set with stands', 'category' => 'licht',
    'owner_id' => $members[3], 'location' => "at Ben's", 'is_standard' => 0, 'notes' => '',
    'purchased_on' => $d('-3 years'), 'price_cents' => 89000]);

  // Ein Koffer mit Inhalt zeigt, wie Bestandteile funktionieren: Besitzer und
  // Lagerort erben sie vom Koffer, eigene Angaben brauchen sie nicht.
  $eqCase = demo_insert('equipment', ['name' => 'Microphone case', 'category' => 'pa',
    'owner_id' => $members[1], 'location' => 'Rehearsal room, left shelf', 'is_standard' => 1,
    'notes' => 'Please put them all back after the gig.',
    'purchased_on' => $d('-2 years'), 'price_cents' => 12900]);
  $mics = [
    ['Vocal microphone', 'Channel 1', 11900],
    ['Vocal microphone', 'Channel 2', 11900],
    ['Kick drum microphone', 'Channel 3', 21900],
    ['Snare microphone', 'Channel 4', 9900],
    ['Overhead left', 'Channel 5', 14900],
    ['Overhead right', 'Channel 6', 14900],
  ];
  foreach ($mics as [$micName, $micSlot, $micPrice]) {
    demo_insert('equipment', ['name' => $micName, 'category' => 'pa',
      'owner_id' => $members[1], 'location' => '', 'is_standard' => 0, 'notes' => '',
      'parent_id' => $eqCase, 'slot' => $micSlot,
      'purchased_on' => $d('-2 years'), 'price_cents' => $micPrice]);
  }

  // Vier Ebenen zeigen, dass die Verschachtelung nicht bei den Bestandteilen
  // aufhört: im Rack steckt ein Empfänger, dazu gehört ein Handsender, und in
  // dem sitzt eine Kapsel.
  $eqRack = demo_insert('equipment', ['name' => 'Wireless rack', 'category' => 'pa',
    'owner_id' => null, 'location' => 'Rehearsal room', 'is_standard' => 1,
    'notes' => 'Fold out the antennas before setting up.',
    'purchased_on' => $d('-18 months'), 'price_cents' => 24900]);
  $eqRx = demo_insert('equipment', ['name' => 'Wireless receiver', 'category' => 'pa',
    'owner_id' => null, 'location' => '', 'is_standard' => 0, 'notes' => '',
    'parent_id' => $eqRack, 'slot' => 'HE 1',
    'purchased_on' => $d('-18 months'), 'price_cents' => 39900]);
  $eqTx = demo_insert('equipment', ['name' => 'Handheld transmitter', 'category' => 'pa',
    'owner_id' => null, 'location' => '', 'is_standard' => 0, 'notes' => '',
    'parent_id' => $eqRx, 'slot' => 'Channel A',
    'purchased_on' => $d('-18 months'), 'price_cents' => 21900]);
  demo_insert('equipment', ['name' => 'Microphone capsule', 'category' => 'pa',
    'owner_id' => null, 'location' => '', 'is_standard' => 0,
    'notes' => 'Cardioid pattern.', 'parent_id' => $eqTx, 'slot' => '',
    'purchased_on' => $d('-18 months'), 'price_cents' => 9900]);

  // Die Kabelkiste ist selbst ein Gerät, kein Ortsname: die Kabel liegen
  // darin, so wie die Mikrofone im Koffer. Eingepackt wird die Kiste, nicht
  // sechs Kabel einzeln.
  $eqCables = demo_insert('equipment', ['name' => 'Cable box', 'category' => 'sonstiges',
    'owner_id' => null, 'location' => 'Rehearsal room', 'is_standard' => 1,
    'notes' => 'Please coil the cables before putting them back.',
    'purchased_on' => $d('-14 months'), 'price_cents' => 4900]);
  for ($i = 1; $i <= 6; $i++) {
    demo_insert('equipment', ['name' => 'XLR cable 10 m #' . $i, 'category' => 'sonstiges',
      'owner_id' => null, 'location' => '', 'is_standard' => 0, 'notes' => '',
      'parent_id' => $eqCables, 'slot' => '',
      'purchased_on' => $d('-14 months'), 'price_cents' => 1490]);
  }

  // Instrumente gehören den Mitgliedern, nicht der Band — der Normalfall, und
  // das deutlichste Beispiel dafür, wofür der Besitzer da ist: den Kaufpreis
  // sieht nur, wem das Gerät gehört.
  foreach ([
    [$members[1], 'Electric guitar', "at Tom's", '-4 years', 129000],
    [$members[2], 'Electric bass', "at Ines's", '-6 years', 98000],
    [$members[3], 'Drums', 'Rehearsal room', '-8 years', 185000],
    [$members[0], 'In-ear system', "at Lisa's", '-1 year', 54900],
  ] as [$ownerId, $name, $where, $when, $price]) {
    demo_insert('equipment', ['name' => $name, 'category' => 'instrument',
      'owner_id' => $ownerId, 'location' => $where, 'is_standard' => 1, 'notes' => '',
      'purchased_on' => $d($when), 'price_cents' => $price]);
  }

  // Packliste für den kommenden Gig: eigene PA, Licht steht vor Ort. Die
  // Verknüpfung hat keine eigene ID, sie hängt an Termin und Gerät und
  // verschwindet mit beiden wieder.
  q("UPDATE events SET pa_source = 'eigene', light_source = 'vorhanden' WHERE id = ?", [$evNext]);
  foreach ([$eqPa, $eqCase, $eqTrailer] as $gearId) {
    q('INSERT IGNORE INTO event_equipment (event_id, equipment_id) VALUES (?,?)', [$evNext, $gearId]);
  }

  demo_install_background();
  demo_install_stage_plot($members);
  demo_install_topics($members);
  demo_install_orders($members);
  demo_install_substitute($members, $evNext, $pw);
  demo_install_photo($members[0]);
  demo_write_logins($logins);
}

/**
 * Die Zugangsdaten der Demokonten einmalig festhalten — außerhalb des
 * Webroots und nur für den Eigentümer lesbar, wie beim ersten Admin-Konto.
 * Ein festes Passwort wie „demo" wäre die Alternative gewesen; das steht dann
 * aber auch auf einer Installation, die längst echt genutzt wird.
 */
function demo_write_logins(array $logins): void {
  if (!$logins) return;
  $text = "Bandregie - demo accounts

"
    . "These accounts exist only while the demo data is installed and are
"
    . "deleted with it. Use them to see the application as a member and as a
"
    . "stand-in, not only as the administrator.

";
  foreach ($logins as $mail => $plain) $text .= str_pad($mail, 24) . $plain . "
";
  $text .= "
Delete this file once you have finished looking around.
";
  @file_put_contents(DATA_DIR . '/DEMO-LOGINS.txt', $text);
  @chmod(DATA_DIR . '/DEMO-LOGINS.txt', 0600);
}

/** Ein Gespräch im Bandbereich — sonst steht dort ein leerer Menüpunkt. */
function demo_install_topics(array $members): void {
  $topic = demo_insert('topics', [
    'title' => 'Merch for the autumn', 'created_by' => $members[0], 'closed' => 0,
  ]);
  foreach ([
    [$members[0], 'I would like T-shirts for the next shows. Who can get us some quotes?'],
    [$members[1], 'On it. Asked two printers, prices should come this week.'],
    [$members[2], 'Ask about tote bags too — they sell better than shirts at small venues.'],
  ] as [$uid, $text]) {
    demo_insert('topic_posts', ['topic_id' => $topic, 'user_id' => $uid, 'text' => $text]);
  }
}

/**
 * Zwei Daueraufträge: die Miete zahlt die Band, dazu die Einzahlung eines
 * Mitglieds. Erst damit zeigt die Kasse, wofür die Gegenüberstellung von
 * Miete und Einzahlungen gut ist.
 */
function demo_install_orders(array $members): void {
  $start = date('Y-m-d', strtotime('-3 months'));
  demo_insert('standing_orders', [
    'owner_id' => null, 'private' => 0, 'type' => 'ausgabe', 'amount_cents' => 5000,
    'category' => 'proberaum', 'description' => 'Rehearsal room rent', 'interval_kind' => 'monthly',
    'start_date' => $start, 'next_date' => $start, 'created_by' => $members[0],
  ]);
  // Jedes Mitglied zahlt monatlich ein — genau dafür ist die
  // Gegenüberstellung mit der Miete da.
  foreach ($members as $memberId) {
    $name = row('SELECT first_name, name FROM users WHERE id = ?', [$memberId]);
    demo_insert('standing_orders', [
      'owner_id' => $memberId, 'private' => 0, 'type' => 'einnahme', 'amount_cents' => 1500,
      'category' => 'einlage', 'description' => 'Deposit ' . ($name['first_name'] ?: $name['name']),
      'interval_kind' => 'monthly', 'start_date' => $start, 'next_date' => $start,
      'created_by' => $memberId,
    ]);
  }
  // Gleich buchen lassen, sonst steht die Kasse bis zum nächsten Seitenaufruf
  // leer. Die dabei entstandenen Zeilen gehören zur Demo und müssen mit ihr
  // wieder verschwinden — orders_run() weiß nichts von Demodaten.
  orders_run();
  foreach (rows('SELECT f.id FROM finances f
                 JOIN demo_rows d ON d.table_name = ? AND d.row_id = f.standing_order_id',
                ['standing_orders']) as $booked) {
    demo_track('finances', (int) $booked['id']);
  }
}

/**
 * Eine Aushilfe samt Anfrage für den kommenden Gig. Ohne sie ist von den
 * eingeschränkten Rechten und der Anfrage-Logik nichts zu sehen.
 */
function demo_install_substitute(array $members, int $eventId, callable $pw): void {
  $sub = demo_insert('users', [
    'name' => 'Nora Falk', 'first_name' => 'Nora', 'last_name' => 'Falk', 'stage_name' => '',
    'email' => 'nora@example.com', 'password_hash' => $pw('nora@example.com'),
    'role' => 'ersatz', 'instrument' => 'Vocals', 'must_change_pw' => 0,
    'substitute_for' => $members[0], 'substitute_rank' => 1,
  ]);
  perm_apply_template($sub, 'ersatz');
  q('INSERT IGNORE INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,?)',
    [$eventId, $sub, $members[0], $members[0]]);
}

/** Ein Foto in der Galerie — dasselbe mitgelieferte Bild, öffentlich gestellt. */
function demo_install_photo(int $uploader): void {
  $source = BASE_DIR . '/seed/demo/stage-crowd.jpg';
  if (!is_file($source)) return;
  $name = 'foto_demo_' . bin2hex(random_bytes(8)) . '.jpg';
  if (!@copy($source, UPLOADS_DIR . '/' . $name)) return;
  demo_insert('photos', [
    'filename' => $name, 'caption' => 'Summer Festival Sampleton', 'is_public' => 1,
    'uploaded_by' => $uploader,
  ]);
}

/**
 * Bühnenplan der Demoband. Die Standardaufstellung kennt die Instrumente
 * schon; dazu kommt, was auf keinem Plan fehlen darf und wonach der
 * Veranstalter sonst fragt: Verstärker, Monitore, Strom.
 */
function demo_install_stage_plot(array $memberIds): void {
  // Nur die Demoband: auf einer Instanz mit echten Mitgliedern hätte die Demo
  // sonst deren Namen auf die Bühne gestellt.
  if (!$memberIds || rows('SELECT id FROM stage_items LIMIT 1')) return;
  $marks = implode(',', array_fill(0, count($memberIds), '?'));
  // Die Standardaufstellung beschriftet den Strom in der Sprache der Band —
  // richtig für eine echte Installation, aber die Demoband spricht Englisch
  // wie ihre übrigen Daten. Der Plan speichert Text, keine Schlüssel.
  $items = stage_default_items(
    rows("SELECT name, stage_name, instrument FROM users WHERE id IN ($marks) ORDER BY id", $memberIds)
  );
  // Abstand zu den Musikern: zwei Beschriftungen übereinander liest niemand.
  // Die Standardaufstellung setzt Gesang auf 50/78, Gitarre 25/60,
  // Bass 22/25, Schlagzeug 50/12 — daran entlang.
  foreach ($items as $i => $item) {
    if ($item['kind'] === 'strom') $items[$i]['label'] = 'Power';
  }
  $items[] = ['kind' => 'amp', 'label' => 'Bass amp', 'x' => 8, 'y' => 32, 'note' => ''];
  $items[] = ['kind' => 'amp', 'label' => 'Guitar amp', 'x' => 10, 'y' => 58, 'note' => ''];
  $items[] = ['kind' => 'di', 'label' => 'DI Bass', 'x' => 40, 'y' => 34, 'note' => ''];
  // Monitore stehen vor der Person, für die sie da sind — seitlich versetzt,
  // damit ihre Beschriftung nicht auf deren Instrumentenzeile fällt.
  $items[] = ['kind' => 'monitor', 'label' => 'Monitor 1', 'x' => 64, 'y' => 90, 'note' => 'Vocals'];
  $items[] = ['kind' => 'monitor', 'label' => 'Monitor 2', 'x' => 12, 'y' => 82, 'note' => 'Guitar'];
  foreach ($items as $i => $item) {
    demo_insert('stage_items', $item + ['position' => $i]);
  }
}

/**
 * Hintergrundbild der Demoband: eine Bühne mit Publikum. Ohne Bild wirkt die
 * öffentliche Seite leer und man sieht nicht, wofür die Einstellung da ist.
 *
 * Das mitgelieferte Bild bleibt liegen, kopiert wird es — sonst wäre es nach
 * dem ersten Entfernen der Demodaten weg. Ein bereits eingestellter
 * Hintergrund wird nicht angetastet: eine echte Band, die sich die Demo
 * ansieht, soll ihr eigenes Bild behalten.
 */
function demo_install_background(): void {
  if (setting('background_file') !== '') return;
  $source = BASE_DIR . '/seed/demo/stage-crowd.jpg';
  if (!is_file($source)) return;
  $name = 'background_demo_' . bin2hex(random_bytes(8)) . '.jpg';
  if (!@copy($source, UPLOADS_DIR . '/' . $name)) return;
  set_setting('background_file', $name);
}

/**
 * Bildnachweis für das mitgelieferte Hintergrundbild — oder null, sobald die
 * Band ihr eigenes eingestellt hat. CC0 verlangt keine Nennung; sie steht
 * trotzdem im Impressum, solange das geschenkte Bild benutzt wird.
 */
function demo_background_credit(): ?string {
  if (!str_starts_with(setting('background_file'), 'background_demo_')) return null;
  return e(t('legal_credit_background')) . ' '
    . '<a href="https://www.pexels.com/photo/panoramic-view-of-crowd-at-music-concert-248963/" rel="noopener">Pexels</a>'
    . ' · <a href="https://creativecommons.org/publicdomain/zero/1.0/" rel="noopener">CC0</a>';
}

/** Das Hintergrundbild der Demo wieder entfernen — aber nur das eigene. */
function demo_remove_background(): void {
  $name = setting('background_file');
  if ($name === '' || !str_starts_with($name, 'background_demo_')) return;
  @unlink(UPLOADS_DIR . '/' . $name);
  set_setting('background_file', '');
}

function demo_remove(): void {
  // Das Bild hängt an keiner Zeile, es erkennt sich am eigenen Namen — und
  // muss deshalb auch weg, wenn sonst nichts mehr zu löschen ist.
  demo_remove_background();
  @unlink(DATA_DIR . '/DEMO-LOGINS.txt');
  // Fotodateien liegen auf der Platte, nicht in der Tabelle. Erst die Datei,
  // dann fällt die Zeile weiter unten mit allen anderen.
  foreach (rows('SELECT p.filename FROM photos p
                 JOIN demo_rows d ON d.table_name = ? AND d.row_id = p.id', ['photos']) as $p) {
    @unlink(UPLOADS_DIR . '/' . $p['filename']);
  }
  $rows = rows('SELECT table_name, row_id FROM demo_rows');
  if (!$rows) return;

  $byTable = [];
  foreach ($rows as $r) $byTable[$r['table_name']][] = (int) $r['row_id'];

  // Zeilen, die auf Demo-Termine zeigen, aber von echten Mitgliedern stammen
  // (Zusagen, Kommentare), gehören mit weg — sonst bleiben Waisen zurück.
  foreach ($byTable['events'] ?? [] as $eventId) {
    q('DELETE FROM attendance WHERE event_id = ?', [$eventId]);
    q('DELETE FROM comments WHERE event_id = ?', [$eventId]);
    q('DELETE FROM event_equipment WHERE event_id = ?', [$eventId]);
    q('UPDATE finances SET event_id = NULL WHERE event_id = ?', [$eventId]);
  }
  foreach ($byTable['users'] ?? [] as $userId) {
    q('DELETE FROM attendance WHERE user_id = ?', [$userId]);
    q('DELETE FROM permissions WHERE user_id = ?', [$userId]);
    q('DELETE FROM substitute_requests WHERE user_id = ?', [$userId]);
    q('UPDATE comments SET user_id = NULL WHERE user_id = ?', [$userId]);
    q('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?', [$userId]);
    q('UPDATE equipment SET owner_id = NULL WHERE owner_id = ?', [$userId]);
    q('UPDATE events SET responsible_id = NULL WHERE responsible_id = ?', [$userId]);
    q('UPDATE finances SET member_id = NULL WHERE member_id = ?', [$userId]);
  }
  foreach ($byTable['setlists'] ?? [] as $setlistId) {
    q('UPDATE events SET setlist_id = NULL WHERE setlist_id = ?', [$setlistId]);
  }
  foreach ($byTable['venues'] ?? [] as $venueId) {
    q('UPDATE events SET venue_id = NULL WHERE venue_id = ?', [$venueId]);
  }
  foreach ($byTable['equipment'] ?? [] as $eqId) {
    q('DELETE FROM equipment_deadlines WHERE equipment_id = ?', [$eqId]);
    q('DELETE FROM event_equipment WHERE equipment_id = ?', [$eqId]);
  }

  // Kindzeilen zuerst, dann die Haupttabellen
  $order = ['comments', 'setlist_songs', 'equipment_deadlines', 'finances', 'tasks',
            'absences', 'events', 'setlists', 'songs', 'venues', 'equipment', 'users'];
  foreach (array_unique([...$order, ...array_keys($byTable)]) as $table) {
    foreach ($byTable[$table] ?? [] as $id) {
      // Sicherheitsnetz: der letzte Admin darf niemals gelöscht werden
      if ($table === 'users' && (int) (row('SELECT COUNT(*) AS n FROM users WHERE role = "admin"')['n'] ?? 0) <= 1
          && (row('SELECT role FROM users WHERE id = ?', [$id])['role'] ?? '') === 'admin') {
        continue;
      }
      q("DELETE FROM `$table` WHERE id = ?", [$id]);
    }
  }
  q('DELETE FROM demo_rows');
}
