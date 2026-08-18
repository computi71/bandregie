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
define('BANDREGIE_VERSION', trim(@file_get_contents(dirname(__DIR__) . '/VERSION') ?: '') ?: 'dev');
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

// Der Tresor kommt vor allem anderen: er hängt nur an der Konfiguration, und
// ohne ihn wüsste weder die Sicherung noch die Dateiausgabe, ob verschlüsselt
// abgelegt wird.
require_once __DIR__ . '/tresor.php';
// Web-Push: Profil (Themen, Geräte-Abo) und mehrere Schreib-Routen lösen
// Mitteilungen aus — auch dieses Modul ist überall im Spiel.
require_once __DIR__ . '/push.php';
// Steuerliche Werte: seit die Nutzungsdauer am einzelnen Gerät steht, fragen
// auch das Geräteformular und die Einstellungen danach — nicht mehr nur die
// Steuerseite, die das Modul früher allein geladen hat.
require_once __DIR__ . '/steuer.php';
// Anmeldung mit Passkey: Login-Seite und Profil fragen danach.
require_once __DIR__ . '/passkey.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/onedrive.php';

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
  error_log('Bandregie: Datenbankverbindung fehlgeschlagen — ' . $e->getMessage());
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
  'months' => 'Januar,Februar,März,April,Mai,Juni,Juli,August,September,Oktober,November,Dezember',
  'nav_start' => 'Start', 'nav_termine' => 'Termine', 'nav_musik' => 'Musik', 'nav_fotos' => 'Fotos',
  'nav_kontakt' => 'Kontakt', 'nav_downloads' => 'Downloads', 'nav_bandbereich' => 'Bandbereich',
  'nav_impressum' => 'Impressum', 'nav_datenschutz' => 'Datenschutz',
  // Beispieltexte in den Eingabefeldern — sie erklären das Feld und
  // gehören deshalb übersetzt wie jede andere Beschriftung.
  'eq_name_ph' => 'z. B. Bandanhänger, PA-Topteile, Funkstrecke',
  'eq_location_ph' => 'z. B. Proberaum, Anhänger, bei Andi',
  'ch_source_ph' => 'z. B. SM57, DI',
  'ord_desc_ph' => 'z. B. Proberaummiete',
  'fin_amount_ph' => 'z. B. 49,90',
  'mem_instrument_ph' => 'z. B. Drums',
  'media_title_ph' => 'z. B. Live beim Stadtfest',
  'song_key_ph' => 'z. B. Am',
  'song_tempo_ph' => 'z. B. 120 BPM',
  'ev_fee_ph' => 'z. B. 800 €',
  'up_title' => 'Aktualisierung',
  'up_out' => 'ist da',
  'up_current' => 'Ihr seid auf dem neuesten Stand.',
  'up_checking' => 'Frage nach …',
  'up_failed' => 'Nachfragen hat nicht geklappt — vielleicht kommt der Server gerade nicht ins Netz.',
  'up_available' => 'Fassung %s ist da',
  'up_intro' => 'Bandregie aktualisiert sich nicht selbst. Dafür müsste der Webserver in sein eigenes Verzeichnis schreiben dürfen, und dann würde aus jeder Lücke, die einmal eine Datei schreiben lässt, eine dauerhafte Übernahme. Ein Befehl auf der Konsole kostet zwei Sekunden mehr und diesen Preis nicht.',
  'up_installed' => 'Installiert:', 'up_latest' => 'Neueste Fassung:', 'up_unknown' => 'nicht nachgesehen',
  'up_how_git' => 'Das Skript sichert erst Datenbank und Dateien und holt dann die neue Fassung:',
  'up_how_plesk' => 'Diese Installation läuft unter Plesk — das ausgelieferte Verzeichnis ist keine Git-Arbeitskopie, ein „git pull" liefe hier ins Leere. Plesk holt und verteilt:',
  'up_plesk_name' => 'Der Wert hinter -name ist der Name des Repositorys in Plesk. Heißt es dort anders — etwa noch wie ein früherer Projektname —, gehört dieser Name in den Befehl, sonst findet Plesk nichts.',
  'up_manual' => 'Diese Installation ist keine Git-Arbeitskopie. Neue Dateien einspielen, aber data/ und app/config.php dabei niemals überschreiben — und vorher eine Sicherung ziehen.',
  'up_cron' => 'Soll es von allein laufen, gehört es in die cron-Tabelle des Benutzers, dem die Arbeitskopie gehört:',
  'up_check' => 'Einmal am Tag nachsehen, ob es eine neue Fassung gibt',
  'up_check_hint' => 'Fragt bei GitHub nach der Versionsnummer — ohne Anmeldung und ohne Angaben über diese Installation. Abgeschaltet fragt Bandregie nie von sich aus nach draußen.',
  'fl_up_saved' => 'Einstellung zur Aktualisierung gespeichert.',
  'tax_title' => 'Umsatzgrenze im Blick',
  'tax_turnover_year' => 'Umsatz %d:',
  'tax_of' => 'von',
  'tax_state_close' => 'Ihr nähert euch der Grenze für das laufende Jahr. Zweierlei steht damit an: Die Befreiung endet ohnehin zum 1. Januar, weil dieses Jahr schon über der Vorjahresgrenze liegt — und wenn ihr die obere Grenze noch reißt, endet sie sofort mit dem Umsatz, der sie reißt.',
  'tax_state_next_year' => 'Der Umsatz liegt über der Vorjahresgrenze. Für dieses Jahr ändert das nichts — die Befreiung endet aber zum 1. Januar von selbst, unabhängig davon, wie das nächste Jahr läuft. Ab dann gehört Umsatzsteuer auf jede Rechnung.',
  'tax_state_over_this' => 'Die Grenze für das laufende Jahr ist überschritten. Die Befreiung endet damit — sprecht mit eurer Steuerberatung, bevor die nächste Rechnung rausgeht.',
  'tax_state_over_prev' => 'Der Umsatz des Vorjahres lag über der Grenze. Für dieses Jahr gilt die Befreiung damit nicht mehr.',
  'tax_counts_hint' => 'Gezählt werden alle Einnahmen der Band außer den Einzahlungen der Mitglieder — die sind Beiträge und kein Verkauf. Private Buchungen bleiben außen vor. Auch der Verkauf eines Geräts zählt nicht mit: Umsätze mit Anlagevermögen bleiben nach § 19 Abs. 2 Satz 2 UStG außer Ansatz. Erkannt wird das an der Verbindung zum Gerät — wer Technik vermietet, nimmt Umsatz ein und bucht deshalb ohne Gerätebezug.',
  'tax_gbr_hint' => 'Gerechnet wird mit dem Umsatz der Band als Ganzes. Das passt, wenn ihr gemeinsam auftretet und abrechnet — der Regelfall. Stellt jedes Mitglied eigene Rechnungen, zählt stattdessen jeder für sich, und diese Zahl sagt darüber nichts.',
  'tax_no_advice' => 'Eine Rechenhilfe, keine Steuerberatung — und nichts davon ist rechtsverbindlich. Was am Ende erklärt wird, verantworten die Band und ihre Steuerberatung, nicht dieses Programm. Die Grenzen stehen in den Einstellungen und lassen sich ändern.',
  'set_tax' => 'Steuerliche Werte',
  'set_tax_hint' => 'Voreingestellt ist der deutsche Stand. Ändert der Gesetzgeber die Zahlen — oder sitzt ihr anderswo —, tragt sie hier ein und bestätigt das Datum.',
  'set_tax_small' => 'Wir nutzen die Kleinunternehmerregelung',
  'set_tax_small_hint' => 'Erst damit rechnet die Kasse mit und warnt an der Grenze.',
  'set_tax_prev' => 'Grenze Vorjahr (€)', 'set_tax_this' => 'Grenze laufendes Jahr (€)',
  'set_tax_gwg' => 'Grenze geringwertige Wirtschaftsgüter (€, netto)',
  'set_tax_gwg_hint' => 'Bis zu diesem Betrag ist ein Gerät im Jahr des Kaufs abgeschrieben, darüber über seine Nutzungsdauer. Die Grenze ist netto zu prüfen — bei 19 % sind das 952 € brutto.',
  'set_tax_afa_years' => 'Nutzungsdauer (Jahre)',
  'set_tax_afa_hint' => 'Gilt für Geräte ohne eigene Angabe und ohne Voreinstellung ihrer Art.',
  'set_tax_gross' => 'Kaufpreise werden brutto erfasst',
  'set_tax_gross_hint' => 'Für die GWG-Grenze rechnet die Kasse dann auf netto herunter. Angesetzt wird weiter der bezahlte Betrag: Ohne Vorsteuerabzug gehört die Umsatzsteuer zu den Anschaffungskosten (§ 9b Abs. 1 EStG).',
  'set_tax_vat_rate' => 'Umsatzsteuersatz für diese Umrechnung (%)',
  'set_tax_afa_cats' => 'Nutzungsdauer je Geräteart (Jahre)',
  'set_tax_afa_cats_hint' => 'Aus der amtlichen AfA-Tabelle „AV": Lautsprecher und Verstärker sieben Jahre, Mischpulte und Mikrofone als Audiogeräte ebenfalls sieben, ganze Beschallungsanlagen neun, Transportbehälter zehn, Anhänger elf. Für Licht führt die AV-Tabelle nichts Passendes; fünf Jahre stehen in der Branchentabelle für Fernsehen, Film und Hörfunk. Instrumente stehen in keiner allgemeinen Tabelle mehr — dort zählt die Angabe am Gerät.',
  'eq_afa_years' => 'Nutzungsdauer (Jahre)',
  'eq_afa_hint' => 'Leer heißt: der Wert für diese Geräteart. Ein Flügel hält länger als eine Snare, deshalb steht er hier einzeln.',
  'set_tax_checked' => 'Werte zuletzt geprüft am',
  'set_tax_source' => 'Stand Juli 2026, Deutschland: § 19 UStG 25.000 € Vorjahr und 100.000 € laufendes Jahr, § 6 Abs. 2 EStG 800 € netto.',
  'taxr_title' => 'Steuerübersicht',
  'taxr_intro' => 'Was in einem Jahr zusammengekommen ist, aufbereitet für die Steuererklärung — zum Ausdrucken oder als Tabelle für die Steuerberatung.',
  'taxr_open' => 'Steuerübersicht öffnen',
  'taxr_scope_own' => 'Meine Zahlen', 'taxr_scope_band' => 'Zahlen der Band',
  'taxr_scope_own_hint' => 'Nur die Buchungen, die als privat eingetragen sind und dir gehören. Was die Band bezahlt hat, steht hier nicht.',
  'taxr_scope_band_hint' => 'Die Zahlen der Bandkasse. Private Buchungen einzelner Mitglieder sind nicht darin — auch die eigenen nicht.',
  'taxr_result_year' => 'Ergebnis %d',
  'taxr_sum' => 'Summe',
  'taxr_afa' => 'Abschreibungen',
  'taxr_equipment' => 'Anschaffungen',
  'help_gbr_title' => 'Rechtsform und Haftung',
  'help_gbr_form' =>'Sobald mehrere gemeinsam auftreten, eine Gage teilen und zusammen Technik anschaffen, sind sie rechtlich eine Gesellschaft bürgerlichen Rechts. Dafür braucht es keinen Vertrag, keine Anmeldung und keinen Namen — sie entsteht durch das Tun (§ 705 BGB). Anders liegt es nur ohne gemeinsamen Zweck: wenn eine Person die Band ist und Musiker je Auftritt bezahlt, oder jeder eigene Rechnungen stellt. Dann gibt es einen Bandleader und Selbstständige, jeden mit eigenen Zahlen.',
  'help_gbr_liability' => 'Daraus folgt etwas, das man kennen sollte: In einer GbR haftet jedes Mitglied persönlich, gesamtschuldnerisch und unbeschränkt — mit dem Privatvermögen, nicht nur mit dem, was in der Bandkasse liegt (§ 721 BGB). Wem Geld zusteht, der darf sich aussuchen, wen von euch er in Anspruch nimmt. Eine Abrede unter euch ändert daran nichts: Sie wirkt untereinander, nicht gegenüber der Vermieterin oder dem Veranstalter. Und wer neu dazukommt, haftet auch für das, was vorher entstanden ist (§ 721a BGB) — das gehört vor dem Einstieg besprochen, nicht danach.',
  'help_gbr_register' => 'Seit dem 1. Januar 2024 gibt es das Gesellschaftsregister und die eingetragene eGbR. Die Eintragung ist freiwillig und weder für das Bestehen der Gesellschaft noch für ihre Rechtsfähigkeit nötig; verlangt wird sie erst, wenn die GbR selbst in ein anderes Register soll, etwa ins Grundbuch. Für eine Band, die Auftritte spielt und einen Proberaum mietet, ist sie in aller Regel entbehrlich. An der persönlichen Haftung ändert eine Eintragung ohnehin nichts.',
  'help_taxr_shares' =>'In der Bandansicht steht zusätzlich eine Zeile je Mitglied: eingezahlt, bekommen, was netto durch die Kasse gegangen ist, und der Gewinnanteil für die eigene Erklärung. Erklärt wird nämlich nicht, was ausgezahlt wurde, sondern der Anteil am Gewinn — bei einer GbR wird er den Gesellschaftern zugerechnet, ob er auf dem Konto liegt oder nicht. Der Anteil ist zu gleichen Teilen gerechnet; habt ihr etwas anderes vereinbart, gilt eure Abrede und nicht diese Spalte. Weicht „Kasse netto" zwischen den Mitgliedern stark ab, tragen einzelne mehr als die anderen — das gehört in der Kasse ausgeglichen, nicht in der Steuererklärung.',
  'pk_login' => 'Mit Passkey anmelden',
  'pk_add' => 'Passkey für dieses Gerät anlegen',
  'pk_remove' => 'Entfernen',
  'pk_label' => 'Name des Geräts',
  'pk_label_placeholder' => 'z. B. mein iPhone',
  'pk_device' => 'Gerät',
  'pk_none' => 'Noch kein Passkey angelegt.',
  'pk_added' => 'angelegt am %1',
  'pk_last_used' => 'zuletzt benutzt am %1',
  'pk_never_used' => 'noch nicht benutzt',
  'pk_cancelled' => 'Abgebrochen.',
  'pk_none_here' => 'Auf diesem Gerät liegt noch kein Passkey. Melde dich mit E-Mail und Passwort an — danach kannst du im Profil einen für dieses Gerät anlegen.',
  'pk_offer_title' => 'Nächstes Mal ohne Passwort?',
  'pk_offer' => 'Auf diesem Gerät kannst du dir einen Passkey anlegen. Dann genügt beim Anmelden dein Gesicht, dein Fingerabdruck oder der Gerätecode — das Passwort bleibt daneben bestehen und funktioniert weiter.',
  'pk_offer_yes' => 'Passkey anlegen',
  'pk_offer_later' => 'Später',
  'pk_unsupported' => 'Dieser Browser kann keine Passkeys. Das Passwort funktioniert weiter.',
  'prof_passkeys' => 'Passkeys',
  'prof_passkeys_hint' => 'Ein Passkey ist ein Schlüssel, der in deinem Schlüsselbund liegt und sich mit Gesichtserkennung, Fingerabdruck oder der Gerätesperre öffnen lässt. Weder Gesicht noch Fingerabdruck verlassen dabei das Gerät — hier liegt nur der öffentliche Teil, mit dem sich ausschließlich prüfen lässt, ob eine Unterschrift von dir stammt. Dein Passwort bleibt daneben bestehen und funktioniert weiter.',
  'prof_passkeys_sync' => 'Einer je Schlüsselbund genügt, nicht einer je Gerät: Ein Passkey im iCloud-Schlüsselbund gilt auf iPhone, iPad und Mac zugleich, einer im Passwortmanager überall dort, wo der eingerichtet ist. Nur gerätegebundene wie Windows Hello brauchen je Rechner einen eigenen. Einen zweiten anzulegen schadet nicht — er ist dann eben ein zweiter Weg herein.',
  // Zweiter Faktor (#169)
  'totp_title' => 'Zweiter Faktor',
  'totp_hint' => 'Ein Passwort kann abgeschaut, erraten oder aus dem Datenleck einer ganz anderen Seite wiederverwendet werden. Der zweite Faktor hilft genau dagegen: eine sechsstellige Zahl, die alle dreißig Sekunden wechselt und nur auf deinem Gerät entsteht. Wer dein Passwort hat, kommt damit trotzdem nicht herein.',
  'totp_passkey_note' => 'Beim Anmelden mit Passkey wird nicht danach gefragt — dort steckt der zweite Faktor schon im Entsperren des Geräts.',
  'totp_none' => 'Noch nicht eingerichtet.',
  'totp_setup_open' => 'Zweiten Faktor einrichten',
  'totp_active_since' => 'Aktiv seit %1.',
  'totp_remove' => 'Zweiten Faktor entfernen',
  'totp_removed' => 'Zweiter Faktor entfernt.',
  'totp_cannot_remove' => 'Die Band hat den zweiten Faktor vorgeschrieben; er lässt sich hier nicht abschalten.',
  'totp_forced_intro' => 'Die Band hat den zweiten Faktor vorgeschrieben. Einmal einrichten, dann geht es weiter — ohne ihn bleibt der Bandbereich zu.',
  'totp_forced_undo' => 'Doch nicht vorschreiben',
  'totp_setup_title' => 'Zweiten Faktor einrichten',
  'totp_setup_app' => '1. Eine Authenticator-App aufs Handy, falls noch keine da ist. Es geht jede: Google Authenticator, Microsoft Authenticator, Aegis, 2FAS, der Passwortmanager oder der eingebaute Schlüsselbund. Alle rechnen dasselbe aus, du bist an keinen Anbieter gebunden.',
  'totp_setup_scan' => '2. Das Konto in die App übernehmen. Welcher Weg der richtige ist, hängt nur davon ab, wo die App liegt.',
  'totp_setup_here' => '📱 Die App liegt auf diesem Gerät — der Normalfall, wenn du gerade am Handy bist. Dann geht es ohne Foto: Der Knopf öffnet die App und legt das Konto dort an. Tut sich nichts, ist noch keine Authenticator-App installiert.',
  'totp_setup_open_app' => 'In der App öffnen',
  'totp_setup_other' => '💻 Die App liegt auf einem anderen Gerät — der Normalfall, wenn du gerade am Rechner sitzt. Dann in der App „Konto hinzufügen" wählen und diesen Code abfotografieren.',
  'totp_setup_confirm' => '3. Die sechsstellige Zahl, die dann erscheint, hier eintragen. Damit ist bewiesen, dass die App wirklich funktioniert — erst dann wird beim Anmelden danach gefragt.',
  'totp_manual_title' => 'Kein Foto möglich?',
  'totp_manual_hint' => 'Dann in der App „Schlüssel manuell eingeben" wählen und diese Zeichenfolge abtippen. Groß- und Kleinschreibung sowie Leerzeichen sind egal.',
  'totp_code_label' => 'Sechsstelliger Code',
  'totp_confirm' => 'Bestätigen und einschalten',
  'totp_wrong' => 'Der Code stimmt nicht. Er wechselt alle dreißig Sekunden — nimm den, der gerade angezeigt wird.',
  'totp_step_title' => 'Zweiter Faktor',
  'totp_step_hint' => 'Passwort stimmt. Jetzt noch die sechsstellige Zahl aus deiner Authenticator-App.',
  'totp_step_recovery' => 'Handy verloren oder App weg? Dann hier einen deiner Rückweg-Codes eintragen — jeder gilt genau einmal. Sind auch die weg, setzt die Bandleitung den zweiten Faktor zurück.',
  'totp_codes_title' => 'Deine Rückwege',
  'totp_codes_hint' => 'Diese Codes ersetzen die App, wenn das Handy weg ist. Jeder gilt genau einmal. Jetzt ausdrucken oder in den Passwortmanager legen — sie werden nur dieses eine Mal gezeigt, danach liegt hier nur noch ihr Abdruck.',
  'totp_codes_left' => 'Noch %1 Rückwege übrig.',
  'totp_codes_none_left' => 'Keine Rückwege mehr übrig. Ohne Handy käme jetzt nur noch die Bandleitung weiter.',
  'totp_codes_new' => 'Neue Rückwege erzeugen',
  'totp_codes_new_hint' => 'Erzeugt zehn neue und macht alle bisherigen ungültig. Braucht einen aktuellen Code aus der App.',
  'totp_reset_member' => 'Zweiten Faktor zurücksetzen',
  'fl_totp_reset' => 'Zweiter Faktor zurückgesetzt. Beim nächsten Anmelden wird nicht mehr danach gefragt.',
  'set_totp' => 'Zweiter Faktor',
  'set_totp_hint' => 'Gilt für das Anmelden mit Passwort. Wer sich mit Passkey anmeldet, wird nie gefragt — dort ist der zweite Faktor die Gerätesperre selbst.',
  'set_totp_off' => 'Aus — niemand kann ihn einrichten, bestehende werden nicht mehr abgefragt.',
  'set_totp_optional' => 'Freiwillig — wer mag, richtet ihn im Profil ein.',
  'set_totp_required' => 'Vorgeschrieben — wer keinen hat, richtet ihn beim nächsten Anmelden ein, bevor es weitergeht.',
  'help_totp_title' => 'Zweiter Faktor beim Anmelden',
  'help_totp_what' => 'Der zweite Faktor ist eine sechsstellige Zahl, die alle dreißig Sekunden wechselt. Sie entsteht aus einem Geheimnis, das nur deine App und dieser Server kennen, und aus der aktuellen Uhrzeit — deshalb braucht die App weder Netz noch ein Konto irgendwo. Wer dein Passwort in die Finger bekommt, kommt ohne dein Handy trotzdem nicht herein.',
  'help_totp_apps' => 'Welche App du nimmst, ist deine Sache: Google Authenticator, Microsoft Authenticator, Aegis, 2FAS, 1Password, Bitwarden oder der Schlüsselbund von Apple und Android. Alle rechnen nach demselben offenen Verfahren, keine ist an Bandregie gebunden, und du kannst jederzeit wechseln.',
  'help_totp_setup' => 'Eingerichtet wird er im Profil unter „Zweiter Faktor": QR-Code mit der App abfotografieren, die angezeigte Zahl einmal eintragen, fertig. Die Zahl bei der Einrichtung ist kein Formalismus — sie beweist, dass die App wirklich rechnet, bevor sie zur Bedingung fürs Hereinkommen wird.',
  'help_totp_recovery' => 'Beim Einschalten bekommst du zehn Rückwege. Jeder ersetzt einmal die App, wenn das Handy weg ist. Sie werden genau einmal gezeigt und danach nur noch als Abdruck gespeichert — ausdrucken oder in den Passwortmanager. Sind alle verbraucht, setzt die Bandleitung den zweiten Faktor zurück.',
  'help_totp_passkey' => 'Beim Anmelden mit Passkey fragt niemand nach einer Zahl. Das ist keine Lücke: Ein Passkey wird mit Gesichtserkennung, Fingerabdruck oder der Gerätesperre freigegeben, das ist bereits ein zweiter Faktor — und zwar einer, der nicht abgetippt werden kann.',
  'help_totp_admin' => 'Die Bandleitung stellt unter Einstellungen ein, ob es ihn gibt, ob er freiwillig ist oder für alle gilt. Bei „vorgeschrieben" wird jeder ohne zweiten Faktor beim nächsten Anmelden durch die Einrichtung geführt, bevor der Bandbereich aufgeht. Und sie kann ihn bei einem Mitglied zurücksetzen, wenn Handy und Rückwege zugleich weg sind.',
  'help_totp_clock' => 'Stimmt der Code nie, geht meist die Uhr des Handys falsch. Das Verfahren rechnet mit der Zeit; eine halbe Minute Abweichung wird verziehen, mehr nicht. In den Handyeinstellungen die Uhrzeit auf automatisch stellen behebt das.',
  'help_push_trouble_title' => 'Mitteilungen kommen nicht an',
  'help_push_trouble_intro' => 'Zwischen einer Mitteilung und deinem Bildschirm liegen vier Schalter, und jeder einzelne kann sie stoppen — meist stumm, ohne Fehlermeldung. Deshalb der Reihe nach von innen nach außen; wer oben anfängt, findet es am schnellsten.',
  'help_push_trouble_app' => '1. In Bandregie: Im Profil unter „Mitteilungen" muss auf diesem Gerät „Auf diesem Gerät aktivieren" gedrückt worden sein — die Einstellung gilt je Gerät, nicht je Konto. Steht auf der Startseite der Hinweis „Mitteilungen sind auf diesem Gerät aus", ist es genau das. Die Haken darüber wählen nur die Themen; sie ersetzen das Aktivieren nicht.',
  'help_push_trouble_site' => '2. Erlaubnis für diese Seite: Der Browser fragt einmal und merkt sich die Antwort. Nachsehen über das Schloss- oder Info-Symbol in der Adressleiste. Steht dort „blockiert", auf „Zulassen" stellen und die Seite neu laden.',
  'help_push_trouble_browser' => '3. Der Browser insgesamt: In Chrome und Edge gibt es einen Hauptschalter — „Vor dem Senden fragen (empfohlen)", zu finden unter edge://settings/content/notifications bzw. chrome://settings/content/notifications. Ist er aus, wird jede Anfrage still abgelehnt, und die Freigabe je Seite steht gar nicht zur Wahl. Kommt weder ein Fenster noch ein Glockensymbol, kann auch eine Sperre nach dreimaligem Wegklicken dahinterstecken: Dann fragt der Browser diese Seite eine Woche lang nicht mehr, ohne das anzuzeigen. Beides umgeht man, indem man die Adresse auf derselben Seite unter „Berechtigt, Benachrichtigungen zu senden" von Hand einträgt.',
  'help_push_trouble_os' => '4. Das Betriebssystem: Der letzte Schalter liegt außerhalb des Browsers, und er ist der am leichtesten übersehene. Unter Windows in den Einstellungen bei „System → Benachrichtigungen" — dort muss der Browser selbst Mitteilungen zeigen dürfen, und „Nicht stören" darf nicht laufen. Auf dem Mac unter „Systemeinstellungen → Mitteilungen" dasselbe für den Browser. Auf dem Handy in den Einstellungen der jeweiligen App.',
  'help_push_trouble_ios' => 'Auf iPhone und iPad gibt es Mitteilungen ausschließlich für die installierte App vom Startbildschirm — solange Bandregie dort im Safari läuft, kommt keine an, egal was eingestellt ist. Erst „Zum Home-Bildschirm" wählen, die App von dort öffnen und dann im Profil aktivieren.',
  'help_push_trouble_dead' => 'Steht alles richtig und es kommt trotzdem nichts: Wahrscheinlich ist das Abo verwaist. Das passiert, wenn Mitteilungen zwischendurch abgeschaltet oder Browserdaten gelöscht wurden — der Zustelldienst nimmt die Nachricht dann weiter an und verwirft sie still, weshalb es hier wie ein Erfolg aussieht. Im Profil einmal abschalten und wieder aktivieren legt ein frisches an; das alte räumt sich nach drei Monaten ohne Lebenszeichen von selbst weg.',
  'push_off_here' => 'Mitteilungen sind auf diesem Gerät aus',
  'push_off_here_hint' => 'Du bekommst hier nichts mit, wenn ein Termin dazukommt oder jemand antwortet — die App bleibt einfach still. Tippen, um sie einzuschalten.',
  'pk_rename' => 'Umbenennen',
  'fl_pk_renamed' => 'Name geändert.',
  'fl_pk_failed' => 'Das hat nicht geklappt. Versuch es noch einmal oder nimm dein Passwort.',
  'fl_pk_removed' => 'Passkey entfernt.',
  'fl_pk_bad_data' => 'Die Antwort des Geräts war unvollständig.',
  'fl_pk_bad_type' => 'Die Antwort des Geräts passt nicht zur Anfrage.',
  'fl_pk_bad_challenge' => 'Die Anfrage ist abgelaufen. Bitte noch einmal versuchen.',
  'fl_pk_bad_origin' => 'Die Antwort kam von einer anderen Adresse.',
  'fl_pk_bad_rp' => 'Dieser Passkey gehört zu einer anderen Seite.',
  'fl_pk_no_presence' => 'Das Gerät hat nicht bestätigt, dass jemand davorsteht.',
  'fl_pk_bad_key' => 'Der Schlüssel des Geräts ließ sich nicht lesen.',
  'help_passkey_title' => 'Anmelden mit Passkey',
  'help_passkey_sync' => 'Wie viele du brauchst, hängt vom Schlüsselbund ab, nicht von der Zahl deiner Geräte. Ein Passkey im iCloud-Schlüsselbund wird zwischen iPhone, iPad und Mac abgeglichen — einmal angelegt, überall nutzbar. Dasselbe gilt für einen Passwortmanager: Dort liegt er am Konto, nicht am Gerät. Nur gerätegebundene Verfahren wie Windows Hello brauchen je Rechner einen eigenen. Wenn du magst, leg trotzdem mehrere an; sie stören einander nicht, und geht einer verloren, bleiben die anderen.',
  'help_passkey' => 'Statt eines Passworts kannst du dich mit einem Passkey anmelden: einem Schlüssel, der im gesicherten Bereich deines Geräts entsteht und dort bleibt. Geöffnet wird er, wie du dein Gerät öffnest — mit Gesicht, Fingerabdruck oder Code. Nichts davon erreicht diesen Server: Er bekommt nur den öffentlichen Teil des Schlüssels und bei jeder Anmeldung eine Unterschrift unter eine Zufallsfrage, die genau einmal gilt. Damit gibt es hier kein Passwort, das gestohlen werden könnte, und keine Anmeldung, die sich anderswo wiederverwenden ließe. Anlegen kannst du ihn im Profil. In der Liste steht er unter dem Namen seines Schlüsselbunds — „Apple Passwörter", „1Password", „Windows Hello" —, denn dort liegt er, und umbenennen kannst du ihn jederzeit. Dein Passwort bleibt bestehen und funktioniert weiter — geht ein Gerät verloren, kommst du damit trotzdem herein und entfernst den Passkey im Profil.',
  'set_tax_start' => 'Gegründet am',
  'set_tax_start_hint' => 'Nur für das Gründungsjahr wichtig: Dort gibt es kein Vorjahr, und die kleinere Grenze gilt für das laufende Jahr — als harte Decke. Hochgerechnet wird nichts, wer im Oktober anfängt hat dieselbe Grenze wie alle. Leer lassen, wenn die Band schon länger besteht.',
  'tax_first_year' => 'Gründungsjahr: Es gibt kein Vorjahr, deshalb gilt die kleinere Grenze für dieses Jahr — und zwar sofort. Wird sie überschritten, endet die Befreiung mit dem Umsatz, der sie reißt, nicht erst zum 1. Januar.',
  'mem_profit_share' => 'Am Gewinn beteiligt',
  'mem_profit_share_hint' => 'Gesellschafter teilen den Gewinn und erklären ihn jeder für sich. Wer für die Band arbeitet, ohne Gesellschafter zu sein — Management, Technik, ein Konto aus alten Zeiten —, gehört hier abgewählt: sonst bekommt er einen Anteil, und allen anderen fehlt er.',
  'taxr_members' => 'Je Mitglied',
  'taxr_members_hint' => 'Wer wie viel eingezahlt und bekommen hat — und was davon in die eigene Steuererklärung gehört. Erklärt wird nicht die Auszahlung, sondern der Gewinnanteil: Bei einer GbR wird der Gewinn den Gesellschaftern zugerechnet, gleich ob er ausgezahlt wurde oder liegen bleibt. Eine Einzahlung ist umgekehrt keine Ausgabe des Mitglieds, sondern Kapital.',
  'taxr_paid_in' => 'Eingezahlt',
  'taxr_took_out' => 'Bekommen',
  'taxr_cash_net' => 'Kasse netto',
  'taxr_share' => 'Gewinnanteil',
  'taxr_share_hint' => 'Gezeigt wird, wer am Gewinn beteiligt ist; wer es nicht ist, steht bei den Mitgliedern abgewählt und taucht hier nicht auf. Der Anteil ist zu gleichen Teilen gerechnet — so gilt es bei einer GbR ohne abweichende Abrede. Habt ihr eine andere Verteilung vereinbart, stimmt die Spalte nicht; die Kasse kennt eure Abrede nicht. Fällt „Kasse netto" bei einzelnen deutlich ab, tragen sie mehr als die anderen: Der Abzug für Miete und Proberaum senkt den Bandgewinn für alle, unabhängig davon, wer ihn bezahlt hat. Geradegerückt wird das nicht über die Steuer, sondern in der Kasse — indem die Einzahlungen zurückgezahlt werden, bevor der Gewinn verteilt wird. Das ändert an keiner Steuerzahl etwas, weil eine Einlagenrückzahlung so wenig Einkommen ist wie die Einlage Ausgabe war.',
  'taxr_equipment_hint' => 'Gerätekäufe stehen nicht bei den Ausgaben, sondern hier: oberhalb der Grenze verteilt sich der Preis über die Nutzungsdauer, gerechnet ab dem Kaufmonat. Sonst stünde derselbe Kauf zweimal im Ergebnis.',
  'taxr_purchased' => 'Gekauft', 'taxr_purchase_price' => 'Kaufpreis',
  'taxr_method' => 'Verfahren', 'taxr_amount_year' => 'In diesem Jahr', 'taxr_remaining' => 'Restwert',
  'taxr_kind_gwg' => 'sofort (geringwertig)',
  'taxr_kind_afa' => 'über %d Jahre (%d–%d)',
  'taxr_disposed' => 'Abgang %d, Restwert ausgebucht',
  'taxr_entries' => 'Einzelbuchungen',
  'taxr_empty' => 'Für dieses Jahr ist nichts gebucht.',
  'taxr_applied' => 'Womit gerechnet wurde',
  'taxr_package' => 'Paket mit Belegen',
  'taxr_package_hint' => 'Das Paket enthält zusätzlich die Belege: die Anhänge der Buchungen dieses Jahres und die Rechnungen der Geräte, die noch abschreiben — deren Papier liegt im Jahr des Kaufs. Eine Rechnung über mehrere Geräte liegt einmal bei.',
  'fl_taxr_no_zip' => 'Für das Paket fehlt die ZIP-Erweiterung von PHP. Die Tabelle allein geht trotzdem.',
  'taxr_small' => 'Kleinunternehmerregelung',
  'help_taxr_what' => 'Die Steuerübersicht fasst ein Jahr zusammen: Einnahmen und Ausgaben nach Kategorie, die Einzelbuchungen dahinter und was von den Anschaffungen in dieses Jahr fällt. Das Blatt lässt sich drucken, und als Tabelle geht es an die Steuerberatung.',
  'help_taxr_scope' => 'Jeder sieht dort seine eigenen privaten Buchungen — die, die nur ihm gehören. Wer die Kasse führt, kann zusätzlich auf die Zahlen der Band umschalten. Die beiden Ansichten enthalten einander nicht, und die privaten Buchungen anderer Mitglieder tauchen in keiner davon auf.',
  'help_taxr_afa' => 'Ein Gerätekauf steht nicht bei den Ausgaben, sondern bei den Anschaffungen: Bis zur Grenze für geringwertige Wirtschaftsgüter zählt er im Jahr des Kaufs vollständig, darüber verteilt er sich gleichmäßig über die Nutzungsdauer, gerechnet ab dem Kaufmonat. Sonst stünde derselbe Kauf zweimal im Ergebnis. Beide Werte stehen in den Einstellungen und gelten für alle Geräte gleich — weicht die Nutzungsdauer eines einzelnen Geräts davon ab, rechnet die Steuerberatung das um.',
  'taxr_small_on' => 'genutzt', 'taxr_small_off' => 'nicht genutzt',
  'taxr_gross_hint' => 'Die Beträge stehen so, wie sie gebucht wurden. Die Umsatzsteuer trennt die Kasse nicht heraus — wer nicht unter die Kleinunternehmerregelung fällt, rechnet mit Nettobeträgen und muss das dabei bedenken.',
  'sys_tax_stale' => 'Steuerliche Werte',
  'sys_tax_stale_detail' => 'seit über einem Jahr nicht bestätigt',
  'sys_tax_stale_conseq' => 'Prüft, ob die Grenzen noch stimmen, und bestätigt das Datum in den Einstellungen.',
  'fl_tax_saved' => 'Steuerliche Werte gespeichert.',
  'help_tax_title' => 'Umsatzgrenze und Kleinunternehmerregelung',
  'help_tax_what' => 'Wer die Kleinunternehmerregelung nutzt, weist auf Rechnungen keine Umsatzsteuer aus und führt keine ab. Dafür darf man auch keine Vorsteuer ziehen.',
  'help_tax_limits' => 'Es sind zwei Grenzen, und sie wirken völlig verschieden. Die eine gilt dem Vorjahr: Wer darüber liegt, verliert die Befreiung zum 1. Januar — das laufende Jahr bleibt unberührt, das nächste ist von Anfang an steuerpflichtig. Die andere gilt dem laufenden Jahr und ist eine harte Decke: Sie wird fortlaufend geprüft, und schon der Umsatz, mit dem sie überschritten wird, ist steuerpflichtig. Die eine schaltet also zum Jahreswechsel, die andere im Moment des Überschreitens. Für eine Band ist fast immer die Vorjahresgrenze die entscheidende — sie ist die kleinere und deshalb die, an die man zuerst stößt.',
  'help_tax_first_year' => 'Im ersten Jahr gibt es kein Vorjahr, und dann gilt die kleinere Grenze für das laufende — als harte Decke wie sonst die große: Überschritten endet die Befreiung mit dem Umsatz, der sie reißt. Anteilig hochgerechnet wird dabei nichts; wer im Oktober anfängt, hat dieselbe Grenze wie jemand, der im Januar begonnen hat. Damit die Kasse das erkennt, gehört das Gründungsdatum in die Einstellungen — ohne Datum rechnet sie wie für jede eingespielte Band.',
  'help_tax_back' =>'Der Rückweg geht von allein: Bleibt der Umsatz später wieder unter der Vorjahresgrenze, gilt die Befreiung im übernächsten Jahr erneut, ohne Antrag. Eine Bindung entsteht nur, wenn man freiwillig auf die Regelung verzichtet — dann fünf Kalenderjahre lang (§ 19 Abs. 3 UStG). Schlicht über die Grenze zu geraten ist kein Verzicht und bindet an nichts.',
  'help_tax_changed' => 'Die Zahlen sind zum 1. Januar 2025 gestiegen, in Deutschland von 22.000 auf 25.000 € für das Vorjahr und von 50.000 auf 100.000 € für das laufende Jahr. Mit der Erhöhung änderte sich auch die Art der zweiten Grenze: Früher war sie eine Prognose zu Jahresbeginn — wer plausibel darunter kalkulierte, blieb das ganze Jahr befreit, selbst wenn am Ende mehr zusammenkam. Heute zählt der tatsächliche Umsatz, laufend. Ratgeberseiten, die noch von einer Schätzung sprechen, sind auf altem Stand.',
  'help_tax_band' => 'Gerechnet wird mit dem Umsatz der Band als Ganzes, nicht pro Kopf. Umsatzsteuerlich ist die GbR der Steuerpflichtige, die Grenze gilt also einmal für alle zusammen. Stellt dagegen jedes Mitglied eigene Rechnungen, hat jeder seine eigene Grenze, und die Zahl in der Kasse sagt darüber nichts.',
  'help_tax_counts' => 'Als Umsatz zählen Gagen und Merch. Einzahlungen der Mitglieder zählen nicht — das sind Beiträge und kein Verkauf. Der Verkauf eines Geräts zählt ebenfalls nicht: Umsätze mit Anlagevermögen bleiben außer Ansatz, sonst schöbe die alte Anlage die Band an eine Grenze, die sie nicht erreicht hat. Erkannt wird das an der Verbindung zum Gerät — Technik zu vermieten ist dagegen Umsatz und wird ohne Gerätebezug gebucht. Private Buchungen bleiben ohnehin außen vor.',
  'help_tax_over' => 'Weil die Jahresgrenze ohne Vorwarnung greift, meldet sich die Kasse schon ab vier Fünfteln davon — nicht erst, wenn es zu spät ist. Wer die Regelung verliert, versteuert Auftritte ermäßigt und Merch zum vollen Satz; was das für euch heißt, klärt ihr besser vorher als hinterher.',
  'help_tax_next_year' => 'Der wahrscheinlichere Fall ist der leisere: Bleibt der Umsatz zwischen den beiden Grenzen, ändert sich dieses Jahr nichts, und zum 1. Januar endet die Befreiung — automatisch, ohne Bescheid, ohne dass jemand etwas tut. Wie hoch der Umsatz im neuen Jahr wird, spielt dann keine Rolle mehr. Wer davon erst beim Steuerbescheid erfährt, hat ein Jahr lang Rechnungen ohne Umsatzsteuer geschrieben und muss sie nachversteuern — aus eigener Tasche, denn beim Veranstalter von damals holt sie niemand mehr.',
  'help_tax_gwg' => 'Für Geräte gilt eine zweite Grenze: Bis zu diesem Betrag ist ein Kauf im Jahr der Anschaffung abgeschrieben, darüber verteilt über die Nutzungsdauer. Geprüft wird netto, und zwar auch dann, wenn die Band als Kleinunternehmerin gar keine Vorsteuer ziehen darf — bei 19 % ist ein Kauf also bis 952 € brutto sofort weg. Angesetzt wird trotzdem der bezahlte Betrag, weil die Umsatzsteuer ohne Vorsteuerabzug zu den Anschaffungskosten gehört. Wie lange sich ein teureres Gerät verteilt, steht bei der Geräteart und lässt sich am einzelnen Gerät überschreiben: Lautsprecher, Verstärker, Mischpulte und Mikrofone sieben Jahre, komplette Beschallungsanlagen neun, Licht fünf, Transportbehälter zehn. Instrumente stehen in keiner allgemeinen Tabelle mehr — Klaviere zehn und Flügel fünfzehn Jahre nach der Tabelle fürs Gastgewerbe, Gitarren und Schlagzeuge in der Praxis fünf bis zehn.',
  'help_tax_sources' => 'Woher die Zahlen stammen',
  'help_tax_checked' => 'Zuletzt geprüft am %s. Ratgeberseiten hinken hier oft hinterher — die Gesetzestexte sind die verlässliche Quelle.',
  'help_tax_src_ustg' => '— die Umsatzgrenzen der Kleinunternehmerregelung',
  'help_tax_src_estg' => '— die Grenze für geringwertige Wirtschaftsgüter',
  'help_tax_src_afa' => '— die Nutzungsdauer für alles darüber',
  'help_tax_offset_title' => 'Was sich gegenseitig aufhebt',
  'help_tax_offset_intro' => 'Die Kasse addiert, was gebucht wurde. Einiges sieht dort nach Einnahme oder Ausgabe aus und ist steuerlich etwas anderes — wer es geradeaus liest, kommt mit gutem Gewissen auf eine falsche Zahl.',
  'help_tax_offset_deposit' => 'Eine Einzahlung ist Geld herein für die Band und Geld heraus für das Mitglied. Sie ist ein Beitrag und kein Verkauf und zählt deshalb nicht zum Umsatz.',
  'help_tax_offset_payout' => 'Eine Ausschüttung steht als Ausgabe im Buch, ist für eine GbR aber keine Betriebsausgabe: Sie verteilt einen Gewinn, der bereits entstanden ist. Wer sie gegen den Gewinn rechnet, zieht ihn zweimal ab.',
  'help_tax_offset_private' => 'Was ein Mitglied privat bezahlt hat, ist dessen Ausgabe und nicht die der Band. Solche Buchungen bleiben deshalb außen vor — beim Mitglied selbst können sie sehr wohl zählen.',
  'help_tax_offset_gwg' => 'Ein Gerät oberhalb der Wertgrenze verlässt die Kasse in einem Betrag, mindert den Gewinn aber über seine Nutzungsdauer verteilt. Im Kassenbuch steht ein Jahr, steuerlich sind es mehrere.',
  'help_tax_levels' => 'Steuermindernd gibt es auf zwei Ebenen: Manches senkt, was die Band verdient hat — anderes senkt, was ein einzelnes Mitglied erklärt. Das eigene Instrument, die Fahrt zur Probe, der Gewinnanteil: Das gehört in die persönliche Erklärung und nicht in die Zahlen der Band. Wer beides zusammenwirft, bekommt eine Summe, die für keine der beiden Seiten stimmt.',
  'set_demo_in_use' => 'Diese Installation ist in Gebrauch — es stehen eigene Mitglieder oder Termine darin. Demodaten gibt es deshalb nicht mehr dazu; sie würden sich unter eure mischen.',
  'fl_demo_in_use' => 'Demodaten gibt es nur, solange die Installation noch leer ist.',
  'tax_comm_title' => 'Anteil Handel',
  'tax_comm_intro' => 'Spielen ist künstlerisch, Merch verkaufen ist Handel. Bei einer Personengesellschaft macht schon ein kleiner Handelsanteil sämtliche Einkünfte gewerblich — auch die aus den Auftritten. Geduldet wird es nur unterhalb beider Grenzen.',
  'tax_comm_of' => 'von',
  'tax_comm_state_close' => 'Der Handelsanteil nähert sich der Grenze.',
  'tax_comm_state_over' => 'Der Handelsanteil liegt über der Grenze. Damit gelten alle Einkünfte der Band als gewerblich, auch die aus den Auftritten — mit Gewerbesteuer oberhalb des Freibetrags. Das gehört mit der Steuerberatung besprochen.',
  'set_tax_comm_share' => 'Handelsanteil höchstens (%)',
  'set_tax_comm_abs' => 'Handelsumsatz höchstens (€)',
  'set_tax_comm_hint' => 'Beides muss gelten, sonst gelten alle Einkünfte als gewerblich. In Deutschland 3 % des Nettoumsatzes und 24.500 € im Jahr, aus der Rechtsprechung zu § 15 Abs. 3 Nr. 1 EStG.',
  'help_est_title' => 'Einkommensteuer: wer zahlt was',
  'help_est_who' => 'Die Band selbst zahlt keine Einkommensteuer. Ihr Gewinn wird einmal ermittelt und auf die Mitglieder verteilt — beim Finanzamt heißt das gesonderte und einheitliche Feststellung. Jedes Mitglied gibt seinen Anteil in der eigenen Erklärung an. Deshalb braucht es beides: saubere Zahlen für die Band und die Klarheit, was davon beim Einzelnen landet.',
  'help_est_euer' => 'Der Gewinn wird bei einer Band in aller Regel als Einnahmen-Überschuss-Rechnung ermittelt: eingenommen minus ausgegeben, im Jahr der Zahlung. Genau das ist ein Kassenbuch — sauber geführt, ist die Arbeit im Wesentlichen getan.',
  'help_est_merch' => 'Spielen ist eine künstlerische Tätigkeit, T-Shirts verkaufen ist Handel. Bei einer Personengesellschaft genügt ein kleiner Handelsanteil, damit sämtliche Einkünfte als gewerblich gelten — auch die aus den Auftritten. Geduldet wird es nur unterhalb beider Grenzen zugleich: unter drei Prozent des Nettoumsatzes und unter 24.500 € im Jahr. Drei Prozent sind wenig: Wer 20.000 € Gagen einnimmt, darf für rund 600 € Merch verkaufen. Darüber wird die ganze Band ein Gewerbe, mit Gewerbesteuer oberhalb des Freibetrags von 24.500 €.',
  'help_est_hobby' => 'Und der umgekehrte Fall: Wer über Jahre keinen Gewinn macht und auch keinen anstrebt, gilt dem Finanzamt womöglich als Liebhaberei. Dann ist die Band steuerlich kein Betrieb — die Verluste sind damit ebenfalls nichts wert.',
  'help_est_src_est15' => '— wann alle Einkünfte gewerblich werden',
  'help_est_src_est18' => '— die künstlerische Tätigkeit',
  'help_est_src_bfh' => '— die Bagatellgrenze aus der Rechtsprechung',
  'about_donate' => 'Trinkgeld',
  'about_donate_link' => 'für das Projekt',
  'about_donate_note' => 'Bandregie ist kostenlos und bleibt es. Wer trotzdem etwas dalassen mag, darf — nötig ist es nicht.',
  'help_est_own_title' => 'Was beim einzelnen Musiker zählt',
  'help_est_own_taxed' => 'Versteuert wird der Anteil am Gewinn — auch wenn nichts ausgezahlt wurde. Spart die Band auf eine PA, hat niemand Geld gesehen und trotzdem erklärt jeder seinen Anteil. Das überrascht regelmäßig.',
  'help_est_own_costs' => 'Dagegen steht, was in den Zahlen der Band gar nicht vorkommt: das eigene Instrument samt Saiten und Zubehör, Fahrten zu Proben und Auftritten, Noten und Fachliteratur, ein Arbeitszimmer unter engen Voraussetzungen, Beiträge zur Künstlersozialkasse. Das gehört in die persönliche Erklärung.',
  'help_est_own_km' => 'Fahrten mit dem eigenen Wagen für die Band sind Betriebsfahrten: 30 Cent je gefahrenem Kilometer. Nicht zu verwechseln mit der Pendlerpauschale, die 2026 auf 38 Cent steigt — die gilt für den Weg zur festen Arbeitsstätte, und die hat nicht, wer jedes Mal woanders spielt.',
  'help_est_own_home' => 'Für das Arbeiten zu Hause gibt es 6 Euro je Tag, höchstens 1.260 Euro im Jahr — das entspricht 210 Tagen.',
  'help_est_own_ksk' => 'Die Künstlersozialkasse versichert, wer künstlerisch selbständig und erwerbsmäßig tätig ist. Unter 3.900 Euro Jahreseinkommen wird keine Versicherungspflicht festgestellt. Umgekehrt gilt: Zahlt die Band selbst Gastmusiker, kann sie die Künstlersozialabgabe schulden — 4,9 Prozent im Jahr 2026, mit einer Bagatellgrenze von 1.000 Euro im Jahr.',
  'help_est_src_ksk' => '— wer versichert ist und ab wann',
  'fin_own_title' => 'Deine eigenen Buchungen',
  'fin_own_hint' => 'Was du privat gebucht hast, sieht nur du — hier zusammengezählt, weil es für deine eigene Erklärung zählt und nicht für die der Band.',
  'privacy_title' => 'Datenschutzerklärung',
  'legal_credits' => 'Bildnachweis',
  'legal_credit_background' => 'Hintergrundbild: Konzertpublikum,',
  'legal_credit_photos' => 'Bilder in der Galerie:',
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
  'inavg_material' => 'Medien', 'inavg_band' => 'Band', 'inavg_konto' => 'Konto',
  'inav_musik' => 'Musik & Videos', 'inav_hilfe' => 'Hilfe', 'inav_ueber' => 'Über',
  'fl_media_saved' => 'Link gespeichert.', 'fl_media_deleted' => 'Link gelöscht.',
  'help_title' => 'Hilfe', 'help_intro' => 'Was steckt hinter den einzelnen Bereichen?',
  'help_more' => 'Mehr zur Anwendung, zur Lizenz und zu den Mitwirkenden steht unter „Über".',
  // Kurzbeschreibung je Bereich — die Schlüssel heißen wie die Bereiche
  'help_termine' => 'Alle Auftritte, Proben und Besprechungen. Jeder sagt zu oder ab, Dateien und Kommentare hängen am Termin, und die Packliste sagt, welche Geräte mitkommen.',
  'help_songs' => 'Das Repertoire mit Tonart, Tempo, Dauer und Status. Noten, Texte und Aufnahmen hängen am Song. Für die Bühne gibt es eine Vollbildansicht: der Text groß, Abschnitte farbig abgesetzt, und er läuft von selbst mit — das Tempo stellst du über das Tempo-Symbol ein, als Zahl oder indem du den Takt mittippst. Der Bildschirm bleibt dabei wach. Wer lieber seinen Notizzettel mit Akkorden liest, schaltet oben darauf um.',
  'help_setlists' => 'Die Reihenfolge für einen Auftritt, mit Pausen und Zugaben. Die Spielzeit rechnet sich aus den Songdauern.',
  'help_orte' => 'Veranstaltungsorte mit Adresse, Ansprechpartner und Erfahrungen von den letzten Malen. Über „Adresse suchen“ holt der Server einmalig die Koordinaten von OpenStreetMap und trägt Adresse und Ort gleich mit ein — das muss unter „Einstellungen → Verbindungen nach außen“ erlaubt sein, sonst bleibt der Knopf grau. Das Navi-Symbol öffnet die Karten-App deines Geräts mit dem Ziel; am iPhone wählst du beim ersten Mal, welche.',
  'help_abwesenheiten' => 'Urlaub und Sperrzeiten. Fällt ein Termin hinein, warnt die Terminliste.',
  'help_aufgaben' => 'Was ansteht und wer es macht.',
  'help_themen' => 'Diskussionen in Ruhe, ohne dass etwas im Chat untergeht.',
  'help_kasse' => 'Einnahmen und Ausgaben der Band, Gagen lassen sich aus den Terminen übernehmen.',
  'help_equipment' => 'Das Inventar samt Bestandteilen, Preisen und Fristen wie Prüfungen oder Versicherungen. Ein Eintrag steht für ein Gerät: Zwei gleiche Mikrofone sind zwei Einträge, durchnummeriert als „#1" und „#2", denn sie werden einzeln getragen, verliehen und vermisst. Für Kleinteile und Meterware gibt es stattdessen das Feld „Menge" — zehn XLR-Tüllen sind keine zehn Einträge. Steht in einer Zeile eine Menge, obwohl es Geräte sind, macht „In einzelne Geräte aufteilen" daraus einzelne Einträge; Preis, Kaufdatum, Rechnung und Bild gehen an jeden mit. Ein neuer Eintrag kann ein Bild übernehmen, das schon im Inventar liegt, statt dieselbe Datei ein zweites Mal hochzuladen.',
  'help_rider' => 'Was ein Veranstalter über eure Technik wissen muss, und die Kanalbelegung fürs Mischpult. Der Bühnenplan ist maßstäblich: Vorgabe sind 8 × 6 m, und alles mit echtem Grundriss — Podeste, Verstärker, Monitore, Boxen — wird in seinem Maß gezeichnet. Daran sieht ein Veranstalter, ob die Band auf seine Bühne passt. „Aus der Mitgliederliste erzeugen" stellt eine Vorlage auf: Schlagzeug hinten Mitte auf einem Podest von 3 × 2 m, Bass hinten links, Gesang vorne, dazu Strom und Stagebox. Danach lässt sich alles verschieben oder über die Zahlenfelder eintragen. Wer im Profil „Ich stehe auf der Bühne" aushakt, wird nicht aufgestellt — dort gehört die Technik hin. Welche Figur ein Mitglied im Plan bekommt, wählt es selbst im Profil; mit „Mein Foto" steht dort das Profilbild.',
  'help_post' => 'Der Posteingang der Band: Anfragen liegen dort, wo aus ihnen ein Termin wird. Die Anwendung holt das eingerichtete Postfach in einem festen Takt ab — nur lesend, und sie markiert im Postfach nichts als gelesen; wer es nebenher im Handy hat, findet es unverändert. Aus dem Text liest sie einen Terminvorschlag: Datum, Uhrzeiten, Ort und Honorar, jeweils mit der Stelle, an der sie es gefunden hat. Der Vorschlag füllt nur das Formular vor — angelegt wird erst auf Klick, und jedes Feld lässt sich vorher ändern. Was sich nicht sicher lesen lässt, bleibt leer, statt geraten zu werden. Die Anfrage wandert als Notiz in den Termin, damit später niemand rätselt, was zugesagt war. Antworten gehen aus der Anwendung an den Absender der Nachricht und bleiben bei ihr stehen. Anhänge stehen dabei mit Name und Größe, geholt werden sie erst beim Übernehmen — dann liegen sie beim Termin, auf demselben Weg wie eine hochgeladene Datei. Dafür muss die Nachricht mit einem Termin verbunden sein: Der Anhang wird abgelegt, nicht gesammelt.',
  'help_fotos' => 'Bilder für die öffentliche Seite und fürs Bandgedächtnis. Beim Hochladen liest die Anwendung Aufnahmedatum und Aufnahmeort aus der Datei und schlägt damit den Termin vor — zugeordnet wird erst auf Klick: einzeln, angehakt über die Leiste oder gleich als ganzer Herkunftsordner. Aus der gespeicherten Datei werden die Angaben danach entfernt: Ein Proberaum ist oft eine Privatadresse, und die soll mit keinem veröffentlichten Foto mitgehen. Nur Originale direkt vom Gerät tragen sie überhaupt; was über Messenger geteilt wurde, hat sie längst verloren. Die Galerie ordnet nach Jahr, Termin und Fotograf — wie der verknüpfte Ordner. Aus verknüpften OneDrive-Ordnern liegt hier nur ein Vorschaubild — das Original bleibt bei OneDrive und ist an der Kachel verlinkt. Statt zu löschen gibt es das Archiv: aus jeder Galerie genommen, aber nicht zerstört, und auf Klick zurückzuholen. Schlagwörter, die Presse-Auswahl fürs Rausgeben und von Hand benannte Personen machen Bilder auffindbar; das Suchfeld sucht über Beschreibung, Herkunft, Termin, Schlagwort und Person — auch im Archiv. Doppelte Dateien findet das Aufräumen in den Einstellungen anhand einer Prüfsumme. Ein verknüpfter OneDrive-Ordner lässt sich außerdem an einen Termin binden: Dann gehören die Bilder darin dorthin, auch die, die erst nächste Woche hineingelegt werden. Heißt der Ordner nach dem Datum oder dem Ort, schlägt die Anwendung den passenden Termin vor — vorausgewählt wird er nicht, denn ein gesetzter Wert wird bestätigt, ohne hinzusehen.',
  'help_musik' => 'Videos und Streams, die auf der öffentlichen Musikseite erscheinen.',
  'help_downloads' => 'Pressematerial für Veranstalter — mit Link zum Weitergeben.',
  'help_mitglieder' => 'Wer zur Band gehört, mit Kontaktdaten und Rollen.',
  'login_only_members' => 'Nur für Mitglieder von', 'login_email' => 'E-Mail', 'login_password' => 'Passwort',
  'login_submit' => 'Einloggen', 'login_failed' => 'E-Mail oder Passwort falsch.',
  'dash_hello' => 'Hallo', 'dash_next_events' => 'Nächste Termine',
  'dash_vote_missing' => 'Rückmeldung fehlt',
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
  'ev_show_cancelled' => 'Abgesagte (%1)', 'ev_hide_cancelled' => 'Abgesagte ausblenden',
  'ev_count' => '%1 Termine', 'ev_count_requested' => '%1 angefragt',
  'ev_count_cancelled' => '%1 abgesagt, ausgeblendet',
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
  'song_year' => 'Erschienen', 'song_year_ph' => 'z. B. 1997',
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
  'sl_drag_hint' => 'Zum Umsortieren am ⠿ ziehen — mit Maus wie mit dem Finger. Die Pfeile tun es auch.',
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
  'photos_caption' => 'Beschreibung',
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
  'eq_photo_reuse' => 'Vorhandenes Bild übernehmen',
  'eq_photo_reuse_hint' => 'Gleiche Geräte sind einzelne Einträge, also fängt das zweite ohne Foto an. Hier steht, was schon im Inventar liegt — Geräte mit derselben Artikelnummer zuerst. Die Datei wird nicht kopiert; löschst du das Bild an einem Gerät, bleibt es am anderen.',
  'eq_photo_take' => 'Bild übernehmen',
  'fl_eq_photo_taken' => 'Bild übernommen.',
  'fl_eq_photo_failed' => 'Das Bild ließ sich nicht übernehmen.',
  'file_back' => 'Zurück',
  'file_no_preview' => 'Diese Datei lässt sich hier nicht anzeigen — speichern oder in einem neuen Tab öffnen.',
  'file_save' => 'Speichern',
  'file_open_tab' => 'In neuem Tab öffnen',
  'files_multi' => 'Rechnung für mehrere Geräte',
  'files_multi_hint' => 'Eine Rechnung zählt selten nur ein Gerät auf. Lade sie hier einmal hoch und hake an, wozu sie gehört — gespeichert wird sie trotzdem nur einmal, sie erscheint aber bei jedem angehakten Gerät.',
  'files_multi_pick' => 'Gehört zu',
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
  'set_favicon_hint' => 'Dieses Zeichen steht im Browsertab und auf dem Startbildschirm des Handys. Für den Tab reichen 64 Pixel, für die Kachel auf dem Handy nicht — 512×512 ist die sichere Größe.',
  'set_favicon_small' => 'Dieses Bild ist %d×%d groß. Im Browsertab sieht man das nicht, auf dem Startbildschirm des Handys schon: die Kachel wird dort mit 192 bis 512 Pixeln gezeichnet, und größer rechnen lässt sich ein kleines Bild nicht. Ein quadratisches PNG mit 512×512 füllt sie aus.',
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
  'fl_file_sealed' => 'Diese Datei ist verschlüsselt abgelegt und lässt sich mit dem eingetragenen Schlüssel nicht öffnen.',
  'set_crypt' => 'Verschlüsselung ruhender Daten',
  'set_crypt_on' => 'Eingeschaltet: Sicherungen und Anhänge liegen verschlüsselt.',
  'set_crypt_off' => 'Ausgeschaltet: Sicherungen und Anhänge liegen im Klartext.',
  'set_crypt_scope' => 'Verschlüsselt sind die Sicherungen und die Dateianhänge auf der Platte. Nicht verschlüsselt ist die laufende Datenbank — dort muss der Server rechnen und sortieren können — und nicht die Bilder unter /uploads, die der Webserver direkt ausliefert.',
  'set_crypt_test' => 'Wirksamkeit geprüft: %s.',
  'set_crypt_plain_files' => '%d Anhänge stammen aus der Zeit davor und liegen noch offen.',
  'set_crypt_seal_now' => 'Jetzt nachverschlüsseln',
  'set_crypt_files_done' => 'Alle Anhänge sind verschlüsselt.',
  'set_crypt_how' => 'Einen Schlüssel erzeugen und als „data_key" in app/config.php eintragen:',
  'set_crypt_lost' => 'Diesen Schlüssel aufbewahren wie das Datenbankpasswort — und nicht in derselben Sicherung. Ohne ihn ist keine verschlüsselte Sicherung mehr zu öffnen.',
  'set_crypt_law' => 'Art. 32 DSGVO verlangt Maßnahmen nach dem Stand der Technik und nennt Verschlüsselung ausdrücklich; Buchstabe d verlangt außerdem, ihre Wirksamkeit regelmäßig zu überprüfen. Genau das tut die Zeile darüber: versiegeln, wieder öffnen, und prüfen, ob eine Veränderung auffällt.',
  'sys_crypt_off' => 'kein Schlüssel eingetragen',
  'sys_crypt_off_hint' => 'Sicherungen und Anhänge liegen im Klartext. Wer die Sicherung in die Hand bekommt — auf dem NAS, beim FTP-Ziel, in der Cloud —, liest die Kasse mit. Schlüssel erzeugen: php app/backup.php key',
  'sys_crypt_broken' => 'Die Verschlüsselung ist eingeschaltet, aber die Prüfung schlägt fehl. Bis das geklärt ist, ist auf die Sicherungen kein Verlass.',
  'sys_webstat' => 'Besucherstatistik',
  'sys_webstat_open' => 'öffentlich lesbar',
  'sys_webstat_closed' => 'geschützt',
  'sys_webstat_unknown' => 'nicht prüfbar',
  'sys_webstat_hint' => 'Unter /plesk-stat liegen die AWStats-Berichte dieser Domain, und darin stehen die IP-Adressen eurer Besucher — offen für jeden, der die Adresse kennt. In Plesk unter „Websites & Domains → Hosting und DNS → Webstatistik" das Häkchen „Zugriff über passwortgeschütztes Verzeichnis" setzen.',
  'sys_crypt_files' => 'Anhänge verschlüsselt',
  'sys_crypt_files_hint' => 'In den Einstellungen unter „Verschlüsselung ruhender Daten" nachverschlüsseln.',
  'fl_crypt_no_key' => 'Es ist kein Schlüssel eingetragen.',
  'fl_crypt_sealed' => '%d Anhänge verschlüsselt.',
  'fl_crypt_sealed_some' => '%d Anhänge verschlüsselt, %d fehlgeschlagen — Einzelheiten im Fehlerprotokoll.',
  'fl_dl_saved' => 'Download-Einstellungen gespeichert.',
  'fl_profile_saved' => 'Profil gespeichert.',
  'fl_email_taken' => 'Diese E-Mail ist schon vergeben.',
  'fl_name_email_required' => 'Name und E-Mail sind Pflicht.',
  'fl_member_updated' => 'Mitglied aktualisiert.',
  'fl_no_self_delete' => 'Du kannst dich nicht selbst löschen.',
  'fl_only_admin_pw' => 'Nur Admins können fremde Passwörter zurücksetzen.',
  'fl_pw_min' => 'Passwort braucht mindestens 8 Zeichen.',
  'fl_pw_changed' => 'Passwort geändert.',
  'fl_demo_locked' => 'In der Demo nicht möglich: Die Zugangsdaten stehen öffentlich, '
    . 'und wer sie ändert, sperrt alle anderen aus. Alles Übrige darfst du ausprobieren.',
  'demo_locked_hint' => 'In der Demo gesperrt — die Zugangsdaten sind öffentlich und '
    . 'gelten für alle Besucher gleichzeitig.',
  'demo_badge' => 'Demo',
  'fl_translations_saved' => 'Übersetzungen gespeichert.',
  'fl_texts_saved' => 'Texte gespeichert.',
  'fl_contact_email_invalid' => 'Das ist keine E-Mail-Adresse — die Kontakt-Adresse blieb unverändert.',
  // Kalender je Mitglied (#222)
  'ical_personal' => 'Dein Kalender-Link',
  'ical_personal_hint' => 'Zeigt genau die Termine, die du auch im Bandbereich siehst. Der Link ist persönlich — nicht weitergeben; wer ihn hat, sieht deine Termine ohne Anmeldung.',
  'ical_new' => 'Neuen Link erzeugen',
  'ical_new_hint' => 'Der bisherige hört damit sofort auf zu gelten.',
  'fl_ical_new' => 'Neuer Kalender-Link erzeugt — der alte gilt nicht mehr.',
  'ical_shared_off' => 'Gemeinsamen Kalender-Link abschalten',
  'ical_shared_hint' => 'Der alte, für alle gleiche Link zeigt jedem Termine — auch Ersatzleuten, die sie im Bandbereich nicht sehen. Sobald jeder seinen persönlichen Link eingerichtet hat, kann er weg.',
  'fl_ical_shared_off' => 'Der gemeinsame Kalender-Link gilt nicht mehr.',
  'ical_shared_gone' => 'Abgeschaltet.',
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
  'sys_php_old' => 'zu alt', 'sys_php_old_hint' => 'Bandregie braucht mindestens PHP 8.1.',
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
  'sys_opt_exif' => 'Ohne EXIF-Erweiterung liest die Anwendung kein Aufnahmedatum aus Fotos — der Vorschlag, zu welchem Termin ein Foto gehört, bleibt dann leer.',
  'sys_opt_imap' => 'Ohne die imap-Erweiterung holt die Anwendung kein Postfach ab — der Posteingang der Band bleibt leer. In PHP 8.4 gehört sie nicht mehr zum Kern und muss eigens nachinstalliert werden.',
  'sys_opt_push' => 'Nötig für Mitteilungen aufs Gerät (Push). Fehlt eine der Voraussetzungen, erscheint der Bereich im Profil gar nicht.',
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
  'fl_fin_saved_amount' => 'Gebucht: %1',
  'fl_price_understood' => 'Preis verstanden als %1.',
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
  // OneDrive als Sicherungsziel (#50)
  'bk_warn_od' => 'Die letzte Sicherung kam nicht ins OneDrive:',
  'bk_od_enabled' => 'Sicherung zusätzlich ins OneDrive der Band legen',
  'bk_od_dir' => 'Ordner im OneDrive',
  'bk_od_keep' => 'Aufbewahren (Anzahl)',
  'bk_od_note' => 'Braucht die eingeschaltete OneDrive-Verbindung und deren Schreibrecht. Eine Verbindung aus der Zeit vor dem Sicherungsziel einmal lösen und neu verbinden — Microsoft fragt dann um die neue Zustimmung.',
  'bk_od_test' => 'OneDrive-Ziel testen',
  'fl_bk_od_ok' => 'OneDrive-Ziel funktioniert: Probedatei geschrieben und wieder entfernt.',
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
  'fl_bk_upload_invalid' => 'Das war keine Bandregie-Sicherung (.tar.gz).',
  'fl_bk_upload_failed' => 'Die Datei ließ sich nicht ablegen — bitte Rechte und freien Platz im Sicherungsverzeichnis prüfen. Es wurde nichts eingetragen.',
  'fl_bk_missing' => 'Diese Sicherung liegt nicht mehr auf dem Server.',
  'bk_target_onedrive' => 'OneDrive',
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
  'set_site_url_passkey' => 'Für Passkeys ist der Eintrag Voraussetzung: Ein Passkey gilt für genau einen Namen, und ohne feste Adresse hieße dieselbe Installation unter www anders als ohne. Solange hier nichts steht, bleibt die Anmeldung mit Passkey ausgeblendet.',
  'app_description' => 'Termine, Setlists und Technik der Band — auch unterwegs.',
  'app_install' => 'Auf dem Handy installieren',
  'app_install_hint' => 'Auf dem iPhone über das Teilen-Symbol, auf Android über das Browsermenü: „Zum Startbildschirm hinzufügen“. Danach hat Bandregie ein eigenes Symbol und startet ohne Adressleiste.',
  'app_install_offline' => 'Ohne Empfang — auf Bühnen der Normalfall — ist trotzdem alles da: Übersicht, Termine, Setlists mit Druckfassung, Songs mit Liedtexten, Noten und Anhänge, Stagerider und Patchliste. Das ist von Haus aus so, damit niemand erst auf der Bühne merkt, dass er nichts mitgenommen hat. Wer weniger will — Noten brauchen Platz —, hakt es im Profil unter „Offline dabeihaben“ ab. Beim Abmelden wird alles gelöscht, damit auf einem geteilten Handy niemand die Termine des Vorgängers findet.',
  'app_install_store' => 'Eine App aus dem App Store oder von Google Play gibt es nicht. Der Weg über den Browser leistet dasselbe — ohne Jahresgebühr, ohne Prüfverfahren bei jeder Änderung und ohne einen zweiten Programmstand, der gepflegt werden will.',
  'song_lyrics' => 'Liedtext',
  'song_lyrics_ph' => "[Strophe]
