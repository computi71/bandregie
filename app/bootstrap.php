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

$db = new PDO(
  "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
  $config['db_user'],
  $config['db_pass'],
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);

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
  'mem_title' => 'Mitglieder', 'mem_new' => 'Neues Mitglied', 'mem_start_pw' => 'Start-Passwort',
  'mem_pw_min' => 'min. 8 Zeichen', 'mem_you' => 'du', 'mem_my_profile' => 'Mein Profil',
  'mem_password' => 'Passwort', 'mem_new_pw' => 'Neues Passwort', 'mem_set' => 'Setzen',
  'mem_first_name' => 'Vorname', 'mem_last_name' => 'Nachname',
  'mem_name_hint' => 'Angezeigt wird „Vorname Nachname“ — oder der Künstlername, falls gesetzt.',
  'mem_mobile' => 'Mobil', 'mem_substitute_for' => 'Ersatz für',
  'mem_substitute_none' => '– niemanden –',
  'mem_instrument_pick' => 'aus dem Equipment wählen',
  'mem_instrument_free' => 'oder frei eintragen',
  'ev_substitute_hint' => 'Ersatz fragen:',
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
  'set_langs_hint' => 'Welche Sprachen im Auswahlmenü der Website erscheinen. Deutsch ist immer aktiv (Fallback).',
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
  'fl_member_required' => 'Name, E-Mail und Passwort (min. 8 Zeichen) sind Pflicht.',
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
  // Bandkasse
  'inav_kasse' => 'Kasse', 'fin_title' => 'Bandkasse', 'fin_balance' => 'Kontostand',
  'fin_income' => 'Einnahmen', 'fin_expense' => 'Ausgaben', 'fin_new' => 'Neue Buchung',
  'fin_type_in' => 'Einnahme', 'fin_type_out' => 'Ausgabe', 'fin_amount' => 'Betrag (€)',
  'fin_category' => 'Kategorie', 'fin_description' => 'Beschreibung',
  'fin_event' => 'Termin (optional)', 'fin_member' => 'Mitglied (optional)',
  'fin_add' => 'Buchen', 'fin_year' => 'Jahr', 'fin_all_years' => 'Alle Jahre',
  'fin_none' => 'Noch keine Buchungen.', 'fin_by_category' => 'Nach Kategorie',
  'fin_import_gage' => 'Gage übernehmen', 'fin_open_fees' => 'Noch nicht verbuchte Gagen',
  'fin_total' => 'Gesamt',
  'fincat_gage' => 'Gage', 'fincat_ausschuettung' => 'Ausschüttung',
  'fincat_einlage' => 'Einlage', 'fincat_merch' => 'Merch/Verkauf',
  'fincat_proberaum' => 'Proberaum', 'fincat_equipment' => 'Equipment', 'fincat_gema' => 'GEMA',
  'fincat_fahrt' => 'Fahrtkosten', 'fincat_verpflegung' => 'Verpflegung', 'fincat_sonstiges' => 'Sonstiges',
  'fl_fin_saved' => 'Buchung gespeichert.', 'fl_fin_deleted' => 'Buchung gelöscht.',
  'fl_fin_invalid' => 'Bitte Datum, Beschreibung und gültigen Betrag angeben.',
  // Produktion (PA/Licht) und Bewertungen
  'prod_pa' => 'PA', 'prod_light' => 'Licht',
  'prod_eigene' => 'Eigenes Material', 'prod_leih' => 'Geliehen/Gemietet', 'prod_vorhanden' => 'Vor Ort vorhanden',
  'prod_none' => 'nicht festgelegt', 'prod_hint' => 'Angebote und Rechnungen kommen als Datei an den Termin.',
  'rate_title' => 'Bewertung', 'rate_your' => 'Deine Bewertung', 'rate_avg' => 'Schnitt',
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
  'rider_positions_lbl' => 'Bühnenaufstellung',
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
  'eq_parts' => 'Bestandteile', 'eq_part_of' => 'Teil von',
  'eq_inherit_hint' => 'Besitzer und Lagerort übernimmt das Bestandteil vom übergeordneten Gerät.',
  'eq_purchased' => 'Kaufdatum', 'eq_price' => 'Kaufpreis',
  'eq_price_each' => 'Kaufpreis (je Stück)',
  'eq_count' => 'Anzahl', 'eq_count_hint' => 'Mehr als eins legt gleich mehrere durchnummerierte Geräte an — praktisch bei Kabeln.',
  'eq_value_sum' => 'Anschaffungswert',
  'eq_images' => 'Bilder und Unterlagen',
  'eq_deadlines' => 'Fristen', 'eq_deadline_new' => 'Neue Frist',
  'eq_deadline_title_ph' => 'z. B. TÜV, Steuer, Versicherung',
  'eq_due' => 'Fällig am', 'eq_interval' => 'Wiederholung',
  'eq_interval_0' => 'einmalig', 'eq_interval_6' => 'halbjährlich',
  'eq_interval_12' => 'jährlich', 'eq_interval_24' => 'alle 2 Jahre',
  'eq_done' => 'Erledigt ✓',
  'eq_done_hint' => '„Erledigt" schiebt wiederkehrende Fristen automatisch um ihr Intervall weiter.',
  'eq_overdue' => 'überfällig', 'eq_due_soon' => 'fällig in', 'eq_days' => 'Tagen',
  'dash_deadlines' => 'Anstehende Fristen',
  'fl_eq_saved_n' => '%d Geräte angelegt.',
  'fl_eq_saved' => 'Equipment gespeichert.', 'fl_eq_deleted' => 'Equipment gelöscht.',
  'fl_deadline_saved' => 'Frist gespeichert.',
  'fl_deadline_done' => 'Frist erledigt — nächster Termin gesetzt.',
  'fl_deadline_done_once' => 'Frist erledigt und entfernt.',
  'fl_deadline_deleted' => 'Frist gelöscht.',
  'mem_finance' => 'Kasse verwalten (Finanz)',
  'fin_badge' => 'Finanz',
  'fin_readonly_hint' => 'Buchungen macht, wer die Kasse verwaltet (Finanz) — du kannst hier alles einsehen.',
  'fl_finance_required' => 'Buchungen darf nur machen, wer die Kasse verwaltet (Finanz).',
];

