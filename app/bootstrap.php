<?php
declare(strict_types=1);

// Session-Cookie absichern: nicht per JavaScript lesbar, nicht bei fremden
// Seitenaufrufen mitgeschickt und über TLS nur verschlüsselt übertragen.
$overTls = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure' => $overTls,
]);
session_start();

// Schutzheader, die keine Konfiguration brauchen
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');

define('BASE_DIR', dirname(__DIR__));
define('BANDROADIE_VERSION', trim(@file_get_contents(dirname(__DIR__) . '/VERSION') ?: '') ?: 'dev');
define('DATA_DIR', BASE_DIR . '/data');
define('UPLOADS_DIR', DATA_DIR . '/uploads');
define('FILES_DIR', DATA_DIR . '/files');
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0775, true);
if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0775, true);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
  http_response_code(500);
  exit('Konfiguration fehlt: app/config.php anlegen (Vorlage: app/config.example.php).');
}
$config = require $configFile;

// Die häufigste Hürde bei der Ersteinrichtung ist ein Tippfehler in den
// Zugangsdaten. Der Rohfehler von PDO nennt Benutzernamen und Dateipfade und
// hilft dabei niemandem — die Meldung sagt, was zu tun ist, die Einzelheiten
// gehen ins Fehlerprotokoll des Servers.
try {
  $db = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  error_log('Bandroadie: Datenbankverbindung fehlgeschlagen — ' . $e->getMessage());
  http_response_code(500);
  exit('Keine Verbindung zur Datenbank. Bitte db_host, db_name, db_user und db_pass '
     . 'in app/config.php prüfen; Einzelheiten stehen im Fehlerprotokoll des Servers.');
}