Zeile eins
Zeile zwei

[Refrain]
…",
  'song_lyrics_hint' => 'Abschnitte in eckige Klammern in eine eigene Zeile: [Strophe], [Refrain], [Bridge], [Solo]. Das genügt, damit sie später hervorgehoben werden können.',
  'song_read' => 'Text und Noten',
  'song_no_lyrics' => 'Für dieses Lied ist kein Text eingetragen.',
  'stage_open' => 'Bühne',
  'stage_hint' => 'Vollbild, großer Text, läuft von selbst',
  'stage_play' => 'Start / Pause',
  'stage_slower' => 'Langsamer',
  'stage_faster' => 'Schneller',
  'stage_tap' => 'Tempo tippen',
  'stage_tempo' => 'Tempo',
  'stage_bpm_hint' => 'Zahl direkt eintippen oder mit − / + und Tippen einstellen.',
  'stage_done' => 'Fertig',
  'geo_navigate' => 'Navi',
  'navi_pick' => 'Womit navigieren?',
  'navi_pick_hint' => 'Zum Wechseln das Navi-Symbol lang drücken.',
  'prof_push' => 'Mitteilungen',
  'prof_push_hint' => 'Push aufs Handy für das, was dich interessiert — je Thema wählbar. Aktiviert wird je Gerät. Ohne Browser-Unterstützung bleibt alles wie bisher.',
  'push_topic_events' => 'Neue Termine',
  'push_topic_comments' => 'Neue Kommentare',
  'push_topic_attendance' => 'Zusagen und Absagen',
  'push_topic_photos' => 'Neue Bilder',
  'push_topic_post' => 'Neue Post',
  'prof_push_enable' => 'Auf diesem Gerät aktivieren',
  'prof_push_disable' => 'Auf diesem Gerät abschalten',
  'prof_push_ios' => 'Am iPhone zuerst „Zum Home-Bildschirm" hinzufügen — Push gibt es dort nur für die installierte App.',
  'prof_push_denied' => 'Der Browser lehnt Mitteilungen ab. Von hier aus lässt sich das nicht ändern — und es kann an zwei Stellen liegen.',
  'prof_push_denied_site' => 'Für diese Seite: in der Adressleiste auf das Schloss- oder Info-Symbol, dort Mitteilungen auf „Zulassen", Seite neu laden.',
  'prof_push_denied_all' => 'Für alle Seiten: Kam nie eine Frage und ist auch kein Glockensymbol zu sehen, ist im Browser der Hauptschalter aus. In Edge unter edge://settings/content/notifications, in Chrome unter chrome://settings/content/notifications — „Vor dem Senden fragen (empfohlen)" muss an sein — der Schalter ist blau, wenn er an ist. Solange er aus ist, wird jede Anfrage still abgelehnt, und die Freigabe je Seite steht gar nicht zur Wahl.',
  'prof_push_denied_os' => 'Bleibt es dabei: In den Windows-Einstellungen unter „System → Benachrichtigungen" muss der Browser selbst Mitteilungen zeigen dürfen.',
  'prof_push_open' => 'Die Frage nach der Erlaubnis wurde nicht beantwortet.',
  'prof_push_open_bell' => 'Kam ein kleines Glockensymbol rechts in der Adressleiste? Chrome und Edge zeigen die Frage oft nur noch so statt als Fenster. Dort antippen, zulassen, hier noch einmal aktivieren.',
  'prof_push_open_embargo' => 'Kam gar nichts, dann fragt der Browser diese Seite gerade nicht mehr: Wer eine solche Frage dreimal wegklickt, bekommt sie eine Woche lang nicht wieder gestellt — ohne Hinweis, und die Seite steht dabei in keiner Sperrliste. Der Weg daran vorbei: in den Browsereinstellungen unter Benachrichtigungen bei „Berechtigt, Benachrichtigungen zu senden" auf „Website hinzufügen" und die Adresse dieser Seite eintragen. Danach hier noch einmal aktivieren.',
  'prof_push_failed' => 'Das Abo ließ sich nicht anlegen. Erlaubnis steht, es hakt woanders — neu laden und noch einmal versuchen.',
  'fl_push_saved' => 'Mitteilungs-Themen gespeichert.',
  'push_ev_title' => 'Neuer Termin',
  // Postfach der Band (#219)
  'push_post_title' => 'Neue Post',
  'push_post_body' => '%1 neue Nachricht(en) im Bandpostfach',
  'inav_post' => 'Post',
  'post_title' => 'Postfach',
  'post_intro' => 'Der Posteingang der Band — dort, wo aus einer Anfrage ein Termin wird.',
  'post_none' => 'Keine Nachrichten.',
  'post_fetch' => 'Jetzt abholen',
  'post_fetched' => 'Abgeholt: %1 neu von %2 angesehenen.',
  'post_no_imap' => 'Diesem Server fehlt die imap-Erweiterung — ohne sie kann die Anwendung kein Postfach lesen.',
  'post_connect_failed' => 'Das Postfach antwortet nicht: %1',
  'post_not_set_up' => 'Es ist kein Postfach eingerichtet.',
  'post_open' => 'Öffnen',
  'post_back' => 'Zurück zum Postfach',
  'post_from' => 'Von',
  'post_proposal' => 'Vorschlag für einen Termin',
  'post_proposal_hint' => 'Aus dem Text gelesen und nur vorgeschlagen — angelegt wird erst auf Klick, und jedes Feld lässt sich vorher ändern.',
  'post_evidence' => 'im Text gefunden als',
  'post_more_dates' => 'Weitere Datumsangaben im Text:',
  'post_nothing_found' => 'Im Text stand nichts, was sich sicher als Termin lesen ließe — bitte von Hand ausfüllen.',
  'post_make_event' => 'Termin anlegen',
  'post_event_linked' => 'Termin dazu:',
  'post_reply' => 'Antworten',
  'post_reply_send' => 'Antwort senden',
  'post_replies' => 'Gesendete Antworten',
  'post_attachments' => 'Anhänge',
  'post_attach_take' => 'In den Termin übernehmen',
  'post_attach_taken' => 'übernommen',
  'post_attach_need_event' => 'Erst einen Termin mit dieser Nachricht verbinden — dann liegt der Anhang dort, wo er gebraucht wird.',
  'post_attach_hint' => 'Geholt wird eine Datei erst beim Übernehmen. Bis dahin steht hier nur, dass sie da ist.',
  'fl_post_attach_done' => '„%1" liegt jetzt beim Termin.',
  'fl_post_attach_failed' => '„%1" konnte nicht geholt werden — vielleicht ist die Nachricht im Postfach nicht mehr da.',
  'fl_post_attach_too_big' => '„%1" ist größer als 20 MB und wurde nicht übernommen.',
  'post_archive' => 'Ins Archiv',
  'post_unarchive' => 'Zurückholen',
  'post_archived_view' => 'Archiv: %1',
  'fl_post_event' => 'Termin angelegt und mit der Nachricht verbunden.',
  'fl_post_replied' => 'Antwort an %1 gesendet.',
  'fl_post_reply_failed' => 'Die Antwort ließ sich nicht senden.',
  'fl_post_archived' => 'Nachricht ins Archiv gelegt.',
  'set_imap' => 'Postfach der Band abholen (IMAP)',
  'set_imap_hint' => 'Nur lesend, und nur dieses eine Postfach — nie ein privates. Die Anwendung markiert nichts als gelesen. Abgeholt wird einmal je Intervall, beim ersten Seitenaufruf oder per Cron.',
  'set_imap_host' => 'Server', 'set_imap_port' => 'Port', 'set_imap_user' => 'Benutzer',
  'set_imap_pass' => 'Passwort', 'set_imap_pass_set' => '(gespeichert — leer lassen, um es zu behalten)',
  'set_imap_folder' => 'Ordner', 'set_imap_tls' => 'Verschlüsselt (TLS)',
  'set_imap_interval' => 'Abholen alle … Minuten',
  'set_imap_test' => 'Postfach testen',
  'fl_imap_ok' => 'Postfach erreichbar: %1 Nachrichten im Ordner.',
  // Täglicher Blick in die OneDrive-Ordner (#214)
  'push_od_title' => 'Neue Bilder bei OneDrive',
  'push_od_body' => '%1 neue Bilder in %2',
  'set_od_auto' => 'OneDrive-Ordner täglich nachsehen',
  'set_od_auto_hint' => 'Einmal am Tag, beim ersten Seitenaufruf oder per Cron. Bei neuen Bildern geht eine Mitteilung an alle, die das Thema „Neue Bilder" nicht abgewählt haben. Geholt wird nichts von selbst — das bleibt der Knopf am Ordner.',
  'push_comment_title' => 'Neuer Kommentar',
  'push_att_yes' => '%1 hat für „%2" zugesagt',
  'push_att_no' => '%1 hat für „%2" abgesagt',
  'push_att_maybe' => '%1 hat für „%2" mit Vielleicht geantwortet',
  'photo_no_event' => 'kein Termin',
  // Mehrere Fotos auf einen Termin (#191)
  'photo_mass' => 'Mehrere auf einen Termin',
  'photo_mass_hint' => 'Von einem Auftritt kommen dreißig Bilder. Hake sie an den Kacheln an, wähle den Termin einmal — fertig.',
  'photo_mass_all' => 'Alle anhaken',
  'photo_mass_none' => 'Keins',
  'photo_mass_go' => 'Angehakte zuordnen',
  'photo_mass_pick' => 'auswählen',
  // Neu-Markierung (#195) und ehrliche Grenzen beim Hochladen (#194)
  'photo_new' => 'NEU',
  'photo_source' => 'Herkunft',
  'photo_folder_none' => 'Noch keinem Termin zugeordnet',
  'photo_folder_count' => 'Bilder: %1',
  // Baum aus Jahr, Termin und Fotograf (#216)
  'photo_source_none' => 'Ohne Herkunftsordner',
  'photo_tree_open' => 'Alles aufklappen',
  'photo_tree_hint' => 'Jahr, Termin, Fotograf — wie im verknüpften Ordner.',
  'photos_upload_lbl_lim' => 'Bilder (max. %1 je Datei, %2 auf einmal)',
  'fl_photo_stored' => 'Gespeichert: %1',
  'fl_photo_skipped_big' => 'Zu groß für %2 und nicht angekommen: %1',
  'fl_photo_skipped_nonimage' => 'Keine Bilder: %1',
  'fl_photo_skipped_error' => 'Beim Übertragen fehlgeschlagen: %1',
  'fl_photo_cap' => 'Der Server nimmt nur %1 Dateien je Absendung — wähle den Rest in einem zweiten Schwung.',
  'fl_photo_too_big_request' => 'Die Absendung war zusammen größer als %1 und wurde vom Server verworfen — es ist nichts angekommen. Nimm weniger Bilder auf einmal.',
  'photo_mass_count' => '%1 angehakt',
  // Ordnerweise zuordnen (#208)
  // Schlagwörter (#201)
  'photo_tag' => 'Schlagwort',
  'photo_tag_suggest' => 'Bühne,Publikum,Backstage,Technik,Presse,Plakat',
  'photo_tag_set' => 'Setzen',
  'photo_tag_unset' => 'Entfernen',
  'photo_tag_filter' => 'Schlagwort „%1"',
  'photo_tag_remove_title' => 'Schlagwort von diesem Bild entfernen',
  'fl_photo_tag' => '%1 Bilder mit „%2" versehen.',
  'fl_photo_tag_removed' => '„%2" bei %1 Bildern entfernt.',
  'fl_photo_tag_empty' => 'Kein Schlagwort angegeben — nichts geändert.',
  // Presse-Auswahl (#202)
  'photo_press' => 'Presse',
  'photo_press_title' => 'Gut genug zum Rausgeben — für Veranstalter und Presse',
  'photo_press_filter' => 'Presse-Auswahl: %1',
  // Personen (#203)
  'photo_person_add' => 'Person',
  'photo_person_filter' => 'Bilder mit %1',
  'photo_person_remove_title' => 'Person von diesem Bild entfernen',
  'photo_person_photos' => 'Fotos',
  // Suche (#204)
  'photo_search' => 'Suchen',
  'photo_search_ph' => 'Beschreibung, Herkunft, Termin, Schlagwort, Person …',
  'photo_search_none' => 'Nichts gefunden zu „%1".',
  'photo_search_count' => '%1 Treffer zu „%2"',
  'photo_filter_off' => 'Filter aufheben',
  'photo_archived_badge' => 'archiviert',
  // Archiv (#200)
  'photo_archive' => 'Archivieren',
  'photo_restore' => 'Zurückholen',
  'photo_archive_view' => 'Archiv: %1',
  'photo_archive_title' => 'Archiv',
  'photo_archive_back' => 'Zurück zur Galerie',
  'photo_archive_empty' => 'Das Archiv ist leer.',
  'photo_archive_hint' => 'Archivierte Bilder sind aus der Galerie genommen, aber nicht gelöscht — hier liegen sie weiter.',
  'fl_photo_archived' => '%1 Bilder archiviert.',
  'fl_photo_restored' => 'Bild zurückgeholt.',
  'photo_folder_assign' => 'Ordner zuordnen',
  'photo_folder_assign_hint' => 'Alle Bilder aus diesem Herkunftsordner — samt Unterordnern — bekommen den Termin.',
  'photo_folder_pick' => '– Ordner wählen –',
  'fl_photo_folder' => '%1 Bilder aus „%2" zugeordnet.',
  'fl_photo_folder_none' => 'Bei %1 Bildern aus „%2" die Zuordnung entfernt.',
  'fl_photo_folder_unknown' => 'Diesen Ordner gibt es nicht — nichts geändert.',
  'fl_photo_mass' => '%1 Fotos zugeordnet.',
  'fl_photo_mass_none' => 'Bei %1 Fotos die Zuordnung entfernt.',
  'fl_photo_mass_nothing' => 'Kein Foto angehakt — nichts geändert.',
  // Blättern und Diashow in der Großansicht (#192)
  'photo_prev' => 'Vorheriges Bild',
  'photo_next' => 'Nächstes Bild',
  'photo_show_start' => 'Diashow',
  'photo_show_stop' => 'Diashow anhalten',
  'photo_exif_hint' => 'Für die Termin-Zuordnung Originale direkt vom Gerät hochladen — über Messenger oder soziale Netze geteilte Kopien verlieren Aufnahmedatum und GPS.',
  'photo_assign' => 'Zuordnen',
  'photo_suggested' => 'Vorschlag aus Datum/GPS',
  'geo_search' => 'Adresse suchen',
  'geo_none_hint' => 'Kein Treffer. OpenStreetMap kennt Adressen, aber kaum Saalnamen — mit Straße und Ort findet es etwas.',
  'geo_searched_as' => 'gesucht als: %1',
  'geo_searching' => 'Suche …',
  'geo_no_results' => 'Keine Treffer.',
  'geo_attribution' => '© OpenStreetMap-Mitwirkende',
  'geo_off_label' => 'deaktiviert',
  'geo_off_hint' => 'Adress-Suche ist deaktiviert. In den Einstellungen aktivierbar — beim Suchen wird die Adresse dann einmal an OpenStreetMap gesendet.',
  'set_geocoding' => 'Adress-Suche über OpenStreetMap erlauben',
  'set_privacy_note' => 'Beim Aktivieren gilt der zugehörige Absatz der Datenschutzerklärung — bitte prüfen, dass er dort steht.',
  'set_push' => 'Mitteilungen aufs Gerät erlauben',
  'fl_fee_unclear' => 'Die Gage steht als Text da und lässt sich nicht eindeutig als Betrag lesen — bitte von Hand buchen.',
  'taxr_neutral' => 'Einlagen und Ausschüttungen (nicht im Ergebnis)',
  'taxr_neutral_hint' => 'Geld zwischen Band und Mitgliedern ist kein Betriebsergebnis: Eine Einlage ist kein Gewinn, eine Ausschüttung keine Betriebsausgabe. Beides steht unten in der Liste, zählt hier oben aber nicht mit.',
  'set_extern' => 'Verbindungen nach außen',
  // OneDrive (#20)
  'od_title' => 'OneDrive',
  'od_hint' => 'Fotos und Dateien liegen bei vielen Bands längst in OneDrive. Verknüpfen heißt darauf zeigen, nicht kopieren — der Platz wird nicht doppelt belegt, und es gibt keine zweite Fassung, von der niemand weiß, welche die richtige ist. Der Upload von hier bleibt daneben bestehen.',
  'od_setup' => 'Anwendung bei Microsoft eintragen',
  'od_setup_hint' => 'Damit sich diese Installation überhaupt anmelden darf, muss sie bei Microsoft als Anwendung registriert sein. Das macht einmal ein Mensch mit dem Konto, dem das OneDrive gehört; heraus kommen eine Kennung und ein Geheimnis, die hier eingetragen werden.',
  'od_redirect_lbl' => 'Diese Rückleitung muss dort eingetragen sein',
  'od_client_id' => 'Anwendungskennung (Client ID)',
  'od_client_secret' => 'Geheimnis (Client Secret)',
  'od_secret_kept' => 'Ein Geheimnis ist eingetragen. Leer lassen heißt behalten — nur eine Eingabe ersetzt es.',
  'od_tenant' => 'Mandant',
  'od_tenant_hint' => '„common" lässt private und geschäftliche Microsoft-Konten herein. Wer eine eigene Organisation hat, trägt deren Kennung ein und sperrt damit alle anderen aus.',
  'od_scopes_lbl' => 'Angefragte Rechte',
  'od_scopes_hint' => 'Nur Lesen. Zum Verknüpfen von Ordnern genügt das, und ein Recht, das niemand braucht, gilt im Schadensfall trotzdem.',
  'od_connect' => 'Mit OneDrive verbinden',
  'od_reconnect' => 'Neu verbinden',
  'od_disconnect' => 'Verbindung lösen',
  'od_connected' => 'Verbunden',
  'od_connected_as' => 'Verbunden als %1',
  'od_since' => 'seit %1',
  'od_not_connected' => 'Noch nicht verbunden.',
  'od_needs_setup' => 'Ohne Anwendungskennung und Geheimnis lässt sich nichts verbinden.',
  'od_needs_enable' => 'OneDrive ist unter „Verbindungen nach außen" ausgeschaltet.',
  'od_disconnect_hint' => 'Das löscht die Zeichen hier. Die Zustimmung bei Microsoft selbst bleibt bestehen — die entziehst du im Microsoft-Konto unter „Apps und Dienste, auf die du zugegriffen hast".',
  'od_state_bad' => 'Die Rückleitung passt nicht zu dieser Sitzung — bitte noch einmal von hier aus verbinden.',
  'od_denied' => 'Die Anmeldung bei Microsoft wurde abgebrochen.',
  'od_ok' => 'Mit OneDrive verbunden.',
  'od_gone' => 'Verbindung gelöst.',
  // Aufräumen (#193)
  'clean_title' => 'Aufräumen',
  'clean_open' => 'Nach Resten sehen',
  'clean_intro' => 'Hier steht, was an toten Verweisen liegen geblieben ist — Anhänge zu gelöschten Dingen, Zeilen ohne Datei, Dateien ohne Zeile. Nichts davon ist irgendwo sichtbar, und Platz belegt es trotzdem. Gelöscht wird erst auf Klick, und nur was hier steht.',
  'clean_nothing' => 'Nichts zu tun — es liegt nichts herum.',
  'clean_entity_gone' => '%1 Anhänge an gelöschten Dingen',
  'clean_entity_gone_hint' => 'Die Sache, an der sie hingen, gibt es nicht mehr. Angezeigt werden sie nirgends, denn es gibt keine Seite, die sie noch auflisten würde.',
  'clean_file_missing' => '%1 Zeilen ohne Datei',
  'clean_file_missing_hint' => 'Der Anhang steht in der Liste, die Datei auf der Platte fehlt. So ein Eintrag führt beim Öffnen ins Leere.',
  'clean_photo_missing' => '%1 Fotos ohne Bilddatei',
  'clean_photo_missing_hint' => 'Das Foto steht in der Galerie, die Bilddatei fehlt. Im Raster ist das ein leeres Kästchen.',
  'clean_files_extra' => '%1 Dateien ohne Zeile',
  'clean_files_extra_hint' => 'Dateien im Anhang-Ordner, auf die kein Eintrag mehr zeigt. Hier ist die Zuordnung eindeutig, deshalb lassen sie sich sicher entfernen.',
  'clean_more' => '… und %1 weitere',
  'clean_sum' => 'Zusammen %1 Reste, davon %2 belegter Platz.',
  'clean_go' => 'Aufräumen',
  'clean_confirm' => 'Die aufgeführten Reste endgültig entfernen? Es wird nur gelöscht, was oben steht.',
  'fl_cleaned' => 'Aufgeräumt: %1 Zeilen, %2 Fotos, %3 Dateien entfernt.',
  'clean_uploads_extra' => '%1 Bilddateien, auf die nichts zeigt',
  // Doppelte Bilder (#199)
  'clean_dups' => 'Doppelte Bilder: %1 Gruppen',
  'clean_dups_hint' => 'Inhaltlich identische Dateien, erkannt an der Prüfsumme. Über Messenger geteilte Kopien werden dabei nicht gefunden — die sind neu gerechnet und Byte für Byte etwas anderes.',
  'clean_dup_keep_hint' => 'Behalte eins je Gruppe — meist das verknüpfte (es kostet fast keinen Platz) oder das älteste.',
  'clean_dup_linked' => 'verknüpft',
  'clean_dup_remove' => 'Dieses entfernen',
  'fl_dup_removed' => 'Bild entfernt.',
  'clean_checksums_left' => 'Noch %1 Bilder ohne Prüfsumme — beim nächsten Öffnen dieser Seite wird weitergerechnet.',
  'clean_uploads_extra_hint' => 'Im Bilder-Ordner. Diese werden bewusst nicht gelöscht: Dorthin verweisen Fotos, Profilbilder und das Hintergrundbild, und eine einzige übersehene Quelle würde echte Bilder vernichten. Wer sicher ist, räumt sie von Hand weg.',
  // Ordner durchsehen und verknüpfen (#20)
  'od_browse_title' => 'OneDrive-Ordner',
  'od_browse_intro' => 'Klick dich zu dem Ordner, in dem euer Material liegt, und verknüpfe ihn. Kopiert wird nichts — Bandregie merkt sich nur, welcher Ordner gemeint ist, und sieht hinein. Bei Microsoft bleibt alles, wo es ist.',
  'od_browse_open' => 'Ordner durchsehen',
  'od_root' => 'Oberste Ebene',
  'od_folder' => 'Ordner',
  'od_empty' => 'Hier liegt nichts.',
  'od_files_here' => '%1 Dateien in diesem Ordner',
  'od_link_this' => 'Diesen Ordner verknüpfen',
  'od_already_linked' => 'Dieser Ordner ist schon verknüpft.',
  'od_linked_title' => 'Verknüpfte Ordner',
  'od_linked_hint' => 'Was hier steht, ist ein Verweis. Löst du die Verknüpfung, verschwindet nur der Verweis — die Dateien bei Microsoft bleiben unberührt.',
  'od_folder_event' => 'Termin dieses Ordners',
  'od_folder_event_none' => '– keiner –',
  'od_folder_event_hint' => 'Bilder aus diesem Ordner gehören zu diesem Termin — auch die, die später dazukommen. An den Dateien bei Microsoft ändert das nichts.',
  'od_folder_event_suggest' => 'Aus dem Ordnernamen geschlossen: %1',
  'od_folder_event_set' => 'Ordner dem Termin zugeordnet. %1 Bild(er) ohne Termin haben ihn übernommen.',
  'od_folder_event_cleared' => 'Zuordnung gelöst. Die bereits zugeordneten Bilder bleiben, wo sie sind.',
  'od_linked_none' => 'Noch kein Ordner verknüpft.',
  'od_items_count' => '%1 Dateien bekannt',
  'od_items_missing' => '%1 fehlen',
  'od_missing_since' => 'fehlt seit %1',
  'od_checked_at' => 'zuletzt nachgesehen %1',
  'od_refresh' => 'Nachsehen',
  'od_refreshed' => 'Nachgesehen in %4 Ordnern: %1 neu, %2 geändert, %3 verschwunden.',
  // Grenzen des Durchgangs (#205)
  'od_capped' => 'Bei %1 Dateien abgebrochen — es liegt mehr darin, als ein Durchgang schafft.',
  'od_too_deep' => '%1 Ordner liegen tiefer als %2 Ebenen und wurden nicht angesehen:',
  'od_part_unreachable' => '%1 Ordner haben nicht geantwortet — ihre Dateien bleiben unverändert.',
  'od_taken' => 'mit Aufnahmedatum',
  // Verknüpfte Bilder in die Galerie holen (#206)
  'od_import' => '%1 von %2 Bildern holen',
  'od_imported' => '%1 Bilder übernommen (%2).',
  'od_import_left' => 'Noch %1 offen — noch einmal drücken macht weiter.',
  'od_import_failed' => 'Bei %1 Bildern kam keine Fassung an.',
  'od_open_original' => 'Original bei OneDrive',
  'od_open_original_title' => 'Hier liegt nur ein Vorschaubild — das Original bleibt bei OneDrive.',
  'od_unreachable' => 'OneDrive antwortet gerade nicht. Es wurde nichts als verschwunden vermerkt — ein Ausfall ist kein Verlust.',
  'od_not_connected' => 'Noch keine Verbindung zu OneDrive. Die richtest du in den Einstellungen ein.',
  'od_linked' => 'Ordner verknüpft.',
  'od_link_failed' => 'Der Ordner ließ sich nicht verknüpfen.',
  'od_unlink' => 'Verknüpfung lösen',
  'od_unlink_confirm' => 'Nur den Verweis lösen? Die Dateien bei Microsoft bleiben liegen.',
  'od_unlinked' => 'Verknüpfung gelöst. Die Dateien bei Microsoft sind unberührt.',
  'od_error_lbl' => 'Letzter Fehler von Microsoft',
  'set_onedrive' => 'OneDrive verbinden (Dateien und Fotos verknüpfen)',
  'set_onedrive_hint' => 'Erlaubt dieser Installation, sich bei Microsoft anzumelden und Ordner zu lesen. Ohne diesen Schalter geht keine Anfrage hinaus.',
  'set_extern_hint' => 'Alles, was diese Installation nach außen tun kann, steht hier zusammen — abschaltbar, jedes für sich. Ist etwas aus, findet die Verbindung nicht statt.',
  'fl_extern_saved' => 'Verbindungen nach außen gespeichert.',
  'set_push_hint' => 'Aus: es gibt keine Mitteilungen, und der Bereich im Profil erscheint nicht. An: Mitglieder können je Thema und Gerät selbst entscheiden. Die Zustellung läuft über den Dienst des jeweiligen Browserherstellers; der Inhalt ist dabei verschlüsselt.',
  'set_geocoding_hint' => 'Aus: keine Verbindung nach außen, nur adress-basierte Navigation. An: beim „Adresse suchen" wird die Adresse einmal an OpenStreetMap gesendet, um Koordinaten zu holen — für punktgenaue Navigation und die Foto-Ort-Zuordnung.',
  'stage_prev' => 'Vorheriger Song',
  'stage_next' => 'Nächster Song',
  'stage_exit' => 'Schließen',
  'stage_empty' => 'Kein Text für dieses Lied.',
  'stage_chords' => 'Noten',
  'song_chords' => 'Mein Notizzettel (Akkorde)',
  'song_chords_others' => 'Notizzettel anderer Musiker',
  'song_chords_copy' => 'In meine kopieren',
  'song_chords_more' => 'Weitere Musiker haben Noten — im Teleprompter über das Dropdown umschaltbar.',
  'song_chords_ph' => "[Intro]\nAm  F  C  G\n\n[Strophe]\nC              G\nText der ersten Zeile …",
  'song_chords_hint' => 'Für Akkorde und Notizen, wie man sie von Hand aufschreibt. Feste Zeichenbreite: Was untereinander steht, bleibt untereinander. Abschnitte wie beim Text in eckige Klammern.',
  'song_chords_none' => 'Für dieses Lied ist kein Notizzettel angelegt.',
  'song_lyrics_bulk' => 'Texte einpflegen',
  'song_lyrics_bulk_hint' => 'Hier lassen sich die Liedtexte mehrerer Songs auf einmal einfügen. Abschnitte in eckigen Klammern ([Refrain]) werden auf der Bühne farbig hervorgehoben.',
  'song_lyrics_bulk_saved' => 'Liedtexte gespeichert.',
  'song_edit_link' => 'Bearbeiten',
  'off_areas' => 'Offline dabeihaben',
  'off_areas_hint' => 'Was hier angehakt ist, liegt auf diesem Gerät und ist ohne Empfang da. Die Auswahl gilt für dich, nicht für die Band — jedes Gerät hat seine eigene.',
  'off_areas_when' => 'Aktualisiert wird im Hintergrund, sobald du eine Seite öffnest und Empfang hast. Ohne Empfang passiert nichts, und es bleibt, was da ist.',
  'off_area_termine' => 'Termine',
  'off_area_setlists' => 'Setlists mit Druckfassung',
  'off_area_songs' => 'Songs mit Liedtexten',
  'off_area_noten' => 'Noten und Anhänge (braucht Platz)',
  'off_area_rider' => 'Stagerider',
  'off_area_kanaele' => 'Patchliste',
  'off_use' => 'Belegt gerade %1 von %2.',
  'fl_off_saved' => 'Offline-Auswahl gespeichert.',
  'off_stale' => '📴 Ohne Verbindung — dieser Stand ist von %1. Änderungen von danach fehlen.',
  'off_take' => 'Diesen Termin mitnehmen',
  'off_busy' => 'wird geholt …',
  'off_done' => '%1 Seiten und Dateien liegen jetzt auf dem Gerät.',
  'off_some' => '%1 geholt, %2 nicht — vermutlich ist der Speicher knapp.',
  'off_failed' => 'Hat nicht geklappt. Mit Empfang noch einmal versuchen.',
  'off_help' => 'Auf einem Termin steht „Diesen Termin mitnehmen": damit holt das Gerät die Setlist mit ihren Noten, den Rider und die Patchliste. Danach ist alles davon ohne Empfang da — auch, was du vorher nie geöffnet hast. Beim Abmelden wird es wieder gelöscht.',
  'app_install_push' => 'Mitteilungen aufs Gerät gibt es im Profil. Voreingestellt sind alle drei Themen — neue Termine, neue Kommentare, Zu- und Absagen —, du wählst also eher ab als an. Losgeschickt wird trotzdem erst etwas, wenn du dein Gerät dort anmeldest; dabei fragt der Browser selbst um Erlaubnis. Am iPhone geht das nur für die installierte App. Steht der Bereich im Profil nicht da, hat die Bandverwaltung Mitteilungen abgeschaltet oder der Server bringt die Voraussetzungen nicht mit.',
  'app_install_badge' => 'Am Symbol steht eine Zahl, wenn etwas auf dich wartet: deine offenen Aufgaben und die kommenden Termine, zu denen du noch nicht zu- oder abgesagt hast. Dazu kommen Mitteilungen, die eingegangen sind, während die App zu war. Öffnest du die App, verschwinden die Mitteilungen aus der Zahl — die Aufgaben bleiben, denn eine Aufgabe erledigt sich nicht dadurch, dass man sie ansieht. Am iPhone schreibt die App die Zahl selbst, auf Android leitet das System sie aus den Mitteilungen in der Leiste ab; ob dort eine Zahl oder nur ein Punkt erscheint, entscheidet der Startbildschirm. Kann ein Gerät das nicht, fehlt nur die Zahl.',
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
  'stagekind_stagebox' => 'Stagebox',
  'stagekind_schlagzeug' => 'Schlagzeug',
  'stage_size' => 'Bühnenmaß',
  'stage_width_m' => 'Breite in Metern',
  'stage_depth_m' => 'Tiefe in Metern',
  'stage_size_hint' => 'Acht auf sechs Meter ist die Vorgabe — das reicht für die meisten Vereins- und Zeltbühnen. Die Positionen stehen in Prozent, ein anderes Maß rückt also nichts durcheinander, sondern ändert nur den Maßstab und das Meterraster.',
  'stage_w' => 'Breite (cm)',
  'stage_d' => 'Tiefe (cm)',
  'stage_size_default' => 'leer = übliches Maß',
  'stage_scale_hint' => 'Podeste, Verstärker, Monitore und Boxen werden maßstäblich gezeichnet. Ein Podest ist üblicherweise 2 × 1 m; drei davon nebeneinander ergeben die 3 × 2 m, auf denen das Schlagzeug steht. Wer ein anderes Maß hat, trägt es ein.',
  'stage_figure' => 'Figur im Bühnenplan',
  'stage_guest' => 'Name (Gast ohne Konto)',
  'stage_label_opt' => 'bei einem Mitglied nicht nötig',
  'stage_figure_auto' => 'Foto, wenn eines da ist',
  'stage_figure_neutral' => 'Neutral',
  'stage_figure_w' => 'Weiblich',
  'stage_figure_m' => 'Männlich',
  'stage_figure_avatar' => 'Mein Foto',
  'stage_figure_hint' => 'Nur für das Symbol im Bühnenplan. Mit „Mein Foto" steht dort dein Profilbild — dann erkennt die Band sich auf dem Plan sofort wieder. Ohne Profilbild gilt wieder die Figur.',
  'stage_member' => 'Mitglied',
  'stage_member_none' => 'niemand bestimmter',
  'stage_stagebox_power' => 'Strom an der Stagebox',
  'stage_podest_modules' => 'aus 3 Modulen 2 × 1 m',
  'prof_on_stage' => 'Ich stehe auf der Bühne',
  'prof_on_stage_hint' => 'Nur für den Bühnenplan. Wer die Band begleitet, ohne zu spielen — Technik, Fahrdienst, Management —, hakt das aus und wird von der Vorlage nicht mehr aufgestellt.',
  'fl_stage_saved' => 'Bühnenplan gespeichert.', 'fl_stage_deleted' => 'Vom Plan genommen.',
  'rider_positions_lbl' => 'Bühnenaufstellung (Text)',
  'rider_positions_ph' => "z. B.
