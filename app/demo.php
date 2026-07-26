<?php
declare(strict_types=1);

// Demodaten: füllen eine frische Installation mit einer erfundenen Band, damit
// man alle Funktionen ausprobieren kann. Jede angelegte Zeile wird in demo_rows
// vermerkt — beim Entfernen wird ausschließlich das wieder gelöscht, echte
// Daten bleiben unangetastet.

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

  // --- Mitglieder (Zufallspasswörter, Wechsel erzwungen — reine Demokonten)
  $pw = fn(): string => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
  $members = [];
  foreach ([
    ['Lisa Berg', 'Lisa', 'lisa@example.com', 'Gesang'],
    ['Tom Krause', 'Tommy', 'tom@example.com', 'Gitarre'],
    ['Ines Adler', '', 'ines@example.com', 'Bass'],
    ['Ben Rauch', 'Benny', 'ben@example.com', 'Schlagzeug'],
  ] as [$name, $stage, $mail, $instr]) {
    $members[] = demo_insert('users', [
      'name' => $name, 'stage_name' => $stage, 'email' => $mail,
      'password_hash' => $pw(), 'role' => 'member', 'instrument' => $instr,
      'must_change_pw' => 1,
    ]);
  }

  // --- Veranstaltungsorte
  $venue1 = demo_insert('venues', [
    'name' => 'Stadthalle Musterstadt', 'city' => 'Musterstadt',
    'address' => "Hallenweg 3\n12345 Musterstadt",
    'contact_name' => 'Frau Sommer', 'contact_email' => 'buehne@example.com',
    'contact_phone' => '0123 456789',
    'notes' => 'Bühne 8 × 6 m, Strom 2 × 32 A, Parken direkt hinter der Halle.',
  ]);
  $venue2 = demo_insert('venues', [
    'name' => 'Kulturscheune Beispieldorf', 'city' => 'Beispieldorf',
    'address' => "Dorfstraße 1\n12346 Beispieldorf",
    'contact_name' => 'Herr Winter', 'contact_email' => 'scheune@example.com',
    'contact_phone' => '0123 987654',
    'notes' => 'Kleine Bühne, eigene PA vorhanden. Anfahrt über den Feldweg.',
  ]);

  // --- Songs (frei erfundene Titel, damit keine echten Rechte berührt werden)
  $songs = [];
  foreach ([
    ['Sommerregen', 'Eigenkomposition', 'G', '128 BPM', 214, 'aktiv'],
    ['Neonlicht', 'Eigenkomposition', 'Am', '140 BPM', 186, 'aktiv'],
    ['Letzter Zug', 'Eigenkomposition', 'D', '96 BPM', 245, 'aktiv'],
    ['Neue Ufer', 'Eigenkomposition', 'C', '132 BPM', 198, 'aktiv'],
    ['Kalter Kaffee', 'Eigenkomposition', 'Em', '150 BPM', 172, 'aktiv'],
    ['Zwischen den Zeilen', 'Eigenkomposition', 'F', '88 BPM', 262, 'aktiv'],
    ['Rückenwind', 'Eigenkomposition', 'A', '160 BPM', 205, 'in_arbeit'],
    ['Nachtschicht', 'Eigenkomposition', 'Bm', '118 BPM', 228, 'in_arbeit'],
    ['Alte Straße', 'Eigenkomposition', 'G', '104 BPM', 190, 'vorschlag'],
    ['Ohne Titel', 'Eigenkomposition', 'C', '', 0, 'vorschlag'],
  ] as [$title, $artist, $key, $tempo, $sec, $status]) {
    $songs[] = demo_insert('songs', [
      'title' => $title, 'artist' => $artist, 'song_key' => $key,
      'tempo' => $tempo, 'duration_sec' => $sec, 'status' => $status,
      'notes' => '',
    ]);
  }

  // --- Setlists: eine gespielte (wird durch den vergangenen Gig fixiert)
  //     und eine für den nächsten Auftritt, mit Pause und Zugabe
  $slPast = demo_insert('setlists', ['name' => 'Sommerfest — gespielt', 'notes' => 'Ablauf wie besprochen.']);
  $pos = 1;
  foreach (array_slice($songs, 0, 6) as $sid) {
    demo_insert('setlist_songs', ['setlist_id' => $slPast, 'song_id' => $sid, 'is_break' => 0, 'position' => $pos++]);
  }

  $slNext = demo_insert('setlists', ['name' => 'Nächster Auftritt', 'notes' => '']);
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
    'type' => 'gig', 'title' => 'Sommerfest Musterstadt', 'date' => $d('-10 weeks'),
    'time' => '20:00', 'time_meet' => '17:00', 'time_end' => '23:00',
    'venue_id' => $venue1, 'location' => '', 'notes' => 'Lief gut, Zugabe war gewünscht.',
    'is_public' => 1, 'setlist_id' => $slPast, 'status' => 'bestaetigt',
    'fee' => '900 €', 'invoice_no' => 'R-2026-014', 'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);
  $evNext = demo_insert('events', [
    'type' => 'gig', 'title' => 'Kulturscheune Beispieldorf', 'date' => $d('+6 weeks'),
    'time' => '21:00', 'time_meet' => '18:00', 'time_end' => '00:30',
    'venue_id' => $venue2, 'location' => '', 'notes' => 'Eigene PA mitbringen, Backline steht.',
    'is_public' => 1, 'setlist_id' => $slNext, 'status' => 'bestaetigt',
    'fee' => '750 €', 'invoice_no' => '', 'public_title' => '', 'public_link' => '',
    'public_info' => 'Einlass 20 Uhr',
  ]);
  demo_insert('events', [
    'type' => 'probe', 'title' => 'Probe vor dem Auftritt', 'date' => $d('+2 weeks'),
    'time' => '19:00', 'time_meet' => '', 'time_end' => '22:00', 'venue_id' => null,
    'location' => 'Proberaum', 'notes' => 'Neue Songs durchgehen.', 'is_public' => 0,
    'setlist_id' => null, 'status' => 'bestaetigt', 'fee' => '', 'invoice_no' => '',
    'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);
  demo_insert('events', [
    'type' => 'besprechung', 'title' => 'Bandbesprechung', 'date' => $d('+3 days'),
    'time' => '19:30', 'time_meet' => '', 'time_end' => '21:00', 'venue_id' => null,
    'location' => 'Proberaum', 'notes' => 'Planung Herbst, Merch, Fotoshooting.',
    'is_public' => 0, 'setlist_id' => null, 'status' => 'bestaetigt', 'fee' => '',
    'invoice_no' => '', 'public_title' => '', 'public_link' => '', 'public_info' => '',
  ]);

  // --- Rückmeldungen und ein Kommentar am kommenden Gig
  foreach ([[0, 'yes'], [1, 'yes'], [2, 'maybe'], [3, 'no']] as [$i, $status]) {
    q('INSERT INTO attendance (event_id, user_id, status) VALUES (?,?,?)', [$evNext, $members[$i], $status]);
  }
  demo_insert('comments', [
    'event_id' => $evNext, 'user_id' => $members[1],
    'text' => 'Ich nehme den großen Verstärker mit, im Auto ist noch Platz für zwei Boxen.',
  ]);

  // --- Aufgaben
  demo_insert('tasks', ['title' => 'Technik-Rider an den Veranstalter schicken', 'notes' => '',
    'assigned_to' => $members[0], 'due_date' => $d('+10 days'), 'status' => 'offen']);
  demo_insert('tasks', ['title' => 'Neue Fotos für die Website aussuchen', 'notes' => 'Am besten vom Sommerfest.',
    'assigned_to' => $members[2], 'due_date' => $d('+3 weeks'), 'status' => 'offen']);
  demo_insert('tasks', ['title' => 'Anhänger zur Prüfung anmelden', 'notes' => '',
    'assigned_to' => null, 'due_date' => $d('-1 week'), 'status' => 'erledigt']);

  // --- Abwesenheit (erzeugt die Warnung beim Termin, falls sie zusammenfällt)
  demo_insert('absences', ['user_id' => $members[3], 'date_from' => $d('+13 days'),
    'date_to' => $d('+20 days'), 'note' => 'Urlaub']);

  // --- Kasse
  $adminId = (int) (row("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")['id'] ?? 0) ?: null;
  foreach ([
    [$d('-10 weeks'), 'einnahme', 90000, 'gage', 'Sommerfest Musterstadt', $evPast],
    [$d('-10 weeks'), 'ausgabe', 60000, 'ausschuettung', 'Ausschüttung Sommerfest', $evPast],
    [$d('-9 weeks'), 'einnahme', 4500, 'merch', 'Verkauf T-Shirts', null],
    [$d('-8 weeks'), 'ausgabe', 12000, 'equipment', 'Neue Mikrofonkabel', null],
    [$d('-6 weeks'), 'ausgabe', 15000, 'proberaum', 'Miete Proberaum (Quartal)', null],
    [$d('-2 weeks'), 'ausgabe', 3200, 'verpflegung', 'Getränke für die Probe', null],
  ] as [$date, $type, $cents, $cat, $desc, $ev]) {
    demo_insert('finances', ['date' => $date, 'type' => $type, 'amount_cents' => $cents,
      'category' => $cat, 'description' => $desc, 'event_id' => $ev, 'member_id' => null,
      'created_by' => $adminId]);
  }

  // --- Equipment mit Fristen
  $eqTrailer = demo_insert('equipment', ['name' => 'Bandanhänger', 'category' => 'transport',
    'owner_id' => null, 'location' => 'Hof am Proberaum', 'is_standard' => 1,
    'notes' => 'Kennzeichen im Handschuhfach, Ersatzrad hinten links.']);
  demo_insert('equipment_deadlines', ['equipment_id' => $eqTrailer, 'title' => 'Hauptuntersuchung',
    'due_date' => $d('+5 weeks'), 'interval_months' => 24, 'notes' => '']);
  demo_insert('equipment_deadlines', ['equipment_id' => $eqTrailer, 'title' => 'Versicherung',
    'due_date' => $d('+4 months'), 'interval_months' => 12, 'notes' => '']);
  demo_insert('equipment', ['name' => 'PA-Anlage (2 Tops, 2 Subs)', 'category' => 'pa',
    'owner_id' => null, 'location' => 'Proberaum', 'is_standard' => 1,
    'notes' => 'Reicht bis etwa 300 Gäste.']);
  demo_insert('equipment', ['name' => 'Lichtset mit Stativen', 'category' => 'licht',
    'owner_id' => $members[3], 'location' => 'bei Ben', 'is_standard' => 0, 'notes' => '']);
}

function demo_remove(): void {
  $rows = rows('SELECT table_name, row_id FROM demo_rows');
  if (!$rows) return;

  $byTable = [];
  foreach ($rows as $r) $byTable[$r['table_name']][] = (int) $r['row_id'];

  // Zeilen, die auf Demo-Termine zeigen, aber von echten Mitgliedern stammen
  // (Zusagen, Kommentare), gehören mit weg — sonst bleiben Waisen zurück.
  foreach ($byTable['events'] ?? [] as $eventId) {
    q('DELETE FROM attendance WHERE event_id = ?', [$eventId]);
    q('DELETE FROM comments WHERE event_id = ?', [$eventId]);
    q('UPDATE finances SET event_id = NULL WHERE event_id = ?', [$eventId]);
  }
  foreach ($byTable['users'] ?? [] as $userId) {
    q('DELETE FROM attendance WHERE user_id = ?', [$userId]);
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