// Termin-Arten und -Status
const EVENT_TYPES = [
  'gig' => 'Gig', 'probe' => 'Probe', 'party' => 'Party', 'aufnahme' => 'Aufnahme-Session',
  'fotoshooting' => 'Fotoshooting', 'besprechung' => 'Besprechung', 'aufbau' => 'Auf-/Abbau',
  'reise' => 'Reise', 'dayoff' => 'Day off', 'sonstiges' => 'Sonstiges',
];
const EVENT_STATUS = [
  'bestaetigt' => 'Findet statt', 'angefragt' => 'Angefragt', 'reserviert' => 'Reserviert',
  'blockiert' => 'Blockiert – offen f. Anfragen', 'abgesagt' => 'Abgesagt',
];
// Sprachen der öffentlichen Seite (Belgien ist über NL/FR abgedeckt)
const LANGS = ['de' => 'Deutsch', 'en' => 'English', 'nl' => 'Nederlands', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano'];

// UI-Texte der öffentlichen Seite (Deutsch = Standard und Fallback)
const UI_STRINGS = [
  'weekdays' => 'So,Mo,Di,Mi,Do,Fr,Sa',
  'nav_start' => 'Start', 'nav_termine' => 'Termine', 'nav_musik' => 'Musik', 'nav_fotos' => 'Fotos',
  'nav_kontakt' => 'Kontakt', 'nav_downloads' => 'Downloads', 'nav_bandbereich' => 'Bandbereich',
  'nav_impressum' => 'Impressum', 'nav_datenschutz' => 'Datenschutz',
  'privacy_title' => 'Datenschutzerklärung',
  'legal_credits' => 'Bildnachweis',
  'legal_credit_background' => 'Hintergrundbild: Konzertpublikum,',
  'home_about' => 'Über uns', 'home_next_gigs' => 'Nächste Gigs', 'home_all_events' => 'Alle Termine',
  'home_impressions' => 'Impressionen', 'home_more_photos' => 'Mehr Fotos',
  'events_upcoming' => 'Kommende Gigs',
  'events_none' => 'Aktuell sind keine öffentlichen Termine angekündigt — schaut bald wieder vorbei!',
  'events_past' => 'Vergangene Gigs', 'events_tickets' => 'Mehr Infos / Tickets', 'events_oclock' => 'Uhr',
  'music_soon' => 'Hier gibt es bald was auf die Ohren.',
  'music_external_from' => 'Externer Inhalt von',
  'music_data_notice' => 'Beim Laden werden Daten (u. a. deine IP-Adresse) an den Anbieter übertragen. Details:',
  'music_load' => 'Inhalt laden', 'music_remember' => 'Für alle Inhalte dieses Anbieters merken',
  'music_listen' => 'Anhören / Ansehen',
  'photos_none' => 'Noch keine Fotos online — kommt bald!',
  'contact_title' => 'Kontakt & Booking',
  'downloads_title' => 'Downloads für Veranstalter',
  'downloads_intro' => 'Technische Unterlagen und Pressematerial von',
  'downloads_questions' => 'Fragen?',
  'downloads_soon' => 'Hier gibt es bald Material zum Herunterladen.',
  // Interner Bereich
  'inav_uebersicht' => 'Übersicht', 'inav_termine' => 'Termine', 'inav_songs' => 'Songs',
  'inav_setlists' => 'Setlists', 'inav_orte' => 'Orte', 'inav_abwesenheiten' => 'Abwesenheiten',
  'inav_aufgaben' => 'Aufgaben', 'inav_fotos' => 'Fotos', 'inav_downloads' => 'Downloads',
  'inav_mitglieder' => 'Mitglieder', 'inav_profil' => 'Profil', 'inav_einstellungen' => 'Einstellungen',
  'inav_intern' => 'Intern', 'inav_zur_website' => 'Zur Website', 'logout' => 'Logout',
  // Überschriften im Klappmenü — siehe $navGroups in app/views/_header.php
  'inavg_planung' => 'Planung', 'inavg_musik' => 'Musik', 'inavg_technik' => 'Technik',
  'inavg_material' => 'Material', 'inavg_band' => 'Band', 'inavg_konto' => 'Konto',
  'inav_musik' => 'Musik & Videos', 'inav_hilfe' => 'Hilfe', 'inav_ueber' => 'Über',
  'fl_media_saved' => 'Link gespeichert.', 'fl_media_deleted' => 'Link gelöscht.',
  'help_title' => 'Hilfe', 'help_intro' => 'Was steckt hinter den einzelnen Bereichen?',
  'help_more' => 'Mehr zur Anwendung, zur Lizenz und zu den Mitwirkenden steht unter „Über".',
  // Kurzbeschreibung je Bereich — die Schlüssel heißen wie die Bereiche
  'help_termine' => 'Alle Auftritte, Proben und Besprechungen. Jeder sagt zu oder ab, Dateien und Kommentare hängen am Termin, und die Packliste sagt, welche Geräte mitkommen.',
  'help_songs' => 'Das Repertoire mit Tonart, Tempo, Dauer und Status. Noten, Texte und Aufnahmen hängen am Song.',
  'help_setlists' => 'Die Reihenfolge für einen Auftritt, mit Pausen und Zugaben. Die Spielzeit rechnet sich aus den Songdauern.',
  'help_orte' => 'Veranstaltungsorte mit Adresse, Ansprechpartner und Erfahrungen von den letzten Malen.',
  'help_abwesenheiten' => 'Urlaub und Sperrzeiten. Fällt ein Termin hinein, warnt die Terminliste.',
  'help_aufgaben' => 'Was ansteht und wer es macht.',
  'help_themen' => 'Diskussionen in Ruhe, ohne dass etwas im Chat untergeht.',
  'help_kasse' => 'Einnahmen und Ausgaben der Band, Gagen lassen sich aus den Terminen übernehmen.',
  'help_equipment' => 'Das Inventar samt Bestandteilen, Preisen und Fristen wie Prüfungen oder Versicherungen.',
  'help_rider' => 'Was ein Veranstalter über eure Technik wissen muss, und die Kanalbelegung fürs Mischpult.',
  'help_fotos' => 'Bilder für die öffentliche Seite und fürs Archiv.',
  'help_musik' => 'Videos und Streams, die auf der öffentlichen Musikseite erscheinen.',
  'help_downloads' => 'Pressematerial für Veranstalter — mit Link zum Weitergeben.',
  'help_mitglieder' => 'Wer zur Band gehört, mit Kontaktdaten und Rollen.',
  'login_only_members' => 'Nur für Mitglieder von', 'login_email' => 'E-Mail', 'login_password' => 'Passwort',
  'login_submit' => 'Einloggen', 'login_failed' => 'E-Mail oder Passwort falsch.',
  'dash_hello' => 'Hallo', 'dash_next_events' => 'Nächste Termine',
  'dash_no_events' => 'Keine anstehenden Termine.', 'dash_create_event' => 'Termin anlegen',
  'dash_all_events' => 'Alle Termine', 'dash_open_tasks' => 'Offene Aufgaben',
  'dash_nothing_open' => 'Nichts offen. 🎉', 'dash_all_tasks' => 'Alle Aufgaben',
  'att_yes' => '✔ Bin dabei', 'att_maybe' => '? Vielleicht', 'att_no' => '✘ Kann nicht',
  'due_until' => 'bis',
  // Gemeinsame Aktionen
  'save' => 'Speichern', 'delete' => 'Löschen', 'edit' => 'Bearbeiten', 'cancel' => 'Abbrechen',
  'upload' => 'Hochladen', 'copy' => 'Kopieren', 'add' => 'Hinzufügen', 'create' => 'Anlegen',
  'send' => 'Senden', 'view' => 'Ansehen', 'confirm_delete' => 'Wirklich löschen?',
  'name' => 'Name', 'email' => 'E-Mail', 'phone' => 'Telefon', 'notes' => 'Notizen',
  'status' => 'Status', 'date' => 'Datum', 'title_lbl' => 'Titel', 'unknown' => 'Unbekannt',
  'instrument' => 'Instrument', 'role' => 'Rolle', 'role_member' => 'Mitglied', 'role_admin' => 'Admin', 'role_ersatz' => 'Ersatz',
  'stage_name' => 'Künstlername', 'copied' => 'Kopiert', 'own_song' => 'eigener', 'optional' => 'optional',
  // Termin-Arten
  'evtype_gig' => 'Gig', 'evtype_probe' => 'Probe', 'evtype_party' => 'Party',
  'evtype_aufnahme' => 'Aufnahme-Session', 'evtype_fotoshooting' => 'Fotoshooting',
  'evtype_besprechung' => 'Besprechung', 'evtype_aufbau' => 'Auf-/Abbau',
  'evtype_reise' => 'Reise', 'evtype_dayoff' => 'Day off', 'evtype_sonstiges' => 'Sonstiges',
  // Termin-Status
  'evstatus_bestaetigt' => 'Findet statt', 'evstatus_angefragt' => 'Angefragt',
  'evstatus_reserviert' => 'Reserviert', 'evstatus_blockiert' => 'Blockiert – offen f. Anfragen',
  'evstatus_abgesagt' => 'Abgesagt',
  // Song-Status
  'songstatus_vorschlag' => 'Vorschlag', 'songstatus_in_arbeit' => 'In Vorbereitung',
  'songstatus_aktiv' => 'Aktives Repertoire', 'songstatus_abgewiesen' => 'Abgewiesen',
  'songstatus_archiv' => 'Aussortiert',
  // Termine
  'ev_only_upcoming' => 'Nur kommende', 'ev_also_past' => 'Auch vergangene',
  'ev_cal_abo' => 'Kalender-Abo', 'ev_new' => 'Neuer Termin', 'ev_type' => 'Art',
  'ev_name_ph' => 'z. B. Stadtfest Mainstage', 'ev_meet' => 'Treffen', 'ev_start' => 'Spielbeginn',
  'ev_end' => 'Ende', 'ev_venue' => 'Veranstaltungsort', 'ev_location_free' => 'Ort (Freitext)',
  'ev_location_free_ph' => 'falls kein gespeicherter Ort passt', 'ev_setlist' => 'Setlist',
  'ev_responsible' => 'Zuständig', 'ev_fee' => 'Gage', 'ev_invoice' => 'Rechnungsnr.',
  'ev_notes' => 'Beschreibung / Notizen', 'ev_notes_ph' => 'Backline, Anfahrt, Technik ...',
  'ev_public_display' => 'Öffentliche Anzeige',
  'ev_show_on_site' => 'Auf der Website zeigen (nur Gigs mit Status „Findet statt")',
  'ev_show_on_site_short' => 'Auf der Website zeigen',
  'ev_public_title' => 'Öffentlicher Terminname', 'ev_public_title_ph' => 'leer = Name des Termins',
  'ev_public_link' => 'Link mit mehr Infos', 'ev_public_info' => 'Kurzinfos',
  'ev_public_info_ph' => 'z. B. Einlass 19 Uhr, Support: XY',
  'ev_public_badge' => 'öffentlich', 'ev_absent_warn' => 'Abwesend an dem Tag:',
  'ev_comments' => 'Kommentare', 'ev_comment_ph' => 'Kommentar schreiben ...',
  'ev_locked' => 'Vergangener Termin — fixiert für die Historie.',
  'ev_delete' => 'Termin löschen', 'ev_none' => 'Noch keine Termine.',
  // Songs
  'songs_title' => 'Songs & Repertoire', 'song_new' => 'Neuer Song', 'song_edit_suffix' => 'bearbeiten',
  'song_original' => 'Original von', 'song_original_ph' => 'leer = eigener Song',
  'song_composer' => 'Komponist/Urheber (GEMA)', 'song_composer_ph' => 'für die GEMA-Musikfolge',
  'song_gema' => 'GEMA-Werknr.', 'song_gema_ph' => 'falls bekannt',
  'song_keylbl' => 'Tonart', 'song_tempo' => 'Tempo', 'song_len' => 'Länge (m:ss)',
  'song_add' => 'Song hinzufügen', 'song_notes_ph' => 'Ablauf, Besonderheiten, Technik ...',
  'songs_usable_hint' => 'In Setlists nutzbar sind Songs mit Status „Aktives Repertoire" und „In Vorbereitung".',
  'songs_col_len' => 'Länge', 'songs_col_uses' => 'Einsätze', 'songs_col_original' => 'Original',
  'songs_none' => 'Noch keine Songs angelegt.', 'songs_uses_title' => 'In Setlists / davon live gespielt',
  // Setlists
  'sl_new_ph' => 'Name der neuen Setlist, z. B. Stadtfest 2026', 'sl_songs' => 'Songs',
  'sl_played_locked' => 'gespielt (fixiert)', 'sl_print' => 'Drucken', 'sl_none' => 'Noch keine Setlists.',
  'sl_print_view' => 'Druckansicht', 'sl_gema_list' => 'GEMA-Liste', 'sl_all' => 'Alle Setlists',
  'sl_total' => 'Gesamtlänge',
  'sl_locked_note' => 'Diese Setlist wurde bereits live gespielt und ist als Historie fixiert. Zum Weiterarbeiten einfach kopieren.',
  'sl_empty' => 'Noch leer — füge unten Songs hinzu.', 'sl_pick' => 'Song auswählen ...',
  'sl_all_used' => 'Alle nutzbaren Songs sind schon drin.',
  'sl_drag_hint' => 'Zum Umsortieren die Zeilen mit der Maus ziehen — auf dem Handy die Pfeile nutzen.',
  'sl_saved' => 'Reihenfolge gespeichert',
  'sl_pause' => 'Pause einfügen', 'sl_encore' => 'Zugabe-Marker',
  'sl_pause_word' => 'PAUSE', 'sl_encore_word' => 'ZUGABE',
  // Orte
  'venues_title' => 'Veranstaltungsorte', 'venues_new' => 'Neuer Veranstaltungsort',
  'venues_name_ph' => 'z. B. Festhalle Musterstadt', 'city' => 'Stadt', 'address' => 'Adresse',
  'contact_person' => 'Kontaktperson', 'venues_notes_ph' => 'Bühne, Strom, Parken, Erfahrungen ...',
  'venues_events_here' => 'Termine an diesem Ort', 'venues_none' => 'Noch keine Veranstaltungsorte gespeichert.',
  // Abwesenheiten
  'abs_title' => 'Abwesenheiten',
  'abs_intro' => 'Urlaub, Dienstreise, „Nicht-Band"-Termine — damit bei der Gig-Planung nichts schiefgeht. Termine an diesen Tagen zeigen automatisch eine Warnung.',
  'abs_from' => 'Von', 'abs_to' => 'Bis (leer = nur ein Tag)', 'abs_reason' => 'Grund (optional)',
  'abs_reason_ph' => 'z. B. Urlaub', 'abs_add' => 'Abwesenheit eintragen',
  'abs_upcoming' => 'Kommende Abwesenheiten', 'abs_none' => 'Alle da — keine Abwesenheiten eingetragen. 🎉',
  'abs_past' => 'Vergangene Abwesenheiten',
  // Aufgaben
  'task_title' => 'Aufgaben', 'task_lbl' => 'Aufgabe', 'task_ph' => 'z. B. PA für Stadtfest organisieren',
  'task_who' => 'Wer?', 'task_due' => 'Bis wann', 'task_details' => 'Details',
  'task_add' => 'Aufgabe anlegen', 'task_toggle' => 'Status wechseln',
  'task_none' => 'Keine Aufgaben — entweder sehr gut organisiert oder sehr entspannt. 🍹',
  // Fotos
  'photos_upload_lbl' => 'Bilder (max. 10 MB pro Datei)', 'photos_caption' => 'Beschreibung',
  'photos_public_now' => 'Direkt öffentlich auf der Website zeigen',
  'photo_intern' => 'intern', 'photo_bg' => 'Hintergrund',
  'photo_bg_title' => 'Als Website-Hintergrund verwenden',
  'photos_none_intern' => 'Noch keine Fotos hochgeladen.',
  // Mitglieder & Profil
  'mem_title' => 'Mitglieder', 'mem_new' => 'Neues Mitglied',   'mem_you' => 'du', 'mem_my_profile' => 'Mein Profil',
  'mem_password' => 'Passwort', 'mem_new_pw' => 'Neues Passwort', 'mem_set' => 'Setzen',
  'mem_first_name' => 'Vorname', 'mem_last_name' => 'Nachname',
  'mem_name_hint' => 'Angezeigt wird „Vorname Nachname“ — oder der Künstlername, falls gesetzt.',
  'mem_mobile' => 'Mobil', 'mem_substitute_for' => 'Ersatz für',
  'mem_substitute_none' => '– niemanden –',
  'mem_instrument_pick' => 'aus dem Equipment wählen',
  'mem_instrument_free' => 'oder frei eintragen',
  'ev_sub_for' => 'Ersatz für',
  'ev_sub_ask' => 'anfragen', 'ev_sub_asked' => 'angefragt', 'ev_sub_open' => 'keine Antwort',
  'ev_sub_requested' => 'Angefragt:', 'ev_sub_withdraw' => 'Anfrage zurückziehen',
  'ev_sub_rehearsals' => 'Proben', 'ev_sub_gigs' => 'Auftritte',
  'mem_substitute_rank' => 'Reihenfolge als Ersatz',
  'mem_substitute_rank_hint' => 'Kleinere Zahl wird zuerst gefragt; 0 heißt „ohne Reihenfolge".',
  'fl_sub_requested' => 'Ersatz angefragt.', 'fl_sub_withdrawn' => 'Anfrage zurückgezogen.',
  'set_sub_auto' => 'Ersatz automatisch anfragen',
  'set_sub_auto_hint' => 'Sagt jemand ab, geht die Anfrage von selbst an einen seiner Ersatzleute. Sagt der auch ab, rückt der nächste nach.',
  'sub_auto_off' => 'aus — nur von Hand anfragen',
  'sub_auto_rank' => 'nach Reihenfolge', 'sub_auto_shuffle' => 'zufällig',
  'sub_auto_rotate' => 'reihum — wer am längsten nicht dran war',
  'mem_edit_admin' => 'Bearbeiten (Admin)', 'mem_own_role' => 'Eigene Rolle nicht änderbar',
  'prof_avatar_remove' => 'Avatar entfernen', 'prof_no_avatar' => 'Noch kein Avatar — unten hochladen.',
  'prof_lang' => 'Sprache', 'prof_avatar_lbl' => 'Avatar (Bild, max. 5 MB)',
  'prof_stage_name_ph' => 'Name auf der Bühne',
  'prof_pw_hint' => 'Passwort ändern: unter „Mitglieder" → 🔑 bei deinem Eintrag. Deine Rolle kann nur ein Admin ändern.',
  // Dateien
  'files_word' => 'Dateien',
  'files_none' => 'Noch keine Dateien — Verträge, Rechnungen, Rider, Aufnahmen ... (max. 20 MB pro Datei)',
  // Veranstalter-Downloads (intern)
  'dl_title' => 'Veranstalter-Downloads',
  'dl_intro' => 'Alles, was Veranstalter brauchen: Tech-Rider, Bühnenplan, Pressefotos, Logo in Druckqualität, Bandinfo. Diese Dateien sind — je nach Modus — ohne Login abrufbar.',
  'dl_release' => 'Freigabe', 'dl_mode' => 'Modus',
  'dl_mode_token' => 'Geheimer Link (nur wer den Link hat)',
  'dl_mode_public' => 'Öffentlich (mit Menüpunkt auf der Website)',
  'dl_mode_off' => 'Aus (niemand außer der Band)',
  'dl_new_token' => 'Neuen geheimen Link erzeugen (alter Link wird ungültig)',
  'dl_current_link' => 'Aktueller Link zum Weitergeben an Veranstalter:',
  'dl_public_note' => 'Öffentlich erreichbar unter /downloads — der Menüpunkt erscheint automatisch auf der Website.',
  'dl_none' => 'Noch keine Downloads — z. B. Tech-Rider, Bühnenplan, Pressefotos, Logo hochladen.',
  // Kalender-Abo
  'cal_title' => 'Kalender-Abo',
  'cal_intro' => 'Alle Band-Termine (Gigs, Proben, Sonstiges) automatisch in deiner Kalender-App — Änderungen erscheinen von selbst.',
  'cal_your_link' => 'Dein Abo-Link', 'cal_open_app' => 'In Kalender-App öffnen',
  'cal_open_hint' => 'öffnet auf den meisten Geräten direkt den Abo-Dialog',
  'cal_copy_manual' => 'Oder den Link manuell kopieren:',
  'cal_token_warn' => 'Der Link enthält ein geheimes Token — bitte nur innerhalb der Band weitergeben.',
  'cal_setup' => 'So richtest du es ein',
  'cal_ios_step1' => 'Oben auf „In Kalender-App öffnen" tippen — oder:',
  'cal_ios_step2' => 'Einstellungen → Apps → Kalender → Accounts → Account hinzufügen → Andere → Kalenderabo hinzufügen',
  'cal_ios_step3' => 'Den kopierten Link einfügen und Weiter → Sichern',
  'cal_and_step1' => 'Am Computer calendar.google.com öffnen (Abos lassen sich nur dort anlegen, nicht in der App)',
  'cal_and_step2' => 'Links neben „Weitere Kalender" auf + → Per URL',
  'cal_and_step3' => 'Den kopierten Link einfügen → Kalender hinzufügen',
  'cal_and_step4' => 'Der Kalender erscheint danach automatisch in der Google-Kalender-App auf dem Handy (ggf. in den App-Einstellungen unter Synchronisierung aktivieren)',
  'cal_out_step1' => 'Outlook (Web oder Desktop): Kalender → Kalender hinzufügen → Aus dem Internet abonnieren',
  'cal_out_step2' => 'Den kopierten Link einfügen, Namen vergeben → Importieren',
  'cal_tb_step1' => 'Kalender → Neuer Kalender → Im Netzwerk',
  'cal_tb_step2' => 'Format iCalendar (ICS), den kopierten Link als Adresse einfügen',
  'cal_note' => 'Hinweis: Kalender-Apps aktualisieren Abos in eigenen Intervallen (meist alle paar Stunden bis einmal täglich) — neue Termine erscheinen also nicht immer sofort.',
  // Übersetzungen
  'tr_title' => 'Übersetzungen',
  'tr_intro' => 'Leere Felder fallen automatisch auf Deutsch zurück. Besucher wählen die Sprache über den Umschalter im Kopfbereich.',
  'tr_col_de' => 'Deutsch (Standard)',
  // Einstellungen
  'set_bandprofile' => 'Bandprofil', 'set_bandname' => 'Bandname', 'set_contact_email' => 'Kontakt-E-Mail',
  'set_texts' => 'Texte (alle Sprachen)',
  'set_texts_hint' => 'Alle Sprachen in einer Box. Leere Felder fallen automatisch zurück: gewählte Sprache → Englisch → Deutsch.',
  'set_about' => 'Über uns', 'set_tagline' => 'Slogan', 'set_booking' => 'Booking-Text',
  'set_public' => 'Öffentliche Seite', 'set_pm' => 'Modus der öffentlichen Seite',
  'set_pm_website' => 'Volle Website (Start, Termine, Musik, Fotos, Kontakt)',
  'set_pm_redirect' => 'Nur Weiterleitung (z. B. zu Facebook) — Bandbereich, Kalender-Abo und Impressum bleiben erreichbar',
  'set_redirect_target' => 'Weiterleitungs-Ziel', 'set_show_past' => 'Auch vergangene Gigs anzeigen',
  'set_max_upcoming' => 'Max. kommende Termine (0 = alle)', 'set_max_past' => 'Max. vergangene Gigs (0 = alle)',
  'set_embed' => 'Externe Inhalte (YouTube/Spotify auf der Musik-Seite)',
  'set_embed_consent' => 'Konform: Zwei-Klick-Einwilligung (empfohlen, DSGVO/TDDDG)',
  'set_embed_direct' => 'Direkt laden: ohne Einwilligung (rechtlich auf eigene Verantwortung)',
  'set_langs' => 'Sprachen',
  'set_langs_hint' => 'Welche Sprachen im Auswahlmenü der Website erscheinen. Die Standardsprache bleibt immer aktiv, alle anderen könnt ihr abschalten.',
  'set_langs_default_locked' => 'Standardsprache',
  'set_default_lang' => 'Standardsprache',
  'set_default_lang_hint' => 'Besucher bekommen automatisch ihre Browsersprache, sofern sie hier aktiviert ist. Passt keine, gilt diese Standardsprache. Eingeloggte Mitglieder sehen ihre Sprache aus dem Profil.',
  'set_langs_check' => 'Übersetzungen prüfen und korrigieren',
  'set_legal' => 'Rechtliches (Pflichtseiten)',
  'set_legal_hint' => 'Impressum ist für Bands mit bezahlten Auftritten Pflicht (§ 5 DDG). Beide Seiten sind im Footer verlinkt. Platzhalter in eckigen Klammern bitte ersetzen. Verbindlich ist die deutsche Fassung.',
  'set_branding' => 'Logo & Hintergrund',
  'set_logo_lbl' => 'Logo (PNG mit Transparenz empfohlen, max. 5 MB)',
  'set_bg_lbl' => 'Hintergrundbild (wird abgedunkelt, max. 5 MB)',
  'set_logo_remove' => 'Logo entfernen', 'set_bg_remove' => 'Hintergrund entfernen',
  'set_favicon_lbl' => 'Site-Icon / Favicon (quadratisches PNG, max. 5 MB)',
  'set_favicon_remove' => 'Favicon entfernen',
  'set_media' => 'Musik & Videos (öffentliche Seite)',
  'set_media_hint' => 'YouTube- und Spotify-Links werden automatisch als Player eingebettet.',
  'set_ical' => 'Kalender-Abo (iCal)',
  'set_ical_hint' => 'Der Link enthält ein geheimes Token — nur mit der Band teilen. Schritt-für-Schritt-Anleitung für alle Geräte:',
  'set_ical_link' => 'Kalender-Abo einrichten',
  'set_demo' => 'Demodaten',
  'set_demo_hint' => 'Füllt die Installation mit einer erfundenen Band: Mitglieder, Songs, Setlists mit Pause und Zugabe, Termine samt Rückmeldungen, Orte, Aufgaben, Kassenbuchungen und Equipment mit Fristen. Zum Ausprobieren, bevor ihr eure eigenen Daten anlegt.',
  'set_demo_add' => 'Demodaten hinzufügen',
  'set_demo_remove' => 'Demodaten entfernen',
  'set_demo_active' => 'Demodaten sind eingespielt. Beim Entfernen wird ausschließlich das gelöscht, was mit den Demodaten angelegt wurde — eure eigenen Einträge bleiben erhalten.',
  'set_demo_confirm' => 'Alle Demodaten entfernen? Eigene Einträge bleiben erhalten.',
  'fl_csrf' => 'Die Aktion ist abgelaufen — bitte die Seite neu laden und noch einmal versuchen.',
  'fl_throttled' => 'Zu viele Fehlversuche. Bitte 15 Minuten warten.',
  'fl_upload_server_limit' => 'Die Datei war zu groß für den Server. Höchstens möglich:',
  'fl_upload_failed' => 'Der Upload hat nicht geklappt — bitte nochmal versuchen.',
  'fl_demo_added' => 'Demodaten eingespielt.',
  'fl_demo_removed' => 'Demodaten entfernt.',
  'set_meta' => 'Facebook- / Instagram-Sync',
  'set_meta_hint' => 'Vorbereitet, aber noch nicht verbunden. Für den automatischen Abgleich von Terminen und Posts braucht es eine Meta-Developer-App (Graph API) mit euren Seiten-Zugriffstokens. Sobald ihr die App bei Meta angelegt habt, bauen wir die Anbindung hier ein.',
  // Flash-Meldungen
  'fl_title_date_required' => 'Titel und Datum sind Pflicht.',
  'fl_locked_event' => 'Vergangene Termine sind fixiert und können nicht mehr geändert oder gelöscht werden.',
  'fl_song_played' => 'Dieser Song wurde schon live gespielt und bleibt für die Historie erhalten — stattdessen auf „Aussortiert" setzen.',
  'fl_setlist_locked' => 'Diese Setlist wurde bereits live gespielt und ist fixiert — zum Ändern bitte kopieren.',
  'fl_bg_set' => 'Foto als Hintergrund gesetzt.',
  'fl_period_invalid' => 'Bitte gültigen Zeitraum angeben.',
  'fl_file_too_big' => 'Datei zu groß (max. 20 MB).',
  'fl_dl_saved' => 'Download-Einstellungen gespeichert.',
  'fl_profile_saved' => 'Profil gespeichert.',
  'fl_email_taken' => 'Diese E-Mail ist schon vergeben.',
  'fl_name_email_required' => 'Name und E-Mail sind Pflicht.',
  'fl_member_updated' => 'Mitglied aktualisiert.',
  'fl_no_self_delete' => 'Du kannst dich nicht selbst löschen.',
  'fl_only_admin_pw' => 'Nur Admins können fremde Passwörter zurücksetzen.',
  'fl_pw_min' => 'Passwort braucht mindestens 8 Zeichen.',
  'fl_pw_changed' => 'Passwort geändert.',
  'fl_translations_saved' => 'Übersetzungen gespeichert.',
  'fl_texts_saved' => 'Texte gespeichert.',
  'fl_settings_saved' => 'Einstellungen gespeichert.',
  'fl_img_too_big' => 'Bild zu groß (max. 5 MB).',
  'fl_branding_saved' => 'Branding aktualisiert.',
  'fl_admin_required' => 'Dafür brauchst du Admin-Rechte.',
  // Rechte je Bereich
  'perm_title' => 'Rechte', 'perm_read' => 'sehen', 'perm_write' => 'ändern',
  'perm_intro' => 'Wer darf welchen Bereich sehen und ändern? Wer ändern darf, darf auch sehen.',
  'perm_admin_all' => 'Admins dürfen alles — Einstellungen, Übersetzungen und Sicherungen bleiben ihnen vorbehalten. Nur die Kasse nicht: die zu führen ist eine eigene Aufgabe und wird hier vergeben.',
  'perm_template' => 'Vorlage einsetzen', 'perm_tpl_member' => 'Mitglied', 'perm_tpl_ersatz' => 'Ersatz',
  'perm_tpl_hint' => 'Setzt alle Häkchen auf die Voreinstellung der Rolle zurück.',
  'perm_open' => 'Rechte vergeben',
  'fl_no_permission' => 'Dafür fehlt dir das Recht.',
  'fl_perm_saved' => 'Rechte gespeichert.',
  // Bandkasse
  'inav_kasse' => 'Kasse', 'fin_title' => 'Bandkasse', 'fin_balance' => 'Kontostand',
  'fin_income' => 'Einnahmen', 'fin_expense' => 'Ausgaben', 'fin_new' => 'Neue Buchung',
  'fin_type_in' => 'Einnahme', 'fin_type_out' => 'Ausgabe', 'fin_amount' => 'Betrag (€)',
  'fin_category' => 'Kategorie', 'fin_description' => 'Beschreibung',
  'fin_event' => 'Termin (optional)', 'fin_member' => 'Mitglied (optional)',
  'fin_add' => 'Buchen', 'fin_all_years' => 'Alle Jahre',
  'fin_none' => 'Noch keine Buchungen.', 'fin_by_category' => 'Nach Kategorie',
  'fin_import_gage' => 'Gage übernehmen', 'fin_open_fees' => 'Noch nicht verbuchte Gagen',
  // Systemprüfung
  'sys_title' => 'Systemprüfung',
  'sys_intro' => 'Was diese Installation kann — und was ihr fehlt, samt Folge.',
  'sys_required' => 'Notwendig', 'sys_optional' => 'Erweitert den Funktionsumfang',
  'sys_operation' => 'Betrieb',
  'sys_ok' => 'vorhanden', 'sys_missing' => 'fehlt',
  'sys_php_old' => 'zu alt', 'sys_php_old_hint' => 'Bandroadie braucht mindestens PHP 8.1.',
  'sys_ext_db' => 'Ohne diese Erweiterung gibt es keine Verbindung zur Datenbank.',
  'sys_ext_text' => 'Nötig für Umlaute und Sonderzeichen in allen Texten.',
  'sys_ext_files' => 'Nötig, um beim Hochladen den Dateityp zu erkennen.',
  'sys_ext_zlib' => 'Nötig für die Sicherungen — sie werden gepackt abgelegt.',
  'sys_writable' => 'beschreibbar', 'sys_not_writable' => 'nicht beschreibbar',
  'sys_not_writable_hint' => 'Uploads, Dateien und Sicherungen können nicht gespeichert werden. Dem Benutzer des Webservers Schreibrecht geben.',
  'sys_opt_gd' => 'Ohne Bildbibliothek gibt es keine verkleinerten Vorschauen — die Galerie lädt dann die Originale.',
  'sys_opt_curl' => 'Ohne curl lassen sich Fremddienste nicht abfragen; auch diese Prüfseite sieht dann weniger.',
  'sys_opt_ftp' => 'Ohne FTP fällt der zweite Sicherungsort weg; die Sicherung bleibt dann nur hier liegen.',
  'sys_opt_zip' => 'Nicht zwingend: Sicherungen werden als tar.gz geschrieben, das geht auch ohne.',
  'sys_opt_openssl' => 'Nötig für verschlüsselte Verbindungen beim Versand und beim FTP-Ziel.',
  'sys_https' => 'Verschlüsselte Verbindung',
  'sys_no_https' => 'aus', 'sys_no_https_hint' => 'Ohne HTTPS gehen Passwörter im Klartext über die Leitung, und die App lässt sich nicht auf dem Handy installieren.',
  'sys_site_url_empty' => 'nicht gesetzt',
  'sys_site_url_hint' => 'Ohne feste Adresse werden Links in E-Mails aus der Anfrage gebaut — angreifbar, wenn der Webserver fremde Hostnamen durchreicht.',
  'sys_no_backup' => 'noch keine', 'sys_no_backup_hint' => 'Es gibt keine frische Sicherung. Unter Sicherung einschalten oder von Hand anstoßen.',
  'sys_cache' => 'Zwischenspeicher für Dateien',
  'sys_cache_none' => 'keine Vorgabe', 'sys_cache_unknown' => 'nicht prüfbar',
  'sys_cache_hint' => 'Der Browser fragt bei jedem Seitenaufruf nach. Bei Apache genügt die mitgelieferte .htaccess; bei nginx die Anweisung aus der README.',
  // Daueraufträge
  'ord_title' => 'Daueraufträge', 'ord_new' => 'Neuer Dauerauftrag',
  'ord_intro' => 'Wiederkehrende Buchungen tragen sich selbst ein — Proberaum, GEMA, Versicherung.',
  'ord_scope' => 'Gilt für', 'ord_scope_band' => 'die Bandkasse', 'ord_scope_own' => 'mich selbst',
  'ord_scope_deposit' => 'meine Einzahlung in die Bandkasse',
  'ord_scope_hint' => 'Eine Einzahlung sehen alle, sie zählt als Einnahme der Band. Was für dich selbst läuft, sieht nur du.',
  'ord_interval' => 'Wie oft', 'ord_monthly' => 'monatlich', 'ord_quarterly' => 'vierteljährlich',
  'ord_yearly' => 'jährlich',
  'ord_start' => 'Erste Buchung', 'ord_end' => 'Letzte Buchung (frei lassen für „unbefristet")',
  'ord_next' => 'nächste Buchung', 'ord_paused' => 'pausiert',
  'ord_pause' => 'Pausieren', 'ord_resume' => 'Fortsetzen',
  'ord_none' => 'Noch keine Daueraufträge.',
  'ord_from_order' => 'aus einem Dauerauftrag',
  'fin_rent_cover' => 'Proberaum aus Einzahlungen',
  'fin_rent_cover_hint' => 'Die Einzahlungen der Mitglieder sind in erster Linie für Miete und Nebenkosten da.',
  'fin_rent_cost' => 'Miete und Nebenkosten',
  'fin_rent_deposits' => 'Einzahlungen der Mitglieder',
  'fin_rent_gap' => 'Die Band zahlt drauf',
  'fin_rent_surplus' => 'Übrig für die Bandkasse',
  'fin_private' => 'privat — siehst nur du',
  'fin_private_sum' => 'Eigene private Buchungen:',
  'fl_order_saved' => 'Dauerauftrag angelegt.', 'fl_order_deleted' => 'Dauerauftrag gelöscht — die gebuchten Beträge bleiben stehen.',
  'fl_order_paused' => 'Dauerauftrag pausiert.', 'fl_order_resumed' => 'Dauerauftrag läuft wieder.',
  'set_fin' => 'Bandkasse',
  'set_fin_open_fees' => 'Noch nicht verbuchte Gagen anzeigen',
  'set_fin_open_fees_hint' => 'Listet auf der Kassenseite alle Auftritte mit Gage, zu denen noch keine Einnahme gebucht ist — samt Knopf zum Übernehmen. Aus, solange ihr das nicht braucht.',
  'fincat_gage' => 'Gage', 'fincat_ausschuettung' => 'Ausschüttung',
  'fincat_einlage' => 'Einzahlung Mitglieder', 'fincat_merch' => 'Merch/Verkauf',
  'fincat_proberaum' => 'Proberaum', 'fincat_nebenkosten' => 'Nebenkosten',
  'fincat_equipment' => 'Equipment', 'fincat_gema' => 'GEMA',
  'fincat_fahrt' => 'Fahrtkosten', 'fincat_verpflegung' => 'Verpflegung', 'fincat_sonstiges' => 'Sonstiges',
  'fl_fin_saved' => 'Buchung gespeichert.', 'fl_fin_deleted' => 'Buchung gelöscht.',
  'fl_fin_invalid' => 'Bitte Datum, Beschreibung und gültigen Betrag angeben.',
  // Produktion (PA/Licht) und Bewertungen
  'prod_pa' => 'PA', 'prod_light' => 'Licht',
  'prod_eigene' => 'Eigenes Material', 'prod_leih' => 'Geliehen/Gemietet', 'prod_vorhanden' => 'Vor Ort vorhanden',
  'prod_none' => 'nicht festgelegt', 'prod_hint' => 'Angebote und Rechnungen kommen als Datei an den Termin.',
  'prod_gear' => 'Was nehmt ihr mit?', 'prod_gear_none' => 'Im Inventar steht noch nichts.',
  'eq_total' => 'Gesamtwert',
  'eq_value_own_only' => 'nur Bandeigentum und deine eigenen Geräte',
  'eq_price_hidden' => 'Kaufpreis und Kaufdatum sieht nur, wem das Gerät gehört.',
  // Sicherungen
  'bk_title' => 'Sicherung', 'bk_enabled' => 'Regelmäßig sichern',
  'bk_interval' => 'Wie oft', 'bk_daily' => 'täglich', 'bk_weekly' => 'wöchentlich',
  'bk_keep' => 'Wie viele Sicherungen aufheben',
  'bk_keep_hint' => 'Ist die Zahl erreicht, wird die älteste gelöscht — aber erst, wenn eine neue vollständig vorliegt.',
  'bk_run_now' => 'Jetzt sichern', 'bk_runs' => 'Letzte Läufe', 'bk_none' => 'Noch nichts gesichert.',
  'bk_gone' => 'nicht mehr vorhanden',
  'bk_status_ok' => 'erfolgreich', 'bk_status_error' => 'fehlgeschlagen',
  'bk_content' => 'Gesichert werden die Datenbank und alle hochgeladenen Dateien. Das Archiv liegt außerhalb des Webverzeichnisses.',
  'bk_auto_hint' => 'Ausgelöst wird beim Aufruf des Bandbereichs, höchstens einmal je Zeitraum. Wer einen Cronjob hat, ruft besser auf:',
  'bk_warn_old' => 'Die letzte erfolgreiche Sicherung ist älter als der eingestellte Zeitraum.',
  'bk_warn_failed' => 'Der letzte Lauf ist fehlgeschlagen.',
  // Ziele der Sicherung
  'bk_targets' => 'Wohin gesichert wird', 'bk_target_local' => 'Eigener Server',
  'bk_target_local_hint' => 'Immer aktiv. Das Archiv liegt außerhalb des Webverzeichnisses und lässt sich hier herunterladen.',
  'bk_target_ftp' => 'FTP-Server', 'bk_ftp_enabled' => 'Zusätzlich auf einen FTP-Server legen',
  'bk_ftp_host' => 'Server', 'bk_ftp_port' => 'Port', 'bk_ftp_user' => 'Benutzer',
  'bk_ftp_pass' => 'Passwort', 'bk_ftp_pass_set' => 'gespeichert — zum Ändern neu eingeben',
  'bk_ftp_dir' => 'Verzeichnis', 'bk_ftp_tls' => 'Verschlüsselt (FTPS)',
  'bk_ftp_passive' => 'Passiver Modus', 'bk_ftp_keep' => 'Wie viele dort aufheben',
  'bk_ftp_test' => 'Verbindung prüfen',
  'bk_ftp_note' => 'Das Passwort muss im Klartext gespeichert werden, sonst kann sich die Sicherung nicht anmelden. Es verlässt den Server nur in Richtung des eingetragenen Servers.',
  'bk_warn_ftp' => 'Die Sicherung liegt hier, konnte aber nicht auf den FTP-Server übertragen werden.',
  // Wiederherstellen
  'bk_restore' => 'Zurückspielen',
  'bk_restore_confirm' => 'Datenbank und hochgeladene Dateien werden durch den Stand dieser Sicherung ersetzt. Vorher wird automatisch eine Sicherheitskopie angelegt. Fortfahren?',
  'bk_restore_hint' => 'Zurückspielen ersetzt den gesamten Datenbestand. Der bisherige Stand wird vorher gesichert, und die alten Dateiordner bleiben daneben liegen.',
  'bk_restore_cli' => 'Startet die Seite gar nicht mehr, geht es auch über die Kommandozeile:',
  'bk_upload' => 'Archiv von außen einspielen',
  'bk_upload_hint' => 'Für den Fall, dass der Server neu aufgesetzt wurde und hier noch keine Sicherung liegt.',
  'bk_uploaded' => 'von außen eingespielt', 'bk_safety_made' => 'Sicherheitskopie:',
  'fl_bk_uploaded' => 'Archiv eingespielt.',
  'fl_bk_upload_invalid' => 'Das war keine Bandroadie-Sicherung (.tar.gz).',
  'fl_bk_missing' => 'Diese Sicherung liegt nicht mehr auf dem Server.',
  'bk_target_onedrive' => 'OneDrive',
  'bk_onedrive_pending' => 'Braucht eine Anmeldung bei Microsoft. Sobald die Verbindung für Dateien und Fotos steht, kann die Sicherung sie mitbenutzen.',
  'fl_bk_targets_saved' => 'Ziele gespeichert.',
  'fl_bk_done' => 'Sicherung erstellt.', 'fl_bk_failed' => 'Sicherung fehlgeschlagen:',
  'fl_bk_saved' => 'Einstellungen zur Sicherung gespeichert.', 'fl_bk_deleted' => 'Sicherung gelöscht.',
  'eq_owner_locked' => 'Preis, Besitzer und Kaufdatum ändern nur der Besitzer und die Verwaltung. Das Gerät umzuhängen gehört dazu — über das übergeordnete Gerät wechselt sonst der Besitzer mit.',
  'ev_gear' => 'Mitnehmen', 'ev_gear_conflict' => 'am selben Tag doppelt verplant',
  'rate_votes' => 'Stimmen', 'rate_vote' => 'Stimme', 'ev_export' => 'Tabelle', 'rate_none' => 'noch nicht bewertet', 'rate_clear' => 'Bewertung zurücknehmen',
  'rate_hint' => 'Wie gern spielt ihr den Song? Nur der Schnitt ist für alle sichtbar.',
  'songs_col_rating' => 'Bewertung',
  // Stagerider
  'inav_rider' => 'Stagerider', 'rider_title' => 'Stagerider',
  'rider_intro' => 'Alles, was ein Veranstalter über eure Technik wissen muss — die Inputliste kommt automatisch aus der Kanalbelegung.',
  'rider_requirements' => 'Anforderungen',
  'rider_stage_lbl' => 'Bühne (Größe, Podeste, Dach)',
  'rider_power_lbl' => 'Strom', 'rider_pa_lbl' => 'PA / Beschallung', 'rider_monitor_lbl' => 'Monitoring',
  'rider_light_lbl' => 'Licht', 'rider_getin_lbl' => 'Anlieferung, Aufbau, Soundcheck',
  'rider_extras_lbl' => 'Sonstiges (Parken, Catering, Backstage)',
  // Bühnenplan
  'set_site_url' => 'Feste Adresse dieser Installation',
  'set_site_url_hint' => 'Wird für Links in E-Mails und im Kalender benutzt. Leer lassen heißt: aus der Anfrage übernehmen — eingetragen ist sicherer.',
  'app_description' => 'Termine, Setlists und Technik der Band — auch unterwegs.',
  'app_install' => 'Auf dem Handy installieren',
  'app_install_hint' => 'Im Browsermenü „Zum Startbildschirm hinzufügen" wählen. Danach startet Bandroadie wie eine App, und Termine, Setlists und Songs sind auch ohne Empfang da.',
  'stage_plot' => 'Bühnenplan', 'stage_back' => 'hinten', 'stage_front' => 'vorne (Publikum)',
  'stage_empty' => 'Noch nichts aufgestellt.',
  'stage_add' => 'Aufstellen', 'stage_kind' => 'Was', 'stage_label' => 'Beschriftung',
  'stage_x' => 'Links–rechts (%)', 'stage_y' => 'Hinten–vorne (%)', 'stage_note' => 'Zusatz',
  'stage_from_members' => 'Aus der Mitgliederliste erzeugen',
  'stage_from_members_hint' => 'Setzt eine Vorlage nach Instrument — Schlagzeug hinten Mitte, Bass hinten links, Gesang vorne. Danach lässt sich alles verschieben.',
  'stage_hint' => 'Der Plan geht in den Stagerider und in den Ausdruck. Vorne ist unten, so wie das Publikum schaut.',
  'stage_replace_warn' => 'Der bisherige Plan wird dabei ersetzt. Fortfahren?',
  'stage_drag_hint' => 'Zum Verschieben die Zahlen ändern — oder das Symbol im Plan ziehen.',
  'stagekind_musiker' => 'Musiker', 'stagekind_amp' => 'Verstärker', 'stagekind_podest' => 'Podest',
  'stagekind_keyboard' => 'Keyboard', 'stagekind_monitor' => 'Monitor', 'stagekind_di' => 'DI-Box',
  'stagekind_strom' => 'Strom', 'stagekind_sonstiges' => 'Sonstiges',
  'fl_stage_saved' => 'Bühnenplan gespeichert.', 'fl_stage_deleted' => 'Vom Plan genommen.',
  'rider_positions_lbl' => 'Bühnenaufstellung (Text)',
  'rider_positions_ph' => "z. B.
Schlagzeug: hinten Mitte, Podest 2 × 2 m
Bass: hinten links
Gitarre: vorne rechts",
  'rider_contacts' => 'Ansprechpartner',
  'rider_contact_tech_lbl' => 'Technik', 'rider_contact_booking_lbl' => 'Booking',
  'rider_inputs' => 'Inputliste', 'rider_inputs_from' => 'Kanalbelegung bearbeiten',
  'rider_inputs_empty' => 'Noch keine Kanäle hinterlegt — die Inputliste bleibt leer.',
  'rider_print' => 'Druckansicht', 'rider_empty_hint' => 'Leere Felder werden im Ausdruck weggelassen.',
  'rider_for' => 'Technische Anforderungen',
  'fl_rider_saved' => 'Stagerider gespeichert.',
  // Kanalbelegung
  'inav_kanaele' => 'Kanäle', 'ch_title' => 'Kanalbelegung',
  'ch_intro' => 'Die Belegung eures Mischpults — entweder aus einer Szenendatei eingelesen oder von Hand gepflegt. Sie ist die Grundlage für die Inputliste im Stagerider.',
  'ch_import' => 'Aus Mischpult-Backup einlesen',
  'ch_import_hint' => 'Szenendatei von Behringer X32/M32 oder WING (.scn). Vorhandene Kanäle mit gleicher Nummer werden aktualisiert, eigene Notizen bleiben erhalten.',
  'ch_file' => 'Szenendatei', 'ch_replace' => 'Vorhandene Belegung vorher leeren',
  'ch_number' => 'Kanal', 'ch_name' => 'Bezeichnung', 'ch_source' => 'Quelle / Mikrofon',
  'ch_add' => 'Kanal hinzufügen', 'ch_none' => 'Noch keine Kanäle — Szenendatei hochladen oder von Hand anlegen.',
  'ch_count' => 'Kanäle', 'ch_export' => 'Tabelle',
  'fl_ch_imported' => 'Kanäle eingelesen:', 'fl_ch_none_found' => 'In der Datei wurden keine Kanalnamen gefunden.',
  'fl_ch_saved' => 'Kanal gespeichert.', 'fl_ch_deleted' => 'Kanal gelöscht.',
  // Diskussionen
  'inav_themen' => 'Themen', 'topic_new' => 'Neues Thema', 'topic_title_ph' => 'Worum geht es?',
  'topic_first_post' => 'Dein erster Beitrag', 'topic_open' => 'Öffnen',
  'topic_posts' => 'Beiträge', 'topic_last' => 'zuletzt', 'topic_none' => 'Noch keine Themen — fang eins an.',
  'topic_reply' => 'Antworten', 'topic_reply_ph' => 'Antwort schreiben ...',
  'topic_close' => 'Thema schließen', 'topic_reopen' => 'Wieder öffnen',
  'topic_closed' => 'geschlossen', 'topic_closed_hint' => 'Dieses Thema ist geschlossen — zum Antworten wieder öffnen.',
  'topic_back' => 'Alle Themen', 'topic_by' => 'von',
  'fl_topic_created' => 'Thema angelegt.', 'fl_topic_deleted' => 'Thema gelöscht.',
  'fl_post_deleted' => 'Beitrag gelöscht.',
  // Über Bandroadie
  'about_title' => 'Über Bandroadie',
  'about_tagline' => 'Website und Organisation für Bands — Termine, Setlists, Songs, Kasse, Equipment.',
  'about_credits' => 'Entwicklung',
  'about_by' => 'Entwickelt von',
  'about_contributors' => 'Mitwirkende',
  'about_thanks' => 'Gebaut von einer Band, die lieber laut spielt als Listen führt — weil „wer hat nochmal die Setlist?" irgendwann keiner mehr hören konnte. Ideen für mehr liegen noch genug rum.',
  'about_project' => 'Projekt',
  'about_license' => 'Lizenz', 'about_source' => 'Quellcode', 'about_version' => 'Version',
  'about_changelog' => 'Was ist neu?', 'about_stack' => 'Technik',
  'about_data_note' => 'Alle Inhalte dieser Instanz — Termine, Songs, Fotos, Dateien — gehören der Band, nicht dem Projekt.',
  'about_license_note' => 'Frei nutzbar für die eigene Band — nur das Anbieten als kommerzieller Dienst bleibt dem Urheber vorbehalten.',
  'about_settings_hint' => 'Version, Lizenz, Quellcode und wer dahintersteckt.',
  'about_open' => 'Öffnen',
  'set_copyright' => 'Copyright-Zeile im Footer',
  'set_copyright_hint' => 'Leer lassen für „© Jahr Bandname" — Jahr wird automatisch gesetzt.',
  // Passwort-Onboarding
  'pw_change_title' => 'Passwort ändern',
  'pw_forced_hint' => 'Willkommen! Bitte vergib jetzt dein eigenes Passwort, bevor es weitergeht.',
  'pw_new' => 'Neues Passwort', 'pw_repeat' => 'Passwort wiederholen',
  'pw_weak' => 'schwach', 'pw_medium' => 'mittel', 'pw_strong' => 'stark', 'pw_very_strong' => 'sehr stark',
  'mem_invite_hint' => 'Das Start-Passwort wird automatisch erzeugt und per E-Mail verschickt. Beim ersten Login muss es geändert werden.',
  'fl_pw_mismatch' => 'Die Passwörter stimmen nicht überein.',
  'fl_member_created_mail' => 'Mitglied angelegt — die Zugangsdaten wurden per E-Mail verschickt.',
  'fl_member_created_nomail' => 'Mitglied angelegt. E-Mail-Versand nicht möglich — bitte dieses Start-Passwort weitergeben:',
  // Passwort vergessen
  'pwreset_link' => 'Passwort vergessen?',
  'pwreset_title' => 'Passwort vergessen',
  'pwreset_intro' => 'Gib deine E-Mail-Adresse ein — wenn sie zu einem Konto gehört, schicken wir dir einen Link zum Zurücksetzen.',
  'pwreset_send' => 'Link anfordern',
  'pwreset_sent' => 'Wenn die Adresse existiert, ist ein Link zum Zurücksetzen unterwegs (1 Stunde gültig).',
  'pwreset_invalid' => 'Der Link ist ungültig oder abgelaufen — bitte neu anfordern.',
  'pwreset_new_title' => 'Neues Passwort vergeben',
  // Finanz-Recht
  // Equipment
  'inav_equipment' => 'Equipment',
  'eq_new' => 'Neues Equipment', 'eq_cat' => 'Kategorie',
  'eqcat_instrument' => 'Instrument', 'eqcat_pa' => 'PA/Ton', 'eqcat_licht' => 'Licht',
  'eqcat_transport' => 'Transport', 'eqcat_sonstiges' => 'Sonstiges',
  'eq_owner' => 'Gehört', 'eq_owner_band' => 'Band', 'eq_location' => 'Lagerort',
  'eq_standard' => 'Standard-Packliste für Konzerte',
  'eq_standard_badge' => 'Packliste',
  'eq_none' => 'Noch kein Equipment erfasst.',
  'eq_parent' => 'Gehört zu', 'eq_parent_none' => '– eigenständig –',
  'eq_slot' => 'Steckplatz / Kanal', 'eq_slot_ph' => 'z. B. Kanal 1',
  'eq_parts' => 'Bestandteile',
  'close' => 'Schließen',
  'eq_inherit_hint' => 'Besitzer und Lagerort übernimmt das Bestandteil vom übergeordneten Gerät.',
  'eq_purchased' => 'Kaufdatum', 'eq_price' => 'Kaufpreis',
  'eq_price_each' => 'Kaufpreis (je Stück)',
  'eq_count' => 'Anzahl', 'eq_count_hint' => 'Mehr als eins legt gleich mehrere durchnummerierte Geräte an — praktisch bei Kabeln.',
  'eq_value_sum' => 'Anschaffungswert',
  'eq_deadline_new' => 'Neue Frist',
  'eq_deadline_title_ph' => 'z. B. TÜV, Steuer, Versicherung',
  'eq_due' => 'Fällig am', 'eq_interval' => 'Wiederholung',
  'eq_interval_0' => 'einmalig', 'eq_interval_6' => 'halbjährlich',
  'eq_interval_12' => 'jährlich', 'eq_interval_24' => 'alle 2 Jahre',
  'eq_done' => 'Erledigt ✓',
  'eq_done_hint' => '„Erledigt" schiebt wiederkehrende Fristen automatisch um ihr Intervall weiter.',
  'eq_overdue' => 'überfällig', 'eq_due_soon' => 'fällig in', 'eq_days' => 'Tagen',
  'dash_deadlines' => 'Anstehende Fristen',
  'fl_eq_saved_n' => '%d Geräte angelegt.',
  'eqb_title' => 'In der Kasse',
  'eqb_payer' => 'Bezahlt hat', 'eqb_payer_band' => 'die Band', 'eqb_payer_private' => 'ich privat',
  'eqb_payer_gets' => 'Der Erlös geht an',
  'eqb_book_purchase' => 'Kauf über %s buchen',
  'eqb_hint' => 'Zahlt die Band, ist es eine Ausgabe wie jede andere. Zahlst du privat, sieht die Buchung nur du und sie zählt nicht zum Bandvermögen.',
  'eqb_needs_price' => 'Trag erst einen Kaufpreis ein, dann lässt sich der Kauf buchen.',
  'eqb_show' => 'in der Kasse',
  'eqb_bought_prefix' => 'Kauf', 'eqb_sold_prefix' => 'Verkauf',
  'eqb_dispose' => 'Verkauft oder ausgemustert',
  'eqb_dispose_hint' => 'Das Gerät bleibt als Geschichte stehen, zählt aber nicht mehr zum Bestand und kommt auf keine Packliste mehr. Ohne Erlös ist es eine Ausmusterung.',
  'eqb_proceeds' => 'Erlös',
  'eqb_disposed_on' => 'abgegeben am %s',
  'eqb_reactivate' => 'Doch wieder in den Bestand',
  'fl_eq_booked' => 'Kauf in der Kasse gebucht.',
  'fl_eq_disposed' => 'Gerät als abgegeben vermerkt.',
  'fl_eq_reactivated' => 'Gerät ist wieder im Bestand.',
  'fl_eq_book_needs_price' => 'Ohne Kaufpreis lässt sich nichts buchen.',
  'fl_eq_booked_already' => 'Dieser Kauf ist bereits gebucht.',
  'eq_split' => 'In einzelne Geräte aufteilen',
  'eq_split_found' => '(sieht nach %d Stück aus)',
  'eq_split_hint' => 'Steht diese Zeile für mehrere gleiche Geräte, macht das daraus durchnummerierte Einzelgeräte — jedes mit eigenem Preis, eigener Frist und eigenem Häkchen auf der Packliste. Die Stückzahl im Namen entfällt dabei.',
  'fl_eq_split' => 'In %d Geräte aufgeteilt.',
  'fl_eq_split_impossible' => 'Aufteilen geht nur ab zwei Stück und nur bei Geräten ohne Bestandteile.',
  'fl_eq_saved' => 'Equipment gespeichert.', 'fl_eq_deleted' => 'Equipment gelöscht.',
  'fl_deadline_saved' => 'Frist gespeichert.',
  'fl_deadline_done' => 'Frist erledigt — nächster Termin gesetzt.',
  'fl_deadline_done_once' => 'Frist erledigt und entfernt.',
  'fl_deadline_deleted' => 'Frist gelöscht.',
  'fin_badge' => 'Finanz',
  'fin_readonly_hint' => 'Buchungen macht, wer die Kasse verwaltet (Finanz) — du kannst hier alles einsehen.',
  'fl_finance_required' => 'Buchungen darf nur machen, wer die Kasse verwaltet (Finanz).',
];

// Bandkassen-Kategorien
const FIN_CATEGORIES = [
  'gage' => 'Gage', 'ausschuettung' => 'Ausschüttung', 'einlage' => 'Einzahlung Mitglieder',
  'merch' => 'Merch/Verkauf', 'proberaum' => 'Proberaum', 'nebenkosten' => 'Nebenkosten',
  'equipment' => 'Equipment', 'gema' => 'GEMA', 'fahrt' => 'Fahrtkosten',
  'verpflegung' => 'Verpflegung', 'sonstiges' => 'Sonstiges',
];

// Wofür die Einzahlungen der Mitglieder in erster Linie da sind. Die Kasse
// stellt beides gegenüber, damit man sieht, was die Band selbst draufzahlt.
const FIN_DEPOSIT_COVERS = ['proberaum', 'nebenkosten'];

// Eine Stückzahl steht allein oder in Klammern: „4×", „(2x)". Mitten im Text
// ist sie keine — „4x4 Case" heißt nicht vier Cases.
const EQ_QUANTITY_RE = '~(?:^|\()\s*(\d{1,2})\s*[x×]\s*(?:\)|$)~ui';

// Welche Felder bei welcher Termin-Art sinnvoll sind — der Rest wird im
// Formular ausgeblendet. Die öffentliche Seite zeigt ausschließlich Gigs,
// deshalb hat der öffentliche Block bei allen anderen Arten keine Wirkung.
/**
 * Welche Felder eine Terminart braucht.
 *
 * 'production' ist die Frage „woher kommen PA und Licht" — die stellt sich
 * nur, wo beschallt wird. 'gear' ist die Packliste, also „was nehmen wir
 * mit"; die stellt sich überall, wo etwas ins Auto geladen wird, auch bei
 * einer Probe oder einer Aufnahme. Zwei Fragen, zwei Gruppen.
 */
const EVENT_TYPE_FIELDS = [
  'gig'          => ['times', 'venue', 'setlist', 'fee', 'production', 'gear', 'public'],
  'probe'        => ['times', 'venue', 'setlist', 'gear'],
  'aufnahme'     => ['times', 'venue', 'setlist', 'gear'],
  'fotoshooting' => ['times', 'venue', 'gear'],
  'aufbau'       => ['times', 'venue', 'production', 'gear'],
  'reise'        => ['times', 'venue', 'gear'],
  'besprechung'  => ['times', 'venue'],
  // Party und Sonstiges sind Termine, bei denen die Band nicht spielt —
  // eine Feier, ein Theaterbesuch. Wo die Band auftritt, ist es ein Gig.
  'party'        => ['times', 'venue'],
  'sonstiges'    => ['times', 'venue'],
  'dayoff'       => [],
];

/**
 * Rechte je Bereich. Der Schlüssel ist der Bereich, dahinter stehen die Pfade,
 * die dazugehören — daran hängt die Prüfung im Front-Controller.
 *
 * Nicht in der Liste und deshalb weiterhin allein den Admins vorbehalten:
 * Einstellungen, Übersetzungen, Sicherungen und die Demodaten. Wer sie
 * bekommt, ist ohnehin Admin; ein Häkchen dafür wäre nur Schein.
 */
const PERM_MODULES = [
  'termine'       => ['/intern/termine', '/intern/kalender', '/intern/kommentare'],
  'songs'         => ['/intern/songs'],
  'setlists'      => ['/intern/setlists'],
  'orte'          => ['/intern/orte'],
  'abwesenheiten' => ['/intern/abwesenheiten'],
  'aufgaben'      => ['/intern/aufgaben'],
  'themen'        => ['/intern/themen', '/intern/thema', '/intern/beitrag'],
  'kasse'         => ['/intern/kasse'],
  'equipment'     => ['/intern/equipment'],
  'rider'         => ['/intern/stagerider', '/intern/kanaele'],
  'fotos'         => ['/intern/fotos'],
  'musik'         => ['/intern/musik'],
  'downloads'     => ['/intern/downloads'],
  'mitglieder'    => ['/intern/mitglieder'],
];

/** Dateianhänge gehören zum Bereich der Sache, an der sie hängen. */
const PERM_ENTITY_MODULES = [
  'event' => 'termine', 'song' => 'songs', 'venue' => 'orte',
  'equipment' => 'equipment', 'setlist' => 'setlists',
];

/**
 * Voreinstellung je Rolle: [lesen, schreiben]. Wer neu angelegt wird, bekommt
 * diese Rechte; danach lassen sie sich einzeln ändern. Admins stehen nicht in
 * der Liste — sie dürfen alles, sonst könnte sich niemand mehr helfen.
 */
/**
 * Bereiche, die auch ein Admin ausdrücklich bekommen muss.
 *
 * Die Kasse ist eine Rolle, kein Nebeneffekt der Verwaltung: wer sie führt,
 * soll das entschieden haben, und in der Rechtematrix soll es jeder sehen.
 * Ohne eigene Zeile fällt ein Admin auf das Mitglieder-Schema zurück und darf
 * die Kasse sehen, aber nicht buchen — den Einblick verliert also niemand.
 */
const PERM_EXPLICIT_MODULES = ['kasse'];

const PERM_TEMPLATES = [
  'member' => [
    'termine' => [1, 1], 'songs' => [1, 1], 'setlists' => [1, 1], 'orte' => [1, 1],
    'abwesenheiten' => [1, 1], 'aufgaben' => [1, 1], 'themen' => [1, 1],
    'kasse' => [1, 0], 'equipment' => [1, 1], 'rider' => [1, 1],
    'fotos' => [1, 1], 'musik' => [1, 1], 'downloads' => [1, 1], 'mitglieder' => [1, 0],
  ],
  // Wer nur einspringt, braucht die Termine, für die er eingeplant ist, und
  // das Material dazu — nicht die Kasse und nicht die Bandinterna. Der
  // Stagerider und die Kanalbelegung gehören dazu: „auf welchem Kanal liegt
  // mein Mikrofon" ist die erste Frage am Aufbautag.
  'ersatz' => [
    'termine' => [1, 0], 'songs' => [1, 0], 'setlists' => [1, 0], 'orte' => [0, 0],
    'abwesenheiten' => [0, 0], 'aufgaben' => [0, 0], 'themen' => [0, 0],
    'kasse' => [0, 0], 'equipment' => [0, 0], 'rider' => [1, 0],
    'fotos' => [0, 0], 'musik' => [0, 0], 'downloads' => [0, 0], 'mitglieder' => [0, 0],
  ],
];

/**
 * Was auf einem Bühnenplan stehen kann. Der Schlüssel landet in der
 * Datenbank, das Zeichen im Plan.
 */
const STAGE_KINDS = [
  'musiker'  => '🧍', 'amp' => '🔊', 'podest' => '⬛', 'keyboard' => '🎹',
  'monitor'  => '📢', 'di' => '🔌', 'strom' => '⚡', 'sonstiges' => '▫',
];

/**
 * Standardaufstellung aus der Mitgliederliste. Schlagzeug hinten Mitte, Bass
 * hinten links, der Rest verteilt sich nach vorn — eine Vorlage zum
 * Verschieben, kein Anspruch auf Richtigkeit.
 */
function stage_default_items(array $members): array {
  // Grobe Zuordnung vom Instrument auf einen Platz [x, y]; y = 0 ist hinten
  $spots = [
    'schlagzeug' => [50, 12], 'drums' => [50, 12], 'percussion' => [70, 18],
    'bass'       => [22, 25], 'keyboard' => [78, 30], 'keys' => [78, 30],
    'gitarre'    => [25, 60], 'e-gitarre' => [25, 60], 'guitar' => [25, 60],
    'gesang'     => [50, 78], 'vocals' => [50, 78], 'saxophon' => [75, 62],
  ];
  $items = [];
  $fallback = [[38, 68], [66, 68], [12, 48], [88, 48], [50, 42]];
  $taken = [];
  foreach (array_values($members) as $i => $m) {
    $key = mb_strtolower(trim((string) ($m['instrument'] ?? '')));
    [$x, $y] = $spots[$key] ?? ($fallback[$i % count($fallback)]);
    // Zwei Namen übereinander kann niemand lesen — wer zu dicht landet,
    // rückt zur Seite, abwechselnd nach rechts und links.
    for ($try = 0; $try < 8; $try++) {
      $clash = false;
      foreach ($taken as [$tx, $ty]) {
        if (abs($tx - $x) < 14 && abs($ty - $y) < 14) { $clash = true; break; }
      }
      if (!$clash) break;
      $x = max(4, min(96, $x + ($try % 2 ? -1 : 1) * 15 * (int) ceil(($try + 1) / 2)));
    }
    $taken[] = [$x, $y];
    $items[] = ['kind' => 'musiker', 'label' => $m['stage_name'] ?: $m['name'],
                'x' => $x, 'y' => $y, 'note' => (string) ($m['instrument'] ?? '')];
  }
  // Strom gehört auf jeden Plan, sonst fragt der Veranstalter genau danach
  $items[] = ['kind' => 'strom', 'label' => 'Strom', 'x' => 8, 'y' => 8, 'note' => '230 V'];
  $items[] = ['kind' => 'strom', 'label' => 'Strom', 'x' => 92, 'y' => 8, 'note' => '230 V'];
  return $items;
}

// Woher PA und Licht bei einem Termin kommen
const PRODUCTION_SOURCES = ['eigene' => 'Eigenes Material', 'leih' => 'Geliehen/Gemietet', 'vorhanden' => 'Vor Ort vorhanden'];

// Equipment-Kategorien
const EQ_CATEGORIES = [
  'instrument' => 'Instrument', 'pa' => 'PA/Ton', 'licht' => 'Licht',
  'transport' => 'Transport', 'sonstiges' => 'Sonstiges',
];

// Song-Lebenszyklus
const SONG_STATUS = [
  'vorschlag' => 'Vorschlag', 'in_arbeit' => 'In Vorbereitung', 'aktiv' => 'Aktives Repertoire',
  'abgewiesen' => 'Abgewiesen', 'archiv' => 'Aussortiert',
];

// ---------- Schema ----------
$tables = [
  "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    instrument VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(10) NOT NULL DEFAULT 'gig',
    title VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    time VARCHAR(5) NOT NULL DEFAULT '',
    time_meet VARCHAR(5) NOT NULL DEFAULT '',
    time_end VARCHAR(5) NOT NULL DEFAULT '',
    location VARCHAR(255) NOT NULL DEFAULT '',
    notes TEXT,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    setlist_id INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'bestaetigt',
    responsible_id INT NULL,
    fee VARCHAR(100) NOT NULL DEFAULT '',
    invoice_no VARCHAR(100) NOT NULL DEFAULT '',
    public_title VARCHAR(255) NOT NULL DEFAULT '',
    public_link VARCHAR(500) NOT NULL DEFAULT '',
    public_info VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS attendance (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(10) NOT NULL,
    PRIMARY KEY (event_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NULL,
    text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS songs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL DEFAULT '',
    song_key VARCHAR(20) NOT NULL DEFAULT '',
    tempo VARCHAR(50) NOT NULL DEFAULT '',
    duration_sec INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'aktiv',
    notes TEXT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS setlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS setlist_songs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setlist_id INT NOT NULL,
    song_id INT NULL,
    is_break TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(190) NOT NULL DEFAULT '',
    address VARCHAR(500) NOT NULL DEFAULT '',
    notes TEXT,
    contact_name VARCHAR(190) NOT NULL DEFAULT '',
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    contact_phone VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    notes TEXT,
    assigned_to INT NULL,
    due_date VARCHAR(10) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    caption VARCHAR(500) NOT NULL DEFAULT '',
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS media_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(20) NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL DEFAULT '',
    url VARCHAR(500) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    size INT NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'sonstiges',
    owner_id INT NULL,
    location VARCHAR(255) NOT NULL DEFAULT '',
    is_standard TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Bühnenplan: was wo steht. x und y sind Prozent der Bühnenfläche, damit
  // der Plan bei jeder Bühnengröße stimmt.
  "CREATE TABLE IF NOT EXISTS stage_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(20) NOT NULL DEFAULT 'musiker',
    label VARCHAR(120) NOT NULL DEFAULT '',
    x TINYINT UNSIGNED NOT NULL DEFAULT 50,
    y TINYINT UNSIGNED NOT NULL DEFAULT 50,
    note VARCHAR(190) NOT NULL DEFAULT '',
    position INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Wer für einen Termin als Ersatz angefragt wurde. Ohne Eintrag hier sieht
  // der Ersatz den Termin nicht — angefragt wird ausdrücklich, nicht daraus
  // abgeleitet, dass jemand abgesagt hat.
  "CREATE TABLE IF NOT EXISTS substitute_requests (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    for_user_id INT NULL,
    requested_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, user_id),
    INDEX idx_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Daueraufträge: wiederkehrende Buchungen, die sich selbst eintragen.
  // owner_id NULL heißt „für die Bandkasse"; steht dort jemand, ist es sein
  // eigener und geht nur ihn etwas an.
  "CREATE TABLE IF NOT EXISTS standing_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NULL,
    type VARCHAR(10) NOT NULL DEFAULT 'ausgabe',
    amount_cents INT NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'sonstiges',
    description VARCHAR(255) NOT NULL,
    interval_kind VARCHAR(12) NOT NULL DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    next_date DATE NOT NULL,
    paused TINYINT(1) NOT NULL DEFAULT 0,
    private TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_next (next_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Rechte je Mitglied und Bereich; fehlt die Zeile, gibt es kein Recht
  "CREATE TABLE IF NOT EXISTS permissions (
    user_id INT NOT NULL,
    module VARCHAR(30) NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 0,
    can_write TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, module)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Jeder Sicherungslauf, auch der fehlgeschlagene
  "CREATE TABLE IF NOT EXISTS backup_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(190) NOT NULL DEFAULT '',
    size_bytes BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'ok',
    message VARCHAR(400) NOT NULL DEFAULT '',
    trigger_kind VARCHAR(10) NOT NULL DEFAULT 'auto'
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Welche Geräte bei einem Termin mitkommen — die Packliste zum Gig
  "CREATE TABLE IF NOT EXISTS event_equipment (
    event_id INT NOT NULL,
    equipment_id INT NOT NULL,
    PRIMARY KEY (event_id, equipment_id),
    INDEX idx_equipment (equipment_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS equipment_deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    title VARCHAR(190) NOT NULL,
    due_date DATE NOT NULL,
    interval_months INT NOT NULL DEFAULT 0,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    INDEX idx_due (due_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS finances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    type VARCHAR(10) NOT NULL DEFAULT 'ausgabe',
    amount_cents INT NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'sonstiges',
    description VARCHAR(255) NOT NULL,
    event_id INT NULL,
    member_id INT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS translations (
    lang VARCHAR(5) NOT NULL,
    tkey VARCHAR(64) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (lang, tkey)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number INT NOT NULL,
    name VARCHAR(190) NOT NULL DEFAULT '',
    source VARCHAR(190) NOT NULL DEFAULT '',
    notes VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE KEY uniq_number (number)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    created_by INT NULL,
    closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS topic_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NULL,
    text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_topic (topic_id, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS song_ratings (
    song_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    PRIMARY KEY (song_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    k VARCHAR(190) NOT NULL,
    ts DATETIME NOT NULL,
    INDEX idx_k (k, ts)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS demo_rows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    row_id INT NOT NULL,
    INDEX idx_table (table_name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($tables as $ddl) $db->exec($ddl);

// ---------- Migrationen für bestehende Installationen ----------
function column_exists(string $table, string $column): bool {
  global $db, $config;
  $st = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
  $st->execute([$config['db_name'], $table, $column]);
  return $st->fetch() !== false;
}
if (!column_exists('events', 'venue_id')) {
  $db->exec('ALTER TABLE events ADD COLUMN venue_id INT NULL AFTER location');
}
if (!column_exists('users', 'pref_lang')) {
  $db->exec("ALTER TABLE users ADD COLUMN pref_lang VARCHAR(5) NOT NULL DEFAULT 'de'");
}
if (!column_exists('users', 'must_change_pw')) {
  $db->exec("ALTER TABLE users ADD COLUMN must_change_pw TINYINT(1) NOT NULL DEFAULT 0");
}
// events.type war VARCHAR(10) — zu kurz für "besprechung" und "fotoshooting",
// diese beiden Termin-Arten ließen sich dadurch nicht speichern.
$typeLen = row("SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.columns
                WHERE table_schema = ? AND table_name = 'events' AND column_name = 'type'", [$config['db_name']]);
if ($typeLen && (int) $typeLen['len'] < 20) {
  $db->exec("ALTER TABLE events MODIFY type VARCHAR(20) NOT NULL DEFAULT 'gig'");
}
foreach ([
  'parent_id'     => 'INT NULL',
  'slot'          => "VARCHAR(60) NOT NULL DEFAULT ''",
  'purchased_on'  => 'DATE NULL',
  'price_cents'   => 'INT NULL',
] as $eqCol => $eqDdl) {
  if (!column_exists('equipment', $eqCol)) $db->exec("ALTER TABLE equipment ADD COLUMN `$eqCol` $eqDdl");
}
// Ergebnis des Zweitziels je Lauf: NULL = nicht eingerichtet, 0 = fehlgeschlagen
if (!column_exists('backup_runs', 'ftp_ok')) {
  $db->exec('ALTER TABLE backup_runs ADD COLUMN ftp_ok TINYINT(1) NULL');
}
// Woher eine Buchung stammt: von Hand oder aus einem Dauerauftrag. Ohne den
// Verweis ließe sich ein falscher Betrag später nicht zurückverfolgen.
if (!column_exists('finances', 'standing_order_id')) {
  $db->exec('ALTER TABLE finances ADD COLUMN standing_order_id INT NULL');
}
// Wem eine Buchung privat gehört. NULL heißt „der Band" — nur diese Zeilen
// zählen für den Kontostand. Was jemand privat zahlt, geht die Band nichts an.
if (!column_exists('finances', 'private_for')) {
  $db->exec('ALTER TABLE finances ADD COLUMN private_for INT NULL');
}
// „Gehört einem Mitglied" und „sieht nur dieses Mitglied" sind zweierlei:
// eine Einzahlung gehört dem Einzahler und geht trotzdem alle an. Bestehende
// Aufträge mit Besitzer waren bis dahin immer privat.
// Ein Gerätekauf gehört in beide Richtungen verknüpft: die Buchung nennt das
// Gerät, das Gerät zeigt seine Buchung.
if (!column_exists('finances', 'equipment_id')) {
  $db->exec('ALTER TABLE finances ADD COLUMN equipment_id INT NULL');
}
// Verkauft oder ausgemustert: das Gerät bleibt als Geschichte stehen, zählt
// aber nicht mehr zum Bestand und kommt auf keine Packliste mehr.
if (!column_exists('equipment', 'disposed_on')) {
  $db->exec('ALTER TABLE equipment ADD COLUMN disposed_on DATE NULL');
}
if (!column_exists('standing_orders', 'private')) {
  $db->exec('ALTER TABLE standing_orders ADD COLUMN private TINYINT(1) NOT NULL DEFAULT 0');
  $db->exec('UPDATE standing_orders SET private = 1 WHERE owner_id IS NOT NULL');
}
foreach (['pa_source', 'light_source'] as $prodCol) {
  if (!column_exists('events', $prodCol)) {
    $db->exec("ALTER TABLE events ADD COLUMN `$prodCol` VARCHAR(20) NOT NULL DEFAULT ''");
  }
}
foreach (['first_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'last_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'phone' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'mobile' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'substitute_for' => 'INT NULL',
          // Reihenfolge unter mehreren Ersatzleuten desselben Mitglieds
          'substitute_rank' => 'INT NOT NULL DEFAULT 0'] as $col => $ddl) {
  if (!column_exists('users', $col)) $db->exec("ALTER TABLE users ADD COLUMN `$col` $ddl");
}
if (!column_exists('users', 'can_finance')) {
  $db->exec("ALTER TABLE users ADD COLUMN can_finance TINYINT(1) NOT NULL DEFAULT 0");
}
if (!column_exists('users', 'reset_token')) {
  $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL, ADD COLUMN reset_expires DATETIME NULL");
}
if (!column_exists('users', 'stage_name')) {
  $db->exec("ALTER TABLE users ADD COLUMN stage_name VARCHAR(190) NOT NULL DEFAULT '' AFTER name,
             ADD COLUMN avatar_file VARCHAR(255) NOT NULL DEFAULT '' AFTER instrument");
}
if (!column_exists('songs', 'composer')) {
  $db->exec("ALTER TABLE songs ADD COLUMN composer VARCHAR(255) NOT NULL DEFAULT '' AFTER artist,
             ADD COLUMN gema_werknr VARCHAR(50) NOT NULL DEFAULT '' AFTER composer");
}
if (!column_exists('setlist_songs', 'id')) {
  $db->exec('ALTER TABLE setlist_songs DROP PRIMARY KEY,
             ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST,
             ADD COLUMN is_break TINYINT(1) NOT NULL DEFAULT 0,
             MODIFY song_id INT NULL');
}

// ---------- Query-Helfer ----------
function q(string $sql, array $params = []): PDOStatement {
  global $db;
  $st = $db->prepare($sql);
  $st->execute($params);
  return $st;
}
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }

function setting(string $key, string $fallback = ''): string {
  $r = row('SELECT value FROM settings WHERE `key` = ?', [$key]);
  return $r ? $r['value'] : $fallback;
}
function set_setting(string $key, string $value): void {
  q('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$key, $value]);
}
function all_settings(): array {
  $out = [];
  foreach (rows('SELECT `key`, value FROM settings') as $r) $out[$r['key']] = $r['value'];
  return $out;
}

// ---------- Grunddaten beim ersten Start ----------
$defaults = [
  'band_name' => 'Meine Band',
  'tagline' => 'Bandname, Logo und Hintergrund in den Einstellungen anpassen',
  'bio' => 'Hier steht bald die Bandbeschreibung.',
  'contact_email' => '',
  'booking_text' => 'Ihr wollt uns buchen? Schreibt uns!',
  'facebook_url' => '', 'instagram_url' => '', 'spotify_url' => '', 'youtube_url' => '',
  'logo_file' => '', 'background_file' => '', 'favicon_file' => '',
  'print_logo_file' => '', 'print_watermark_file' => '',
  'rider_stage' => '', 'rider_power' => '', 'rider_pa' => '', 'rider_monitor' => '',
  'rider_light' => '', 'rider_getin' => '', 'rider_extras' => '', 'rider_positions' => '',
  'rider_contact_tech' => '', 'rider_contact_booking' => '',
  'impressum_text' => '', 'privacy_text' => '', 'copyright_text' => '',
  'public_show_past' => '0', 'public_limit_upcoming' => '10', 'public_limit_past' => '5',
  'public_embed_mode' => 'consent',
  'public_mode' => 'website',
  'redirect_url' => '',
  // Feste Adresse der Installation. Leer heißt „aus der Anfrage nehmen";
  // eingetragen schützt sie Links in E-Mails vor einem gefälschten Host.
  'site_url' => '',
  'enabled_langs' => 'de,en,nl,fr,es,it',
  // Sicherungen sind aus, bis jemand sie einschaltet — sonst füllt eine
  // Installation ungefragt die Platte des Servers, auf dem sie liegt.
  'backup_enabled' => '0', 'backup_interval' => 'daily', 'backup_keep' => '7',
  // Ziele: der eigene Server ist immer dabei, FTP und OneDrive kommen dazu
  'backup_ftp_enabled' => '0', 'backup_ftp_host' => '', 'backup_ftp_port' => '21',
  'backup_ftp_user' => '', 'backup_ftp_pass' => '', 'backup_ftp_dir' => '',
  'backup_ftp_tls' => '1', 'backup_ftp_passive' => '1', 'backup_ftp_keep' => '14',
  // Ersatz wird von Hand angefragt, bis die Band etwas anderes einstellt
  'substitute_auto' => 'off',
  // Die Liste der noch nicht verbuchten Gagen bleibt aus, bis jemand sie will
  'fin_open_fees' => '0',
];
// Neuinstallationen starten auf Englisch; bestehende Installationen behalten
// Deutsch, damit ein Update ihre Seite nicht plötzlich umstellt.
$freshInstall = row('SELECT 1 FROM settings LIMIT 1') === null;
$defaults['default_lang'] = $freshInstall ? 'en' : 'de';

foreach ($defaults as $k => $v) {
  if (row('SELECT 1 FROM settings WHERE `key` = ?', [$k]) === null) set_setting($k, $v);
}
// Früher gab es nur ein Namensfeld. Der bisherige Inhalt wandert einmalig in den
// Vornamen, damit niemand seinen Namen neu eintippen muss.
if (setting('names_split') !== '1') {
  // Am letzten Leerzeichen trennen: "Lisa Berg" -> Lisa + Berg, "Sebastian" -> Sebastian
  foreach (rows("SELECT id, name FROM users WHERE first_name = '' AND name != ''") as $u) {
    $pos = mb_strrpos(trim($u['name']), ' ');
    $first = $pos === false ? trim($u['name']) : mb_substr(trim($u['name']), 0, $pos);
    $last = $pos === false ? '' : mb_substr(trim($u['name']), $pos + 1);
    q('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?', [$first, $last, $u['id']]);
  }
  set_setting('names_split', '1');
}
if (setting('ical_token') === '') set_setting('ical_token', bin2hex(random_bytes(16)));
if (setting('downloads_token') === '') set_setting('downloads_token', bin2hex(random_bytes(16)));
if (setting('downloads_mode') === '') set_setting('downloads_mode', 'token');

// Rechte einmalig aus den bisherigen Rollen übernehmen: alle behalten genau
// das, was sie vorher durften, und das Finanz-Häkchen wird zum Schreibrecht
// in der Kasse. Ein Update darf niemandem etwas wegnehmen, ohne zu fragen.
if (setting('permissions_migrated') !== '1' && row('SELECT 1 FROM users LIMIT 1')) {
  foreach (rows('SELECT id, role, can_finance FROM users') as $permUser) {
    if ($permUser['role'] === 'admin') continue;
    perm_apply_template((int) $permUser['id'], $permUser['role'] === 'ersatz' ? 'ersatz' : 'member');
    if ($permUser['role'] !== 'ersatz' && (int) $permUser['can_finance'] === 1) {
      q("UPDATE permissions SET can_write = 1 WHERE user_id = ? AND module = 'kasse'", [$permUser['id']]);
    }
  }
  set_setting('permissions_migrated', '1');
}

// Ersatzleute dürfen den Stagerider und die Kanalbelegung sehen; wer schon
// angelegt ist, bekommt das Recht nachgereicht. Wer es einem Ersatz von Hand
// wieder wegnimmt, behält das — die Zeile wird nur einmal angefasst.
if (setting('perm_ersatz_rider') !== '1' && setting('permissions_migrated') === '1') {
  q("UPDATE permissions p JOIN users u ON u.id = p.user_id
     SET p.can_read = 1 WHERE u.role = 'ersatz' AND p.module = 'rider'");
  set_setting('perm_ersatz_rider', '1');
}

// „Musik & Videos" ist aus den Einstellungen in einen eigenen Bereich gezogen.
// Wer die Fotos pflegen darf, pflegt auch die Musikseite — beides ist Inhalt
// der öffentlichen Seite. Ohne diese Zeile stünde der Bereich nach dem Update
// für alle auf „kein Recht".
if (setting('perm_musik_migrated') !== '1' && setting('permissions_migrated') === '1') {
  q("INSERT INTO permissions (user_id, module, can_read, can_write)
     SELECT user_id, 'musik', can_read, can_write FROM permissions WHERE module = 'fotos'
     ON DUPLICATE KEY UPDATE can_read = VALUES(can_read), can_write = VALUES(can_write)");
  set_setting('perm_musik_migrated', '1');
}

// Mitgelieferte Übersetzungen einspielen — nicht nur bei der Erstinstallation,
// sondern auch dann, wenn eine neue Version weitere Seed-Dateien mitbringt.
// Die Seeds ergänzen ausschließlich fehlende Schlüssel; im Bandbereich von Hand
// gepflegte Texte bleiben unverändert.
$seedFiles = glob(BASE_DIR . '/seed/translations/*.sql') ?: [];
$seedStamp = '';
foreach ($seedFiles as $seedFile) $seedStamp .= basename($seedFile) . ':' . filesize($seedFile) . '|';
$seedStamp = sha1($seedStamp);
if (setting('translations_seed') !== $seedStamp) {
  foreach ($seedFiles as $seedFile) {
    try { $db->exec((string) file_get_contents($seedFile)); } catch (PDOException) { /* Seed ist optional */ }
  }
  set_setting('translations_seed', $seedStamp);
}

// Einmalig: Uploads aus der Zeit vor der Zugriffsprüfung tragen sprechende,
// durchzählbare Namen. Als Migration und nicht als Skript, das jemand finden
// muss — sonst behält eine Installation die alten Namen aus Versehen.
if (setting('uploads_renamed') === '') {
  uploads_randomise_names();
  set_setting('uploads_renamed', date('Y-m-d'));
}

// Erster Start: Admin-Konto mit zufälligem Passwort anlegen. Das Passwort steht
// einmalig in data/INITIAL-PASSWORD.txt (außerhalb des Webroots) und muss beim
// ersten Login geändert werden — so werden keine festen Zugangsdaten ausgeliefert.
if ((int) row('SELECT COUNT(*) AS n FROM users')['n'] === 0) {
  $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $startPw = '';
  for ($i = 0; $i < 14; $i++) $startPw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  // Das erste Konto verwaltet auch die Kasse — sonst wäre sie nach der
  // Installation für niemanden bedienbar. Später frei vergebbar.
  q('INSERT INTO users (name, email, password_hash, role, must_change_pw, can_finance) VALUES (?,?,?,?,1,1)',
    ['Admin', 'admin@example.com', password_hash($startPw, PASSWORD_DEFAULT), 'admin']);
  @file_put_contents(DATA_DIR . '/INITIAL-PASSWORD.txt',
    "Bandroadie — initial administrator account\n\n"
    . "Email:    admin@example.com\nPassword: $startPw\n\n"
    . "You must change this password at first login. Change the email address\n"
    . "under Intern -> Profil, then delete this file.\n");
  @chmod(DATA_DIR . '/INITIAL-PASSWORD.txt', 0600);
}

// ---------- Auth & Ansicht ----------
function current_user(): ?array {
  static $user = false;
  if ($user === false) {
    $user = empty($_SESSION['uid']) ? null
      // can_finance steht bewusst nicht mehr dabei: seit es Rechte je Bereich
      // gibt, sagt die Spalte nichts mehr aus. Sie bleibt nur für die einmalige
      // Übernahme beim Update stehen.
      : row('SELECT id, name, stage_name, email, role, instrument, avatar_file, must_change_pw, substitute_for FROM users WHERE id = ?', [$_SESSION['uid']]);
  }
  return $user;
}
function require_login(): array {
  $u = current_user();
  if (!$u) { redirect('/login'); }
  return $u;
}
function require_admin(): array {
  $u = require_login();
  if ($u['role'] !== 'admin') { flash(t('fl_admin_required')); redirect('/intern'); }
  return $u;
}

// ---------- Rechte je Bereich ----------

/** Gespeicherte Rechte eines Mitglieds: Bereich => ['read' => bool, 'write' => bool]. */
function perm_of(int $userId): array {
  static $cache = [];
  if (!isset($cache[$userId])) {
    $cache[$userId] = [];
    foreach (rows('SELECT module, can_read, can_write FROM permissions WHERE user_id = ?', [$userId]) as $r) {
      $cache[$userId][$r['module']] = ['read' => (bool) $r['can_read'], 'write' => (bool) $r['can_write']];
    }
  }
  return $cache[$userId];
}

/** Darf jemand in einem Bereich lesen ($need = 'read') oder ändern ('write')? */
function perm_allows(?array $user, string $module, string $need = 'read'): bool {
  if (!$user) return false;
  // Admins dürfen alles — außer, was ausdrücklich vergeben sein will. Eine
  // Band zu verwalten ist nicht dasselbe, wie ihre Kasse zu führen.
  if (($user['role'] ?? '') === 'admin' && !in_array($module, PERM_EXPLICIT_MODULES, true)) return true;
  $all = perm_of((int) $user['id']);
  if (!$all) {
    // Kein einziger Eintrag heißt „noch nicht entschieden", nicht „verboten" —
    // sonst wäre ein Konto, das außerhalb der Mitgliederverwaltung entstanden
    // ist, für immer ausgesperrt. Sobald eine Zeile da ist, gilt sie genau.
    $tpl = PERM_TEMPLATES[$user['role'] ?? 'member'] ?? PERM_TEMPLATES['member'];
    [$read, $write] = $tpl[$module] ?? [0, 0];
    return $need === 'write' ? (bool) $write : (bool) ($read || $write);
  }
  $p = $all[$module] ?? null;
  if (!$p) return false;
  // Wer ändern darf, darf auch sehen — alles andere wäre eine Falle
  return $need === 'write' ? $p['write'] : ($p['read'] || $p['write']);
}

/**
 * Termine, die jemand sehen darf — null heißt „alle". Nur Ersatzleute werden
 * eingeschränkt: Sie sehen die Termine, für die sie ausdrücklich angefragt
 * wurden. Dass jemand abgesagt hat, ist noch keine Anfrage — gefragt wird in
 * der Band, und erst der Knopf macht daraus einen Termin, der sie angeht.
 */
function visible_event_ids(?array $user): ?array {
  if (!$user || ($user['role'] ?? '') === 'admin' || !is_substitute($user)) return null;
  return array_map('intval', array_column(
    rows('SELECT event_id FROM substitute_requests WHERE user_id = ?', [$user['id']]), 'event_id'));
}

/**
 * Ersatzleute eines Mitglieds, in ihrer Reihenfolge — dazu, wie oft sie schon
 * dabei waren. Die Zahlen kommen aus den Zusagen, es pflegt sie niemand.
 */
function substitutes_for(int $memberId): array {
  return rows(
    "SELECT u.id, u.name, u.substitute_rank,
            (SELECT COUNT(*) FROM attendance a JOIN events e ON e.id = a.event_id
              WHERE a.user_id = u.id AND a.status = 'yes' AND e.type = 'probe') AS proben,
            (SELECT COUNT(*) FROM attendance a JOIN events e ON e.id = a.event_id
              WHERE a.user_id = u.id AND a.status = 'yes' AND e.type = 'gig') AS gigs
     FROM users u WHERE u.substitute_for = ?
     ORDER BY u.substitute_rank = 0, u.substitute_rank, u.name", [$memberId]);
}

// Wie der nächste Ersatz gewählt wird, wenn die Band das automatisch möchte
const SUB_AUTO_MODES = ['off', 'rank', 'shuffle', 'rotate'];

/**
 * Sucht den nächsten Ersatz für ein Mitglied bei einem Termin. Schon
 * angefragte fallen heraus, sonst würde dieselbe Person zweimal gefragt.
 *
 * rank    — die hinterlegte Reihenfolge
 * shuffle — zufällig, damit nicht immer dieselbe Person zuerst gefragt wird
 * rotate  — wer am längsten nicht dran war; wer noch nie gefragt wurde, zuerst
 */
function pick_substitute(int $memberId, int $eventId, string $mode): ?array {
  $asked = array_map('intval', array_column(
    rows('SELECT user_id FROM substitute_requests WHERE event_id = ?', [$eventId]), 'user_id'));
  $subs = array_values(array_filter(substitutes_for($memberId),
    fn($s) => !in_array((int) $s['id'], $asked, true)));
  if (!$subs) return null;

  if ($mode === 'shuffle') return $subs[random_int(0, count($subs) - 1)];
  if ($mode === 'rotate') {
    $last = [];
    foreach (rows('SELECT r.user_id, MAX(e.date) AS d FROM substitute_requests r
                   JOIN events e ON e.id = r.event_id GROUP BY r.user_id') as $r) {
      $last[(int) $r['user_id']] = (string) $r['d'];
    }
    usort($subs, fn($a, $b) => ($last[(int) $a['id']] ?? '') <=> ($last[(int) $b['id']] ?? ''));
  }
  return $subs[0]; // bei 'rank' steht die Reihenfolge schon in substitutes_for()
}

/**
 * Fragt automatisch den nächsten Ersatz an, wenn die Band das eingestellt hat.
 * Aufgerufen, sobald jemand absagt — auch dann, wenn ein Ersatz absagt, denn
 * dann rückt der nächste nach.
 */
function substitute_auto_request(int $eventId, int $memberId, int $byUserId): void {
  $mode = setting('substitute_auto') ?: 'off';
  if ($mode === 'off' || !in_array($mode, SUB_AUTO_MODES, true)) return;
  // Vergangene Termine brauchen keinen Ersatz mehr
  if (!row('SELECT 1 FROM events WHERE id = ? AND date >= ?', [$eventId, date('Y-m-d')])) return;
  $pick = pick_substitute($memberId, $eventId, $mode);
  if (!$pick) return;
  q('INSERT IGNORE INTO substitute_requests (event_id, user_id, for_user_id, requested_by) VALUES (?,?,?,?)',
    [$eventId, $pick['id'], $memberId, $byUserId]);
}

/** Angefragte Ersatzleute je Termin: [event_id][] => Zeile mit Name und Antwort. */
function substitute_requests_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT r.*, u.name, f.name AS for_name,
                        (SELECT status FROM attendance a WHERE a.event_id = r.event_id AND a.user_id = r.user_id) AS answer
                 FROM substitute_requests r
                 JOIN users u ON u.id = r.user_id
                 LEFT JOIN users f ON f.id = r.for_user_id
                 WHERE r.event_id IN ($in) ORDER BY r.created_at", $eventIds) as $r) {
    $out[(int) $r['event_id']][] = $r;
  }
  return $out;
}

/** Setlists zu den sichtbaren Terminen; null heißt „alle". */
function visible_setlist_ids(?array $user): ?array {
  $events = visible_event_ids($user);
  if ($events === null) return null;
  if (!$events) return [];
  $in = implode(',', array_fill(0, count($events), '?'));
  return array_map('intval', array_column(
    rows("SELECT DISTINCT setlist_id FROM events WHERE id IN ($in) AND setlist_id IS NOT NULL", $events),
    'setlist_id'));
}

/** Songs auf den sichtbaren Setlists; null heißt „alle". */
function visible_song_ids(?array $user): ?array {
  $setlists = visible_setlist_ids($user);
  if ($setlists === null) return null;
  if (!$setlists) return [];
  $in = implode(',', array_fill(0, count($setlists), '?'));
  return array_map('intval', array_column(
    rows("SELECT DISTINCT song_id FROM setlist_songs WHERE setlist_id IN ($in) AND song_id IS NOT NULL", $setlists),
    'song_id'));
}

/**
 * Darf jemand diesen einen Termin sehen? Für alle außer Ersatzleuten ja.
 *
 * Die drei Fragen unten gehören zusammen an eine Stelle: Wer eine zweite
 * Route auf denselben Datensatz baut — Druckansicht, Export, Dateianhang —
 * muss die Prüfung mitnehmen, und das soll eine Zeile sein.
 */
function may_see_event(?array $user, int $eventId): bool {
  $ids = visible_event_ids($user);
  return $ids === null || in_array($eventId, $ids, true);
}

/** Darf jemand diese Setlist sehen? */
function may_see_setlist(?array $user, int $setlistId): bool {
  $ids = visible_setlist_ids($user);
  return $ids === null || in_array($setlistId, $ids, true);
}

/** Darf jemand diesen Song sehen? */
function may_see_song(?array $user, int $songId): bool {
  $ids = visible_song_ids($user);
  return $ids === null || in_array($songId, $ids, true);
}

/**
 * Darf jemand diesen Dateianhang sehen? Der Anhang erbt die Sichtbarkeit von
 * der Sache, an der er hängt — sonst käme über die Datei heraus, was die
 * Seite selbst verbirgt.
 */
function may_see_file(?array $user, array $file): bool {
  $id = (int) $file['entity_id'];
  return match ($file['entity_type']) {
    'event' => may_see_event($user, $id),
    'setlist' => may_see_setlist($user, $id),
    'song' => may_see_song($user, $id),
    default => true,
  };
}

/** Baut „AND id IN (...)“ für eine Sichtbarkeitsliste; null lässt alles durch. */
function visible_clause(?array $ids, string $column = 'id'): array {
  if ($ids === null) return ['', []];
  if (!$ids) return [" AND 1 = 0", []];
  return [" AND $column IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
}

/** Zu welchem Bereich gehört ein Pfad? null heißt: für alle Angemeldeten offen. */
function perm_module_for(string $path): ?string {
  // Dateianhänge folgen dem Bereich der Sache, an der sie hängen — sonst
  // könnte man an einen Termin nichts anhängen, ohne Rechte an allen Dateien.
  if ($path === '/intern/dateien') {
    return PERM_ENTITY_MODULES[$_POST['entity_type'] ?? ''] ?? null;
  }
  if (preg_match('~^/intern/datei/(\d+)~', $path, $m)) {
    $f = row('SELECT entity_type FROM files WHERE id = ?', [$m[1]]);
    return PERM_ENTITY_MODULES[$f['entity_type'] ?? ''] ?? null;
  }
  foreach (PERM_MODULES as $module => $prefixes) {
    foreach ($prefixes as $prefix) {
      if ($path === $prefix || str_starts_with($path, $prefix . '/')) return $module;
    }
  }
  return null;
}

/**
 * Schreibende Pfade, die schon mit dem Leserecht offenstehen, weil man dort
 * nur über sich selbst bestimmt: auf einen Termin antworten, den eigenen
 * Dauerauftrag verwalten, die eigene Buchung wieder löschen. Die Routen
 * prüfen anschließend selbst, dass es wirklich die eigene Sache ist.
 */
const SELF_SERVICE_PATHS = [
  '~^/intern/termine/\d+/zusage$~',
  '~^/intern/kasse/dauerauftrag$~',
  '~^/intern/kasse/dauerauftrag/\d+/(pause|delete)$~',
  '~^/intern/kasse/\d+/delete$~',
];

function is_self_service(string $path): bool {
  foreach (SELF_SERVICE_PATHS as $pattern) {
    if (preg_match($pattern, $path)) return true;
  }
  return false;
}

/** Rechte einer Rolle setzen; Admins brauchen keine Zeilen, sie dürfen alles. */
function perm_apply_template(int $userId, string $role): void {
  $tpl = PERM_TEMPLATES[$role] ?? PERM_TEMPLATES['member'];
  foreach ($tpl as $module => [$read, $write]) {
    q('INSERT INTO permissions (user_id, module, can_read, can_write) VALUES (?,?,?,?)
       ON DUPLICATE KEY UPDATE can_read = VALUES(can_read), can_write = VALUES(can_write)',
      [$userId, $module, $read, $write]);
  }
}

// Kassen-Schreibrecht: nur Mitglieder mit Finanz-Häkchen (vergeben Admins unter Mitglieder)
function can_finance(): bool {
  // Seit es Rechte je Bereich gibt, ist das Finanz-Häkchen das Schreibrecht
  // an der Kasse. Zwei Schalter für dieselbe Sache wären nur verwirrend.
  return perm_allows(current_user(), 'kasse', 'write');
}

/**
 * Darf jemand diese Buchung löschen? Bandbuchungen räumt auf, wer an der
 * Kasse schreiben darf; eine private Buchung gehört nur ihrem Besitzer —
 * auch die Kassenwartin fasst sie nicht an.
 */
function may_edit_finance(?array $entry): bool {
  if (!$entry) return false;
  if ($entry['private_for'] === null) return can_finance();
  return (int) $entry['private_for'] === (int) (current_user()['id'] ?? 0);
}

function redirect(string $to): never { header("Location: $to"); exit; }

/**
 * Zurück zur vorherigen Seite. Der Browser schickt dafür den Referer mit —
 * der kommt aber von außen und darf nicht ungeprüft in die Weiterleitung.
 * Sonst schickt eine fremde Seite Besucher über die eigene Adresse wieder zu
 * sich selbst zurück und leiht sich so das Vertrauen in die Domain.
 * Übernommen wird nur, was auf diese Installation zeigt.
 */
function back(string $fallback): never {
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref === '') redirect($fallback);
  $parts = parse_url($ref);
  $host = $parts['host'] ?? '';
  if ($host !== '' && $host !== ($_SERVER['HTTP_HOST'] ?? '')) redirect($fallback);
  $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
  // Kein „//evil.example" — das wäre für den Browser wieder ein fremder Host
  redirect(str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $fallback);
}
function flash(string $msg): void { $_SESSION['flash'] = $msg; }

function e(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Anzeigename aus Vor- und Nachname; bleibt der alte, wenn beide leer sind. */
function display_name(string $first, string $last, string $fallback = ''): string {
  $name = trim(trim($first) . ' ' . trim($last));
  return $name !== '' ? $name : $fallback;
}

// ---------- CSRF ----------
// Jedes Formular trägt ein Sitzungs-Token; ohne gültiges Token wird kein POST
// ausgeführt. Damit können fremde Seiten keine Aktionen im Namen eines
// angemeldeten Mitglieds auslösen.
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field(): string {
  return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}
function csrf_valid(): bool {
  return is_string($_POST['_token'] ?? null) && hash_equals(csrf_token(), $_POST['_token']);
}

// ---------- Versuchsbremse ----------
// Zählt fehlgeschlagene Versuche pro Kennung und IP; nach zu vielen wird für
// eine Weile abgewiesen, damit Passwörter nicht durchprobiert werden können.
function throttle_key(string $action, string $id): string {
  return $action . '|' . mb_strtolower(trim($id)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
}
function throttle_blocked(string $action, string $id, int $max = 8, int $minutes = 15): bool {
  $row = row('SELECT COUNT(*) AS n FROM login_attempts WHERE k = ? AND ts > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
    [throttle_key($action, $id), $minutes]);
  return (int) ($row['n'] ?? 0) >= $max;
}
function throttle_note(string $action, string $id): void {
  q('INSERT INTO login_attempts (k, ts) VALUES (?, NOW())', [throttle_key($action, $id)]);
  q('DELETE FROM login_attempts WHERE ts < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}
function throttle_clear(string $action, string $id): void {
  q('DELETE FROM login_attempts WHERE k = ?', [throttle_key($action, $id)]);
}

// ---------- Upload-Grenzen ----------
// PHP verwirft zu große Uploads still: tmp_name ist leer, size 0. Ohne Blick auf
// den Fehlercode sieht der Upload für Nutzer erfolgreich aus, obwohl nichts ankam.
function ini_bytes(string $key): int {
  $v = trim((string) ini_get($key));
  if ($v === '') return 0;
  $n = (int) $v;
  return match (strtolower(substr($v, -1))) {
    'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
  };
}
function max_upload_bytes(): int {
  $limits = array_filter([ini_bytes('upload_max_filesize'), ini_bytes('post_max_size')]);
  return $limits ? min($limits) : 0;
}
function fmt_bytes(int $b): string {
  if ($b >= 1048576) return round($b / 1048576) . ' MB';
  return $b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B';
}
/** true, wenn der Upload fehlschlug — meldet dem Nutzer auch gleich den Grund. */
function upload_rejected(int $errorCode): bool {
  if ($errorCode === UPLOAD_ERR_OK || $errorCode === UPLOAD_ERR_NO_FILE) return false;
  flash(in_array($errorCode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
    ? t('fl_upload_server_limit') . ' ' . fmt_bytes(max_upload_bytes())
    : t('fl_upload_failed'));
  return true;
}

/**
 * Sprachen im Auswahlmenü. Die Standardsprache ist immer dabei — sonst
 * hätte die Seite eine Rückfallebene, die niemand aufrufen kann. Alle
 * anderen darf eine Band abschalten, auch Deutsch.
 */
function enabled_langs(): array {
  $langs = array_values(array_intersect(array_keys(LANGS), array_map('trim', explode(',', setting('enabled_langs', 'de')))));
  $default = default_lang();
  if (!in_array($default, $langs, true)) array_unshift($langs, $default);
  return $langs;
}
/**
 * Die Standardsprache. Sie fragt bewusst nicht bei enabled_langs() nach —
 * das prüft umgekehrt gegen sie, und beide würden sich sonst gegenseitig
 * aufrufen.
 */
function default_lang(): string {
  $lang = setting('default_lang', 'de');
  return array_key_exists($lang, LANGS) ? $lang : 'de';
}
/** Wunschsprache aus dem Accept-Language-Header, sofern sie aktiviert ist. */
function browser_lang(): ?string {
  $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
  if ($header === '') return null;
  $wanted = [];
  foreach (explode(',', $header) as $part) {
    $bits = explode(';q=', trim($part));
    $code = strtolower(substr(trim($bits[0]), 0, 2));
    $q = isset($bits[1]) ? (float) $bits[1] : 1.0;
    if ($code !== '' && $q > ($wanted[$code] ?? -1)) $wanted[$code] = $q;
  }
  arsort($wanted);
  foreach (array_keys($wanted) as $code) {
    if (in_array($code, enabled_langs(), true)) return $code;
  }
  return null;
}
// Reihenfolge: eigene Wahl (Umschalter/Profil) -> Browsersprache -> Standardsprache
function current_lang(): string {
  static $lang = null;
  if ($lang !== null) return $lang;
  foreach ([$_SESSION['pub_lang'] ?? null, browser_lang(), default_lang()] as $candidate) {
    if ($candidate !== null && in_array($candidate, enabled_langs(), true)) return $lang = $candidate;
  }
  return $lang = default_lang();
}
function t(string $key): string {
  static $cache = null;
  $lang = current_lang();
  if ($lang === 'de') return UI_STRINGS[$key] ?? $key;
  if ($cache === null) {
    $cache = [];
    foreach (rows('SELECT tkey, value FROM translations WHERE lang = ?', [$lang]) as $r) $cache[$r['tkey']] = $r['value'];
  }
  return ($cache[$key] ?? '') !== '' ? $cache[$key] : (UI_STRINGS[$key] ?? $key);
}
// Übersetzte Labels für Termin-Arten/-Status und Song-Status
function event_type_label(string $k): string { return t('evtype_' . $k) !== 'evtype_' . $k ? t('evtype_' . $k) : $k; }
function event_status_label(string $k): string { return t('evstatus_' . $k) !== 'evstatus_' . $k ? t('evstatus_' . $k) : $k; }
function song_status_label(string $k): string { return t('songstatus_' . $k) !== 'songstatus_' . $k ? t('songstatus_' . $k) : $k; }
function fin_category_label(string $k): string { return t('fincat_' . $k) !== 'fincat_' . $k ? t('fincat_' . $k) : $k; }
function production_label(string $k): string { return $k === '' ? '' : (t('prod_' . $k) !== 'prod_' . $k ? t('prod_' . $k) : $k); }
function eq_category_label(string $k): string { return t('eqcat_' . $k) !== 'eqcat_' . $k ? t('eqcat_' . $k) : $k; }
function fmt_money(int $cents): string { return number_format($cents / 100, 2, ',', '.') . ' €'; }

/**
 * Preiseingabe in Cent; leer bleibt leer.
 *
 * Punkt und Komma bedeuten je nach Land das Gegenteil, deshalb wird das
 * Trennzeichen aus der Eingabe erschlossen statt angenommen: „1.249,90",
 * „1,249.90", „231.27" und „231,27" ergeben alle das Erwartete. Bleibt ein
 * einzelnes Trennzeichen mit genau drei Ziffern dahinter, ist es die
 * Tausendergruppe — „1.249" sind tausendzweihundertneunundvierzig.
 */
function price_to_cents(string $raw): ?int {
  $raw = trim($raw);
  if ($raw === '') return null;
  $raw = str_replace([' ', "\u{00A0}", '€'], '', $raw);

  $lastDot = strrpos($raw, '.');
  $lastComma = strrpos($raw, ',');
  if ($lastDot !== false && $lastComma !== false) {
    // Beide vorhanden: das hintere trennt die Nachkommastellen.
    $decimalAt = max($lastDot, $lastComma);
  } elseif ($lastDot === false && $lastComma === false) {
    $decimalAt = null;
  } else {
    $sep = $lastDot !== false ? '.' : ',';
    $at = $lastDot !== false ? $lastDot : $lastComma;
    $onlyOnce = substr_count($raw, $sep) === 1;
    $decimalAt = $onlyOnce && strlen($raw) - $at - 1 !== 3 ? $at : null;
  }

  $whole = $decimalAt === null ? $raw : substr($raw, 0, $decimalAt);
  $fraction = $decimalAt === null ? '' : substr($raw, $decimalAt + 1);
  $clean = preg_replace('~\D~', '', $whole) . ($fraction === '' ? '' : '.' . preg_replace('~\D~', '', $fraction));
  if ($clean === '' || $clean === '.') return null;
  $cents = (int) round((float) $clean * 100);
  return str_starts_with(ltrim($raw), '-') ? -$cents : $cents;
}

/**
 * Adresse einer mitgelieferten Datei, mit Versionsanhang.
 *
 * Der Anhang wechselt mit jeder Version. Dadurch darf der Browser die Datei
 * lange behalten, holt sie nach einem Update aber sofort neu — ohne dass
 * jemand am Webserver etwas einstellen muss. Genau deshalb steht das hier
 * und nicht in einer Serverkonfiguration: Wer das Projekt woanders
 * installiert, hat diesen Vorteil ohne Zutun.
 */
function asset(string $path): string {
  return $path . '?v=' . rawurlencode(BANDROADIE_VERSION);
}

/**
 * Darf jemand diese hochgeladene Datei sehen? Logo, Hintergrund, Favicon und
 * als öffentlich markierte Fotos gehören auf die Website; das Fotoarchiv und
 * die Bilder der Mitglieder nicht. Eine Stelle entscheidet das — sonst hat
 * die nächste Route, die Bilder ausliefert, die Prüfung wieder nicht.
 */
function may_see_upload(?array $user, string $name): bool {
  $branding = array_filter([
    setting('logo_file'), setting('background_file'), setting('favicon_file'),
    setting('print_logo_file'), setting('print_watermark_file'),
  ]);
  if (in_array($name, $branding, true)) return true;

  $photo = row('SELECT is_public FROM photos WHERE filename = ?', [$name]);
  if ((int) ($photo['is_public'] ?? 0) === 1) return true;
  if (!$user) return false;
  return !$photo || perm_allows($user, 'fotos');
}

/**
 * Verkleinerte Fassung eines Bildes, beim ersten Abruf erzeugt und danach
 * wiederverwendet. Die Galerie zeigt Kacheln von 160 bis 230 Pixeln, lud
 * bisher aber die Originale — bei hundert Fotos ein Vielfaches der nötigen
 * Datenmenge. Fehlt die Bildbibliothek, gibt es eben das Original.
 */
function thumb_file(string $name, int $width = 480): ?string {
  $source = UPLOADS_DIR . '/' . $name;
  if (!is_file($source) || !function_exists('imagecreatetruecolor')) return null;

  $dir = DATA_DIR . '/thumbs';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $target = $dir . '/' . $width . '_' . preg_replace('~[^\w.\-]~', '_', $name) . '.jpg';
  if (is_file($target) && filemtime($target) >= filemtime($source)) return $target;

  $info = @getimagesize($source);
  if (!$info) return null;
  $img = match ($info['mime']) {
    'image/jpeg' => @imagecreatefromjpeg($source),
    'image/png'  => @imagecreatefrompng($source),
    'image/gif'  => @imagecreatefromgif($source),
    'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
    default      => false,
  };
  if (!$img) return null;

  // Kleinere Bilder werden nicht künstlich vergrößert
  $scale = min(1, $width / max(1, imagesx($img)));
  $w = max(1, (int) round(imagesx($img) * $scale));
  $h = max(1, (int) round(imagesy($img) * $scale));
  $small = imagecreatetruecolor($w, $h);
  imagecopyresampled($small, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
  imagejpeg($small, $target, 82);
  imagedestroy($small);
  imagedestroy($img);
  return is_file($target) ? $target : null;
}

/**
 * Symbol für den Startbildschirm. Hat die Band ein quadratisches Logo
 * hochgeladen, nimmt die App das; sonst das mitgelieferte Zeichen. Skaliert
 * wird nichts — GD fehlt auf manchen Servern, und ein verzerrtes Logo wäre
 * schlimmer als ein neutrales Symbol.
 */
function app_icon(int $size): string {
  $logo = setting('logo_file');
  if ($logo !== '' && is_file(UPLOADS_DIR . '/' . $logo)) {
    $info = @getimagesize(UPLOADS_DIR . '/' . $logo);
    if ($info && $info[0] === $info[1] && $info[0] >= $size) return '/uploads/' . rawurlencode($logo);
  }
  return "/assets/app/icon-$size.png";
}

/**
 * Die Lagerorte, die schon vergeben sind — als Vorschlagsliste. Ohne sie
 * heißt derselbe Ort dreimal anders geschrieben, und dann gruppiert nichts
 * mehr. Neue Orte bleiben trotzdem frei eintippbar.
 */
function eq_locations(array $items): array {
  return eq_distinct_values($items, 'location');
}

/** Dasselbe für die Steckplätze: „Kanal 1", „Wechselkopf" wiederholen sich. */
function eq_slots(array $items): array {
  return eq_distinct_values($items, 'slot');
}

/** Vorhandene Werte eines Feldes, ohne Dubletten und ohne Rücksicht auf Groß-/Kleinschreibung. */
function eq_distinct_values(array $items, string $field): array {
  $seen = [];
  foreach ($items as $item) {
    $value = trim((string) ($item[$field] ?? ''));
    if ($value !== '') $seen[mb_strtolower($value)] = $value;
  }
  sort($seen, SORT_NATURAL | SORT_FLAG_CASE);
  return $seen;
}

/**
 * Private Uploads auf unerratbare Namen umstellen — einmalig.
 *
 * Vor der Zugriffsprüfung hießen Dateien nach ihrem Inhalt und ihrem Datum
 * („foto_2025-06-14_003.jpg"). Erreichbar ist das heute nicht mehr, die Namen
 * beschreiben aber weiter, was drinsteckt, und lassen sich durchzählen, sobald
 * jemand eine Sitzung hat. Öffentliche Bilder — Logo, Hintergrund, Favicon —
 * bleiben lesbar benannt, die sollen ja abgerufen werden.
 *
 * @return int Zahl der umbenannten Dateien
 */
function uploads_randomise_names(): int {
  $done = 0;
  foreach ([['photos', 'filename', UPLOADS_DIR, 'foto'],
            ['users', 'avatar_file', UPLOADS_DIR, 'avatar'],
            ['files', 'filename', FILES_DIR, 'datei']] as [$table, $column, $dir, $prefix]) {
    foreach (rows("SELECT id, `$column` AS name FROM `$table` WHERE `$column` <> ''") as $r) {
      if (!is_file($dir . '/' . $r['name'])) continue;
      $ext = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($r['name'], PATHINFO_EXTENSION)));
      $new = $prefix . '_' . bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
      if (!@rename($dir . '/' . $r['name'], $dir . '/' . $new)) continue;
      q("UPDATE `$table` SET `$column` = ? WHERE id = ?", [$new, $r['id']]);
      $done++;
    }
  }
  // Ein Vorschaubild trägt den Namen seiner Quelle im eigenen. Weg damit —
  // beim nächsten Aufruf entsteht es neu.
  foreach (glob(DATA_DIR . '/thumbs/*') ?: [] as $thumb) @unlink($thumb);
  return $done;
}

/** Kaufpreis und -datum eines Geräts als eine lesbare Angabe. */
function eq_purchase_label(array $eq): string {
  $parts = [];
  if ($eq['price_cents'] !== null && $eq['price_cents'] !== '') $parts[] = fmt_money((int) $eq['price_cents']);
  if (!empty($eq['purchased_on'])) $parts[] = fmt_date($eq['purchased_on']);
  return implode(' · ', $parts);
}

/**
 * Preis, Besitzer und Kaufdatum sagen, wem ein Gerät gehört und was es wert
 * ist — das ändert nur, wem es gehört, und die Verwaltung. Bandeigenes
 * Material hat keinen Besitzer, gehört also allen und bleibt für alle offen.
 */
function eq_may_edit_owner_fields(?array $eq, ?array $user): bool {
  if (($user['role'] ?? '') === 'admin') return true;
  // Bandeigenes Material pflegen die Mitglieder gemeinsam — wer nur einspringt,
  // verwaltet nicht das Eigentum der Band.
  if (empty($eq['owner_id'])) return !is_substitute($user);
  return (int) $eq['owner_id'] === (int) ($user['id'] ?? 0);
}

/**
 * Steckt in Name oder Steckplatz eine Stückzahl („4×", „(2×)")? Beim Import
 * aus einer Liste landet die Menge oft im Text, und dann steht eine Zeile für
 * vier Kabel. Der Fund ist nur ein Vorschlag für das Formular — aufgeteilt
 * wird erst, wenn jemand es bestätigt. „4x4 Case" ist keine Stückzahl.
 */
function eq_quantity_hint(array $eq): ?int {
  foreach ([(string) ($eq['slot'] ?? ''), (string) ($eq['name'] ?? '')] as $text) {
    if (preg_match(EQ_QUANTITY_RE, trim($text), $m)) {
      $n = (int) $m[1];
      if ($n > 1 && $n <= 99) return $n;
    }
  }
  return null;
}

/** Die Stückzahl aus einem Text entfernen — sie steht danach in eigenen Zeilen. */
function eq_strip_quantity(string $text): string {
  return trim(preg_replace(EQ_QUANTITY_RE, '', trim($text)) ?? $text);
}

/**
 * Was ein Gerät gekostet hat und wann es gekauft wurde, geht die Band nur bei
 * ihrem eigenen Material etwas an. Was jemandem persönlich gehört, sieht sein
 * Besitzer — und die Verwaltung, die die Werte pflegen können muss.
 */
function eq_may_see_price(?array $eq, ?array $user): bool {
  if (($user['role'] ?? '') === 'admin') return true;
  if (empty($eq['owner_id'])) return true;
  return (int) $eq['owner_id'] === (int) ($user['id'] ?? 0);
}

/**
 * Springt jemand nur ein? Die Rolle sagt es, und das Feld „vertritt" sagt es
 * auch — beides zählt, sonst hinge das Ergebnis davon ab, welche der beiden
 * Angaben gerade gepflegt wurde.
 */
function is_substitute(?array $user): bool {
  return ($user['role'] ?? '') === 'ersatz' || !empty($user['substitute_for']);
}

/** Geräte nach übergeordnetem Gerät sortiert; ohne Übergeordnetes zählt 0. */
function eq_by_parent(array $items): array {
  $out = [];
  foreach ($items as $it) $out[(int) $it['parent_id']][] = $it;
  return $out;
}

/**
 * Alles, was unter einem Gerät hängt — über beliebig viele Ebenen. Ein Rack
 * enthält einen Empfänger, der zu einem Mikrofon gehört, das eine Kapsel hat.
 * Der Zähler bremst eine Schleife in den Daten aus, statt sich aufzuhängen.
 */
function eq_descendants(int $id, array $items): array {
  $byParent = eq_by_parent($items);
  $out = [];
  $stack = [$id];
  while ($stack && count($out) < 500) {
    foreach ($byParent[array_pop($stack)] ?? [] as $child) {
      $childId = (int) $child['id'];
      if (isset($out[$childId])) continue;
      $out[$childId] = true;
      $stack[] = $childId;
    }
  }
  return array_keys($out);
}

/**
 * Anschaffungswert eines Geräts samt allem, was darin steckt — gezählt wird,
 * was einen Preis hat und was der Betrachter sehen darf. Eine Summe ist eine
 * Summe; dass sie nur zusammenzählt, was da ist, braucht keine Fußnote.
 *
 * @return int Summe in Cent
 */
function eq_tree_value(array $eq, array $items, ?array $user): int {
  $byId = array_column($items, null, 'id');
  $sum = 0;
  foreach ([(int) $eq['id'], ...eq_descendants((int) $eq['id'], $items)] as $id) {
    $item = $byId[$id] ?? null;
    if (!$item || !empty($item['disposed_on'])
        || $item['price_cents'] === null || $item['price_cents'] === ''
        || !eq_may_see_price($item, $user)) continue;
    $sum += (int) $item['price_cents'];
  }
  return $sum;
}

/**
 * Bestandteile eines Geräts ausgeben — ruft sich für tiefere Ebenen selbst
 * auf. Als Funktion, damit jede Ebene ihre eigenen Variablen hat; ein
 * require mitten in der Schleife würde sich gegenseitig überschreiben.
 */
function eq_render_parts(array $childItems, array $ctx, int $depth = 1): void {
  ['childrenOf' => $childrenOf, 'items' => $items, 'members' => $members,
   'filesByEq' => $filesByEq, 'user' => $user, 'bookingsByEq' => $bookingsByEq] = $ctx;
  include BASE_DIR . '/app/views/intern/_equipment_children.php';
}
// Übersetzbare Inhalte (Bio, Slogan, Booking-Text, Rechtstexte):
// gewählte Sprache -> Standardsprache -> Englisch -> Deutsch (Basis in settings)
function content(string $key): string {
  $lang = current_lang();
  if ($lang !== 'de') {
    foreach (array_unique([$lang, default_lang(), 'en']) as $tryLang) {
      if ($tryLang === 'de') break;
      $v = row('SELECT value FROM translations WHERE lang = ? AND tkey = ?', [$tryLang, 'content_' . $key]);
      if ($v && trim($v['value']) !== '') return $v['value'];
    }
  }
  return setting($key);
}
/**
 * Vollständige Adresse für Links in E-Mails und Kalenderdateien.
 *
 * Die Adresse aus dem Anfragekopf zu nehmen ist bequem, aber angreifbar:
 * Wer beim Zurücksetzen eines Passworts einen fremden Host mitschickt,
 * bekommt einen Link auf seine eigene Seite in die fremde Mail. Steht eine
 * feste Adresse in den Einstellungen, gilt die; sonst wird der Host nur
 * übernommen, wenn er wie ein Hostname aussieht.
 */
function absolute_url(string $path): string {
  $fixed = setting('site_url');
  if ($fixed !== '') return rtrim($fixed, '/') . $path;
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
  if (!preg_match('~^[A-Za-z0-9.\-]+(:\d+)?$~', $host)) $host = 'localhost';
  return $scheme . '://' . $host . $path;
}
function fmt_date(?string $iso): string {
  if (!$iso) return '';
  $t = strtotime($iso);
  if (!$t) return $iso;
  $wd = explode(',', t('weekdays'))[(int) date('w', $t)] ?? '';
  return "$wd, " . date('d.m.Y', $t);
}
function fmt_duration(int|string|null $sec): string {
  $sec = (int) $sec;
  if ($sec <= 0) return '–';
  return floor($sec / 60) . ':' . str_pad((string) ($sec % 60), 2, '0', STR_PAD_LEFT);
}

function view(string $template, array $vars = []): never {
  $settings = all_settings();
  $user = current_user();
  $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/') ?: '/';
  $flashMsg = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  extract($vars);
  require BASE_DIR . '/app/views/' . $template . '.php';
  exit;
}