Schlagzeug: hinten Mitte, Podest 2 × 2 m
Bass: hinten links
Gitarre: vorne rechts",
  'rider_contacts' => 'Ansprechpartner',
  'rider_contact_tech_lbl' => 'Technik', 'rider_contact_booking_lbl' => 'Booking',
  'rider_contact_member' => 'Mitglied',
  'rider_contact_none' => 'niemand',
  'rider_contact_free' => 'oder abweichend eintragen',
  'rider_contact_hint' => 'Ein Mitglied ist die bessere Angabe als getippter Text: Ändert sich seine Nummer, ändert sich der Rider mit. Der Freitext bleibt für Technik, die von außen kommt und hier kein Konto hat.',
  'rider_inputs' => 'Inputliste', 'rider_inputs_from' => 'Kanalbelegung bearbeiten',
  'rider_inputs_empty' => 'Noch keine Kanäle hinterlegt — die Inputliste bleibt leer.',
  'rider_print' => 'Druckansicht', 'rider_empty_hint' => 'Leere Felder werden im Ausdruck weggelassen.',
  'rider_for' => 'Technische Anforderungen',
  'fl_rider_saved' => 'Stagerider gespeichert.',
  // Kanalbelegung
  'inav_kanaele' => 'Kanäle', 'ch_title' => 'Kanalbelegung',
  'ch_intro' => 'Die Belegung eures Mischpults — entweder aus einer Szenendatei eingelesen oder von Hand gepflegt. Sie ist die Grundlage für die Inputliste im Stagerider.',
  'ch_import' => 'Aus Mischpult-Backup einlesen',
  'ch_import_hint' => 'Szene eines Behringer X32 oder Midas M32 (.scn) oder eine Momentaufnahme vom Behringer WING (.snap). Die WING-Datei bringt auch die Quelle mit, die X32-Szene nur die Beschriftung. Vorhandene Kanäle mit gleicher Nummer werden aktualisiert, eigene Notizen bleiben erhalten.',
  'ch_file' => 'Szenendatei', 'ch_replace' => 'Vorhandene Belegung vorher leeren',
  'ch_number' => 'Kanal', 'ch_name' => 'Bezeichnung', 'ch_source' => 'Mikrofon / DI',
  'ch_patch' => 'Port',
  'ch_input' => 'Eingang',
  'ch_patch_ph' => 'z. B. A1',
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
  // Über Bandregie
  'about_title' => 'Über Bandregie',
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
  // Rechnungen (#180)
  'inv_title' => 'Rechnungen',
  'inv_hint' => 'Eine Rechnung wird einmal erfasst, auch wenn zwanzig Geräte darauf stehen. Am Gerät wird sie dann nur ausgewählt — so bleibt der Beleg an einer Stelle, und ein angehängtes PDF liegt nicht zwanzigmal auf der Platte.',
  'inv_new' => 'Rechnung erfassen',
  'inv_supplier' => 'Händler',
  'inv_supplier_ph' => 'Thomann, Just Music, privat …',
  'inv_order_no' => 'Auftragsnummer',
  'inv_invoice_no' => 'Belegnummer',
  'inv_date' => 'Rechnungsdatum',
  'inv_total' => 'Rechnungssumme',
  'inv_no_short' => 'Beleg',
  'inv_order_short' => 'Auftrag',
  'inv_untitled' => 'Rechnung ohne Angaben',
  'inv_none' => 'Noch keine Rechnung erfasst.',
  'inv_pick' => 'Rechnung',
  'inv_pick_none' => 'keine',
  'inv_items' => '%1 Geräte auf diesem Beleg',
  'inv_items_one' => '1 Gerät auf diesem Beleg',
  'inv_article_no' => 'Artikelnummer beim Händler',
  'inv_saved' => 'Rechnung gespeichert.',
  'inv_needs_something' => 'Ohne Händler, Auftrags- oder Belegnummer lässt sich die Rechnung später nicht zuordnen — mindestens eines davon bitte eintragen.',
  'inv_deleted' => 'Rechnung gelöscht. Die Geräte bleiben, nur der Verweis ist weg.',
  'inv_delete_hint' => 'Löschen entfernt nur den Beleg und seine Anhänge — kein Gerät verschwindet dadurch.',
  'inv_privacy' => 'Auf einer Händlerrechnung stehen Anschrift und Zahlungsmittel des Käufers. Wer ein Gerät besitzt, sieht dessen Rechnung immer. Sonst sieht sie nur die Kassenführung — und auch das nur, wenn ausschließlich Bandeigentum darauf steht: Sobald ein persönliches Gerät auf dem Beleg ist, bleibt er beim Besitzer.',
  'eq_acquired' => 'Angeschafft als',
  'eq_acquired_unknown' => 'nicht erfasst',
  'eq_acq_neu' => 'Neu', 'eq_acq_bware' => 'B-Ware', 'eq_acq_gebraucht' => 'Gebraucht',
  'eq_acquired_hint' => 'B-Ware sind geöffnete Rückläufer und Vorführgeräte — neuwertig, aber eben nicht neu. Der Unterschied zählt beim Wiederverkauf und bei der Abschreibung: Ein gebraucht gekauftes Gerät hat eine kürzere Restnutzungsdauer als ein fabrikneues.',
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
  'fl_eq_disposed_booked' => 'Abgegeben und %1 als Einnahme gebucht.',
  'fl_eq_disposed_free' => 'Abgegeben — ohne Erlös, deshalb keine Buchung in der Kasse.',
  'fl_eq_reactivated' => 'Gerät ist wieder im Bestand.',
  'fl_eq_book_needs_price' => 'Ohne Kaufpreis lässt sich nichts buchen.',
  'fl_eq_booked_already' => 'Dieser Kauf ist bereits gebucht.',
  'eq_quantity' => 'Menge',
  'eq_quantity_hint' => 'Nur für Kleinteile und Meterware — zehn XLR-Tüllen sind keine zehn Einträge. Echte Geräte bleiben bei 1 und bekommen je Stück ihren eigenen Eintrag: Ein Mikrofon wird einzeln getragen, verliehen und vermisst.',
  'eq_quantity_n' => '%1 Stück',
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
  // Die Post ist ein eigener Bereich: Wer Termine pflegt, muss nicht das
  // Postfach der Band lesen dürfen — und umgekehrt (#219).
  'post'          => ['/intern/post'],
  'musik'         => ['/intern/musik'],
  'downloads'     => ['/intern/downloads'],
  'mitglieder'    => ['/intern/mitglieder'],
];