// Bandkassen-Kategorien
const FIN_CATEGORIES = [
  'gage' => 'Gage', 'ausschuettung' => 'Ausschüttung', 'einlage' => 'Einlage',
  'merch' => 'Merch/Verkauf', 'proberaum' => 'Proberaum', 'equipment' => 'Equipment',
  'gema' => 'GEMA', 'fahrt' => 'Fahrtkosten', 'verpflegung' => 'Verpflegung',
  'sonstiges' => 'Sonstiges',
];

// Welche Felder bei welcher Termin-Art sinnvoll sind — der Rest wird im
// Formular ausgeblendet. Die öffentliche Seite zeigt ausschließlich Gigs,
// deshalb hat der öffentliche Block bei allen anderen Arten keine Wirkung.
const EVENT_TYPE_FIELDS = [
  'gig'          => ['times', 'venue', 'setlist', 'fee', 'production', 'public'],
  'party'        => ['times', 'venue', 'setlist', 'fee', 'production'],
  'probe'        => ['times', 'venue', 'setlist'],
  'aufnahme'     => ['times', 'venue', 'setlist'],
  'fotoshooting' => ['times', 'venue'],
  'besprechung'  => ['times', 'venue'],
  'aufbau'       => ['times', 'venue', 'production'],
  'reise'        => ['times'],
  'dayoff'       => [],
  'sonstiges'    => ['times', 'venue', 'setlist', 'fee', 'production'],
];

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
foreach (['pa_source', 'light_source'] as $prodCol) {
  if (!column_exists('events', $prodCol)) {
    $db->exec("ALTER TABLE events ADD COLUMN `$prodCol` VARCHAR(20) NOT NULL DEFAULT ''");
  }
}
foreach (['first_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'last_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'phone' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'mobile' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'substitute_for' => 'INT NULL'] as $col => $ddl) {
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
  'enabled_langs' => 'de,en,nl,fr,es,it',
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

// Erstinstallation: mitgelieferte Übersetzungen einspielen, damit alle Sprachen
// sofort verfügbar sind (danach werden sie im Bandbereich gepflegt).
if ((int) row('SELECT COUNT(*) AS n FROM translations')['n'] === 0) {
  foreach (glob(BASE_DIR . '/seed/translations/*.sql') ?: [] as $file) {
    try { $db->exec((string) file_get_contents($file)); } catch (PDOException) { /* Seed ist optional */ }
  }
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
      : row('SELECT id, name, stage_name, email, role, instrument, avatar_file, must_change_pw, can_finance FROM users WHERE id = ?', [$_SESSION['uid']]);
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

// Kassen-Schreibrecht: nur Mitglieder mit Finanz-Häkchen (vergeben Admins unter Mitglieder)
function can_finance(): bool {
  $u = current_user();
  return $u !== null && !empty($u['can_finance']);
}

function redirect(string $to): never { header("Location: $to"); exit; }
function back(string $fallback): never { redirect($_SERVER['HTTP_REFERER'] ?? $fallback); }
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

function enabled_langs(): array {
  $langs = array_values(array_intersect(array_keys(LANGS), array_map('trim', explode(',', setting('enabled_langs', 'de')))));
  if (!in_array('de', $langs, true)) array_unshift($langs, 'de');
  return $langs;
}
function default_lang(): string {
  $lang = setting('default_lang', 'de');
  return in_array($lang, enabled_langs(), true) ? $lang : 'de';
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
  return $lang = 'de';
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

/** Kaufpreis und -datum eines Geräts als eine lesbare Angabe. */
function eq_purchase_label(array $eq): string {
  $parts = [];
  if ($eq['price_cents'] !== null && $eq['price_cents'] !== '') $parts[] = fmt_money((int) $eq['price_cents']);
  if (!empty($eq['purchased_on'])) $parts[] = fmt_date($eq['purchased_on']);
  return implode(' · ', $parts);
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
function absolute_url(string $path): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
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