/** Dateianhänge gehören zum Bereich der Sache, an der sie hängen. */
// Jeder Anhang-Typ braucht hier seinen Bereich. Fehlt einer, liefert
// perm_module_for() null — und dann greift die Rechteprüfung im
// Frontcontroller gar nicht erst. Genau so waren Kassenbelege und
// Veranstalter-Downloads für jedes angemeldete Konto abrufbar.
const PERM_ENTITY_MODULES = [
  'event' => 'termine', 'song' => 'songs', 'venue' => 'orte',
  'equipment' => 'equipment', 'setlist' => 'setlists',
  'finance' => 'kasse', 'download' => 'downloads',
  // Anhängen darf, wer Geräte pflegt — Papierkram gehört zur Gerätepflege, und
  // wer die Rechnung in der Hand hat, ist meist der Käufer selbst. Wer sie
  // danach lesen darf, entscheidet may_see_invoice() strenger: Anschrift und
  // Zahlungsmittel des Käufers gehen nicht jeden an, der Geräte pflegt.
  'invoice' => 'equipment',
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

/**
 * Wohin ein Trinkgeld geht. Steht beim Entwickler, weil damit die Person
 * gemeint ist und nicht das Repository. Leer lassen heißt:
 * die Zeile erscheint nirgends — wer das Projekt weitergibt, muss keine
 * fremde Zahlungsadresse mitschleppen.
 */
const DONATE_URL = 'https://paypal.me/computi71';

const PERM_TEMPLATES = [
  'member' => [
    'termine' => [1, 1], 'post' => [1, 1], 'songs' => [1, 1], 'setlists' => [1, 1], 'orte' => [1, 1],
    'abwesenheiten' => [1, 1], 'aufgaben' => [1, 1], 'themen' => [1, 1],
    'kasse' => [1, 0], 'equipment' => [1, 1], 'rider' => [1, 1],
    'fotos' => [1, 1], 'musik' => [1, 1], 'downloads' => [1, 1], 'mitglieder' => [1, 0],
  ],
  // Wer nur einspringt, braucht die Termine, für die er eingeplant ist, und
  // das Material dazu — nicht die Kasse und nicht die Bandinterna. Der
  // Stagerider und die Kanalbelegung gehören dazu: „auf welchem Kanal liegt
  // mein Mikrofon" ist die erste Frage am Aufbautag.
  'ersatz' => [
    'termine' => [1, 0], 'post' => [0, 0], 'songs' => [1, 0], 'setlists' => [1, 0], 'orte' => [0, 0],
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
  'musiker'  => '🧍', 'schlagzeug' => '🥁', 'amp' => '🔊', 'podest' => '⬛',
  'keyboard' => '🎹', 'monitor'  => '📢', 'di' => '🔌', 'stagebox' => '🎛',
  'strom' => '⚡', 'sonstiges' => '▫',
];

/**
 * Wie groß die Dinge wirklich sind, in Zentimetern [Breite, Tiefe].
 *
 * Gezeichnet wird maßstäblich, und dafür muss der Plan die Maße kennen. Ein
 * Podest, das aussieht wie ein Verstärker, hilft keinem Veranstalter beim
 * Aufbau — und ob drei Podeste nebeneinander auf die Bühne passen, sieht man
 * erst, wenn sie die Fläche einnehmen, die sie tatsächlich brauchen.
 *
 * Null heißt: kein Grundriss, nur ein Zeichen an dieser Stelle. Ein Mensch und
 * eine Steckdose belegen keine planbare Fläche.
 */
const STAGE_SIZES = [
  // Ein Schlagzeug ist kein Punkt: Ein fünfteiliges Set mit Becken braucht
  // gute zwei auf knapp zwei Meter. Genau daran sieht man, ob das Podest reicht.
  'musiker' => [0, 0], 'schlagzeug' => [200, 180], 'amp' => [60, 35], 'podest' => [200, 100],
  'keyboard' => [140, 40], 'monitor' => [50, 35], 'di' => [14, 11],
  'stagebox' => [60, 60], 'strom' => [0, 0], 'sonstiges' => [0, 0],
];

/**
 * Die Figur eines Mitglieds im Bühnenplan.
 *
 * Bewusst eine Auswahl und kein Geschlechtsfeld: Für ein Symbol im Plan muss
 * niemand sein Geschlecht in eine Datenbank schreiben. Wer sich wiedererkennen
 * will, wählt aus — oder nimmt sein Foto, dann steht auf der Bühne das Gesicht
 * statt eines Strichmännchens.
 */
// Der leere Schlüssel heißt „nicht gewählt" und nicht „neutral": Ohne Wahl steht
// das Profilfoto im Plan, sofern eines da ist — sonst sähen alle Mitglieder gleich
// aus, und niemand geht in sechs Profile, um ein Symbol auszusuchen. Wer sein
// Gesicht nicht auf dem Rider haben will, wählt ausdrücklich „neutral"; deshalb
// braucht das einen eigenen Wert (#187).
const STAGE_FIGURES = ['' => '🧍', 'neutral' => '🧍', 'w' => '🧍‍♀️', 'm' => '🧍‍♂️', 'avatar' => '🙂'];

// Radius des Profilfotos im Bühnenplan, in Zeichnungseinheiten (1 = 1 cm). 40
// heißt 80 cm — mehr als ein Mensch breit ist, aber ein Gesicht muss auf einem
// Plan zu erkennen sein, der über eine ganze Bühne geht. Bei 30 war es ein Punkt.
const STAGE_FOTO_R = 40;

/**
 * Was im Bühnenplan für dieses Mitglied steht: ['foto' => bool, 'figur' => string].
 *
 * Eine Stelle für die Regel, weil sie an drei Orten gebraucht wird — Plan,
 * Druckansicht und Mitgliederliste — und drei Kopien davon auseinanderlaufen.
 */
function stage_figure_for(?array $member): array {
  $gewaehlt = (string) ($member['stage_figure'] ?? '');
  $hatFoto  = !empty($member['avatar_file']);
  return [
    'foto'  => $hatFoto && ($gewaehlt === 'avatar' || $gewaehlt === ''),
    'figur' => STAGE_FIGURES[$gewaehlt] ?? STAGE_FIGURES[''],
  ];
}

/** Das Bühnenmaß in Metern [Breite, Tiefe]. Acht auf sechs ist die Vorgabe. */
function stage_size(): array {
  $b = (int) setting('stage_width_m', '8');
  $t = (int) setting('stage_depth_m', '6');
  return [max(2, min(30, $b ?: 8)), max(2, min(20, $t ?: 6))];
}

/** Grundriss eines Eintrags in Zentimetern — eigenes Maß, sonst das seiner Art. */
function stage_footprint(array $it): array {
  [$kb, $kt] = STAGE_SIZES[$it['kind'] ?? ''] ?? [0, 0];
  $b = $it['width_cm'] ?? null;
  $t = $it['depth_cm'] ?? null;
  return [$b !== null ? max(0, (int) $b) : $kb, $t !== null ? max(0, (int) $t) : $kt];
}

/**
 * Standardaufstellung aus der Mitgliederliste. Schlagzeug hinten Mitte, Bass
 * hinten links, der Rest verteilt sich nach vorn — eine Vorlage zum
 * Verschieben, kein Anspruch auf Richtigkeit.
 */
function stage_default_items(array $members): array {
  // Nur wer auf die Bühne gehört. Die Aufrufer geben die ganze Mitgliederliste
  // herein; gefiltert wird hier, damit keine Route das vergessen kann.
  $members = array_values(array_filter($members, fn($m) => !array_key_exists('on_stage', $m) || (int) $m['on_stage'] === 1));
  // Grobe Zuordnung vom Instrument auf einen Platz [x, y]; y = 0 ist hinten
  $spots = [
    // Der Schlagzeuger steht hinter seinem Set, nicht darin: Bei gleichem y lief
    // seine Beschriftung über den Umriss des Schlagzeugs. Nicht weiter nach
    // hinten, weil ein Profilfoto von 80 cm die Beschriftung höher schiebt als
    // ein Zeichen — sonst stünde der Name über der Bühnenkante.
    'schlagzeug' => [50, 15], 'drums' => [50, 15], 'percussion' => [70, 18],
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
    // Der Verweis aufs Mitglied macht die Figur und das Foto möglich — ohne ihn
    // wäre der Eintrag nur ein Name, und der Plan wüsste nicht, wer dort steht.
    // Kein getippter Name: Der Verweis aufs Mitglied trägt ihn, und zwei Namen
    // in einer Zeile lesen sich wie ein Fehler (#187).
    $items[] = ['kind' => 'musiker', 'label' => '',
                'x' => $x, 'y' => $y, 'note' => (string) ($m['instrument'] ?? ''),
                'user_id' => (int) ($m['id'] ?? 0) ?: null];
  }

  // Das Schlagzeugpodest: 3 × 2 m, hinten in der Mitte. Zusammengesetzt wird es
  // aus drei Modulen von 2 × 1 m, quer gestellt — das steht in der Notiz, denn
  // beim Aufbau zählt, wie viele Teile gebraucht werden.
  //
  // Ein Eintrag und nicht drei: Die Positionen sind ganze Prozent, und ein
  // Meter ist auf einer Achtmeterbühne 12,5 % — nicht darstellbar. Drei
  // Module lägen dann bei 38, 50 und 63 Prozent, also mit vier Zentimeter
  // Lücke auf der einen und vier Zentimeter Überlappung auf der anderen
  // Seite. Als eine Fläche stimmt das Maß exakt.
  //
  // y = 18 statt weiter hinten, weil 2 m Tiefe auf einer 6-m-Bühne ein Drittel
  // ausmachen: Die Mitte muss mindestens 17 % vom Rand weg liegen, sonst ragt
  // das Podest hinten heraus.
  $items[] = ['kind' => 'podest', 'label' => '', 'note' => t('stage_podest_modules'),
              'x' => 50, 'y' => 18, 'width_cm' => 300, 'depth_cm' => 200];

  // Das Schlagzeug steht auf dem Podest, und zwar als eigenes Ding: Erst wenn
  // seine Fläche im Plan liegt, sieht man, ob 3 × 2 m reichen — der
  // Schlagzeuger allein sagt darüber nichts.
  // y = 24 und nicht weiter hinten: Bei 22 lief die Oberkante des Umrisses genau
  // durch das Instrument unter dem Namen des Schlagzeugers. Vorn ragt das Set
  // damit ein paar Zentimeter über das Podest — das ist keine Ungenauigkeit,
  // sondern genau die Auskunft, ob 3 × 2 m reichen.
  $items[] = ['kind' => 'schlagzeug', 'label' => t('stagekind_schlagzeug'), 'note' => '',
              'x' => 50, 'y' => 24];

  // Strom gehört auf jeden Plan, sonst fragt der Veranstalter genau danach.
  // Die Beschriftung kommt aus den Übersetzungen: der Plan wird verschickt,
  // und zwar an Veranstalter, die nicht zwingend Deutsch lesen.
  $power = t('stagekind_strom');
  $items[] = ['kind' => 'strom', 'label' => $power, 'x' => 8, 'y' => 6, 'note' => '230 V'];
  $items[] = ['kind' => 'strom', 'label' => $power, 'x' => 92, 'y' => 6, 'note' => '230 V'];
  // Die Stagebox steht seitlich hinten, wo das Multicore ankommt. Strom hängt
  // fest an ihr, deshalb braucht sie keinen eigenen Blitz daneben.
  $items[] = ['kind' => 'stagebox', 'label' => t('stagekind_stagebox'),
              'x' => 6, 'y' => 30, 'note' => t('stage_stagebox_power')];
  return $items;
}

// Woher PA und Licht bei einem Termin kommen
const PRODUCTION_SOURCES = ['eigene' => 'Eigenes Material', 'leih' => 'Geliehen/Gemietet', 'vorhanden' => 'Vor Ort vorhanden'];

// Equipment-Kategorien
const EQ_CATEGORIES = [
  'instrument' => 'Instrument', 'pa' => 'PA/Ton', 'licht' => 'Licht',
  'transport' => 'Transport', 'sonstiges' => 'Sonstiges',
];

/**
 * In welchem Zustand ein Gerät angeschafft wurde.
 *
 * Drei Stufen und nicht zwei, weil B-Ware weder das eine noch das andere ist:
 * geöffnete Rückläufer und Vorführgeräte, die neuwertig sein können oder auch
 * nicht. Sie einfach als „neu" zu führen wäre bequem und im Zweifel falsch —
 * beim Wiederverkauf wie beim Finanzamt, denn ein gebraucht gekauftes Gerät
 * hat eine kürzere Restnutzungsdauer als ein fabrikneues.
 *
 * Leer bedeutet „nicht erfasst" und ist kein vierter Zustand: Bei den Geräten,
 * die schon vor diesem Feld im Bestand standen, weiß es niemand mehr.
 */
const EQ_ACQUIRED = ['neu' => 'Neu', 'bware' => 'B-Ware', 'gebraucht' => 'Gebraucht'];

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

  "CREATE TABLE IF NOT EXISTS post_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(64) NOT NULL,
    folder VARCHAR(120) NOT NULL DEFAULT 'INBOX',
    from_name VARCHAR(190) NOT NULL DEFAULT '',
    from_mail VARCHAR(190) NOT NULL DEFAULT '',
    subject VARCHAR(400) NOT NULL DEFAULT '',
    sent_at DATETIME NULL,
    body_text MEDIUMTEXT,
    size_bytes INT NOT NULL DEFAULT 0,
    event_id INT NULL,
    replied_at DATETIME NULL,
    archived_at DATETIME NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_uid (folder, uid),
    KEY idx_event (event_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Was an einer Nachricht hängt (#19). Erfasst wird nur, dass es da ist —
  // geholt wird eine Datei erst, wenn jemand sie haben will. Ein Postfach ist
  // kein Ablagesystem, und ungefragt Megabytes zu ziehen ist keine Höflichkeit.
  "CREATE TABLE IF NOT EXISTS post_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    part VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    mime VARCHAR(120) NOT NULL DEFAULT '',
    size_bytes INT NOT NULL DEFAULT 0,
    encoding TINYINT NOT NULL DEFAULT 0,
    file_id INT NULL,
    taken_at DATETIME NULL,
    UNIQUE KEY uniq_part (message_id, part),
    KEY idx_message (message_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS post_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    sent_by INT NULL,
    to_mail VARCHAR(190) NOT NULL,
    subject VARCHAR(400) NOT NULL DEFAULT '',
    body TEXT,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_message (message_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS photo_tags (
    photo_id INT NOT NULL,
    tag VARCHAR(60) NOT NULL,
    PRIMARY KEY (photo_id, tag),
    KEY idx_tag (tag)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS photo_people (
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (photo_id, user_id),
    KEY idx_person (user_id)
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
  // Verknüpfte OneDrive-Ordner (#20). Verknüpft, nicht kopiert: Gespeichert wird
  // nur, welcher Ordner gemeint ist — die Dateien bleiben, wo sie liegen.
  "CREATE TABLE IF NOT EXISTS od_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(190) NOT NULL,
    name VARCHAR(190) NOT NULL DEFAULT '',
    path VARCHAR(400) NOT NULL DEFAULT '',
    linked_by INT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_at DATETIME NULL,
    UNIQUE KEY uniq_item (item_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Was in einem verknüpften Ordner gesehen wurde. Ein Zwischenstand, kein
  // Besitz: Er erlaubt es, eine Seite ohne Netz zu zeigen und zu erkennen, was
  // seit dem letzten Blick verschwunden ist. Verschwundenes wird vermerkt und
  // nicht gelöscht — sonst fällt niemandem auf, dass etwas fehlt.
  "CREATE TABLE IF NOT EXISTS od_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT NOT NULL,
    item_id VARCHAR(190) NOT NULL,
    name VARCHAR(190) NOT NULL DEFAULT '',
    size BIGINT NOT NULL DEFAULT 0,
    mime VARCHAR(120) NOT NULL DEFAULT '',
    modified_at DATETIME NULL,
    web_url VARCHAR(600) NOT NULL DEFAULT '',
    seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    missing_since DATETIME NULL,
    UNIQUE KEY uniq_folder_item (folder_id, item_id),
    KEY idx_folder (folder_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

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

  /*
   * Rechnungen zu Anschaffungen (#180).
   *
   * Eine eigene Zeile und nicht ein Textfeld am Gerät: Eine Rechnung über
   * zwanzig Positionen ist ein Beleg, nicht zwanzig. Sie zwanzigmal
   * abzuschreiben heißt, sie zwanzigmal pflegen zu müssen und neunzehnmal zu
   * vergessen — und ein PDF zwanzigmal abzulegen kostet zwanzigmal Platz.
   *
   * Der Händler steht hier und nicht am Gerät, denn er gehört zum Beleg. Die
   * Artikelnummer steht am Gerät, denn die gilt je Ding.
   */
  "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier VARCHAR(120) NOT NULL DEFAULT '',
    order_no VARCHAR(40) NOT NULL DEFAULT '',
    invoice_no VARCHAR(40) NOT NULL DEFAULT '',
    invoice_date DATE NULL,
    total_cents INT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_order (supplier, order_no)
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
/** Gibt es diesen Schlüssel schon? Damit eine Migration zweimal laufen darf. */
function index_exists(string $table, string $index): bool {
  global $db, $config;
  $st = $db->prepare('SELECT 1 FROM information_schema.statistics
                      WHERE table_schema = ? AND table_name = ? AND index_name = ?');
  $st->execute([$config['db_name'], $table, $index]);
  return $st->fetch() !== false;
}

function column_exists(string $table, string $column): bool {
  global $db, $config;
  $st = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
  $st->execute([$config['db_name'], $table, $column]);
  return $st->fetch() !== false;
}
if (!column_exists('events', 'venue_id')) {
  $db->exec('ALTER TABLE events ADD COLUMN venue_id INT NULL AFTER location');
}
// Koordinaten des Veranstaltungsorts (per Geocoding gefüllt, optional): für
// punktgenaue Navigation und später die Foto-Ort-Zuordnung. Bleiben leer,
// solange die Band das Geocoding nicht aktiviert.
if (!column_exists('venues', 'lat')) {
  $db->exec('ALTER TABLE venues ADD COLUMN lat DECIMAL(9,6) NULL, ADD COLUMN lng DECIMAL(9,6) NULL');
}
// Fotos an Termine hängen: Aufnahmedatum und GPS aus den EXIF-Daten, plus die
// zugeordnete Event-ID. Alles optional — ohne EXIF bleibt das Foto unzugeordnet.
if (!column_exists('photos', 'event_id')) {
  $db->exec('ALTER TABLE photos ADD COLUMN event_id INT NULL,
             ADD COLUMN taken_at DATETIME NULL,
             ADD COLUMN lat DECIMAL(9,6) NULL, ADD COLUMN lng DECIMAL(9,6) NULL');
}
if (!column_exists('users', 'pref_lang')) {
  $db->exec("ALTER TABLE users ADD COLUMN pref_lang VARCHAR(5) NOT NULL DEFAULT 'de'");
}
// Web-Push (#24): ein Abo je Gerät; die Themen-Auswahl liegt am Mitglied
// (users.push_topics), nicht am Gerät. Der Endpunkt kann lang sein — für die
// Eindeutigkeit steht sein Hash, nicht er selbst, im Schlüssel.
$db->exec('CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL UNIQUE,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(120) NOT NULL,
    auth VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
// Wann sich dieses Gerät zuletzt gemeldet hat. Daran — und nur daran — lässt
// sich ein totes Abo erkennen: Der Zustelldienst nimmt Nachrichten an ein
// abgeschaltetes Gerät weiter mit „201" entgegen und verwirft sie still.
if (!column_exists('push_subscriptions', 'last_seen_at')) {
  $db->exec('ALTER TABLE push_subscriptions ADD COLUMN last_seen_at DATETIME NULL');
}
if (!column_exists('users', 'push_topics')) {
  $db->exec("ALTER TABLE users ADD COLUMN push_topics VARCHAR(190) NOT NULL DEFAULT ''");
}
// Die Anmeldung über Apple, Google und Facebook ist entfallen (#167). Die
// Verknüpfungstabelle geht mit: Sie hielt Kennungen dieser Anbieter, und ohne
// die Anmeldung wäre das eine Datensammlung ohne Zweck. Die Zugangsdaten der
// Anbieter verschwinden ebenfalls — ein vergessenes Client-Secret in der
// Datenbank ist ein Geheimnis, das niemandem mehr nützt und trotzdem gilt.
if (setting('login_providers_removed') !== '1') {
  $db->exec('DROP TABLE IF EXISTS user_identities');
  q("DELETE FROM settings WHERE `key` LIKE 'oauth_%'");
  // Die Übersetzungen dazu wären sonst Karteileichen: Schlüssel, die kein
  // Text mehr abruft, aber jede Sprachliste weiter aufblähen.
  q("DELETE FROM translations WHERE tkey LIKE 'set_oauth%' OR tkey LIKE 'fl_oauth%'
       OR tkey LIKE 'prof_identit%' OR tkey IN
       ('help_login','help_login_title','login_or','login_with','prof_identity_as')");
  set_setting('login_providers_removed', '1');
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
  // Nutzungsdauer dieses Geräts. NULL heißt: die Voreinstellung seiner Art
  // gilt — eine Snare und ein Flügel teilen die Kategorie, aber nicht die
  // Lebensdauer.
  'afa_years'     => 'INT NULL',
  // Neu, B-Ware oder gebraucht angeschafft. Leer heißt „nicht erfasst" — bei
  // Altbestand weiß das niemand mehr, und geraten wäre schlechter als offen.
  'acquired_as'   => "VARCHAR(12) NOT NULL DEFAULT ''",
  // Die Nummer, unter der der Händler dieses Ding führt. Eigene Spalte statt
  // Freitext in den Notizen: Danach lässt sich suchen und vergleichen, und ein
  // zweiter Kauf desselben Artikels ist am Feld erkennbar statt an Textsuche.
  'article_no'    => "VARCHAR(40) NOT NULL DEFAULT ''",
  // Der Beleg, auf dem dieses Gerät steht. Mehrere Geräte zeigen auf dieselbe
  // Rechnung — genau darum ist sie eine eigene Zeile.
  'invoice_id'    => 'INT NULL',
  // Wie viele Stück dieser Eintrag zählt. Für Kleinteile und Meterware: Zehn
  // XLR-Tüllen sind keine zehn Inventarzeilen. Echte Geräte bleiben bei 1 und
  // bekommen je Stück ihren eigenen Eintrag — ein Mikrofon wird einzeln
  // getragen, verliehen und vermisst (#185).
  'quantity'      => 'INT NOT NULL DEFAULT 1',
] as $eqCol => $eqDdl) {
  if (!column_exists('equipment', $eqCol)) $db->exec("ALTER TABLE equipment ADD COLUMN `$eqCol` $eqDdl");
}
// Ergebnis des Zweitziels je Lauf: NULL = nicht eingerichtet, 0 = fehlgeschlagen
if (!column_exists('backup_runs', 'ftp_ok')) {
  $db->exec('ALTER TABLE backup_runs ADD COLUMN ftp_ok TINYINT(1) NULL');
}
// Das OneDrive-Ziel vermerkt seinen Erfolg getrennt (#50), wie das FTP-Ziel:
// NULL heißt „war nicht eingerichtet", 0 heißt „eingerichtet und gescheitert".
if (!column_exists('backup_runs', 'od_ok')) {
  $db->exec('ALTER TABLE backup_runs ADD COLUMN od_ok TINYINT(1) NULL');
}
// Woher eine Buchung stammt: von Hand oder aus einem Dauerauftrag. Ohne den
// Verweis ließe sich ein falscher Betrag später nicht zurückverfolgen.
if (!column_exists('finances', 'standing_order_id')) {
  $db->exec('ALTER TABLE finances ADD COLUMN standing_order_id INT NULL');
}
// Ein Dauerauftrag darf denselben Termin nur einmal buchen. Ohne diesen
// Schlüssel entstand die Miete zweimal, wenn am Fälligkeitstag zwei Leute
// gleichzeitig die Seite öffneten — und ebenso nach einem Abbruch mitten im
// Nachholen. NULL kollidiert in MySQL nicht, Handbuchungen bleiben also frei.
if (!index_exists('finances', 'uniq_order_date')) {
  try {
    $db->exec('ALTER TABLE finances ADD UNIQUE KEY uniq_order_date (standing_order_id, date)');
  } catch (PDOException $e) {
    // Schon vorhandene Doppelbuchungen verhindern den Schlüssel. Das ist kein
    // Grund, die Seite anzuhalten — aber es gehört ins Log, damit es auffällt.
    error_log('Bandregie: uniq_order_date nicht angelegt, vermutlich wegen vorhandener '
      . 'Doppelbuchungen — bitte prüfen: ' . $e->getMessage());
  }
}
// Wem eine Buchung privat gehört. NULL heißt „der Band" — nur diese Zeilen
// zählen für den Kontostand. Was jemand privat zahlt, geht die Band nichts an.
if (!column_exists('finances', 'private_for')) {
  $db->exec('ALTER TABLE finances ADD COLUMN private_for INT NULL');
}
// „Gehört einem Mitglied" und „sieht nur dieses Mitglied" sind zweierlei:
// eine Einzahlung gehört dem Einzahler und geht trotzdem alle an. Bestehende
// Aufträge mit Besitzer waren bis dahin immer privat.
// Der Stagebox-Eingang ist nicht das Mikrofon: „A1" sagt, wo das Signal
// eingesteckt ist, „SM57" sagt, was es erzeugt. Ein Rider braucht beides.
if (!column_exists('channels', 'patch')) {
  $db->exec("ALTER TABLE channels ADD COLUMN patch VARCHAR(60) NOT NULL DEFAULT '' AFTER number");
}
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
/**
 * Was offline vorgehalten werden kann. Je Mitglied wählbar — das Telefon ist
 * persönlich, und wer nur singt, braucht die Patchliste nicht.
 *
 * 'noten' meint die Anhänge: Noten, Verträge, Aufnahmen. Sie sind das
 * Schwergewicht und deshalb eine eigene Entscheidung.
 */
const OFFLINE_AREAS = ['termine', 'setlists', 'songs', 'noten', 'rider', 'kanaele'];

// Worüber Push-Mitteilungen sprechen können — je Mitglied abwählbar.
const PUSH_TOPICS = ['events', 'comments', 'attendance', 'photos', 'post'];
const PUSH_NICHTS = '-';

/**
 * Die Themen eines Mitglieds — Abwahl statt Anwahl, wie beim Offline-Vorrat.
 *
 * Leer heißt „noch nie eingestellt": dann sind alle Themen dabei. Das schickt
 * niemandem etwas gegen seinen Willen — eine Mitteilung entsteht erst, wenn
 * jemand sein Gerät anmeldet, und dabei fragt der Browser selbst um Erlaubnis.
 * Wer alle Themen abwählt, speichert '-' und bekommt nichts; ohne diese
 * Unterscheidung bekäme genau der wieder alles, der es abbestellt hat.
 */
function push_topics(?array $user): array {
  $roh = trim((string) ($user['push_topics'] ?? ''));
  if ($roh === '') return PUSH_TOPICS;
  if ($roh === PUSH_NICHTS) return [];
  return array_values(array_intersect(PUSH_TOPICS, array_map('trim', explode(',', $roh))));
}

// Liedtext: gehört nicht in die Notizen. Notizen sind für die Band („Schluss
// offen"), der Text ist, was jemand beim Singen liest — und der wird lang.
// Welche Bereiche jemand offline dabeihaben will. Leer heißt: nichts von
// selbst — der Knopf am Termin geht trotzdem.
if (!column_exists('users', 'offline_scope')) {
  $db->exec("ALTER TABLE users ADD COLUMN offline_scope VARCHAR(190) NOT NULL DEFAULT ''");
}
// Bühnenplan (#183): Grundriss je Eintrag, damit maßstäblich gezeichnet werden
// kann, und der Verweis aufs Mitglied — nur so kommt das Foto in den Plan.
// NULL beim Maß heißt „nimm das Übliche seiner Art"; ein eigenes Maß hat nur,
// was vom Üblichen abweicht (ein 3x2-Podest aus drei Modulen etwa).
foreach ([
  'width_cm' => 'INT NULL',
  'depth_cm' => 'INT NULL',
  'user_id'  => 'INT NULL',
] as $siCol => $siDdl) {
  if (!column_exists('stage_items', $siCol)) $db->exec("ALTER TABLE stage_items ADD COLUMN `$siCol` $siDdl");
}
// Die Figur, mit der jemand im Plan steht. Kein Geschlechtsfeld: Für ein
// Symbol muss das niemand hinterlegen, gewählt wird selbst.
if (!column_exists('users', 'stage_figure')) {
  $db->exec("ALTER TABLE users ADD COLUMN stage_figure VARCHAR(16) NOT NULL DEFAULT ''");
}
// Wer überhaupt auf der Bühne steht. Ein Techniker, ein Manager, ein Fahrer
// gehören zur Band, aber nicht in den Bühnenplan — die Vorlage hat sie bisher
// mitaufgestellt. Neu ist an, damit sich für bestehende Installationen nichts
// ändert; wer nicht draufgehört, wird ausgehakt.
if (!column_exists('users', 'on_stage')) {
  $db->exec('ALTER TABLE users ADD COLUMN on_stage TINYINT(1) NOT NULL DEFAULT 1');
}
// Wann jemand die Fotos zuletzt angesehen hat (#195). Je Mitglied, denn „neu"
// ist keine Eigenschaft des Bildes, sondern eine des Betrachters: Wer vier
// Wochen nicht hineingesehen hat, dem ist mehr neu als dem, der gestern da war.
// NULL heißt „noch nie" — dann ist alles neu, und das ist beim ersten Besuch
// nicht hilfreich, deshalb setzt die Seite den Zeitpunkt beim ersten Mal, ohne
// etwas als neu zu zeigen.
if (!column_exists('users', 'photos_seen_at')) {
  $db->exec('ALTER TABLE users ADD COLUMN photos_seen_at DATETIME NULL');
}
// Woher ein Bild kommt (#197). Beim Hochladen der ursprüngliche Dateiname, bei
// einem verknüpften Bild später der Ordnerpfad. Eine Spalte für beides, denn die
// Frage ist dieselbe: Wo lag das im Original? Bestehende Bilder bleiben leer —
// die Angabe ist verloren und wird nicht erfunden.
// Das Erscheinungsjahr der Fassung, die die Band spielt (#239). Optional: Ein
// geratenes Jahr ist schlechter als keines.
if (!column_exists('songs', 'release_year')) {
  $db->exec('ALTER TABLE songs ADD COLUMN release_year SMALLINT UNSIGNED NULL');
}
// Ein verknüpfter Ordner darf zu einem Termin gehören (#21): Ordner heißen nach
// dem Auftritt, und dann sollen die Bilder darin auch dort landen.
if (!column_exists('od_folders', 'event_id')) {
  $db->exec('ALTER TABLE od_folders ADD COLUMN event_id INT NULL');
}
if (!column_exists('photos', 'source')) {
  $db->exec("ALTER TABLE photos ADD COLUMN source VARCHAR(400) NOT NULL DEFAULT ''");
}
// Die Serien sind fort (#218). Die zwei Spalten und ihr Index gehen mit: Ohne
// die Funktion bedeuten sie nichts, und eine Spalte ohne Bedeutung wird beim
// nächsten Lesen falsch verstanden. Verloren geht dabei kein Wissen — der
// Zwischenstand war jederzeit neu errechenbar, solange es die Funktion gab.
if (column_exists('photos', 'stack_id')) {
  $db->exec('DROP INDEX idx_photos_stack ON photos');
  $db->exec('ALTER TABLE photos DROP COLUMN stack_id, DROP COLUMN stack_cover');
}
// Der Weg einer Datei im verknüpften Ordner (#205). Er ist die eigentliche
// Auskunft: „Bilder/2026/AKF/Sven Löffler" sagt Termin und Fotograf, und das ist
// mehr, als diese Anwendung je erraten könnte. Das Aufnahmedatum kommt aus
// derselben Antwort von Graph mit — ohne es lässt sich keine Serie bilden (#198).
if (!column_exists('od_items', 'rel_path')) {
  $db->exec("ALTER TABLE od_items ADD COLUMN rel_path VARCHAR(400) NOT NULL DEFAULT '',
                                  ADD COLUMN taken_at DATETIME NULL");
}
// Was Graph über ein Bild weiß, an der Verknüpfung festhalten (#206). Microsoft
// hat das EXIF beim Hochladen gelesen und gibt es heraus — Kamera, Ort, Maße und
// eine Prüfsumme. Dieselbe Auskunft aus einer 15-MB-Datei zu holen wäre
// tausendfacher Aufwand für dasselbe Ergebnis.
if (!column_exists('od_items', 'camera')) {
  $db->exec("ALTER TABLE od_items
    ADD COLUMN camera VARCHAR(120) NOT NULL DEFAULT '',
    ADD COLUMN lat DECIMAL(9,6) NULL,
    ADD COLUMN lng DECIMAL(9,6) NULL,
    ADD COLUMN img_w INT NOT NULL DEFAULT 0,
    ADD COLUMN img_h INT NOT NULL DEFAULT 0,
    ADD COLUMN sha256 CHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN imported_at DATETIME NULL");
}
// Ein Galeriebild, das auf eine Datei bei OneDrive zeigt (#206). Lokal liegt nur
// die gerechnete Fassung; das Original bleibt, wo es ist, und wird verlinkt.
// Eine gerechnete Fassung trägt kein EXIF — ein öffentliches Bild ist damit von
// sich aus metadatenfrei, ohne dass etwas entfernt werden muss.
if (!column_exists('photos', 'od_item_id')) {
  $db->exec("ALTER TABLE photos
    ADD COLUMN od_item_id VARCHAR(190) NOT NULL DEFAULT '',
    ADD COLUMN od_web_url VARCHAR(600) NOT NULL DEFAULT '',
    ADD COLUMN camera VARCHAR(120) NOT NULL DEFAULT '',
    ADD COLUMN img_w INT NOT NULL DEFAULT 0,
    ADD COLUMN img_h INT NOT NULL DEFAULT 0");
  $db->exec('CREATE INDEX idx_photos_od ON photos (od_item_id)');
}
// Archiv (#200): aus der Galerie nehmen, ohne zu zerstören. Löschen können die
// Mitglieder einer Band einander nicht zumuten — ein Bild, das jemand anderes
// braucht, wäre endgültig weg. Archiviert heißt: nicht mehr im Weg, aber da.
if (!column_exists('photos', 'archived_at')) {
  $db->exec('ALTER TABLE photos ADD COLUMN archived_at DATETIME NULL');
}
// Fürs Rausgeben gut genug (#202). Nicht dasselbe wie is_public: Ein Bild kann
// dem Veranstalter taugen und trotzdem nicht auf die Website gehören — und
// umgekehrt. Zwei Fragen, zwei Antworten.
if (!column_exists('photos', 'is_press')) {
  $db->exec('ALTER TABLE photos ADD COLUMN is_press TINYINT(1) NOT NULL DEFAULT 0');
}
// Ein Kalender-Zeichen je Mitglied (#222). Bisher gab es genau eines für die
// ganze Band; damit konnte der Feed nicht wissen, wessen Kalender er füllt —
// und ein Ersatzmusiker sah über den Link Termine, die ihm die Anwendung
// verbirgt. Das alte gemeinsame Zeichen bleibt gültig, bis es jemand
// abschaltet: In irgendeiner Kalender-App läuft es gerade.
if (!column_exists('users', 'ical_token')) {
  $db->exec("ALTER TABLE users ADD COLUMN ical_token CHAR(32) NOT NULL DEFAULT ''");
  $db->exec('CREATE INDEX idx_users_ical ON users (ical_token)');
}
// Doppelte finden (#199). Eine Prüfsumme des Dateiinhalts, keine Ähnlichkeit:
// Sie erkennt exakte Kopien mit Sicherheit und neu komprimierte gar nicht. Das
// ist eine bewusste Grenze und keine halbe Lösung — was ein Messenger neu
// gerechnet hat, ist Byte für Byte etwas anderes.
if (!column_exists('photos', 'checksum')) {
  $db->exec("ALTER TABLE photos ADD COLUMN checksum CHAR(64) NOT NULL DEFAULT ''");
  $db->exec('CREATE INDEX idx_photos_checksum ON photos (checksum)');
}
// Zweiter Faktor (#169). Drei Spalten, denn drei Dinge sind zu unterscheiden:
// das Geheimnis, ob es je bestätigt wurde, und die Rückwege. Ohne das
// Bestätigungsdatum sperrt sich aus, wer den QR-Code scannt und die App
// gleich wieder löscht — dann läge ein Geheimnis im Konto, das niemand hat.
if (!column_exists('users', 'totp_secret')) {
  $db->exec("ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN totp_confirmed_at DATETIME NULL,
    ADD COLUMN totp_recovery TEXT NULL");
}
// Wer am Gewinn beteiligt ist. Nicht jedes Konto gehört einem Gesellschafter:
// ein Manager, eine Technikerin, ein aufbewahrtes Konto eines Ausgetretenen —
// die alle bekämen sonst einen Anteil, und allen anderen fehlte er. Neu ist an,
// damit sich für bestehende Installationen nichts ändert; Aushilfen sind ohnehin
// nie beteiligt und werden nicht gefragt.
// Passkeys (#168): je Gerät einer, mehrere je Mitglied — Handy und Rechner
// sind zwei. credential_id ist die Kennung des Geräts und eindeutig; sie ist
// binär und wird deshalb in der URL-Schreibweise abgelegt, damit sie sich
// vergleichen lässt, ohne jedes Mal umzurechnen.
$db->exec("CREATE TABLE IF NOT EXISTS passkeys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key TEXT NOT NULL,
    label VARCHAR(60) NOT NULL DEFAULT '',
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    UNIQUE KEY uniq_credential (credential_id),
    KEY idx_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// Wer den Passkey verwahrt — iCloud, Google, 1Password. Auch aufgehoben, wenn
// wir den Namen dazu heute nicht kennen: Die Anbieterliste wächst, und dann
// lässt sich ein alter Eintrag nachträglich beschriften.
if (!column_exists('passkeys', 'aaguid')) {
  $db->exec("ALTER TABLE passkeys ADD COLUMN aaguid VARCHAR(36) NOT NULL DEFAULT ''");
}
if (!column_exists('users', 'profit_share')) {
  $db->exec('ALTER TABLE users ADD COLUMN profit_share TINYINT(1) NOT NULL DEFAULT 1');
}
if (!column_exists('songs', 'lyrics')) {
  $db->exec('ALTER TABLE songs ADD COLUMN lyrics MEDIUMTEXT NULL AFTER notes');
}
// Der Notizzettel: Akkorde und Handschrift-Notizen, wie sie ein Gitarrist
// aufschreibt. Getrennt vom Liedtext, weil er in fester Zeichenbreite gelesen
// wird — was untereinander steht (Akkord über der Silbe), bleibt untereinander.
if (!column_exists('songs', 'chords')) {
  $db->exec('ALTER TABLE songs ADD COLUMN chords MEDIUMTEXT NULL AFTER lyrics');
}
// Notizzettel sind musikerspezifisch: je Song und Mitglied ein eigener. Der
// alte gemeinsame songs.chords bleibt als Spalte erhalten (Sicherheit), wird
// aber nicht mehr geschrieben; sein Inhalt wandert einmalig zum Admin, damit
// nichts verloren geht.
$db->exec('CREATE TABLE IF NOT EXISTS song_chords (
    song_id INT NOT NULL,
    user_id INT NOT NULL,
    content MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (song_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
if (setting('chords_migrated') !== '1') {
  $migAdmin = row("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
  if ($migAdmin) {
    q('INSERT IGNORE INTO song_chords (song_id, user_id, content)
       SELECT id, ?, chords FROM songs WHERE chords IS NOT NULL AND TRIM(chords) <> ?',
      [$migAdmin['id'], '']);
  }
  set_setting('chords_migrated', '1');
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
  // Mitteilungen aufs Gerät: an, aber abwählbar. Ein Push entsteht erst, wenn
  // ein Mitglied im Profil ein Thema wählt UND sein Gerät anmeldet — der
  // Browser fragt dabei selbst um Erlaubnis. Dieser Schalter macht die Funktion
  // also nur verfügbar; von allein geht nichts hinaus.
  'push_enabled' => '1',
  // Einmal am Tag nachsehen, ob es eine neue Fassung gibt. Gefragt wird nach
  // einer Versionsnummer, gesendet wird nichts über die Installation.
  // Aus, wie jede Kommunikation nach außen: die Prüfung fragt GitHub, und das
  // ist eine Entscheidung der Band, keine Voreinstellung. Einschaltbar in den
  // Einstellungen — bestehende Installationen behalten ihren Wert.
  'update_check' => '0', 'update_checked_at' => '0', 'update_latest' => '',
  // Steuerliche Werte. Voreinstellung ist der deutsche Stand vom Juli 2026;
  // sie stehen hier, damit eine Band sie ändern kann, wenn der Gesetzgeber
  // sie ändert oder die Band anderswo sitzt. Aus ist die Grenzwarnung, bis
  // jemand sagt, dass die Regelung überhaupt gilt.
  'tax_small_business' => '0',
  'tax_limit_prev_year' => '25000',
  'tax_limit_this_year' => '100000',
  'tax_gwg_limit' => '800',
  // Die GWG-Grenze ist netto zu prüfen, auch ohne Vorsteuerabzug. Erfasst wird
  // in der Kasse aber, was tatsächlich bezahlt wurde — für eine Band unter der
  // Kleinunternehmerregelung also brutto. Wer netto erfasst, stellt das um.
  'tax_prices_gross' => '1',
  'tax_vat_rate' => '19',
  // Nutzungsdauer je Geräteart; woher die Zahlen kommen, steht bei
  // TAX_AFA_BY_CATEGORY.
  'tax_afa_instrument' => '7',
  'tax_afa_pa' => '7',
  'tax_afa_licht' => '5',
  'tax_afa_transport' => '10',
  'tax_values_checked' => '2026-07-28',
  // Bagatellgrenze der Abfärberegelung: beides muss halten.
  'tax_commercial_share' => '3', 'tax_commercial_abs' => '24500',
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
// Die Menge stand im Namen: „Neutrik NC3 FXX (10x)" (#185). Beim Übernehmen der
// Händlerbestellungen ist sie dort gelandet, weil es kein Feld dafür gab. Eine
// Zahl im Anzeigenamen lässt sich nicht filtern, summieren oder korrigieren —
// sie gehört in eine Spalte. Nur Einträge mit Menge 1 werden angefasst, damit
// ein von Hand gesetzter Wert nicht überschrieben wird.
if (setting('eq_quantity_from_name') !== '1' && column_exists('equipment', 'quantity')) {
  foreach (rows("SELECT id, name FROM equipment WHERE quantity = 1 AND name REGEXP '\\\\([0-9]+x\\\\)'") as $eqQ) {
    if (!preg_match('~^(.*?)\s*\((\d+)x\)\s*$~', (string) $eqQ['name'], $eqM)) continue;
    $menge = (int) $eqM[2];
    $rest  = trim($eqM[1]);
    // Ein „(2x)" mitten im Namen gehört zum Produkt („Kabel 2x XLR") und bleibt,
    // wo es ist; nur das Zählsuffix am Ende wandert.
    if ($menge < 2 || $rest === '') continue;
    q('UPDATE equipment SET name = ?, quantity = ? WHERE id = ?', [$rest, $menge, (int) $eqQ['id']]);
  }
  set_setting('eq_quantity_from_name', '1');
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
// Der Hilfetext zu den Mitteilungen sagte, es gebe sie noch nicht — seit v1.147
// gibt es sie. Ein Seed ergänzt nur Fehlendes und käme an einen bestehenden
// Eintrag nicht heran, deshalb hier gezielt: geändert wird ausschließlich, wo
// noch der alte Wortlaut steht, damit von Hand gepflegte Fassungen bleiben.
// Vor der Umstellung auf Abwahl bedeutete ein leeres Feld zweierlei: „noch nie
// eingestellt" und „alle Haken entfernt und gespeichert" — die alte Route
// schrieb beides als ''. Seit der Umstellung heißt leer „alles an", und damit
// bekäme ausgerechnet die Person alles zurück, die es abbestellt hatte.
//
// Unterscheiden lässt sich das nachträglich nur an einem Anhaltspunkt: Wer ein
// Gerät angemeldet bzw. je einen Offline-Bereich gespeichert hat, hat den
// Dialog bewusst benutzt. Für die gilt das leere Feld als „nichts".
if (setting('optout_migrated') !== '1') {
  q("UPDATE users SET push_topics = '-'
     WHERE push_topics = '' AND EXISTS (SELECT 1 FROM push_subscriptions p WHERE p.user_id = users.id)");
  set_setting('optout_migrated', '1');
}
// Fünf Hilfetexte beschrieben Vergangenes: den Offline-Vorrat als Anwahl, die
// Mitteilungen ebenso, und zu Bühne, Adress-Suche und Foto-Auswertung stand
// nichts. Die deutschen Fassungen sind korrigiert — die Übersetzungen dazu
// erreicht ein Seed nicht, der nur Fehlendes ergänzt. Also die veralteten
// gezielt entfernen; der Seed legt sie danach neu an.
if (setting('help_texts_2026_08') !== '1') {
  q("DELETE FROM translations WHERE tkey IN
     ('app_install_offline','app_install_push','help_songs','help_orte','help_fotos')");
  set_setting('help_texts_2026_08', '1');
}
// Der Fotobereich kann inzwischen mehr, als sein Hilfetext wusste (v1.197 bis
// v1.203). Der neue Text steht in Seed 16 SELBST — ein späterer Seed erreicht
// ihn nie, weil das Neueinspielen alle Seeds der Reihe nach laufen lässt und
// der früheste gewinnt. Genau daran ist der erste Versuch (Wächter …08b)
// gescheitert: weggeräumt, und Seed 16 setzte den alten Text zurück.
// Die Serien sind fort (#218): ihre Texte auch. Sonst bliebe in sechs Sprachen
// stehen, was die Anwendung nicht mehr kann — und in den Einstellungen ein
// Schalter-Zustand, den niemand mehr umlegen kann.
if (setting('stacks_texts_gone') !== '1') {
  q("DELETE FROM translations WHERE tkey IN
     ('set_stacks','set_stacks_hint','photo_stack_count','photo_stack_open','photo_stack_title',
      'photo_stack_back','photo_stack_cover','photo_stack_is_cover','photo_stack_whole',
      'photo_stack_gone','fl_photo_stack_cover')");
  q("DELETE FROM settings WHERE `key` IN ('stacks_enabled','stacks_built','stacks_built_camera',
      'stacks_hint_default_off')");
  set_setting('stacks_texts_gone', '1');
}
if (setting('help_fotos_2026_08e') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_fotos'");
  set_setting('help_fotos_2026_08e', '1');
}
if (setting('push_help_fixed') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'app_install_push'
     AND (value LIKE '%noch nicht%' OR value LIKE '%do not exist yet%'
          OR value LIKE '%n\\'existent pas encore%' OR value LIKE '%todavía no existen%'
          OR value LIKE '%zijn er nog niet%' OR value LIKE '%non ci sono ancora%')");
  set_setting('push_help_fixed', '1');
}
// Vier Texte zur Steuer sagten die halbe Wahrheit: die GWG-Grenze gilt netto,
// auch ohne Vorsteuerabzug, die Nutzungsdauer steht jetzt je Geräteart, und der
// Verkauf eines Geräts zählt nach § 19 Abs. 2 Satz 2 UStG nicht zum Umsatz. Ein
// Seed ergänzt nur Fehlendes und käme an die alten Fassungen nicht heran.
// Die Hilfe zum Inventar sagte nichts darüber, wann eine Zeile für ein Gerät
// steht und wann für zehn Kleinteile (#185). Der Text in Seed 29 ist ergänzt,
// aber ein Seed ergänzt nur Fehlendes — die alte Fassung muss weg, sonst bleibt
// sie stehen und beschreibt die Hälfte.
if (setting('help_equipment_quantity') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_equipment'");
  set_setting('help_equipment_quantity', '1');
}
// Die Hilfe zum Rider beschrieb den Bühnenplan von vor dem Maßstab (#186):
// keine Vorlage, keine Podestgröße, keine Figuren. Derselbe Handgriff wie oben —
// ein Seed ergänzt nur Fehlendes, die alte Fassung muss deshalb weichen.
if (setting('help_rider_stageplot') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_rider'");
  set_setting('help_rider_stageplot', '1');
}
if (setting('tax_texts_2026_08') !== '1') {
  q("DELETE FROM translations WHERE tkey IN
     ('set_tax_gwg_hint','set_tax_afa_hint','help_tax_gwg','tax_counts_hint')");
  set_setting('tax_texts_2026_08', '1');
}
// Die Hilfe nannte beide Umsatzgrenzen, sagte aber nicht, dass sie ganz
// verschieden wirken: die eine schaltet zum Jahreswechsel, die andere im
// Moment des Überschreitens. Dazu stand dort noch, verkauftes Equipment zähle
// zum Umsatz — seit v1.158.0 stimmt das nicht mehr.
// Rechtsform und Haftung stehen jetzt im Abschnitt zur Steuerübersicht, weil
// sie unabhängig von der Kleinunternehmerregelung gelten. Der alte Satz dazu
// stand mitten im Text über die Umsatzgrenze und ist dort herausgenommen.
// Die Menügruppe mit Fotos, Musik und Downloads hieß „Material" — unscharf für
// das, was drinsteht, und die Sprachen waren sich uneins: Französisch sagte
// längst „Médias". Jetzt überall Medien.
// Der Hinweis unter der Mitgliedertabelle sagt jetzt auch, wer überhaupt
// aufgeführt wird — seit es den Schalter zur Gewinnbeteiligung gibt.
// Ein Passkey gehört einem Schlüsselbund, nicht einem Gerät: Der im iCloud-
// Schlüsselbund gilt auf iPhone, iPad und Mac zugleich. Die Texte sagten
// „einen pro Gerät" und schickten damit alle auf den falschen Weg.
// Einträge, die vor der Anbietererkennung entstanden sind, tragen den erratenen
// Plattformnamen. Wo die Kennung inzwischen einen Anbieter benennt, wird er
// nachgetragen — aber nur bei den geratenen Namen. Was jemand selbst getippt
// hat, bleibt: Ein Name, den man vergeben hat, gehört einem.
// Eine Meldung für drei Ursachen nannte oft die falsche: „blockiert" stand
// auch dann da, wenn der Browser gar nicht gefragt hatte. Der Text ist ersetzt.
if (setting('push_reasons_text') !== '3') {
  q("DELETE FROM translations WHERE tkey IN ('prof_push_denied','prof_push_open')");
  set_setting('push_reasons_text', '3');
}
// Schritt 2 der Einrichtung hieß „diesen Code abfotografieren" — was auf dem
// Handy nicht geht, weil die App auf demselben Gerät liegt. Der Schritt trennt
// jetzt die beiden Fälle, und der alte Text muss weg: Ein Seed schreibt eine
// vorhandene Zeile nicht um.
if (setting('totp_setup_texts') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'totp_setup_scan'");
  set_setting('totp_setup_texts', '1');
}
if (setting('passkey_relabel') !== '1') {
  foreach (rows("SELECT id, label, aaguid FROM passkeys WHERE aaguid <> ''") as $pkRow) {
    $besser = PASSKEY_ANBIETER[$pkRow['aaguid']] ?? '';
    $geraten = in_array($pkRow['label'], ['iPhone', 'iPad', 'Mac', 'Android', 'Windows', 'Linux'], true);
    if ($besser !== '' && $geraten) {
      q('UPDATE passkeys SET label = ? WHERE id = ?', [$besser, $pkRow['id']]);
    }
  }
  set_setting('passkey_relabel', '1');
}
if (setting('passkey_keychain_text') !== '1') {
  q("DELETE FROM translations WHERE tkey IN ('prof_passkeys_hint','help_passkey')");
  set_setting('passkey_keychain_text', '1');
}
if (setting('profit_share_hint') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'taxr_share_hint'");
  set_setting('profit_share_hint', '1');
}
if (setting('nav_media_2026_08') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'inavg_material'");
  set_setting('nav_media_2026_08', '1');
}
if (setting('gbr_help_2026_08') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_tax_band'");
  set_setting('gbr_help_2026_08', '1');
}
if (setting('tax_help_limits') !== '1') {
  q("DELETE FROM translations WHERE tkey IN
     ('help_tax_what','help_tax_counts','help_tax_over','help_tax_next_year')");
  set_setting('tax_help_limits', '1');
}
if (setting('translations_seed') !== $seedStamp) {
  foreach ($seedFiles as $seedFile) {
    try {
      $db->exec((string) file_get_contents($seedFile));
    } catch (PDOException $seedError) {
      // Ein Seed darf fehlschlagen, ohne die Seite mitzureißen — aber nicht
      // lautlos. Ein Tippfehler in einer Zeichenkette lässt sonst den halben
      // Rest der Datei aus, und niemand merkt es, bis eine Sprache Lücken hat.
      error_log('Bandregie: Seed ' . basename($seedFile) . ' abgebrochen: ' . $seedError->getMessage());
    }
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
    "Bandregie — initial administrator account\n\n"
    . "Email:    admin@example.com\nPassword: $startPw\n\n"
    . "You must change this password at first login. This file is removed the\n"
    . "moment you do, so it can never outlive the password it holds. Change\n"
    . "the email address afterwards under Intern -> Profil.\n");
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
      : row('SELECT id, name, stage_name, email, role, instrument, avatar_file, must_change_pw, substitute_for, offline_scope FROM users WHERE id = ?', [$_SESSION['uid']]);
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
 * Bilder, die schon im Inventar liegen und an diesem Gerät noch fehlen (#184).
 * Zwei gleiche Geräte sind zwei Einträge, also fängt das zweite ohne Foto an —
 * dieselbe Datei ein zweites Mal hochzuladen wäre die einzige Alternative.
 *
 * Vorn stehen die Bilder von Geräten mit derselben Artikelnummer: Das ist der
 * Zwilling, um den es fast immer geht. Danach die mit gleichem Namensanfang,
 * denn Altbestände haben oft keine Artikelnummer.
 */
function eq_photo_choices(int $eqId, int $limit = 60): array {
  $eq = row('SELECT article_no, name FROM equipment WHERE id = ?', [$eqId]);
  if (!$eq) return [];
  // Namensanfang bis zum Zählsuffix: „Shure KSM9 HS #2" sucht „Shure KSM9 HS".
  $stamm = trim(preg_replace('~\s*#\d+\s*$~', '', (string) $eq['name']));
  $treffer = rows(
    "SELECT f.id, f.filename, f.original_name, f.size, e.id AS eq_id, e.name AS eq_name,
            (e.article_no <> '' AND e.article_no = ?) AS gleiche_nummer,
            (? <> '' AND e.name LIKE CONCAT(?, '%')) AS gleicher_name
       FROM files f
       JOIN equipment e ON e.id = f.entity_id
      WHERE f.entity_type = 'equipment'
        AND e.id <> ?
        AND LOWER(SUBSTRING_INDEX(f.original_name, '.', -1)) IN ('jpg','jpeg','png','gif','webp')
        -- Was hier schon hängt, muss nicht angeboten werden.
        AND NOT EXISTS (SELECT 1 FROM files x WHERE x.entity_type = 'equipment'
                          AND x.entity_id = ? AND x.filename = f.filename)
      ORDER BY gleiche_nummer DESC, gleicher_name DESC, e.name, f.original_name",
    [(string) $eq['article_no'], $stamm, $stamm, $eqId, $eqId]);
  // Dasselbe Bild hängt oft an mehreren Geräten. Entdoppelt wird hier und nicht
  // per GROUP BY: mit ONLY_FULL_GROUP_BY dürfte die Abfrage sonst nicht laufen.
  $auswahl = [];
  foreach ($treffer as $t) {
    if (isset($auswahl[$t['filename']])) continue;
    $auswahl[$t['filename']] = $t;
    if (count($auswahl) >= $limit) break;
  }
  return array_values($auswahl);
}

/**
 * Was der Server beim Hochladen wirklich zulässt (#194).
 *
 * Gelesen und nicht hingeschrieben: Die Grenzen stehen in der PHP-Einrichtung
 * und ändern sich mit ihr. Eine Zahl im Text wäre spätestens beim nächsten
 * Serverumzug eine Lüge — und die Lüge war der eigentliche Fehler: Die Seite
 * versprach 10 MB, wo der Server 2 MB annahm, und verlor den Rest schweigend.
 *
 * @return array{per_file: int, per_request: int, max_files: int}
 */
function upload_limits(): array {
  $byte = static function (string $wert): int {
    $wert = trim($wert);
    if ($wert === '' || $wert === '-1') return 0;   // 0 heißt hier: keine Grenze
    $zahl = (int) $wert;
    return match (strtolower(substr($wert, -1))) {
      'g' => $zahl * 1024 * 1024 * 1024,
      'm' => $zahl * 1024 * 1024,
      'k' => $zahl * 1024,
      default => $zahl,
    };
  };
  $jeDatei = $byte((string) ini_get('upload_max_filesize'));
  $jeAnfrage = $byte((string) ini_get('post_max_size'));
  // Die kleinere Grenze gewinnt: Eine Datei kann nicht größer sein als die
  // Anfrage, die sie trägt.
  if ($jeAnfrage > 0 && ($jeDatei === 0 || $jeDatei > $jeAnfrage)) $jeDatei = $jeAnfrage;
  return ['per_file' => $jeDatei, 'per_request' => $jeAnfrage,
          'max_files' => max(1, (int) ini_get('max_file_uploads'))];
}

/**
 * Wurde die Anfrage von PHP verworfen, weil sie zu groß war?
 *
 * Bei überschrittenem post_max_size wirft PHP $_POST UND $_FILES weg. Die Seite
 * bekommt einen POST ohne Inhalt und täte sonst schlicht nichts — der stillste
 * aller Fehler. Erkennbar ist es nur an der Länge, die der Browser gemeldet hat.
 */
function upload_too_big(): bool {
  return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !$_POST && !$_FILES
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * Welche Anhang-Art zu welcher Tabelle gehört. Grundlage fürs Aufräumen: Eine
 * Zeile, deren Gegenstand es nicht mehr gibt, zeigt ins Leere.
 */
const FILE_ENTITY_TABLES = [
  'event' => 'events', 'song' => 'songs', 'venue' => 'venues', 'setlist' => 'setlists',
  'equipment' => 'equipment', 'invoice' => 'invoices', 'finance' => 'finances',
  // „download" hängt an keiner Tabelle — das sind die Dateien für Veranstalter.
  'download' => null,
];

/**
 * Nachsehen, was an toten Verweisen herumliegt. Ändert nichts (#193).
 *
 * Vier Arten, und sie sind unterschiedlich gefährlich:
 *  - entity_gone: Anhang zeigt auf einen Gegenstand, den es nicht mehr gibt
 *  - file_missing: die Zeile ist da, die Datei auf der Platte fehlt
 *  - photo_missing: dasselbe bei einem Foto
 *  - files_extra: Datei im Anhang-Ordner, auf die keine Zeile mehr zeigt
 *
 * Für den Bilder-Ordner wird nur gezählt und nicht gelöscht: Dort verweisen
 * Fotos, Profilbilder und das Hintergrundbild hinein, und eine einzige
 * vergessene Quelle würde beim Aufräumen echte Bilder vernichten.
 *
 * @return array{entity_gone: array, file_missing: array, photo_missing: array, files_extra: array, uploads_extra: int}
 */
function orphan_scan(): array {
  $entityGone = [];
  foreach (FILE_ENTITY_TABLES as $typ => $tabelle) {
    if ($tabelle === null) continue;
    foreach (rows("SELECT id, entity_id, original_name, filename FROM files f
                   WHERE entity_type = ?
                     AND NOT EXISTS (SELECT 1 FROM $tabelle t WHERE t.id = f.entity_id)", [$typ]) as $f) {
      $entityGone[] = $f + ['entity_type' => $typ];
    }
  }
  // Unbekannte Anhang-Art zählt auch als tot: Sie kann von keiner Seite mehr
  // angezeigt werden, weil may_see_file() sie ablehnt.
  $bekannt = array_keys(FILE_ENTITY_TABLES);
  $platz = implode(',', array_fill(0, count($bekannt), '?'));
  foreach (rows("SELECT id, entity_type, entity_id, original_name, filename FROM files
                 WHERE entity_type NOT IN ($platz)", $bekannt) as $f) {
    $entityGone[] = $f;
  }

  $fileMissing = [];
  foreach (rows('SELECT id, entity_type, entity_id, original_name, filename FROM files') as $f) {
    if (!is_file(FILES_DIR . '/' . $f['filename'])) $fileMissing[] = $f;
  }
  $photoMissing = [];
  // Archivierte bleiben unangetastet (#200): Wer etwas ins Archiv legt, hat
  // entschieden, dass es bleibt — das Aufräumen widerspricht dem nicht.
  foreach (rows('SELECT id, filename, caption FROM photos WHERE archived_at IS NULL') as $p) {
    if (!is_file(UPLOADS_DIR . '/' . $p['filename'])) $photoMissing[] = $p;
  }

  // Im Anhang-Ordner ist files.filename die einzige Quelle — dort lässt sich
  // sicher sagen, was niemand mehr braucht.
  $benutzt = array_flip(array_column(rows('SELECT DISTINCT filename FROM files'), 'filename'));
  $filesExtra = [];
  foreach (glob(FILES_DIR . '/*') ?: [] as $pfad) {
    if (!is_file($pfad)) continue;
    $name = basename($pfad);
    if (!isset($benutzt[$name])) $filesExtra[] = ['filename' => $name, 'size' => filesize($pfad)];
  }

  // Im Bilder-Ordner nur zählen. Quellen: Fotos, Profilbilder, Hintergrundbild.
  $bilderBenutzt = array_flip(array_merge(
    array_column(rows('SELECT DISTINCT filename FROM photos'), 'filename'),
    array_column(rows("SELECT DISTINCT avatar_file FROM users WHERE avatar_file IS NOT NULL AND avatar_file <> ''"), 'avatar_file'),
    array_filter([setting('background_file')])
  ));
  $uploadsExtra = 0;
  foreach (glob(UPLOADS_DIR . '/*') ?: [] as $pfad) {
    if (is_file($pfad) && !isset($bilderBenutzt[basename($pfad)])) $uploadsExtra++;
  }

  return ['entity_gone' => $entityGone, 'file_missing' => $fileMissing,
          'photo_missing' => $photoMissing, 'files_extra' => $filesExtra,
          'uploads_extra' => $uploadsExtra];
}

/**
 * Aufräumen, was der Fund benennt. Nur die drei sicheren Arten — der
 * Bilder-Ordner wird nie angefasst.
 *
 * @return array{rows: int, files: int, photos: int}
 */
function orphan_clean(): array {
  $fund = orphan_scan();
  $zeilen = $dateien = $fotos = 0;

  foreach ([...$fund['entity_gone'], ...$fund['file_missing']] as $f) {
    q('DELETE FROM files WHERE id = ?', [(int) $f['id']]);
    $zeilen++;
  }
  foreach ($fund['photo_missing'] as $p) {
    q('DELETE FROM photos WHERE id = ?', [(int) $p['id']]);
    $fotos++;
  }
  // Erst nach dem Löschen der Zeilen erneut sehen, was übrig ist: Eine Datei,
  // deren letzte Zeile gerade wegfiel, gehört jetzt dazu.
  foreach (orphan_scan()['files_extra'] as $d) {
    if (@unlink(FILES_DIR . '/' . $d['filename'])) $dateien++;
  }
  return ['rows' => $zeilen, 'files' => $dateien, 'photos' => $fotos];
}

/**
 * Anhänge einer Sache entfernen — Zeilen und, wenn niemand sie mehr braucht,
 * die Datei selbst.
 *
 * Die Datei wird erst gelöscht, wenn keine Zeile mehr auf sie zeigt: Dieselbe
 * Datei hängt an mehreren Stellen, sobald eine Rechnung mehrere Geräte nennt
 * oder ein Gerät das Foto seines Zwillings übernommen hat (#184). Ohne diese
 * Zählung verliert der Zwilling sein Bild (#188).
 *
 * @return int Wie viele Zeilen entfernt wurden
 */
/**
 * Die Grenze für einen Anhang. Sie stand als Zahl im Upload-Zweig; seit auch
 * Mailanhänge diesen Weg gehen (#19), braucht sie einen Namen — sonst gilt sie
 * an einer Stelle und an der anderen nicht.
 */
const FILE_MAX_BYTES = 20 * 1024 * 1024;

/**
 * Der Name, unter dem eine Datei auf der Platte liegt.
 *
 * Der Zufallsanteil ist nicht die Zugriffsprüfung — die steht in der Route. Er
 * sorgt dafür, dass Namen nichts verraten und sich nicht durchzählen lassen.
 * Wie die Datei wirklich heißt, steht in original_name.
 */
function file_safe_name(string $original): string {
  $endung = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($original, PATHINFO_EXTENSION))) ?? '';
  return 'datei_' . bin2hex(random_bytes(16)) . ($endung !== '' ? '.' . $endung : '');
}

/**
 * Eine Datei aus dem Speicher in den Anhangsbestand legen — für alles, was
 * nicht durch ein Formular kommt (#19).
 *
 * Derselbe Weg wie beim Hochladen: dieselbe Grenze, dieselbe Versiegelung,
 * dieselbe Tabelle. Zwei Wege für dieselbe Sache laufen auseinander, und dann
 * gilt eine Regel nur noch an einer Stelle.
 *
 * @return int|null Kennung der Zeile, oder null wenn nichts gespeichert wurde
 */
function file_store_content(string $entityType, int $entityId, string $inhalt,
                            string $originalName, ?int $wer): ?int {
  global $db;
  if ($inhalt === '' || strlen($inhalt) > FILE_MAX_BYTES) return null;
  $safe = file_safe_name($originalName);
  if (@file_put_contents(FILES_DIR . '/' . $safe, $inhalt) === false) return null;
  if (crypt_available()) file_seal_at_rest(FILES_DIR . '/' . $safe);
  q('INSERT INTO files (entity_type, entity_id, filename, original_name, size, uploaded_by) VALUES (?,?,?,?,?,?)',
    [$entityType, $entityId, $safe, mb_substr($originalName, 0, 255), strlen($inhalt), $wer]);
  return (int) $db->lastInsertId();
}

function files_purge(string $entityType, int $entityId): int {
  $weg = 0;
  foreach (rows('SELECT id, filename FROM files WHERE entity_type = ? AND entity_id = ?',
                [$entityType, $entityId]) as $f) {
    q('DELETE FROM files WHERE id = ?', [(int) $f['id']]);
    $weg++;
    if (!row('SELECT id FROM files WHERE filename = ?', [$f['filename']])) {
      @unlink(FILES_DIR . '/' . $f['filename']);   // schon weg ist auch in Ordnung
    }
  }
  return $weg;
}

/**
 * Wohin ein Anhang zurückführt: [Tabelle, eigene Seite, Übersicht]. Nicht jede
 * Sache hat eine eigene Seite — Termine, Orte, Buchungen und Rechnungen leben
 * auf ihrer Liste. Dann führt der Weg zurück eben dorthin.
 */
const FILE_ENTITY_PAGES = [
  'event'     => [null,        null,                          '/intern/termine'],
  'song'      => ['songs',     '/intern/songs/%d',            '/intern/songs'],
  'venue'     => [null,        null,                          '/intern/orte'],
  'setlist'   => ['setlists',  '/intern/setlists/%d',         '/intern/setlists'],
  'equipment' => ['equipment', '/intern/equipment/%d/detail', '/intern/equipment'],
  'invoice'   => [null,        null,                          '/intern/equipment'],
  'finance'   => [null,        null,                          '/intern/kasse'],
  'download'  => [null,        null,                          '/intern/downloads'],
];

/**
 * Zu welcher Seite gehört ein Anhang? Die installierte App läuft als eigenes
 * Fenster ohne Zurück-Pfeil (`display: standalone`), deshalb muss der Weg
 * zurück im Inhalt stehen und darf nicht dem Browser überlassen bleiben.
 */
function file_entity_url(array $file): string {
  // Ein künftiger Anhang-Typ landet auf der Übersicht statt im Nichts.
  [$tabelle, $seite, $liste] = FILE_ENTITY_PAGES[$file['entity_type']] ?? [null, null, '/intern'];
  if ($seite === null) return $liste;
  // Ein Zurück-Link, der 404 antwortet, ist dieselbe Sackgasse, die diese Seite
  // beseitigen soll — also erst nachsehen, ob die Sache noch da ist. Der
  // Tabellenname steht in FILE_ENTITY_PAGES und kommt nie aus einer Anfrage.
  return row("SELECT id FROM $tabelle WHERE id = ?", [(int) $file['entity_id']])
    ? sprintf($seite, (int) $file['entity_id'])
    : $liste;
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
    // Ein Kassenbeleg gehört zu seiner Buchung: private Auslagen sieht nur,
    // wer die Buchung selbst sehen darf.
    'finance' => may_see_finance_file($user, $id),
    // Eine Händlerrechnung trägt Anschrift und Zahlungsmittel des Käufers —
    // strenger als der Bereich, an dem sie hängt.
    'invoice' => may_see_invoice($user, $id),
    // Diese drei hängen an ihrem Bereich, den der Frontcontroller schon prüft.
    'venue', 'equipment', 'download' => true,
    // Unbekannter Typ heißt nein. Andersherum wäre jeder künftige Anhang-Typ
    // erst einmal für alle offen, bis jemand daran denkt — das ist die falsche
    // Richtung für eine Zugriffsprüfung.
    default => false,
  };
}

/** Darf jemand den Beleg zu dieser Kassenbuchung sehen? */
function may_see_finance_file(?array $user, int $financeId): bool {
  if (!$user) return false;
  $f = row('SELECT private_for FROM finances WHERE id = ?', [$financeId]);
  if (!$f) return false;
  // Private Auslagen gehören dem Mitglied, alles andere der Bandkasse.
  if ($f['private_for'] !== null) return (int) $f['private_for'] === (int) $user['id'];
  return perm_allows($user, 'kasse');
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
  // Ein vorhandenes Bild übernehmen ist eine Änderung am Gerät (#184).
  if ($path === '/intern/dateien/uebernehmen') return 'equipment';
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

/**
 * Ist diese Installation eine öffentliche Demo?
 *
 * Der Schalter steht in app/config.php und ausdrücklich nicht in den
 * Einstellungen: In einer Demo ist jeder Besucher Admin, und was in den
 * Einstellungen steht, könnte er als Erstes abschalten.
 */
function is_demo(): bool {
  global $config;
  return !empty($config['is_demo']);
}

/**
 * Bricht ab, wenn diese Installation eine öffentliche Demo ist.
 *
 * Gilt für alles, was ein späterer Besucher nicht mehr rückgängig machen kann:
 * Kennwörter, Konten und ausgehende Post. Die Zugangsdaten stehen öffentlich
 * auf der Werbeseite — wer das Admin-Kennwort ändert oder ein Konto löscht,
 * sperrt damit alle anderen bis zum nächsten Zurücksetzen aus.
 *
 * Geprüft wird hier in der Route und nicht nur in der Oberfläche: ein Formular
 * auszublenden hält niemanden davon ab, es trotzdem abzuschicken.
 */
function deny_in_demo(string $backTo): void {
  if (!is_demo()) return;
  flash(t('fl_demo_locked'));
  redirect($backTo);
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
/**
 * Wer im Rider als Ansprechpartner steht — Mitglied oder Freitext.
 *
 * Ein Mitglied ist die bessere Angabe: Ändert sich seine Nummer, ändert sich
 * der Rider mit, statt dass irgendwo eine alte Handynummer steht, die der
 * Veranstalter am Konzerttag anruft. Der Freitext bleibt für die Fälle, in
 * denen die Technik von außen kommt und niemandes Konto hier existiert.
 *
 * @return array{name: string, zeilen: array<string>}
 */
function rider_contact(string $art, array $settings): array {
  $id = (int) ($settings['rider_contact_' . $art . '_user'] ?? 0);
  if ($id > 0) {
    $u = row('SELECT name, stage_name, phone, mobile, email FROM users WHERE id = ?', [$id]);
    if ($u) {
      $zeilen = array_values(array_filter([
        (string) ($u['mobile'] ?? ''), (string) ($u['phone'] ?? ''), (string) ($u['email'] ?? ''),
      ], fn($z) => trim($z) !== ''));
      return ['name' => (string) ($u['stage_name'] ?: $u['name']), 'zeilen' => $zeilen];
    }
  }
  $frei = trim((string) ($settings['rider_contact_' . $art] ?? ''));
  return ['name' => '', 'zeilen' => $frei !== '' ? preg_split('~
?
~', $frei) : []];
}

/**
 * Wer ist die Technik? Geraten, nicht hinterlegt: Am Instrument steht bei
 * solchen Konten „Ton", „FOH", „Technik" oder „Mischer". Das ist ein Vorschlag
 * für die Auswahl, keine Festlegung — überschrieben wird er mit einem Klick.
 */
function rider_tech_guess(array $members): int {
  foreach ($members as $m) {
    if (preg_match('~technik|ton|foh|sound|misch|licht~i', (string) ($m['instrument'] ?? ''))) {
      return (int) $m['id'];
    }
  }
  return 0;
}

/** Beschriftung für den Anschaffungszustand; leer bleibt leer. */
function eq_acquired_label(string $k): string {
  return isset(EQ_ACQUIRED[$k]) ? t('eq_acq_' . $k) : '';
}

/**
 * Darf jemand diese Rechnung sehen?
 *
 * Nicht das Bereichsrecht allein entscheidet, sondern auch der Besitz — in
 * beide Richtungen:
 *
 * Wer ein Gerät besitzt, sieht dessen Rechnung. Es ist sein Kauf, seine
 * Anschrift, sein Geld; ihn davon auszuschließen wäre absurd.
 *
 * Umgekehrt reicht das Kassenrecht nicht für jeden Beleg. Steht auf einer
 * Rechnung auch nur ein Gerät, das jemandem persönlich gehört, dann ist es
 * eine private Rechnung mit privater Anschrift und privatem Zahlungsmittel —
 * die geht die Kassenführung nichts an. Nur bei reinem Bandeigentum ist der
 * Beleg ein Bandbeleg.
 *
 * Ein Beleg ohne jedes Gerät (gerade erfasst, noch nichts zugeordnet) ist
 * Bandsache: Er kann noch niemandem gehören.
 */
function may_see_invoice(?array $user, int $invoiceId): bool {
  if (($user['role'] ?? '') === 'admin') return true;
  $uid = (int) ($user['id'] ?? 0);
  $eigner = rows('SELECT DISTINCT owner_id FROM equipment WHERE invoice_id = ?', [$invoiceId]);
  $privat = false;
  foreach ($eigner as $e) {
    if ($e['owner_id'] === null) continue;
    if ((int) $e['owner_id'] === $uid) return true;
    $privat = true;
  }
  return !$privat && perm_allows($user, 'kasse', 'read');
}

/**
 * Der Beleg aus einem Formular, geprüft — oder null.
 *
 * Es genügt nicht, dass die Zahl eine Rechnung trifft: Wer einen Beleg nicht
 * sehen darf, darf auch kein Gerät daran hängen. Sonst wäre die Zuordnung ein
 * Weg, an fremde Privatrechnungen zu kommen, ohne sie je aufzurufen — es reicht
 * dann, ein eigenes Gerät danebenzuhängen.
 */
function eq_invoice_input(mixed $eingabe, ?array $user): ?int {
  $id = (int) ($eingabe ?? 0);
  if ($id <= 0) return null;
  if (!row('SELECT id FROM invoices WHERE id = ?', [$id])) return null;
  return may_see_invoice($user, $id) ? $id : null;
}

/**
 * Rechnungen, absteigend nach Datum — für die Auswahl am Gerät.
 *
 * Ohne Datum zuletzt: Ein Beleg, dem noch das Datum fehlt, ist unfertig und
 * gehört nicht an den Anfang der Liste.
 */
function invoice_list(?array $user = null): array {
  $alle = rows('SELECT * FROM invoices ORDER BY invoice_date IS NULL, invoice_date DESC, id DESC');
  if ($user === null) return $alle;
  return array_values(array_filter($alle, fn($inv) => may_see_invoice($user, (int) $inv['id'])));
}

/**
 * Eine Rechnung in einer Zeile: Händler, Nummern, Datum, Summe.
 *
 * Was fehlt, wird weggelassen statt als Lücke gezeigt — ein Beleg, von dem nur
 * die Auftragsnummer bekannt ist, soll trotzdem lesbar dastehen.
 */
function invoice_label(array $inv): string {
  $teile = [];
  if ($inv['supplier'] !== '') $teile[] = $inv['supplier'];
  if (($inv['invoice_no'] ?? '') !== '') $teile[] = t('inv_no_short') . ' ' . $inv['invoice_no'];
  elseif (($inv['order_no'] ?? '') !== '') $teile[] = t('inv_order_short') . ' ' . $inv['order_no'];
  if (!empty($inv['invoice_date'])) $teile[] = fmt_date($inv['invoice_date']);
  if ($inv['total_cents'] !== null) $teile[] = fmt_money((int) $inv['total_cents']);
  return $teile ? implode(' · ', $teile) : t('inv_untitled');
}

/**
 * Wie viele Geräte auf diesem Beleg stehen. Genau das ist der Grund, warum es
 * die Tabelle gibt — also soll man es auch sehen.
 */
function invoice_item_count(int $invoiceId): int {
  return (int) (row('SELECT COUNT(*) AS n FROM equipment WHERE invoice_id = ?', [$invoiceId])['n'] ?? 0);
}
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
// Eine Abschnittsmarke ([Refrain]) auf eine Kategorie abbilden, damit ein Blick
// die Stelle über die Farbe wiederfindet. Deutsch und Englisch, weil die
// Konvention beides zulässt; Unbekanntes bleibt neutral ('other').
function lyrics_category(string $label): string {
  $l = mb_strtolower(trim($label));
  $groups = [
    'chorus' => ['refrain', 'chorus', 'hook'],
    'verse'  => ['strophe', 'verse'],
    'bridge' => ['bridge', 'brücke', 'bruecke'],
    'solo'   => ['solo', 'instrumental'],
    'intro'  => ['intro', 'einleitung'],
    'outro'  => ['outro', 'ende', 'schluss', 'coda'],
  ];
  foreach ($groups as $cat => $words) {
    foreach ($words as $w) if (str_contains($l, $w)) return $cat;
  }
  return 'other';
}

// Liedtext in Zeilen zerlegen und Abschnittsmarken erkennen. Der gespeicherte
// Text bleibt unangetastet — hier wird nur fürs Anzeigen strukturiert, damit
// Leseseite und Bühnenansicht dieselbe Erkennung nutzen und nicht auseinander-
// laufen. Marke: ['part' => Beschriftung, 'cat' => Kategorie]; sonst ['text'].
function lyrics_lines(?string $text): array {
  $out = [];
  foreach (preg_split('~\R~', (string) $text) ?: [] as $line) {
    if (preg_match('~^\s*\[(.{1,40})\]\s*$~u', $line, $m)) {
      $out[] = ['part' => $m[1], 'cat' => lyrics_category($m[1])];
    } else {
      $out[] = ['text' => $line];
    }
  }
  return $out;
}

// Aus dem frei getippten Tempo-Feld (z. B. "128 BPM") die Zahl ziehen. 0, wenn
// keine dransteht — dann bleibt die Bühne bei ihrer Vorgabe.
function song_bpm(?string $tempo): int {
  return preg_match('~\d{2,3}~', (string) $tempo, $m) ? (int) $m[0] : 0;
}

// Alle nicht-leeren Notizzettel zu einem Song, mit Musikernamen; der eigene
// zuerst. 'mine' markiert den eigenen Eintrag.
function song_chords_all(int $songId, int $meId): array {
  return rows('SELECT sc.user_id, sc.content, u.name, (sc.user_id = ?) AS mine
               FROM song_chords sc JOIN users u ON u.id = sc.user_id
               WHERE sc.song_id = ? AND TRIM(sc.content) <> ?
               ORDER BY mine DESC, u.name', [$meId, $songId, '']);
}
function song_chords_mine(int $songId, int $meId): string {
  $r = row('SELECT content FROM song_chords WHERE song_id = ? AND user_id = ?', [$songId, $meId]);
  return $r['content'] ?? '';
}
// Den eigenen Notizzettel setzen — leer heißt löschen, damit kein leerer
// Eintrag im Musiker-Dropdown auftaucht.
function song_chords_set(int $songId, int $meId, string $content): void {
  if (trim($content) === '') {
    q('DELETE FROM song_chords WHERE song_id = ? AND user_id = ?', [$songId, $meId]);
  } else {
    q('INSERT INTO song_chords (song_id, user_id, content) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE content = VALUES(content)', [$songId, $meId, $content]);
  }
}

// Das Navigationsziel als Text: mit gespeicherten Koordinaten punktgenau
// ("lat,lng"), sonst Name/Adresse/Stadt in einer Zeile (Zeilenumbrüche → Komma).
// Leer, wenn nichts bekannt ist — dann zeigt die Ansicht keinen Knopf.
function navi_dest(string ...$parts): string {
  $clean = array_filter(array_map(
    fn(string $p): string => trim(str_replace(["\r\n", "\n"], ', ', $p)), $parts));
  return implode(', ', $clean);
}
function venue_dest(array $v): string {
  if (!empty($v['lat']) && !empty($v['lng'])) return $v['lat'] . ',' . $v['lng'];
  return navi_dest($v['name'] ?? '', $v['address'] ?? '', $v['city'] ?? '');
}

// Web-Fallback für den Navi-Link (Desktop und ohne JavaScript): OpenStreetMap
// zeigt den Ort — bewusst nicht Google. Auf dem Handy ersetzt route.js den Link
// durch die native Karten-App: iPhone → Apple Karten, Android → die als Standard
// eingestellte App (geo:). Die Anwendung selbst ruft dabei nichts ab.
function navi_web(string $dest): string {
  return $dest === '' ? '' : 'https://www.openstreetmap.org/search?query=' . rawurlencode($dest);
}

// Adresse → Treffer mit Koordinaten, über OpenStreetMap/Nominatim. Eine Anfrage
// je Aufruf, mit User-Agent, wie es die Nominatim-Richtlinie verlangt (ohne den
// antwortet der Dienst mit 403). Fehler (Dienst weg, Timeout) ergeben eine leere
// Liste — die Oberfläche meldet dann „keine Treffer". Aufgerufen nur, wenn die
// Band Geocoding erlaubt hat.
function geocode_request(string $q): array {
  $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=3&q=' . rawurlencode($q);
  $ctx = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Bandregie/' . BANDREGIE_VERSION . " (self-hosted band tool)
Accept: application/json
",
    'timeout' => 8,
  ]]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return [];
  $data = json_decode($raw, true);
  if (!is_array($data)) return [];
  $out = [];
  foreach ($data as $r) {
    if (!isset($r['lat'], $r['lon'], $r['display_name'])) continue;
    // Aus den Bestandteilen eine saubere Adresse bauen (Straße Hausnummer /
    // PLZ Ort), damit ein Treffer auch die Adress- und Stadt-Felder füllen kann,
    // wenn nur der Name eingegeben war. Ohne Bestandteile bleibt der Anzeigename.
    $a = is_array($r['address'] ?? null) ? $r['address'] : [];
    $city = (string) ($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? '');
    $street = trim(($a['road'] ?? '') . ' ' . ($a['house_number'] ?? ''));
    $addr = trim($street . "
" . trim(($a['postcode'] ?? '') . ' ' . $city));
    $out[] = [
      'name' => (string) $r['display_name'],
      'address' => $addr !== '' ? $addr : (string) $r['display_name'],
      'city' => $city,
      'lat' => (string) $r['lat'],
      'lng' => (string) $r['lon'],
      'searched' => $q,
    ];
  }
  return $out;
}

/**
 * Wie viele Nachfragen mit weniger Wörtern höchstens gestellt werden.
 * Nominatim erlaubt etwa eine Anfrage je Sekunde; zwei Nachfragen mit Abstand
 * bleiben in der Richtlinie und halten den Klick unter drei Sekunden.
 */
const GEO_RETRIES = 2;

/**
 * Suche mit Rückzug (#234).
 *
 * Nominatim ist ein Adressverzeichnis, keine Namenssuche: Der Freitext muss in
 * JEDEM Wort treffen, ein einziges unbekanntes lässt das ganze Ergebnis leer
 * ausgehen. Gemessen: „Treysa" findet den Ort, „Rockschuppen Treysa" findet
 * nichts — und Saalnamen stehen selten in OpenStreetMap.
 *
 * Bleibt die Suche leer, fällt deshalb das erste Wort weg und es wird erneut
 * gefragt: vorn steht der Name, hinten der Ort. Ein Treffer auf Ortsebene ist
 * zum Hinfahren genug und allemal besser als nichts.
 *
 * Am Treffer steht, wonach wirklich gesucht wurde — sonst hält jemand den Ort
 * für den Saal.
 */
function geocode_search(string $q): array {
  $q = trim(preg_replace('~\s+~u', ' ', $q) ?? '');
  if ($q === '') return [];
  $treffer = geocode_request($q);
  $worte = preg_split('~[\s,]+~u', $q) ?: [];
  for ($i = 0; !$treffer && $i < GEO_RETRIES && count($worte) > 1; $i++) {
    array_shift($worte);
    // Abstand halten, statt drei Anfragen in einem Wimpernschlag zu stellen.
    usleep(1100000);
    $treffer = geocode_request(implode(' ', $worte));
  }
  return $treffer;
}

// EXIF eines Bildes: Aufnahmedatum und GPS, falls vorhanden. Ohne (kein JPEG,
// keine Daten) kommt Leeres zurück — dann bleibt das Foto unzugeordnet.
function photo_exif(string $path): array {
  $out = ['taken_at' => null, 'lat' => null, 'lng' => null];
  if (!function_exists('exif_read_data')) return $out;
  $ex = @exif_read_data($path);
  if (!is_array($ex)) return $out;
  $dt = (string) ($ex['DateTimeOriginal'] ?? $ex['DateTime'] ?? '');
  if (preg_match('~^(\d{4}):(\d{2}):(\d{2}) (\d{2}:\d{2}:\d{2})~', $dt, $m)) {
    $out['taken_at'] = "$m[1]-$m[2]-$m[3] $m[4]";
  }
  if (isset($ex['GPSLatitude'], $ex['GPSLongitude'], $ex['GPSLatitudeRef'], $ex['GPSLongitudeRef'])) {
    $out['lat'] = gps_decimal($ex['GPSLatitude'], (string) $ex['GPSLatitudeRef']);
    $out['lng'] = gps_decimal($ex['GPSLongitude'], (string) $ex['GPSLongitudeRef']);
  }
  return $out;
}
// EXIF-GPS (Grad/Minute/Sekunde als Brüche + N/S/E/W) in Dezimalgrad.
function gps_decimal($coord, string $ref): ?float {
  if (!is_array($coord) || count($coord) < 3) return null;
  $frac = function ($v): float {
    if (is_string($v) && str_contains($v, '/')) {
      [$n, $d] = array_pad(explode('/', $v), 2, '1');
      return (float) $d === 0.0 ? 0.0 : (float) $n / (float) $d;
    }
    return (float) $v;
  };
  $dec = $frac($coord[0]) + $frac($coord[1]) / 60 + $frac($coord[2]) / 3600;
  return in_array(strtoupper($ref), ['S', 'W'], true) ? -$dec : $dec;
}
// Grobe Entfernung zweier Punkte in Kilometern (Haversine) — reicht, um bei
// mehreren Events am selben Tag den nächstgelegenen Ort zu bestimmen.
function geo_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
  $dLat = deg2rad($lat2 - $lat1);
  $dLng = deg2rad($lng2 - $lng1);
  $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
  return 6371 * 2 * asin(min(1.0, sqrt($a)));
}
// Der vorgeschlagene Termin für ein Foto: der am Aufnahmetag. Gibt es mehrere am
// selben Tag und hat das Foto GPS, gewinnt der mit dem nächstgelegenen Ort.
// Immer nur ein Vorschlag — zugeordnet wird erst auf Klick.
function photo_suggest_event(array $photo, array $events): ?array {
  $date = substr((string) ($photo['taken_at'] ?? ''), 0, 10);
  if ($date === '') return null;
  $sameDay = array_values(array_filter($events, fn($e) => substr((string) $e['date'], 0, 10) === $date));
  if (!$sameDay) return null;
  if (count($sameDay) === 1) return $sameDay[0];
  if ($photo['lat'] !== null && $photo['lng'] !== null) {
    $best = null; $bestDist = INF;
    foreach ($sameDay as $e) {
      if ($e['lat'] === null || $e['lng'] === null) continue;
      $d = geo_distance_km((float) $photo['lat'], (float) $photo['lng'], (float) $e['lat'], (float) $e['lng']);
      if ($d < $bestDist) { $bestDist = $d; $best = $e; }
    }
    if ($best) return $best;
  }
  return $sameDay[0]; // sonst der erste am Tag — bleibt ein Vorschlag
}

/**
 * Die Terminliste für ein Bild, der naheliegendste zuerst (#207).
 *
 * Die Anwendung weiß, welcher Termin zum Aufnahmedatum passt — dann soll er
 * auch oben stehen und nicht vorgewählt in der Mitte einer langen Liste. Bei
 * gleichem Abstand gewinnt der jüngere Termin; ohne Aufnahmedatum bleibt die
 * Liste, wie sie ist, denn dann gibt es nichts, dem etwas nahe sein könnte.
 */
function events_by_closeness(array $events, ?string $takenAt): array {
  $tag = substr((string) $takenAt, 0, 10);
  if ($tag === '') return $events;
  $anker = strtotime($tag);
  if ($anker === false) return $events;
  usort($events, function ($a, $b) use ($anker) {
    $da = abs(strtotime(substr((string) $a['date'], 0, 10)) - $anker);
    $db = abs(strtotime(substr((string) $b['date'], 0, 10)) - $anker);
    return $da <=> $db ?: strcmp((string) $b['date'], (string) $a['date']);
  });
  return $events;
}


/**
 * Ein Schlagwort in seine gespeicherte Form bringen (#201). Getrimmt und auf
 * eine Länge begrenzt; Groß und Klein bleiben, wie eingegeben — „Bühne" soll
 * „Bühne" heißen. Verglichen wird über die Datenbank-Kollation, die Groß und
 * Klein gleichsetzt, sodass „bühne" kein zweites Wort wird.
 */
function tag_norm(string $tag): string {
  return mb_substr(trim(preg_replace('~\s+~u', ' ', $tag) ?? ''), 0, 60);
}

/**
 * Alle vergebenen Schlagwörter mit ihrer Zahl, fürs Filtern und für die
 * Vorschlagsliste. Dazu eine kleine Grundmenge, solange sie unbenutzt ist —
 * damit die ersten Wörter nicht vierzig private Erfindungen werden. Wird ein
 * Wort nirgends mehr benutzt, verschwindet es von selbst: Es gibt keinen Stamm,
 * in dem es weiterlebte.
 *
 * @return list<array{tag: string, count: int}>
 */
function photo_tags_all(): array {
  $vergeben = rows('SELECT tag, COUNT(*) AS count FROM photo_tags GROUP BY tag ORDER BY tag');
  $da = array_map('mb_strtolower', array_column($vergeben, 'tag'));
  foreach (explode(',', t('photo_tag_suggest')) as $vorschlag) {
    $vorschlag = tag_norm($vorschlag);
    if ($vorschlag !== '' && !in_array(mb_strtolower($vorschlag), $da, true)) {
      $vergeben[] = ['tag' => $vorschlag, 'count' => 0];
    }
  }
  return $vergeben;
}

/**
 * Die Galerie als Baum: Jahr → Termin → Fotograf (#216).
 *
 * Seit #196 stand jeder Termin als eigene Überschrift untereinander. Bei 517
 * Bildern eines Auftritts ist das ein Streifen aus 115 Kacheln — richtig
 * gruppiert und trotzdem unbrauchbar. Die Form, in der Menschen denken, liegt
 * längst bei OneDrive und kommt über den Herkunftspfad mit: „Bilder/2026/AKF/
 * Sven Löffler". Also zeigt die Galerie diese Form.
 *
 * Die dritte Ebene entsteht nur, wenn ein Termin wirklich mehrere
 * Herkunftsordner hat. Ein Auftritt mit einem Fotografen bekommt keine
 * Zwischenebene, die nichts trennt.
 *
 * Rein rechnend, damit prüfbar: Die Einteilung ist die eigentliche Entscheidung.
 *
 * @param  list<array> $fotos Zeilen mit event_id, event_title, event_date, source
 * @return list<array{key: string, label: string, total: int, events: list<array{
 *         key: string, label: string, date: ?string, total: int,
 *         groups: list<array{key: string, label: string, photos: list<array>}>}>}>
 */
function photo_tree(array $fotos): array {
  $jahre = [];
  foreach ($fotos as $f) {
    $datum = (string) ($f['event_date'] ?? '');
    // Ohne Termin ein eigener Zweig, und der bleibt oben: Das ist der Stapel,
    // an dem gearbeitet wird.
    $jahr = $f['event_id'] && $datum !== '' ? substr($datum, 0, 4) : '';
    $terminSchl = $f['event_id'] ? (string) (int) $f['event_id'] : '';
    // Der Fotograf ist der letzte Ordner im Herkunftspfad — nicht der Dateiname.
    $quelle = trim((string) ($f['source'] ?? ''), '/');
    $schnitt = strrpos($quelle, '/');
    $ordner = $schnitt === false ? '' : substr($quelle, 0, $schnitt);
    $wer = $ordner === '' ? '' : (string) array_slice(explode('/', $ordner), -1)[0];

    $jahre[$jahr]['label'] = $jahr === '' ? t('photo_folder_none') : $jahr;
    $jahre[$jahr]['events'][$terminSchl]['label'] = $terminSchl === ''
      ? t('photo_folder_none')
      : trim(($datum !== '' ? date('d.m.', (int) strtotime($datum)) . ' ' : '') . (string) ($f['event_title'] ?? ''));
    $jahre[$jahr]['events'][$terminSchl]['date'] = $datum !== '' ? $datum : null;
    $jahre[$jahr]['events'][$terminSchl]['groups'][$wer][] = $f;
  }

  // Neueste zuerst; der Zweig ohne Termin bleibt vorn, weil er kein Jahr hat.
  // Die Umwandlung ist nötig, nicht Zierde: PHP macht aus dem Schlüssel „2026"
  // die Zahl 2026, und strcmp() nimmt seit PHP 8 keine Zahl mehr an.
  uksort($jahre, function ($a, $b): int {
    if ((string) $a === '') return -1;
    if ((string) $b === '') return 1;
    return strcmp((string) $b, (string) $a);
  });
  $raus = [];
  foreach ($jahre as $jahrSchl => $jahr) {
    uasort($jahr['events'], fn($a, $b) => ($b['date'] ?? '9999') <=> ($a['date'] ?? '9999'));
    $termine = [];
    $jahrZahl = 0;
    foreach ($jahr['events'] as $evSchl => $ev) {
      // Als Text sortieren: Ein Fotografen-Ordner, der „2026" heißt, wäre sonst
      // eine Zahl und landete vor allen Namen.
      ksort($ev['groups'], SORT_STRING);
      $gruppen = [];
      $evZahl = 0;
      foreach ($ev['groups'] as $werSchl => $bilder) {
        $evZahl += count($bilder);
        $gruppen[] = ['key' => (string) $werSchl,
                      'label' => $werSchl === '' ? t('photo_source_none') : (string) $werSchl,
                      'photos' => $bilder];
      }
      $jahrZahl += $evZahl;
      $termine[] = ['key' => (string) $evSchl, 'label' => (string) $ev['label'],
                    'date' => $ev['date'], 'total' => $evZahl, 'groups' => $gruppen];
    }
    $raus[] = ['key' => (string) $jahrSchl, 'label' => (string) $jahr['label'],
               'total' => $jahrZahl, 'events' => $termine];
  }
  return $raus;
}

/**
 * Die Herkunftsordner der Galerie, auf jeder Ebene, mit Zahl und Datum (#208).
 *
 * Aus „Bilder/2026/AKF/Sven Löffler/094A1704.jpg" werden vier wählbare Ordner:
 * Bilder, …/2026, …/AKF und …/AKF/Sven Löffler — wer den Termin zuordnet, will
 * mal den ganzen Auftritt und mal nur einen Fotografen fassen. Auch die oberste
 * Ebene steht dabei: Bei „AKF/Sven/…" IST sie der Auftritt, und eine Regel, die
 * das erraten wollte, hat sich schon einmal geirrt.
 *
 * Das Datum je Ordner ist der häufigste Aufnahmetag darunter — er trägt den
 * Terminvorschlag, wie die Nähe-Ordnung in #207. Rein rechnend, damit prüfbar.
 *
 * @param  list<array{source: ?string, taken_at: ?string}> $fotos
 * @return list<array{path: string, count: int, date: string}> nach Pfad sortiert
 */
function photo_folder_agg(array $fotos): array {
  $zaehl = [];
  $tage = [];
  foreach ($fotos as $f) {
    $quelle = trim((string) ($f['source'] ?? ''), '/');
    $schnitt = strrpos($quelle, '/');
    if ($schnitt === false) continue; // nur ein Dateiname — kein Ordner
    $ordner = substr($quelle, 0, $schnitt);
    $tag = substr((string) ($f['taken_at'] ?? ''), 0, 10);
    // Jede Ebene zählt mit, denn jede ist wählbar.
    $pfad = '';
    foreach (explode('/', $ordner) as $teil) {
      $pfad = $pfad === '' ? $teil : $pfad . '/' . $teil;
      $zaehl[$pfad] = ($zaehl[$pfad] ?? 0) + 1;
      if ($tag !== '') $tage[$pfad][$tag] = ($tage[$pfad][$tag] ?? 0) + 1;
    }
  }
  ksort($zaehl);
  $raus = [];
  foreach ($zaehl as $pfad => $n) {
    $beste = '';
    $besteZahl = 0;
    foreach ($tage[$pfad] ?? [] as $tag => $wie) {
      // Bei Gleichstand der jüngere Tag — wie überall in der Nähe-Ordnung.
      if ($wie > $besteZahl || ($wie === $besteZahl && $tag > $beste)) {
        $beste = $tag;
        $besteZahl = $wie;
      }
    }
    $raus[] = ['path' => $pfad, 'count' => $n, 'date' => $beste];
  }
  return $raus;
}

/**
 * Prüfsummen nachtragen (#199). Nicht beim Hochfahren und nicht in einem
 * beliebigen Aufruf: Ein Bestand von fünfhundert großen Bildern zu lesen dauert,
 * und diese Wartezeit hätte dann zufällig jemand, der etwas ganz anderes wollte.
 * Deshalb in Schritten und nur dort, wo jemand die Doppelten sehen will.
 *
 * Bilder, deren Datei fehlt, bleiben ohne Prüfsumme — die stehen im Aufräumen
 * schon als eigene Art. Ohne diese Ausnahme blieben sie für immer offen.
 *
 * @return array{done: int, left: int}
 */
function checksums_fill(int $hoechstens = 200): array {
  $offen = rows("SELECT id, filename, od_item_id FROM photos WHERE checksum = '' ORDER BY id");
  $getan = 0;
  $fehlend = 0;
  foreach ($offen as $p) {
    // Verknüpfte Bilder (#206): Lokal liegt nur die Vorschau, und deren Summe
    // wäre die falsche Aussage. Die Summe des Originals kennt Graph — sie steht
    // an der Verknüpfung und macht ein hochgeladenes Duplikat des Originals
    // erkennbar. Nur geschäftliche Laufwerke geben keine sha256 heraus; dann
    // bleibt die Vorschau-Summe, die wenigstens doppelte Übernahmen erkennt.
    $summe = ($p['od_item_id'] ?? '') !== ''
      ? (string) (row('SELECT sha256 FROM od_items WHERE item_id = ?', [$p['od_item_id']])['sha256'] ?? '')
      : '';
    if ($summe === '') {
      $pfad = UPLOADS_DIR . '/' . $p['filename'];
      if (!is_file($pfad)) { $fehlend++; continue; }
      if ($getan >= $hoechstens) break;
      $summe = hash_file('sha256', $pfad);
      if ($summe === false) { $fehlend++; continue; }
    } elseif ($getan >= $hoechstens) {
      break;
    }
    q('UPDATE photos SET checksum = ? WHERE id = ?', [$summe, (int) $p['id']]);
    $getan++;
  }
  return ['done' => $getan, 'left' => max(0, count($offen) - $fehlend - $getan)];
}

/**
 * Bilder, die inhaltlich gleich sind, nach Prüfsumme gruppiert.
 *
 * @return list<array{checksum: string, photos: list<array>}> je Gruppe das
 *         älteste Bild zuerst — das ist der naheliegende Kandidat zum Behalten,
 *         weil an ihm die längere Geschichte hängt.
 */
function photo_duplicates(): array {
  $summen = array_column(rows("SELECT checksum FROM photos WHERE checksum <> ''
                               GROUP BY checksum HAVING COUNT(*) > 1"), 'checksum');
  $gruppen = [];
  foreach ($summen as $s) {
    $gruppen[] = ['checksum' => $s, 'photos' => rows(
      'SELECT p.*, u.name AS uploader, e.title AS event_title FROM photos p
       LEFT JOIN users u ON u.id = p.uploaded_by
       LEFT JOIN events e ON e.id = p.event_id
       WHERE p.checksum = ? ORDER BY p.id', [$s])];
  }
  return $gruppen;
}

/**
 * Das persönliche Kalender-Zeichen eines Mitglieds (#222); wird beim ersten
 * Hinsehen vergeben. Beim Wechseln entsteht ein neues — der alte Link ist dann
 * tot, und genau dafür gibt es ihn: ein Handy weg, ein Link zu viel geteilt.
 */
function ical_token_for(int $userId, bool $neu = false): string {
  $u = row('SELECT ical_token FROM users WHERE id = ?', [$userId]);
  if (!$u) return '';
  $zeichen = (string) $u['ical_token'];
  if ($zeichen === '' || $neu) {
    $zeichen = bin2hex(random_bytes(16));
    q('UPDATE users SET ical_token = ? WHERE id = ?', [$zeichen, $userId]);
  }
  return $zeichen;
}

/**
 * Ein Wert, der in eine Mail-Kopfzeile darf (#220).
 *
 * Zeilenumbrüche entfernen ist hier keine Kosmetik: Ein CR oder LF in einem
 * Betreff oder in Reply-To beendet die Kopfzeile, und alles danach ist eine
 * eigene — auch ein „Bcc:". Der Bandname und die Kontaktadresse kommen aus den
 * Einstellungen, und in der Demo ist jeder Besucher Admin; damit konnte jeder
 * über die öffentliche Passwort-Vergessen-Seite Mail an beliebige Empfänger
 * auslösen, verschickt vom Server dieses Projekts.
 *
 * Auch der senkrechte Tabulator und das Nullzeichen fliegen: manche
 * Mail-Programme behandeln sie als Umbruch.
 */
function mail_from_address(): string {
  // Aus site_url und nicht aus dem Host der Anfrage: Der Host kommt vom
  // Aufrufer. Mit einem gefälschten Host-Kopf trug jede Mail dieser
  // Installation eine fremde Absenderdomain — dieselbe Überlegung wie bei
  // od_redirect_uri(). Fällt site_url aus, bleibt der Host als Notnagel.
  $host = (string) parse_url(setting('site_url'), PHP_URL_HOST);
  if ($host === '') $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
  $host = preg_replace('~^www\.~', '', $host) ?? $host;
  // Nur, was in einem Hostnamen vorkommt — der Rest hätte in einer Kopfzeile
  // nichts zu suchen.
  $host = preg_replace('~[^A-Za-z0-9.\-]~', '', $host) ?: 'localhost';
  return 'no-reply@' . $host;
}

function mail_header_value(string $wert, int $max = 200): string {
  $sauber = preg_replace('~[\r\n\x00\x0B\x0C]+~', ' ', $wert) ?? '';
  return mb_substr(trim($sauber), 0, $max);
}

/**
 * Ein Bild archivieren oder zurückholen (#200).
 *
 * Aus jeder Galerie genommen, aber nicht zerstört: Datei und Zeile bleiben,
 * und ein Klick holt das Bild zurück.
 */
function photo_archive(int $id, bool $hinein): bool {
  $p = row('SELECT id, archived_at FROM photos WHERE id = ?', [$id]);
  if (!$p) return false;
  if ($hinein === ($p['archived_at'] !== null)) return true; // schon so
  q('UPDATE photos SET archived_at = ? WHERE id = ?',
    [$hinein ? date('Y-m-d H:i:s') : null, $id]);
  return true;
}

/**
 * Ein Bild samt Datei entfernen.
 *
 * Die Datei nur löschen, wenn sie niemand sonst nennt: Zwei Zeilen auf denselben
 * Dateinamen entstehen beim Hochladen nicht, aber wer das später einführt, soll
 * hier keine Bilder verlieren.
 */
function photo_remove(int $id): bool {
  $p = row('SELECT id, filename FROM photos WHERE id = ?', [$id]);
  if (!$p) return false;
  q('DELETE FROM photos WHERE id = ?', [$id]);
  q('DELETE FROM photo_tags WHERE photo_id = ?', [$id]);
  q('DELETE FROM photo_people WHERE photo_id = ?', [$id]);
  if (!row('SELECT 1 FROM photos WHERE filename = ?', [$p['filename']])) {
    @unlink(UPLOADS_DIR . '/' . $p['filename']);
  }
  return true;
}

/**
 * Besteht die Antwort nur aus Kopfzeilen?
 *
 * HEAD wird wie GET behandelt (siehe index.php), damit Routen überhaupt
 * greifen. Wo ein Rumpf teuer ist — eine Datei lesen, eine versiegelte
 * entschlüsseln —, steigt der Aufrufer hiermit vorher aus. Der Webserver würfe
 * den Rumpf ohnehin weg; ihn zu erzeugen wäre Arbeit für den Papierkorb.
 */
function head_only(): bool {
  return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
}

function asset(string $path): string {
  return $path . '?v=' . rawurlencode(BANDREGIE_VERSION);
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

  $photo = row('SELECT is_public, archived_at FROM photos WHERE filename = ?', [$name]);
  // Archiviert zählt nicht mehr als öffentlich (#200): Die Adresse eines Bildes,
  // das jemand aus der Galerie genommen hat, soll nicht weiter für alle gelten.
  if ((int) ($photo['is_public'] ?? 0) === 1 && ($photo['archived_at'] ?? null) === null) return true;
  if (!$user) return false;
  return !$photo || perm_allows($user, 'fotos');
}

/**
 * Verkleinerte Fassung eines Bildes, beim ersten Abruf erzeugt und danach
 * wiederverwendet. Die Galerie zeigt Kacheln von 160 bis 230 Pixeln, lud
 * bisher aber die Originale — bei hundert Fotos ein Vielfaches der nötigen
 * Datenmenge. Fehlt die Bildbibliothek, gibt es eben das Original.
 */
/**
 * Entfernt die Aufnahmedaten aus einer Bilddatei, indem sie neu geschrieben
 * wird — dabei bleibt keine EXIF-Zeile übrig.
 *
 * Das ist kein Selbstzweck: Ein Proberaum ist oft eine Privatwohnung, und die
 * Koordinaten daraus gingen mit jedem öffentlichen Foto mit hinaus, dazu
 * Kameraseriennummer und Besitzername. Für die Zuordnung zu einem Termin
 * brauchen wir sie nicht in der Datei — sie stehen längst in der Datenbank.
 *
 * Fehlt die Bildbibliothek, bleibt die Datei wie sie ist; dann melden wir das
 * ehrlich zurück, statt Sicherheit vorzutäuschen.
 */
function photo_strip_exif(string $path): bool {
  if (!is_file($path) || !function_exists('imagecreatetruecolor')) return false;
  $info = @getimagesize($path);
  if (!$info) return false;
  // Nur JPEG trägt die Aufnahmedaten, um die es geht. PNG und WebP neu zu
  // schreiben brächte nichts und kostete nur: bei PNG ginge dabei sogar die
  // Transparenz verloren — ein Bandlogo stünde danach auf schwarzem Grund.
  if ($info['mime'] !== 'image/jpeg') return true;
  // GD hält das Bild unkomprimiert im Speicher (Breite × Höhe × 4 Byte). Ein
  // Foto aus einer heutigen Handykamera sprengt damit ein knappes Limit, und
  // der Upload bräche mitten ab — Datei ohne Datenbankzeile. Lieber gar nicht
  // anfassen und das ehrlich melden.
  $brauchen = (int) ($info[0] * $info[1] * 4 * 1.8);
  $frei = memory_limit_bytes() - memory_get_usage(true);
  if ($frei > 0 && $brauchen > $frei) return false;
  $img = @imagecreatefromjpeg($path);
  if (!$img) return false;
  // Die Drehung steckt bei Handyfotos NUR in den EXIF-Daten. Entfernte man sie
  // ersatzlos, läge jedes Hochformat-Foto danach quer — deshalb erst in die
  // Pixel schreiben, dann verwerfen.
  $orient = (int) (@exif_read_data($path)['Orientation'] ?? 1);
  $gedreht = match ($orient) {
    3 => @imagerotate($img, 180, 0),
    6 => @imagerotate($img, -90, 0),
    8 => @imagerotate($img, 90, 0),
    default => null,
  };
  if ($gedreht) { imagedestroy($img); $img = $gedreht; }
  // In eine Nachbardatei schreiben und erst dann ersetzen: bricht es ab, bleibt
  // das Original stehen, statt halb geschrieben zu sein.
  $tmp = $path . '.strip';
  $ok = imagejpeg($img, $tmp, 92);
  imagedestroy($img);
  if (!$ok || !is_file($tmp)) { @unlink($tmp); return false; }
  return @rename($tmp, $path);
}

/** Das Speicherlimit in Bytes; 0 heißt „kein Limit". */
function memory_limit_bytes(): int {
  $roh = trim((string) ini_get('memory_limit'));
  if ($roh === '' || $roh === '-1') return 0;
  $zahl = (int) $roh;
  return match (strtolower(substr($roh, -1))) {
    'g' => $zahl * 1024 * 1024 * 1024,
    'm' => $zahl * 1024 * 1024,
    'k' => $zahl * 1024,
    default => $zahl,
  };
}

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
 * Symbol für den Startbildschirm — für iOS als apple-touch-icon, für Android
 * über das Manifest. Auf dem Handy ist das das einzige Zeichen, das jemand
 * von der Band zu sehen bekommt, und es wird groß gezeichnet: 192 bis 512
 * Pixel, nicht die 16, mit denen ein Browsertab auskommt.
 *
 * Ein quadratisches Logo in voller Größe wird unverändert durchgereicht.
 * Sonst wird eines gezeichnet — aus dem größeren der beiden Bilder, die die
 * Band hochgeladen hat.
 */
function app_icon(int $size): string {
  $logo = setting('logo_file');
  if ($logo !== '' && is_file(UPLOADS_DIR . '/' . $logo)) {
    $info = @getimagesize(UPLOADS_DIR . '/' . $logo);
    if ($info && $info[0] === $info[1] && $info[0] >= $size) return '/uploads/' . rawurlencode($logo);
  }
  return app_icon_drawn($size) ?? "/assets/app/icon-$size.png";
}

/**
 * Die Vorlage für das App-Symbol: das Favicon, wenn es groß genug ist, sonst
 * das Logo, sonst das Favicon in welcher Größe auch immer.
 *
 * Das Favicon hat den Vorrang, weil es das Zeichen ist, das die Band für die
 * kleine Fläche gewählt hat. Ist es aber winzig und liegt ein ordentliches
 * Logo daneben, ist das Logo die bessere Vorlage — lieber ein breites Logo
 * mittig auf der Kachel als ein hochgerechneter Fleck.
 */
function app_icon_source(): ?string {
  $mass = function (string $name): ?array {
    if ($name === '') return null;
    $path = UPLOADS_DIR . '/' . $name;
    if (!is_file($path)) return null;
    $info = @getimagesize($path);
    return $info ? ['path' => $path, 'min' => min($info[0], $info[1]), 'mime' => $info['mime']] : null;
  };
  $favicon = $mass(setting('favicon_file'));
  $logo = $mass(setting('logo_file'));
  if ($favicon && $favicon['min'] >= 192) return $favicon['path'];
  if ($logo && $logo['min'] >= 192) return $logo['path'];
  return $favicon['path'] ?? $logo['path'] ?? null;
}

/**
 * Das App-Symbol zeichnen: die Vorlage füllt die Kachel bis auf einen Rand,
 * der Hintergrund richtet sich nach der Vorlage.
 *
 * Der Hintergrund ist der Punkt, an dem der erste Versuch danebenlag: ein
 * schwarzer Totenkopf mit durchsichtigem Grund auf der dunklen Hausfarbe war
 * ein dunkles Quadrat. Gerechnet wird deshalb die Helligkeit dessen, was
 * wirklich gezeichnet ist — dunkle Zeichnung, heller Grund und umgekehrt.
 *
 * @return string|null öffentlicher Pfad oder null, wenn es nicht geht
 */
function app_icon_drawn(int $size): ?string {
  if (!function_exists('imagecreatetruecolor')) return null;
  $source = app_icon_source();
  if ($source === null) return null;

  $dir = DATA_DIR . '/appicons';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $name = 'icon-' . $size . '-' . substr(sha1($source . filemtime($source)), 0, 12) . '.png';
  $target = $dir . '/' . $name;
  if (is_file($target)) return '/appicon/' . $name;

  $info = @getimagesize($source);
  $img = $info ? match ($info['mime']) {
    'image/png'  => @imagecreatefrompng($source),
    'image/jpeg' => @imagecreatefromjpeg($source),
    'image/gif'  => @imagecreatefromgif($source),
    'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
    default      => false,
  } : false;
  if (!$img) return null;

  $canvas = imagecreatetruecolor($size, $size);
  $hell = app_icon_is_dark($img);
  imagefill($canvas, 0, 0, $hell
    ? imagecolorallocate($canvas, 0xF5, 0xF2, 0xEE)   // helle Fläche für eine dunkle Zeichnung
    : imagecolorallocate($canvas, 0x17, 0x12, 0x0F)); // sonst die Hausfarbe
  imagealphablending($canvas, true);

  // Bis auf einen Rand füllen: ein Symbol soll die Kachel ausnutzen
  $rand = (int) round($size * 0.12);
  $platz = $size - 2 * $rand;
  $scale = min($platz / max(1, imagesx($img)), $platz / max(1, imagesy($img)));
  $w = max(1, (int) round(imagesx($img) * $scale));
  $h = max(1, (int) round(imagesy($img) * $scale));
  imagecopyresampled($canvas, $img, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0,
                     $w, $h, imagesx($img), imagesy($img));
  imagepng($canvas, $target);
  imagedestroy($canvas);
  imagedestroy($img);

  // Ältere Fassungen desselben Maßes wegräumen
  foreach (glob($dir . '/icon-' . $size . '-*.png') ?: [] as $alt) {
    if ($alt !== $target) @unlink($alt);
  }
  return is_file($target) ? '/appicon/' . $name : null;
}

/**
 * Ist die Zeichnung dunkel? Gemessen wird nur, was auch zu sehen ist:
 * durchsichtige Stellen bleiben außen vor, sonst entschiede der leere Rand
 * über die Farbe des Hintergrunds.
 */
function app_icon_is_dark($img): bool {
  $summe = 0.0; $zahl = 0;
  $breite = imagesx($img); $hoehe = imagesy($img);
  $schritt = max(1, (int) (max($breite, $hoehe) / 64));   // ein Raster genügt
  for ($x = 0; $x < $breite; $x += $schritt) {
    for ($y = 0; $y < $hoehe; $y += $schritt) {
      $farbe = imagecolorat($img, $x, $y);
      if ((($farbe >> 24) & 0x7F) > 64) continue;         // zu durchsichtig
      $summe += 0.299 * (($farbe >> 16) & 0xFF) + 0.587 * (($farbe >> 8) & 0xFF) + 0.114 * ($farbe & 0xFF);
      $zahl++;
    }
  }
  return $zahl > 0 && $summe / $zahl < 128;
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
 * Für wie viele Stück steht diese Zeile? Zuerst zählt die Spalte `quantity` —
 * eine gepflegte Angabe schlägt jede Textsuche. Erst wenn dort 1 steht, wird im
 * Text nachgesehen: Beim Import aus einer Liste landet die Menge oft im Namen
 * („4×", „(2×)"), und dann steht eine Zeile für vier Kabel. Der Fund ist nur
 * ein Vorschlag für das Formular — aufgeteilt wird erst, wenn jemand es
 * bestätigt. „4x4 Case" ist keine Stückzahl.
 */
function eq_quantity_hint(array $eq): ?int {
  $gepflegt = (int) ($eq['quantity'] ?? 1);
  if ($gepflegt > 1 && $gepflegt <= 99) return $gepflegt;
  foreach ([(string) ($eq['slot'] ?? ''), (string) ($eq['name'] ?? '')] as $text) {
    if (preg_match(EQ_QUANTITY_RE, trim($text), $m)) {
      $n = (int) $m[1];
      if ($n > 1 && $n <= 99) return $n;
    }
  }
  return null;
}

/**
 * Für wie viele Geräte steht diese Zeile? Eins heißt: es gibt nichts
 * aufzuteilen (#238).
 */
function eq_split_count(array $eq): int {
  return max(1, (int) (eq_quantity_hint($eq) ?? 1));
}

/**
 * Eine angehängte Nummer entfernen — „Drums #2" wird zu „Drums".
 *
 * Ohne das wächst der Name bei jedem Aufteilen um ein weiteres „ #1": aus
 * „Drums #1" wurde „Drums #1 #1", und auf der öffentlichen Demo standen nach
 * einer Stunde achtzehn davon in einer Zeile (#238).
 */
function eq_strip_number(string $text): string {
  return trim(preg_replace('~(?:\s*#\d{1,3})+$~u', '', trim($text)) ?? $text);
}

/** Die Stückzahl aus einem Text entfernen — sie steht danach in eigenen Zeilen. */
function eq_strip_quantity(string $text): string {
  return trim(preg_replace(EQ_QUANTITY_RE, '', trim($text)) ?? $text);
}

/**
 * Die eingegebene Menge auf etwas Sinnvolles bringen. Mindestens 1, denn eine
 * Zeile über null Stück ist keine; die Obergrenze hält Zahlendreher aus dem
 * Bestand heraus. Leer heißt 1 und nicht 0 — sonst verschwände ein Gerät aus
 * dem Bestand, nur weil jemand das Feld geleert hat.
 */
function eq_quantity_input(mixed $eingabe): int {
  return min(9999, max(1, (int) $eingabe));
}

/** „10 Stück" für die Anzeige — bei einem Stück steht da nichts. */
function eq_quantity_label(array $eq): string {
  $n = (int) ($eq['quantity'] ?? 1);
  return $n > 1 ? str_replace('%1', (string) $n, t('eq_quantity_n')) : '';
}

/**
 * Das erste Bild unter den Anhängen eines Geräts, für die Vorschau in der Liste.
 *
 * Eine Liste aus hundert Zeilen wie „Cordial CFY 3 VPP" sagt niemandem, was
 * dort im Regal liegt. Ein Bild daneben schon — deshalb steht es in der
 * Übersicht und nicht erst zwei Klicks tiefer im Anhang-Block.
 *
 * Erkannt wird an der Endung des ursprünglichen Namens, nicht am gespeicherten
 * Dateinamen: Der ist bewusst zufällig, und bei eingeschalteter Verschlüsselung
 * trägt er ohnehin keine Endung mehr.
 */
function eq_thumb(array $files): ?array {
  foreach ($files as $f) {
    $endung = strtolower(pathinfo((string) ($f['original_name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($endung, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return $f;
  }
  return null;
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

/**
 * Was für dieses Mitglied noch offen ist — die Zahl am App-Symbol.
 *
 * Zwei Dinge, die eine Handlung verlangen: eigene Aufgaben, die niemand
 * abgehakt hat, und kommende Termine, zu denen die Rückmeldung fehlt.
 * Vergangene Termine zählen nicht — dort ändert eine Antwort nichts mehr.
 *
 * Die Sichtbarkeit gilt auch hier: Ersatzleute sehen nur die Termine, für die
 * sie angefragt wurden, und dürfen auch nur die gezählt bekommen. Eine Zahl,
 * die etwas mitzählt, das man nicht öffnen kann, wäre nicht erklärbar.
 */
/**
 * Termine, bei denen die eigene Rückmeldung fehlt (#236).
 *
 * Eine eigene Funktion, weil dieselbe Frage an zwei Stellen gestellt wird: Die
 * Zahl am App-Symbol zählt sie, und der Kasten „Offene Aufgaben" muss sie zeigen.
 * Kamen die beiden aus getrennten Abfragen, sagte die Zahl „2" und darunter
 * stand nichts — genau so war es.
 *
 * Nicht dabei: Abgesagtes (darüber stimmt niemand ab), Blockiertes (keine
 * Verabredung) und alles, was schon beantwortet ist — egal wie.
 */
function open_votes(array $user): array {
  $sichtbar = visible_event_ids($user);
  if ($sichtbar === []) return [];               // Ersatz ohne Anfrage: keine Termine
  $nurDiese = '';
  $werte = [(int) $user['id']];
  if ($sichtbar !== null) {
    $nurDiese = ' AND e.id IN (' . implode(',', array_fill(0, count($sichtbar), '?')) . ')';
    $werte = [...$werte, ...$sichtbar];
  }
  return rows("SELECT e.id, e.date, e.time, e.type, e.status, e.title, e.location
               FROM events e
               WHERE e.date >= CURDATE()
                 AND e.status NOT IN ('abgesagt', 'blockiert')
                 AND NOT EXISTS (SELECT 1 FROM attendance a
                                 WHERE a.event_id = e.id AND a.user_id = ?)"
              . $nurDiese . ' ORDER BY e.date, e.time', $werte);
}

/**
 * Was am App-Symbol steht: eigene offene Aufgaben und fehlende Rückmeldungen.
 * Die zweite Hälfte kommt aus open_votes(), damit Zahl und Liste nicht
 * auseinanderlaufen können.
 */
function open_items_count(array $user): int {
  $offen = (int) row("SELECT COUNT(*) c FROM tasks WHERE assigned_to = ? AND status = 'offen'",
                     [(int) $user['id']])['c'];
  return $offen + count(open_votes($user));
}

/**
 * Alle Spuren eines Mitglieds außerhalb der Mitgliederliste beseitigen.
 *
 * Zwei Sorten Daten, zwei Behandlungen — und die Grenze ist bewusst gezogen:
 *   * Was nur diese Person betrifft (Rückmeldungen, Abwesenheiten mit ihren
 *     Notizen, Rechte, Bewertungen, Notizzettel, Anmeldungen, Geräte,
 *     Ersatzanfragen), wird gelöscht.
 *   * Was zur Geschichte der Band gehört (Kommentare, Aufgaben, Kassenbuch,
 *     Verantwortlichkeiten, Inventar), verliert nur die Zuordnung. Ein
 *     Kassenbuch, aus dem Zeilen verschwinden, stimmt nicht mehr — und
 *     steuerlich relevante Einträge dürfen gar nicht weg. Anonymisieren
 *     erfüllt das Auskunfts- und Löschverlangen, ohne die Bücher zu zerreißen.
 *
 * Das Mitglied selbst löscht der Aufrufer — hier geht es um alles daneben.
 */
function user_purge(int $userId): void {
  foreach (['attendance', 'permissions', 'song_chords', 'song_ratings',
            'push_subscriptions', 'passkeys', 'substitute_requests',
            'absences'] as $table) {
    q("DELETE FROM $table WHERE user_id = ?", [$userId]);
  }
  // Private Daueraufträge sterben mit ihrem Besitzer. Sonst buchen sie weiter,
  // ohne dass jemand sie sieht (die Liste zeigt private nur ihrem Eigentümer)
  // oder abschalten kann (may_edit_order verweigert fremde private, auch
  // Admins) — ein unsichtbarer Buchungsgenerator.
  q('DELETE FROM standing_orders WHERE owner_id = ? AND private = 1', [$userId]);
  q('UPDATE standing_orders SET owner_id = NULL WHERE owner_id = ?', [$userId]);
  q('UPDATE standing_orders SET created_by = NULL WHERE created_by = ?', [$userId]);
  q('UPDATE comments SET user_id = NULL WHERE user_id = ?', [$userId]);
  q('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?', [$userId]);
  q('UPDATE tasks SET created_by = NULL WHERE created_by = ?', [$userId]);
  q('UPDATE equipment SET owner_id = NULL WHERE owner_id = ?', [$userId]);
  q('UPDATE events SET responsible_id = NULL WHERE responsible_id = ?', [$userId]);
  q('UPDATE finances SET member_id = NULL WHERE member_id = ?', [$userId]);
  // Private Buchungen bleiben als Beleg im Buch, verlieren aber ihren Bezug:
  // auf einer Nummer stehen zu bleiben, die ein künftiges Mitglied erben kann,
  // wäre das Gegenteil einer Löschung.
  q('UPDATE finances SET private_for = NULL WHERE private_for = ?', [$userId]);
  q('UPDATE photos SET uploaded_by = NULL WHERE uploaded_by = ?', [$userId]);
  q('DELETE FROM photo_people WHERE user_id = ?', [$userId]);
  q('UPDATE files SET uploaded_by = NULL WHERE uploaded_by = ?', [$userId]);
  q('UPDATE topics SET created_by = NULL WHERE created_by = ?', [$userId]);
  q('DELETE FROM topic_posts WHERE user_id = ?', [$userId]);
  // Ersatzanfragen für die Lücke dieses Mitglieds zeigen sonst ins Leere —
  // substitute_auto_request() arbeitet genau mit diesem Feld.
  q('DELETE FROM substitute_requests WHERE for_user_id = ?', [$userId]);
  q('UPDATE substitute_requests SET requested_by = NULL WHERE requested_by = ?', [$userId]);
  // Wer als Ersatz einem ausgeschiedenen Mitglied zugeordnet war, hängt sonst
  // an einer Nummer, die es nicht mehr gibt.
  q('UPDATE users SET substitute_for = NULL WHERE substitute_for = ?', [$userId]);
}

/**
 * Die Bereiche, die ein Mitglied offline dabeihaben will.
 *
 * @return string[]
 */
/**
 * Was ein Mitglied offline dabeihat — Abwahl statt Anwahl.
 *
 * Leer heißt „noch nie etwas eingestellt": dann ist alles dabei. Auf der Bühne
 * gibt es kein Netz, und wer dort merkt, dass er nichts mitgenommen hat, kann
 * es nicht mehr nachholen. Der Vorrat muss also da sein, ohne dass jemand
 * vorher daran gedacht hat; wie viel Platz er belegen darf, begrenzt ohnehin
 * der Service Worker.
 *
 * Wer bewusst nichts will, wählt alles ab — das speichert '-' und ist etwas
 * anderes als „noch nie gewählt". Ohne diese Unterscheidung bekäme genau die
 * Person alles zurück, die es abbestellt hat.
 */
const OFFLINE_NICHTS = '-';

function offline_scope(?array $user): array {
  $roh = trim((string) ($user['offline_scope'] ?? ''));
  if ($roh === '') return OFFLINE_AREAS;
  if ($roh === OFFLINE_NICHTS) return [];
  return array_values(array_intersect(OFFLINE_AREAS, array_map('trim', explode(',', $roh))));
}

/**
 * Welche Adressen daraus folgen. Der Service Worker holt sie im Hintergrund;
 * hier entsteht nur die Liste.
 *
 * @return string[]
 */
function offline_urls(array $user): array {
  $bereiche = offline_scope($user);
  if (!$bereiche) return [];

  $urls = ['/intern'];
  if (in_array('termine', $bereiche, true)) $urls[] = '/intern/termine';
  if (in_array('rider', $bereiche, true)) {
    $urls[] = '/intern/stagerider';
    $urls[] = '/intern/stagerider/print';
  }
  if (in_array('kanaele', $bereiche, true)) $urls[] = '/intern/kanaele';

  $songIds = [];
  if (in_array('setlists', $bereiche, true)) {
    $urls[] = '/intern/setlists';
    foreach (rows('SELECT id FROM setlists ORDER BY id DESC LIMIT 50') as $sl) {
      $urls[] = '/intern/setlists/' . (int) $sl['id'];
      $urls[] = '/intern/setlists/' . (int) $sl['id'] . '/print';
    }
  }
  if (in_array('songs', $bereiche, true)) {
    $urls[] = '/intern/songs';
    foreach (rows("SELECT id FROM songs WHERE status <> 'archiv' ORDER BY title") as $song) {
      $songIds[] = (int) $song['id'];
      $urls[] = '/intern/songs/' . (int) $song['id'];
      $urls[] = '/intern/songs/' . (int) $song['id'] . '/buehne';
      $urls[] = '/intern/songs/' . (int) $song['id'] . '/noten';
    }
  }

  // Anhänge nur, wenn ausdrücklich gewollt: das ist die Datenmenge.
  if (in_array('noten', $bereiche, true)) {
    $fuer = [];
    if ($songIds) $fuer['song'] = $songIds;
    if (in_array('setlists', $bereiche, true)) {
      $fuer['setlist'] = array_map('intval', array_column(rows('SELECT id FROM setlists'), 'id'));
    }
    if (in_array('termine', $bereiche, true)) {
      $fuer['event'] = array_map('intval', array_column(
        rows('SELECT id FROM events WHERE date >= CURDATE() - INTERVAL 30 DAY'), 'id'));
    }
    // Direkt abgefragt und nicht über files_map(): das steht im Frontcontroller,
    // und diese Datei soll auch von der Kommandozeile aus benutzbar bleiben.
    foreach ($fuer as $art => $ids) {
      if (!$ids) continue;
      $marken = implode(',', array_fill(0, count($ids), '?'));
      foreach (rows("SELECT id FROM files WHERE entity_type = ? AND entity_id IN ($marken)",
                    [$art, ...$ids]) as $datei) {
        $urls[] = '/intern/datei/' . (int) $datei['id'];
      }
    }
  }
  return array_values(array_unique($urls));
}

/**
 * Alle Geräte außer einem, als [id => Name] — die Auswahl, an die eine
 * Rechnung zusätzlich gehängt werden kann. Abgegebene bleiben draußen: an ein
 * Gerät, das die Band nicht mehr hat, heftet niemand einen neuen Beleg.
 *
 * @return array<int, string>
 */
function eq_other_names(array $items, int $exceptId): array {
  $out = [];
  foreach ($items as $it) {
    if ((int) $it['id'] === $exceptId || !empty($it['disposed_on'])) continue;
    $out[(int) $it['id']] = (string) $it['name'];
  }
  return $out;
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
    // Mal der Menge: Der Preis gilt je Stück, und eine Zeile über zehn Tüllen
    // ist zehn Tüllen wert. Vor der eigenen Spalte stand die Zahl im Namen und
    // fiel bei jeder Summe unter den Tisch (#185).
    $sum += (int) $item['price_cents'] * max(1, (int) ($item['quantity'] ?? 1));
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
/**
 * Monat und Jahr für die Zwischenüberschriften der Terminliste (#233).
 * Der Monatsname kommt aus den Texten, nicht aus der Zeitzone des Servers:
 * date('F') spricht immer Englisch.
 */
/**
 * Was unter „Nächste Termine" auf der Übersicht steht (#235).
 *
 * Hier steht, was STATTFINDET — nichts anderes. Ein Gig ist erst ein Gig, wenn
 * er bestätigt ist; eine Anfrage ist eine Frage und gehört zu den offenen
 * Aufgaben, wo die Rückmeldeknöpfe stehen (#236). Vorher standen Anfragen hier
 * mit, und dann liest ein Blick auf die Übersicht fünf Termine, von denen zwei
 * vielleicht nie passieren.
 *
 *   • die nächsten ZWEI bestätigten Gigs,
 *   • jeden bestätigten Termin, der kein Gig ist — Probe, Besprechung, Party,
 *     Aufnahme, Fotoshooting, Auf-/Abbau, Reise, Day off, Sonstiges,
 *   • nichts Angefragtes, nichts Reserviertes, nichts Abgesagtes, nichts
 *     Blockiertes.
 *
 * Die Zweiergrenze gilt allein für Gigs, weil nur die zu Dutzenden im Kalender
 * stehen. Alles andere ist eine einzelne Verabredung, die man kennen muss — eine
 * Probe am Freitag ist keine Zeile, die warten kann, bis fünf Gigs vorüber sind.
 * Deshalb wird nicht aufgezählt, sondern unterschieden: Gig oder nicht.
 *
 * @param array<int, int>|null $sichtbar Kennungen aus visible_event_ids()
 */
const DASH_GIGS = 2;
const DASH_MAX = 12;

function dashboard_events(?array $sichtbar, string $heute): array {
  [$wo, $args] = visible_clause($sichtbar);
  $gigs = rows("SELECT * FROM events
                WHERE date >= ? AND type = 'gig' AND status = 'bestaetigt'$wo
                ORDER BY date, time LIMIT " . DASH_GIGS, [$heute, ...$args]);
  $rest = rows("SELECT * FROM events
                WHERE date >= ? AND status = 'bestaetigt' AND type <> 'gig'$wo
                ORDER BY date, time", [$heute, ...$args]);

  $zusammen = [];
  foreach ([...$gigs, ...$rest] as $ev) $zusammen[(int) $ev['id']] = $ev;
  usort($zusammen, fn($a, $b) => [$a['date'], $a['time']] <=> [$b['date'], $b['time']]);
  // Eine Obergrenze, damit der Kasten nicht zur zweiten Terminliste wird; wer
  // alles will, hat den Knopf darunter.
  return array_slice($zusammen, 0, DASH_MAX);
}

function fmt_month(?string $iso): string {
  if (!$iso) return '';
  $t = strtotime($iso);
  if (!$t) return $iso;
  $name = explode(',', t('months'))[(int) date('n', $t) - 1] ?? date('m', $t);
  return $name . ' ' . date('Y', $t);
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
