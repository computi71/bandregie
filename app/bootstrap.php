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
// Neu und geändert (#321): nur Funktionen, lädt wie die anderen oben mit.
require_once __DIR__ . '/marks.php';
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
// Eine Uhr für alles (#295). PHP läuft auf den meisten Servern in UTC, die
// Datenbank auf der Systemzeit — ohne Festlegung hielt die Anwendung bis ein
// Uhr nachts den Vortag für „heute", und Zeitstempel aus SQL und aus PHP
// standen zwei Stunden auseinander. Ein unbekannter Name fällt auf Berlin
// zurück statt die Seite zu zerreißen; der Verbindung wird derselbe Versatz
// mitgegeben, damit NOW() und date() dasselbe sagen.
$appZeitzone = (string) ($config['timezone'] ?? 'Europe/Berlin');
if (!in_array($appZeitzone, timezone_identifiers_list(), true)) {
  error_log("Bandregie: unbekannte Zeitzone '$appZeitzone' in app/config.php — Europe/Berlin gilt");
  $appZeitzone = 'Europe/Berlin';
}
date_default_timezone_set($appZeitzone);

try {
  $db = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      // Als Versatz statt als Name: Namen kennt MariaDB nur mit geladenen
      // Zeitzonentabellen, und die fehlen auf vielen Servern. Der Versatz gilt
      // für diese Anfrage, und eine Anfrage erlebt keinen Zeitumstellungswechsel.
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
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
// Die deutschen Texte, das Wörterbuch der Oberfläche (#328).
require_once __DIR__ . '/strings/de.php';

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
  'gig'          => ['times', 'venue', 'setlist', 'support', 'fee', 'production', 'gear', 'public'],
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
  // Gäste buchen heißt, Leute von außen zu einem Termin einzuladen und ihnen
  // für den Abend Rider und Setliste zu öffnen — ein eigener Bereich, den eine
  // Band einzelnen Mitgliedern auch nehmen kann (#294).
  'gaeste'        => ['/intern/gaeste'],
  // Mail im Namen der Band hinauszuschicken ist eine eigene Entscheidung: Wer
  // das Postfach liest, muss nicht antworten dürfen, und wer Mitglieder pflegt,
  // muss ihnen keine Mail schicken können. Der Bereich hat bewusst keinen Pfad
  // — es gibt keine Seite „Versand", geprüft wird an den zwei Stellen, die
  // wirklich versenden: die Antwort im Postfach und die Einladung eines neuen
  // Mitglieds (#270).
  'mailversand'   => [],
  // Angebote rechnen und verschicken ist Bandleitung und Booking, nicht jede
  // Hand: Wer die Preisliste und die Rabatte sieht, sieht auch, was die Band
  // wirklich verlangt (#302).
  'angebote'      => ['/intern/angebote'],
  // Verträge stehen neben den Angeboten und doch für sich: Wer rechnen darf,
  // muss nicht unterschreiben dürfen, und ein Bookingagent von außen bekommt
  // genau diese beiden und sonst nichts (#303, #308).
  'vertraege'     => ['/intern/vertraege'],
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
// Was auch ein Admin nicht von selbst hat. Eine Band zu verwalten ist nicht
// dasselbe, wie ihre Kasse zu führen — und nicht dasselbe, wie ihre Post zu
// lesen. Im Postfach liegen Anfragen, Rechnungen und private Antworten, und
// dass jemand die Anwendung verwaltet, ist noch kein Grund, sie mitzulesen
// (#219, #270).
const PERM_EXPLICIT_MODULES = ['kasse', 'post'];

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
    'mailversand' => [1, 1], 'gaeste' => [1, 1], 'angebote' => [1, 1], 'vertraege' => [1, 1],
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
    'mailversand' => [0, 0], 'gaeste' => [0, 0], 'angebote' => [0, 0], 'vertraege' => [0, 0],
  ],
  // Ein Bookingagent arbeitet für die Band, ohne in ihr zu sein: Er braucht die
  // Termine und die Verträge, und er muss ein Thema aufmachen können. Was die
  // Band unter sich bespricht, geht ihn nichts an — deshalb sieht er von den
  // Themen nur die eigenen und die, zu denen sie ihn dazuholt (#308).
  'booking' => [
    'termine' => [1, 1], 'post' => [0, 0], 'songs' => [0, 0], 'setlists' => [0, 0], 'orte' => [1, 0],
    'abwesenheiten' => [0, 0], 'aufgaben' => [0, 0], 'themen' => [1, 1],
    'kasse' => [0, 0], 'equipment' => [0, 0], 'rider' => [1, 0],
    'fotos' => [0, 0], 'musik' => [0, 0], 'downloads' => [0, 0], 'mitglieder' => [0, 0],
    'mailversand' => [0, 0], 'gaeste' => [0, 0], 'angebote' => [1, 0], 'vertraege' => [1, 1],
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

// Werte, die zur Laufzeit gebraucht werden und bisher zwischen den Migrationen
// standen (#328). Dort waren sie nur historisch: Sobald das Schema hinter ein
// Tor wandert, wären sie bei geschlossenem Tor nicht definiert — und push.php,
// das Profil und „angemeldet bleiben" liefen ins Leere.
/**
 * Was offline vorgehalten werden kann. Je Mitglied wählbar — das Telefon ist
 * persönlich, und wer nur singt, braucht die Patchliste nicht.
 *
 * 'noten' meint die Anhänge: Noten, Verträge, Aufnahmen. Sie sind das
 * Schwergewicht und deshalb eine eigene Entscheidung.
 */
const OFFLINE_AREAS = ['termine', 'setlists', 'songs', 'noten', 'rider', 'kanaele'];

// Worüber Push-Mitteilungen sprechen können — je Mitglied abwählbar.
const PUSH_TOPICS = ['events', 'comments', 'attendance', 'photos', 'post', 'topics'];
const PUSH_NICHTS = '-';

/**
 * Die Themen eines Mitglieds — Abwahl statt Anwahl, wie beim Offline-Vorrat.
 *
 * Gespeichert wird, was jemand ABGEWÄHLT hat, nicht was er behalten will
 * (#323). Das ist der Unterschied zwischen „ich will den Chat nicht" und „den
 * Chat gab es noch nicht, als ich gespeichert habe": Stünde hier die Liste des
 * Behaltenen, fiele jedes später hinzugekommene Thema bei allen heraus, die
 * überhaupt je gespeichert haben — und niemand käme darauf, warum.
 *
 * Leer heißt „nichts abgewählt": dann sind alle Themen dabei. Das schickt
 * niemandem etwas gegen seinen Willen — eine Mitteilung entsteht erst, wenn
 * jemand sein Gerät anmeldet, und dabei fragt der Browser selbst um Erlaubnis.
 * Wer alles abwählt, speichert '-' und bekommt nichts; ohne diesen eigenen
 * Wert schliche sich ein neues Thema bei genau dem wieder ein, der alles
 * abbestellt hat.
 */
function push_topics(?array $user): array {
  $roh = trim((string) ($user['push_topics'] ?? ''));
  if ($roh === '') return PUSH_TOPICS;
  if ($roh === PUSH_NICHTS) return [];
  return array_values(array_diff(PUSH_TOPICS, array_map('trim', explode(',', $roh))));
}

// Die Konstanten stehen hier und nicht bei den Funktionen darunter: PHP zieht
// Funktionen vor, `const` aber nicht — und die Zeilen gleich darunter benutzen
// sie bereits.
const REMEMBER_COOKIE = 'bandregie_bleiben';
const REMEMBER_DAYS = 90;

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
    postcode VARCHAR(20) NOT NULL DEFAULT '',
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

  // Was aus einer Einladung wurde (#293). mail() sagt nur, dass der eigene
  // Mailserver die Nachricht genommen hat; ob Gmail sie eine Sekunde später
  // abweist, steht allein im Log des Mailservers. bin/mail-status.php trägt
  // das hier nach — anhand unserer eigenen Message-ID.
  // Gäste (#294): Leute, die für einen Abend dazukommen — Tontechniker,
  // Bläsersatz, Aushilfe — ohne Mitglied zu sein. Kein Login, keine Rechte;
  // ihr einziger Schlüssel ist das Token einer Buchung.
  "CREATE TABLE IF NOT EXISTS guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    function_name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(60) NOT NULL DEFAULT '',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS guest_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guest_id INT NOT NULL,
    event_id INT NOT NULL,
    function_name VARCHAR(120) NOT NULL DEFAULT '',
    token CHAR(64) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'angefragt',
    invited_at DATETIME NULL,
    answered_at DATETIME NULL,
    access_until DATETIME NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (event_id), INDEX (guest_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Wie war es mit dem Gast? Je Einsatz und je Mitglied ein Urteil, 1 bis 5
  // Sterne mit einer Zeile dazu — die Kontaktliste rechnet daraus den Schnitt.
  // Intern; ein Gast sieht das nie (#294).
  "CREATE TABLE IF NOT EXISTS guest_ratings (
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    stars TINYINT NOT NULL,
    comment VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (booking_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Der Veranstalter ist nicht der Ort (#303): Eine Halle heißt „Strandfest",
  // gebucht wird sie über eine Agentur zwei Orte weiter, und der Vertrag geht
  // an die Agentur.
  "CREATE TABLE IF NOT EXISTS promoters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    contact_name VARCHAR(190) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(60) NOT NULL DEFAULT '',
    mobile VARCHAR(60) NOT NULL DEFAULT '',
    street VARCHAR(190) NOT NULL DEFAULT '',
    postcode VARCHAR(20) NOT NULL DEFAULT '',
    city VARCHAR(190) NOT NULL DEFAULT '',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Wer von außen welchen Vertrag sehen darf (#309) — dieselbe Idee wie bei den
  // Themen: Die Liste lässt zu und nimmt nie weg.
  "CREATE TABLE IF NOT EXISTS contract_access (
    contract_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (contract_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Der Wortlaut steht in der Zeile und nicht in der Vorlage: Was verschickt
  // und unterschrieben wurde, darf sich nicht ändern, weil die Band ein halbes
  // Jahr später ihre Vorlage anfasst.
  "CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NULL,
    promoter_id INT NULL,
    quote_id INT NULL,
    contract_no VARCHAR(60) NOT NULL DEFAULT '',
    contract_date DATE NOT NULL,
    fee_cents INT NOT NULL DEFAULT 0,
    play_from VARCHAR(5) NOT NULL DEFAULT '',
    play_to VARCHAR(5) NOT NULL DEFAULT '',
    get_in VARCHAR(5) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'entwurf',
    sent_at DATETIME NULL,
    signed_at DATETIME NULL,
    body MEDIUMTEXT,
    notes TEXT,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Ein Angebot friert seine Posten ein (#302): Ändert die Band später ihre
  // Preisliste, darf ein verschicktes Angebot nicht plötzlich anders aussehen.
  // Deshalb stehen die gerechneten Zeilen als Zeilen in der Datenbank und
  // werden erst beim Speichern neu gebildet.
  "CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NULL,
    title VARCHAR(190) NOT NULL DEFAULT '',
    customer VARCHAR(190) NOT NULL DEFAULT '',
    quote_date DATE NOT NULL,
    play_minutes INT NOT NULL DEFAULT 0,
    km INT NOT NULL DEFAULT 0,
    nights INT NOT NULL DEFAULT 0,
    own_pa TINYINT(1) NOT NULL DEFAULT 0,
    surcharge_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    surcharge_label VARCHAR(120) NOT NULL DEFAULT '',
    discount_mode VARCHAR(10) NOT NULL DEFAULT 'none',
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    discount_cents INT NOT NULL DEFAULT 0,
    discount_label VARCHAR(120) NOT NULL DEFAULT '',
    discount_show TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    label VARCHAR(190) NOT NULL,
    amount_cents INT NOT NULL DEFAULT 0,
    sort INT NOT NULL DEFAULT 0,
    INDEX idx_quote (quote_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS mail_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    to_email VARCHAR(190) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'einladung',
    message_id VARCHAR(190) NOT NULL UNIQUE,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    status_at DATETIME NULL,
    detail VARCHAR(255) NOT NULL DEFAULT ''
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

  // Wer ein Thema wann zuletzt gesehen hat (#317). Ohne diesen Stand lässt sich
  // „ungelesen" nicht sagen, und die Zahl am Symbol könnte den Chat nicht
  // mitzählen.
  // Wer was schon gesehen hat (#321). Eine Zeile je Mitglied und Eintrag,
  // gesetzt beim Öffnen. Die Art steht als Wort dabei, damit nicht für jede
  // Sorte eine eigene Tabelle entsteht, die dasselbe tut.
  "CREATE TABLE IF NOT EXISTS seen_marks (
    user_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    item_id INT NOT NULL,
    seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, kind, item_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS topic_reads (
    user_id INT NOT NULL,
    topic_id INT NOT NULL,
    seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, topic_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    created_by INT NULL,
    closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  // Wer von außen ein Thema sehen darf (#308). Die Liste lässt nur zu und nimmt
  // nie weg: Mitglieder sehen weiterhin alles, hier stehen ausschließlich die
  // Konten, die sonst nichts sähen.
  "CREATE TABLE IF NOT EXISTS topic_access (
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (topic_id, user_id)
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

  // Angemeldet bleiben (#262): Das Gerät hält einen Zufallswert, der Server nur
  // dessen Prüfsumme — eine gestohlene Datenbank ergibt damit keine Anmeldung.
  // selector findet die Zeile, validator_hash beweist sie.
  "CREATE TABLE IF NOT EXISTS login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(32) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uniq_selector (selector),
    INDEX idx_user (user_id)
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
// Postleitzahl als eigenes Feld (#249). Bisher stand sie im Adress-Feld mit
// drin — oder nirgends. Beim Nachrüsten wird sie dort herausgeholt, wo sie
// erkennbar ist: eine Zeile „12345 Ort" oder eine reine Zahlengruppe. Erkennbar
// heißt streng: alles andere bleibt unangetastet im Adresstext, denn eine
// halb geratene Adresse ist schlimmer als eine ungeteilte.
if (!column_exists('venues', 'postcode')) {
  $db->exec("ALTER TABLE venues ADD COLUMN postcode VARCHAR(20) NOT NULL DEFAULT '' AFTER city");
  foreach ($db->query('SELECT id, address, city FROM venues')->fetchAll() as $v) {
    $stadt = (string) $v['city'];
    [$rest, $plz, $ort] = address_split_postcode((string) $v['address']);
    if ($plz !== '' && $stadt === '') $stadt = $ort;
    // Getippt wurde die PLZ auch schon ins Stadt-Feld — „34549 Edertal" ist ein
    // vollständiger Ort, nur im falschen Kasten.
    if ($plz === '') {
      [$restStadt, $plz, $ort] = address_split_postcode($stadt);
      if ($plz !== '') $stadt = $ort !== '' ? $ort : $restStadt;
    }
    if ($plz === '') continue;
    $st = $db->prepare('UPDATE venues SET address = ?, postcode = ?, city = ? WHERE id = ?');
    $st->execute([$rest, $plz, $stadt, $v['id']]);
  }
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
// Wann und von wem zuletzt geändert (#321). Bestehende Zeilen bleiben leer:
// Was es vor den Marken schon gab, ist für niemanden neu, und eine Bandhistorie
// als Stapel ungesehener Punkte wäre der sichere Weg, dass niemand mehr
// hinsieht. Lieder hatten bisher gar keinen Zeitstempel.
if (!column_exists('songs', 'created_at')) {
  $db->exec('ALTER TABLE songs ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
}
foreach (['events', 'songs', 'setlists', 'quotes', 'contracts'] as $markiert) {
  if (!column_exists($markiert, 'updated_at')) {
    $db->exec("ALTER TABLE `$markiert` ADD COLUMN updated_at DATETIME NULL,
                                       ADD COLUMN updated_by INT NULL");
  }
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
// Wer sich noch nie angemeldet hat, hat hier NULL — daran hängt der Knopf zum
// erneuten Senden der Zugangsdaten (#274). Bestehende Konten bleiben leer, bis
// sie sich das nächste Mal anmelden; das ist richtig so, denn wann sie es
// zuletzt taten, weiß niemand mehr.
if (!column_exists('users', 'last_login_at')) {
  $db->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL");
}
// Wann das Start-Passwort erzeugt wurde. Ohne Stempel gilt es unbegrenzt —
// bestehende Konten sollen sich durch das Update nicht plötzlich aussperren.
if (!column_exists('users', 'start_pw_at')) {
  $db->exec("ALTER TABLE users ADD COLUMN start_pw_at DATETIME NULL");
}
// „Kommende Termine von selbst mitnehmen" (#277). Aus: Wer den Knopf drückt,
// bekommt weiterhin genau den einen Auftritt.
if (!column_exists('users', 'offline_auto')) {
  $db->exec("ALTER TABLE users ADD COLUMN offline_auto TINYINT(1) NOT NULL DEFAULT 0");
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
// Wer sonst noch auf dem Zettel steht: die Vorband — oder der Hauptact, wenn
// die Band selbst die Vorband ist. Stand bisher in den Notizen und war dort
// für Export und Kalender unsichtbar (#287).
if (!column_exists('events', 'support_act')) {
  $db->exec("ALTER TABLE events ADD COLUMN support_act VARCHAR(255) NOT NULL DEFAULT '' AFTER location");
}
// Mitglieder ohne E-Mail-Adresse (#291): Die Besetzung steht am ersten Tag,
// die Adressen kommen nach. Ohne Adresse gibt es keinen Zugang — NULL, nicht
// Leerstring, denn UNIQUE lässt beliebig viele NULL zu, aber nur ein ''.
$emailNullable = $db->prepare('SELECT is_nullable FROM information_schema.columns
                               WHERE table_schema = ? AND table_name = ? AND column_name = ?');
$emailNullable->execute([$config['db_name'], 'users', 'email']);
if (($emailNullable->fetchColumn() ?: 'NO') === 'NO') {
  $db->exec('ALTER TABLE users MODIFY email VARCHAR(190) NULL');
}
foreach (['first_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'last_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
          'phone' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'mobile' => "VARCHAR(60) NOT NULL DEFAULT ''",
          // Anschrift (#306): Ein Mitglied hatte zwei Nummern und keine Adresse.
          // Gebraucht wird sie, sobald jemand etwas verschickt — Merch, ein
          // Vertrag, eine Weihnachtskarte.
          'street' => "VARCHAR(190) NOT NULL DEFAULT ''",
          'postcode' => "VARCHAR(20) NOT NULL DEFAULT ''",
          'city' => "VARCHAR(190) NOT NULL DEFAULT ''",
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
// Die Anweisung der Klammer braucht ihr eigenes Feld: „Drop D" gilt für die
// Klammer, „Andi in D" für den einen Song — im selben Feld verdrängte eines das
// andere, und genau das ist beim Übertragen der Vorlage passiert (#242).
if (!column_exists('setlist_songs', 'bracket_note')) {
  $db->exec("ALTER TABLE setlist_songs ADD COLUMN bracket_note VARCHAR(200) NOT NULL DEFAULT ''");
}
// Die handgezeichnete Klammer der Papier-Setlisten (#242): Zeilen mit derselben
// Nummer gehören zusammen — gespielt ohne Absetzen, eine Stimmung, ein Bogen.
// Die Anweisung steht an der ersten Zeile der Klammer.
if (!column_exists('setlist_songs', 'bracket')) {
  $db->exec('ALTER TABLE setlist_songs ADD COLUMN bracket TINYINT UNSIGNED NULL');
}
// Blöcke aus den Papier-Setlisten (#241): eine Marke, die trennt wie ein Strich
// auf dem Zettel, mit der Anweisung, die dort daneben steht.
if (!column_exists('setlist_songs', 'note')) {
  $db->exec("ALTER TABLE setlist_songs ADD COLUMN note VARCHAR(200) NOT NULL DEFAULT ''");
}
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

/**
 * Alle Einstellungen, einmal je Anfrage geholt.
 *
 * Vorher stellte jeder setting()-Aufruf eine eigene Abfrage, bei 400
 * Aufrufstellen im Code. Fehlt die Tabelle, ist das kein Fehler, sondern die
 * Auskunft „Schema unbekannt" — genau die braucht das Tor, das entscheidet,
 * ob die Migrationen überhaupt laufen müssen.
 *
 * $neuLaden erzwingt eine echte Abfrage statt des Zwischenspeichers, für
 * settings_forget(). $setzen überschreibt den Zwischenspeicher direkt ohne
 * Abfrage, für set_setting() — ein Formular wie der Bühnenplan setzt in
 * einem POST ein Dutzend Werte, und keiner davon soll die ganze Tabelle
 * neu laden müssen, wenn er schon im Speicher steht.
 */
function settings_all(bool $neuLaden = false, ?array $setzen = null): array {
  static $alle = null;
  if ($setzen !== null) {
    $alle = $setzen;
  } elseif ($alle === null || $neuLaden) {
    try {
      $alle = array_column(rows('SELECT `key`, value FROM settings'), 'value', 'key');
    } catch (PDOException $e) {
      $alle = [];
    }
  }
  return $alle;
}

/**
 * Den Zwischenspeicher wirklich neu laden — für das Tor, nachdem es am
 * Schema geschrieben hat, und für die zwei Migrationen, die Zeilen per
 * DELETE aus settings entfernen statt über set_setting() zu gehen.
 */
function settings_forget(): void {
  settings_all(true);
}

function setting(string $key, string $fallback = ''): string {
  return settings_all()[$key] ?? $fallback;
}

function set_setting(string $key, string $value): void {
  q('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$key, $value]);
  // Durchschreibend, aber ohne zweite Abfrage: der Speicher kennt den alten
  // Stand schon, also im Speicher nachführen statt neu zu laden. Ein Formular
  // mit einem Dutzend Feldern zahlt sonst ein Dutzend volle Tabellenabfragen.
  $alle = settings_all();
  $alle[$key] = $value;
  settings_all(false, $alle);
}

function all_settings(): array {
  return settings_all();
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
  // Welche Druckbögen Logo und Wasserzeichen tragen (#304). Das Logo überall:
  // Es sagt, von wem das Blatt ist. Das Wasserzeichen nur auf der Setliste —
  // Steuerübersicht und GEMA-Meldung sind Formulare, dort stört ein Bild
  // hinter den Zahlen. Angebot und Vertrag tragen es, sobald es sie gibt.
  'print_logo_docs' => 'setlist,rider,tax,gema,quote,help', 'print_watermark_docs' => 'setlist,quote',
  // Preisliste der Kalkulation (#302), alles in Cent. Leer ausgeliefert:
  // Was eine Band verlangt, weiß nur sie selbst, und eine erfundene Zahl
  // im Angebot wäre schlimmer als ein leeres Feld.
  'quote_base_cents' => '0', 'quote_hour_cents' => '0',
  'quote_km_cents' => '0', 'quote_km_free' => '0',
  'quote_night_cents' => '0', 'quote_pa_cents' => '0',
  'quote_min_cents' => '0', 'quote_discount_private' => '0',
  // Der Vertragstext gehört der Band, nicht diesem Programm. Leer heißt: Es
  // gilt die mitgelieferte Vorlage in der Sprache der Installation. Sobald
  // jemand sie bearbeitet, steht sie hier und wird nie wieder überschrieben.
  // Wie die Band Aufträge vereinbart (#312). Der direkte Weg ist die Vorgabe,
  // weil die meisten Bands den Vertrag schicken und nicht erst ein Angebot.
  'contract_flow' => 'direkt',
  'contract_text' => '',
  // Wie viel ein Bookingagent vom Kalender sieht (#309). Zu heißt zu, solange
  // niemand etwas anderes sagt: Eine Rolle, die mit offenem Kalender ankommt,
  // hat schon durchgereicht, bevor jemand ans Zumachen denkt.
  'booking_event_scope' => 'busy',
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
    perm_apply_template((int) $permUser['id'],
                       in_array($permUser['role'], ['ersatz', 'booking'], true) ? $permUser['role'] : 'member');
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

// Angemeldet bleiben: Ohne Sitzung, aber mit gültigem Merkmal wird die Sitzung
// hier wiederhergestellt — bevor irgendeine Route nach dem Mitglied fragt (#262).
if (empty($_SESSION['uid']) && isset($_COOKIE[REMEMBER_COOKIE])) {
  $wieder = remember_check();
  if ($wieder !== null) {
    session_regenerate_id(true);
    $_SESSION['uid'] = $wieder;
  }
}

// Das Postfach muss seit #270 auch einem Admin ausdrücklich gegeben werden.
// Ohne diese Zeile verlöre beim Update jede bestehende Installation ihr
// Postfach — auch die Person, die es täglich liest. Einmalig und am Schlüssel
// gemerkt: Wer das Recht später bewusst entzieht, bekommt es nicht zurück.
// Neuer Bereich „Gäste" (#294): Wer schon Rechtezeilen hat, wird genau danach
// beurteilt — ohne Zeile für den neuen Bereich wäre er für alle Bestehenden zu.
// Mitglieder bekommen ihn, wie die Vorlage ihn gibt; Ersatzleute nicht, Admins
// haben ihn ohnehin. Einmalig, damit ein späterer Entzug bestehen bleibt.
// Die Queue-ID des Mailservers zur Einladung merken (#296): Postfix nennt die
// Message-ID nur einmal, beim Annehmen; das Ergebnis eines zurückgestellten
// Versuchs kommt Stunden später und trägt nur noch die Queue-ID. Ohne sie
// bliebe die Zeile für immer bei „wird erneut versucht".
if (!column_exists('mail_log', 'queue_id')) {
  $db->exec("ALTER TABLE mail_log ADD COLUMN queue_id VARCHAR(20) NOT NULL DEFAULT '', ADD INDEX (queue_id)");
}

if (setting('migr_gaeste_perm') === '') {
  foreach (rows("SELECT DISTINCT u.id FROM users u JOIN permissions p ON p.user_id = u.id WHERE u.role = 'member'") as $gRow) {
    q('INSERT IGNORE INTO permissions (user_id, module, can_read, can_write) VALUES (?, ?, 1, 1)', [$gRow['id'], 'gaeste']);
  }
  set_setting('migr_gaeste_perm', '1');
}

// Angebote rechnen darf, wer schon Rechte-Zeilen hat — sonst stünde der neue
// Bereich bei bestehenden Bands auf „nein" und niemand fände ihn (#302).
// Derselbe Kontaktblock überall (#306): Der Gast bekommt die Mobilnummer, die
// die Einladung per WhatsApp ohnehin braucht, und eine Anschrift; der
// Ansprechpartner eines Ortes bekommt seine Mobilnummer.
foreach (['mobile' => "VARCHAR(60) NOT NULL DEFAULT ''",
          'street' => "VARCHAR(190) NOT NULL DEFAULT ''",
          'postcode' => "VARCHAR(20) NOT NULL DEFAULT ''",
          'city' => "VARCHAR(190) NOT NULL DEFAULT ''"] as $gastSpalte => $gastDdl) {
  if (!column_exists('guests', $gastSpalte)) $db->exec("ALTER TABLE guests ADD COLUMN `$gastSpalte` $gastDdl");
}
if (!column_exists('venues', 'contact_mobile')) {
  $db->exec("ALTER TABLE venues ADD COLUMN contact_mobile VARCHAR(60) NOT NULL DEFAULT '' AFTER contact_phone");
}

// Wer einen Termin eingetragen hat (#309). Gebraucht für den Bookingagenten:
// Seine eigenen Anfragen muss er sehen, auch wenn ihm der übrige Kalender nur
// als „belegt" erscheint. Bestehende Termine bleiben ohne Urheber — sie sind
// von der Band und gehören damit zur zweiten Gruppe.
if (!column_exists('events', 'created_by')) {
  $db->exec('ALTER TABLE events ADD COLUMN created_by INT NULL');
}

// Verträge sehen und schreiben darf, wer schon Rechte-Zeilen hat (#303).
if (setting('migr_vertraege_perm') === '') {
  foreach (rows("SELECT DISTINCT u.id FROM users u JOIN permissions p ON p.user_id = u.id WHERE u.role = 'member'") as $vRow) {
    q('INSERT IGNORE INTO permissions (user_id, module, can_read, can_write) VALUES (?, ?, 1, 1)', [$vRow['id'], 'vertraege']);
  }
  set_setting('migr_vertraege_perm', '1');
}

// Der Vertrag ist ein Druckbogen wie die anderen.
if (setting('migr_help_print_docs') === '') {
  $hTeile = array_filter(array_map('trim', explode(',', (string) setting('print_logo_docs'))));
  if (!in_array('help', $hTeile, true)) $hTeile[] = 'help';
  set_setting('print_logo_docs', implode(',', $hTeile));
  set_setting('migr_help_print_docs', '1');
}

if (setting('migr_contract_print_docs') === '') {
  foreach (['print_logo_docs', 'print_watermark_docs'] as $druckListe2) {
    $teile2 = array_filter(array_map('trim', explode(',', (string) setting($druckListe2))));
    if (!in_array('contract', $teile2, true)) $teile2[] = 'contract';
    set_setting($druckListe2, implode(',', $teile2));
  }
  set_setting('migr_contract_print_docs', '1');
}

if (setting('migr_angebote_perm') === '') {
  foreach (rows("SELECT DISTINCT u.id FROM users u JOIN permissions p ON p.user_id = u.id WHERE u.role = 'member'") as $aRow) {
    q('INSERT IGNORE INTO permissions (user_id, module, can_read, can_write) VALUES (?, ?, 1, 1)', [$aRow['id'], 'angebote']);
  }
  set_setting('migr_angebote_perm', '1');
}

// Das Angebot ist ein Druckbogen wie die anderen und soll Logo und
// Wasserzeichen tragen. Bestehende Installationen haben die Listen schon, also
// wird es einmalig angehängt — ohne eine abgewählte Setliste wieder anzuhaken.
if (setting('migr_quote_print_docs') === '') {
  foreach (['print_logo_docs', 'print_watermark_docs'] as $druckListe) {
    $teile = array_filter(array_map('trim', explode(',', (string) setting($druckListe))));
    if (!in_array('quote', $teile, true)) $teile[] = 'quote';
    set_setting($druckListe, implode(',', $teile));
  }
  set_setting('migr_quote_print_docs', '1');
}

if (setting('migr_post_explicit') === '') {
  foreach (rows("SELECT id FROM users WHERE role = 'admin'") as $adminZeile) {
    q('INSERT INTO permissions (user_id, module, can_read, can_write) VALUES (?, ?, 1, 1)
       ON DUPLICATE KEY UPDATE can_read = 1, can_write = 1', [$adminZeile['id'], 'post']);
  }
  set_setting('migr_post_explicit', '1');
}

// Reparatur zu #272: Die Migration darüber gab jedem Admin eine Postfach-Zeile
// — und damit hatte er zum ersten Mal überhaupt Zeilen. Wer Zeilen hat, wird
// genau danach beurteilt, und die Kasse, die ein Admin vorher über die Vorlage
// „Mitglied" sehen (nicht führen) durfte, war damit weg. Wessen einzige Zeile
// das Postfach ist, der hatte vorher keine: Er bekommt die Kasse zum Sehen
// zurück, genau wie die Vorlage sie gab.
if (setting('migr_kasse_repair') === '') {
  foreach (rows("SELECT id FROM users u WHERE u.role = 'admin'
                 AND NOT EXISTS (SELECT 1 FROM permissions p WHERE p.user_id = u.id AND p.module <> 'post')") as $adminZeile) {
    q('INSERT INTO permissions (user_id, module, can_read, can_write) VALUES (?, ?, 1, 0)
       ON DUPLICATE KEY UPDATE can_read = can_read', [$adminZeile['id'], 'kasse']);
  }
  set_setting('migr_kasse_repair', '1');
}

// Die Demodaten aus v1.260.0 trugen ihre Gästebewertungen in die Demo-Liste
// ein, als hätten sie einen eigenen Schlüssel. Haben sie nicht — guest_ratings
// steht auf Buchung und Mitglied. In der Liste steht deshalb zweimal die Null,
// und das Entfernen der Demodaten lief in ein DELETE … WHERE id auf eine
// Tabelle ohne id: Abbruch mittendrin, halb entfernte Demo. Die Zeilen sind
// nichts wert, sie zeigen auf keine Bewertung; das Aufräumen läuft jetzt über
// die Buchung.
if (setting('migr_demo_guest_ratings') === '') {
  q("DELETE FROM demo_rows WHERE table_name = 'guest_ratings'");
  set_setting('migr_demo_guest_ratings', '1');
}

// Wer sich vor v1.244.0 angemeldet hat, hinterließ davon keinen Stempel — ein
// Passwort-Login schreibt nichts mit. Was sich beweisen lässt, wird einmalig
// nachgetragen: ein Passkey, ein bestätigter zweiter Faktor, ein Merkmal für
// „angemeldet bleiben" oder ein Push-Abo entsteht nur nach einer Anmeldung.
// Wo es keinen Beleg gibt, bleibt die Spalte leer — dann sagt die Liste
// „keine Anmeldung bekannt" und behauptet nichts (#275).
if (setting('migr_login_backfill') === '') {
  q("UPDATE users u SET u.last_login_at = GREATEST(
       COALESCE((SELECT MAX(p.last_used_at) FROM passkeys p WHERE p.user_id = u.id), '1000-01-01'),
       COALESCE(u.totp_confirmed_at, '1000-01-01'),
       COALESCE((SELECT MAX(COALESCE(l.last_used_at, l.created_at)) FROM login_tokens l WHERE l.user_id = u.id), '1000-01-01'),
       COALESCE((SELECT MAX(s.last_seen_at) FROM push_subscriptions s WHERE s.user_id = u.id), '1000-01-01'))
     WHERE u.last_login_at IS NULL
       AND GREATEST(
       COALESCE((SELECT MAX(p.last_used_at) FROM passkeys p WHERE p.user_id = u.id), '1000-01-01'),
       COALESCE(u.totp_confirmed_at, '1000-01-01'),
       COALESCE((SELECT MAX(COALESCE(l.last_used_at, l.created_at)) FROM login_tokens l WHERE l.user_id = u.id), '1000-01-01'),
       COALESCE((SELECT MAX(s.last_seen_at) FROM push_subscriptions s WHERE s.user_id = u.id), '1000-01-01')) > '1000-01-01'");
  set_setting('migr_login_backfill', '1');
}

// Ab jetzt zählt der Chat am App-Symbol mit (#317). Was am Tag des Updates
// schon dasteht, gilt als gelesen: Sonst fände jedes Mitglied beim ersten
// Öffnen die gesamte Geschichte der Band als ungelesen vor — eine Zahl, die
// niemand durch Lesen wieder loswird, weil sie nie ungelesen war.
// Nur einmal, und nur für die, die es jetzt schon gibt; wer später dazukommt,
// fängt an seinem eigenen Beitrittstag an.
// Der Stichtag der Marken (#321). Dateien tragen ihren Zeitstempel seit jeher
// selbst — ohne diese Grenze stünde am Tag des Updates jede Datei der letzten
// Jahre als „neu" da. Für alles andere ist die Grenze überflüssig und
// trotzdem richtig.
// Sekunden sind zu grob für einen Vergleich zwischen „angesehen" und
// „geändert": Wer eine Karte liest, während jemand anderes sie speichert,
// bekäme die Änderung nie zu sehen — einmal im Jahr, nicht nachstellbar, und
// deshalb am teuersten zu suchen. Millisekunden für alle Zeitpunkte, die
// gegeneinander verglichen werden.
if (setting('migr_marks_ms') === '') {
  $db->exec('ALTER TABLE seen_marks MODIFY seen_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)');
  $db->exec('ALTER TABLE topic_reads MODIFY seen_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)');
  $db->exec('ALTER TABLE topic_posts MODIFY created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)');
  foreach (array_column(ITEM_KINDS, 'tabelle') as $markiert) {
    if ($markiert === 'files') continue;   // trägt seinen Zeitstempel selbst
    $db->exec("ALTER TABLE `$markiert` MODIFY updated_at DATETIME(3) NULL");
  }
  set_setting('migr_marks_ms', '1');
}

if (setting('marks_since') === '') set_setting('marks_since', date('Y-m-d H:i:s'));

// Die Themenliste speichert ab jetzt das Abgewählte (#323). Umgerechnet wird
// gegen die fünf Themen, die es gab, ALS diese Listen geschrieben wurden — nicht
// gegen die heutigen. Sonst würde genau das neue Thema, das bei allen fehlt, als
// Abwahl festgeschrieben, und der Fehler wäre für immer eingebaut.
if (setting('migr_push_abwahl') === '') {
  $themenDamals = ['events', 'comments', 'attendance', 'photos', 'post'];
  foreach (rows("SELECT id, push_topics FROM users
                  WHERE push_topics <> '' AND push_topics <> ?", [PUSH_NICHTS]) as $zeile) {
    $behalten = array_map('trim', explode(',', (string) $zeile['push_topics']));
    $abgewaehlt = array_values(array_diff($themenDamals, $behalten));
    q('UPDATE users SET push_topics = ? WHERE id = ?',
      [$abgewaehlt ? implode(',', $abgewaehlt) : '', $zeile['id']]);
  }
  set_setting('migr_push_abwahl', '1');
}

if (setting('migr_topic_reads') === '') {
  q('INSERT IGNORE INTO topic_reads (user_id, topic_id, seen_at)
     SELECT u.id, t.id, NOW(3) FROM users u CROSS JOIN topics t');
  set_setting('migr_topic_reads', '1');
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
// Die Adress-Suche fragt seit #249 feldweise, und die PLZ ist ein eigenes Feld —
// der Hilfetext beschrieb die alte Freitextsuche. Der neue steht in Seed 16
// selbst (dem fruehesten, der den Schluessel setzt); hier wird nur der veraltete
// weggeraeumt, damit der Seed ihn neu anlegen kann.
if (setting('help_orte_postcode') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_orte'");
  set_setting('help_orte_postcode', '1');
}
// Der Fotobereich kann inzwischen mehr, als sein Hilfetext wusste (v1.197 bis
// v1.203). Der neue Text steht in Seed 16 SELBST — ein späterer Seed erreicht
// ihn nie, weil das Neueinspielen alle Seeds der Reihe nach laufen lässt und
// der früheste gewinnt. Genau daran ist der erste Versuch (Wächter …08b)
// gescheitert: weggeräumt, und Seed 16 setzte den alten Text zurück.
// Die Serien sind fort (#218): ihre Texte auch. Sonst bliebe in sechs Sprachen
// stehen, was die Anwendung nicht mehr kann — und in den Einstellungen ein
// Schalter-Zustand, den niemand mehr umlegen kann.
// Die Spalte über songs.artist hiess „Original" — seit die Herkunft eines Covers
// in den Notizen steht (#251), zeigt das Feld den Interpreten der gespielten
// Fassung. Der alte Wortlaut muss weg, der neue kommt aus den Seeds 03/04 (#256).
if (setting('col_interpret') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'songs_col_original'");
  set_setting('col_interpret', '1');
}
// Der Teleprompter-Einstieg (#254) und die waehlbaren Felder im Ausdruck (#255)
// standen in keinem Hilfetext. Der neue Wortlaut kommt aus Seed 29; hier wird
// der alte weggeraeumt (#259).
if (setting('help_setlists_v4') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_setlists'");
  set_setting('help_setlists_v4', '1');
}
// Die Symbole erscheinen nur, wo es etwas zu zeigen gibt (#250) — das steht
// jetzt auch in der Hilfe. Der neue Text kommt aus Seed 16; hier wird der
// veraltete weggeraeumt, damit der Seed ihn neu anlegen kann.
if (setting('help_songs_icons') !== '1') {
  q("DELETE FROM translations WHERE tkey = 'help_songs'");
  set_setting('help_songs_icons', '1');
}
// Die Sammelseite „Texte einpflegen" ist fort (#250): ihre Texte auch, sonst
// stehen in sechs Sprachen Sätze über eine Seite, die es nicht mehr gibt.
if (setting('lyrics_bulk_gone') !== '1') {
  q("DELETE FROM translations WHERE tkey IN
     ('song_lyrics_bulk','song_lyrics_bulk_hint','song_lyrics_bulk_saved')");
  set_setting('lyrics_bulk_gone', '1');
}
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
      : row('SELECT id, name, stage_name, email, role, instrument, avatar_file, must_change_pw, substitute_for, offline_scope, offline_auto FROM users WHERE id = ?', [$_SESSION['uid']]);
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
  // Band zu verwalten ist nicht dasselbe, wie ihre Kasse zu führen oder ihre
  // Post zu lesen.
  if (($user['role'] ?? '') === 'admin') {
    if (!in_array($module, PERM_EXPLICIT_MODULES, true)) return true;
    // Und ein ausdrücklicher Bereich gilt nur, wenn er wirklich eingetragen
    // ist. Sonst fiele ein frisch angelegter Admin — der bekommt keine einzige
    // Rechtezeile — auf die Vorlage „Mitglied" zurück und hätte Kasse und
    // Postfach doch wieder, obwohl genau das verhindert werden sollte (#271).
    $eigen = perm_of((int) $user['id'])[$module] ?? null;
    if (!$eigen) return false;
    return $need === 'write' ? (bool) $eigen['write'] : (bool) ($eigen['read'] || $eigen['write']);
  }
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

// ---------- Karten- und Listendaten ----------
//
// Diese fünf sammeln zu einer Menge Kennungen, was daran hängt: Geräte,
// Konflikte, Dateien, Rückmeldungen. Sie standen im Front-Controller, wurden
// aber längst auch von hier aufgerufen (offline_urls, event_view_data) — und
// damit hing bootstrap.php an einer Datei, die ein Cron-Skript nie lädt (#279).

/** Packlisten mehrerer Termine: je Termin-ID die Geräte mit Name und Bestandteil-Kennung. */
function event_gear_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT ee.event_id, e.id, e.name, e.parent_id
                 FROM event_equipment ee JOIN equipment e ON e.id = ee.equipment_id
                 WHERE ee.event_id IN ($in) ORDER BY e.category, e.name", $eventIds) as $r) {
    $out[(int) $r['event_id']][] = $r;
  }
  return $out;
}
/**
 * Geräte, die an einem Tag bei mehreren Terminen eingeplant sind. Zwei Gigs am
 * selben Samstag teilen sich keine PA — darauf weist die Terminliste hin.
 */
function event_gear_conflicts(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT ee.event_id, e.name FROM event_equipment ee
                 JOIN equipment e ON e.id = ee.equipment_id
                 JOIN events ev ON ev.id = ee.event_id
                 WHERE ee.event_id IN ($in) AND EXISTS (
                   SELECT 1 FROM event_equipment o JOIN events oe ON oe.id = o.event_id
                   WHERE o.equipment_id = ee.equipment_id AND o.event_id <> ee.event_id
                     AND oe.date = ev.date AND oe.status <> 'abgesagt'
                 ) AND ev.status <> 'abgesagt'
                 ORDER BY e.name", $eventIds) as $r) {
    $out[(int) $r['event_id']][] = $r['name'];
  }
  return $out;
}
function files_map(string $type, array $ids): array {
  if (!$ids) return [];
  $in = implode(',', array_map('intval', $ids));
  $map = [];
  foreach (rows("SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by
                 WHERE f.entity_type = ? AND f.entity_id IN ($in) ORDER BY f.created_at", [$type]) as $f) {
    $map[$f['entity_id']][] = $f;
  }
  return $map;
}
function attendance_map(array $eventIds): array {
  if (!$eventIds) return [];
  $in = implode(',', array_map('intval', $eventIds));
  $map = [];
  foreach (rows("SELECT a.event_id, a.status, a.user_id, u.name FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.event_id IN ($in)") as $r) {
    $map[$r['event_id']][] = $r;
  }
  return $map;
}
function my_attendance(array $eventIds, int $userId): array {
  if (!$eventIds) return [];
  $in = implode(',', array_map('intval', $eventIds));
  $map = [];
  foreach (rows("SELECT event_id, status FROM attendance WHERE user_id = ? AND event_id IN ($in)", [$userId]) as $r) {
    $map[$r['event_id']] = $r['status'];
  }
  return $map;
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
 * Gibt es überhaupt eine öffentliche Seite, oder leitet sie weiter?
 *
 * Steht der Auftritt nach außen auf Weiterleitung, ist nichts zu
 * veröffentlichen: Ein Haken „Auf der Website zeigen" verspräche dann etwas,
 * das niemand zu sehen bekommt. Die gespeicherten Werte bleiben, wo sie sind —
 * wer zurückschaltet, findet seinen alten Stand vor (#288).
 */
function public_page_active(): bool {
  return setting('public_mode') !== 'redirect';
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
/**
 * Angemeldet bleiben (#262).
 *
 * Gemessen lief die Sitzung nach 24 Minuten Untätigkeit ab (gc_maxlifetime),
 * und das Cookie starb mit dem Fenster. Wer die App eine Woche nicht öffnete,
 * war abgemeldet, ohne es je gewollt zu haben — und ohne Sitzung holt die App
 * ihre Zahl nicht, frischt ihr Push-Abo nicht auf und merkt nicht, wenn der
 * Browser es erneuert hat. Genau daran verschwand die Zahl am Symbol.
 *
 * Das Cookie trägt „selector:validator". Der Server speichert den selector im
 * Klartext (er sucht die Zeile) und vom validator nur den SHA-256. Wer die
 * Datenbank hat, hat damit keine Anmeldung. Jede Benutzung tauscht den
 * validator aus: Ein abgehörtes Cookie ist nach dem nächsten Aufruf wertlos.
 */
/** Ein frisches Merkmal für dieses Gerät ausstellen. Nur nach vollständiger Anmeldung. */
function remember_issue(int $uid): void {
  if (is_demo()) return;   // in der Demo ist jeder Admin, und sie setzt sich stündlich zurück
  $selector = bin2hex(random_bytes(16));
  $validator = bin2hex(random_bytes(32));
  q('INSERT INTO login_tokens (user_id, selector, validator_hash, expires_at)
     VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL ? DAY))',
    [$uid, $selector, hash('sha256', $validator), REMEMBER_DAYS]);
  remember_cookie($selector . ':' . $validator, time() + REMEMBER_DAYS * 86400);
}

/** Das Cookie setzen oder löschen — an einer Stelle, damit die Flags nicht auseinanderlaufen. */
function remember_cookie(string $wert, int $bis): void {
  $overTls = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
  setcookie(REMEMBER_COOKIE, $wert, [
    'expires' => $bis, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => $overTls,
  ]);
}

/**
 * Das Merkmal prüfen und dabei erneuern. Gibt die Mitglieds-Kennung zurück
 * oder null; ein ungültiges Cookie wird weggeräumt, damit es nicht bei jedem
 * Aufruf erneut geprüft wird.
 */
function remember_check(): ?int {
  $roh = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
  if (!str_contains($roh, ':')) return null;
  [$selector, $validator] = explode(':', $roh, 2);
  $zeile = row('SELECT * FROM login_tokens WHERE selector = ? AND expires_at > NOW()', [$selector]);
  if (!$zeile || !hash_equals((string) $zeile['validator_hash'], hash('sha256', $validator))) {
    remember_cookie('', time() - 3600);
    if ($zeile) q('DELETE FROM login_tokens WHERE id = ?', [$zeile['id']]);   // abgelaufen oder gefälscht
    return null;
  }
  // Frischer validator und neue 90 Tage: Wer die App benutzt, bleibt drin.
  $neu = bin2hex(random_bytes(32));
  q('UPDATE login_tokens SET validator_hash = ?, last_used_at = NOW(),
     expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?',
    [hash('sha256', $neu), REMEMBER_DAYS, $zeile['id']]);
  remember_cookie($selector . ':' . $neu, time() + REMEMBER_DAYS * 86400);
  return (int) $zeile['user_id'];
}

/** Beim Abmelden dieses Gerät vergessen, beim Passwortwechsel alle. */
function remember_forget(?int $uid = null): void {
  $roh = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
  if (str_contains($roh, ':')) {
    q('DELETE FROM login_tokens WHERE selector = ?', [explode(':', $roh, 2)[0]]);
  }
  if ($uid !== null) q('DELETE FROM login_tokens WHERE user_id = ?', [$uid]);
  remember_cookie('', time() - 3600);
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
/**
 * Welche Verträge dieses Konto sehen darf (#309).
 *
 * null heißt alle. Für Konten von außen: die selbst angelegten und die, zu
 * denen die Band sie geholt hat — wie bei den Themen.
 */
function visible_contract_ids(?array $user): ?array {
  if (!$user || !is_outsider($user)) return null;
  $eigene = array_column(rows('SELECT id FROM contracts WHERE created_by = ?', [(int) $user['id']]), 'id');
  $geteilt = array_column(rows('SELECT contract_id FROM contract_access WHERE user_id = ?', [(int) $user['id']]), 'contract_id');
  return array_map('intval', array_unique([...$eigene, ...$geteilt]));
}

function may_see_contract(?array $user, int $contractId): bool {
  $erlaubt = visible_contract_ids($user);
  return $erlaubt === null || in_array($contractId, $erlaubt, true);
}

/** Wer von außen zu diesem Vertrag geholt wurde. */
function contract_outsiders(int $contractId): array {
  return rows('SELECT u.id, u.name FROM contract_access a JOIN users u ON u.id = a.user_id
               WHERE a.contract_id = ? ORDER BY u.name', [$contractId]);
}

/**
 * Sieht dieses Konto den Kalender mit Inhalt oder nur als belegt? (#309)
 *
 * Für alle in der Band: mit Inhalt. Für ein Konto von außen entscheidet die
 * Band per Einstellung — und selbst dann bleiben die eigenen Einträge offen,
 * sonst könnte ein Bookingagent mit seiner eigenen Anfrage nicht arbeiten.
 */
function event_scope_busy_only(?array $user): bool {
  return is_outsider($user) && setting('booking_event_scope', 'busy') !== 'all';
}

/**
 * Nimmt den Terminen ihren Inhalt, die dieses Konto nur als belegt sehen darf.
 *
 * Geschwärzt wird hier, wo die Zeilen gelesen werden, und nicht in den
 * Ansichten. Eine Terminliste zieht Kommentare, Zusagen, Gäste und Dateien mit;
 * eine Ansicht, die den Titel verbirgt, während ein Teilstück darunter den Ort
 * ausgibt, hat nichts verborgen. Was hier zurückkommt, ist gefahrlos.
 *
 * Zurück kommen die bereinigten Termine und die Kennungen derer, die geschwärzt
 * wurden — der Aufrufer wirft ihre Nebendaten damit gleich mit weg.
 */
function events_redact(array $events, ?array $user): array {
  if (!event_scope_busy_only($user)) return [$events, []];
  $geschwaerzt = [];
  foreach ($events as $i => $ev) {
    if ((int) ($ev['created_by'] ?? 0) === (int) $user['id']) continue;
    $geschwaerzt[] = (int) $ev['id'];
    $events[$i] = [
      'id' => (int) $ev['id'], 'date' => $ev['date'], 'type' => $ev['type'],
      'status' => $ev['status'], 'title' => t('ev_busy'),
      // Alles Weitere ist leer und nicht etwa ausgelassen: Die Ansichten lesen
      // diese Schlüssel, und ein fehlender Schlüssel wäre eine Warnung statt
      // einer leeren Zeile.
      'time' => '', 'time_meet' => '', 'time_end' => '', 'location' => '', 'notes' => '',
      'is_public' => 0, 'setlist_id' => null, 'responsible_id' => null, 'fee' => '',
      'invoice_no' => '', 'public_title' => '', 'public_link' => '', 'public_info' => '',
      'support_act' => '', 'venue_id' => null, 'venue_name' => '', 'venue_city' => '',
      'venue_address' => '', 'venue_postcode' => '', 'venue_lat' => null, 'venue_lng' => null,
      'pa_source' => '', 'light_source' => '', 'created_by' => $ev['created_by'] ?? null,
      'created_at' => $ev['created_at'] ?? null, 'redacted' => true,
    ];
  }
  return [$events, $geschwaerzt];
}

/**
 * Steht dieses Konto außerhalb der Band? (#308)
 *
 * Für solche Konten sind Themen nicht offen, sondern werden einzeln
 * freigegeben. Mitglieder, Ersatzleute und die Bandleitung sind innen — für sie
 * ändert sich nichts.
 */
function is_outsider(?array $user): bool {
  return ($user['role'] ?? '') === 'booking';
}

/**
 * Welche Themen dieses Konto sehen darf.
 *
 * Gibt null zurück, wenn alle — das ist der Normalfall und spart der
 * Themenliste jede zusätzliche Bedingung. Für Konten von außen kommt die Liste
 * aus zwei Quellen: was sie selbst aufgemacht haben, und wozu die Band sie
 * geholt hat.
 */
function visible_topic_ids(?array $user): ?array {
  if (!$user || !is_outsider($user)) return null;
  $eigene = array_column(rows('SELECT id FROM topics WHERE created_by = ?', [(int) $user['id']]), 'id');
  $geteilt = array_column(rows('SELECT topic_id FROM topic_access WHERE user_id = ?', [(int) $user['id']]), 'topic_id');
  return array_map('intval', array_unique([...$eigene, ...$geteilt]));
}

/**
 * Darf dieses Konto dieses eine Thema sehen?
 *
 * Das Recht kommt zuerst: „kein Außenstehender" heißt nicht „darf den Chat".
 * Eine Aushilfe ist Mitglied und hat den Bereich trotzdem nicht — ohne diese
 * Zeile ging ihr der Anriss eines Beitrags aufs Telefon, zu einer Seite, die
 * sie nicht öffnen kann. Die Prüfung steht hier und nicht bei den Aufrufern,
 * damit Liste, Zahl und Mitteilung nicht wieder auseinanderlaufen.
 */
function may_see_topic(?array $user, int $topicId): bool {
  if (!perm_allows($user, 'themen')) return false;
  $erlaubt = visible_topic_ids($user);
  return $erlaubt === null || in_array($topicId, $erlaubt, true);
}

/**
 * Ein Beitrag im Chat: anlegen und die Mitteilung dazu (#316).
 *
 * Beides an einer Stelle, weil es zwei Wege zu einem Beitrag gibt — das neue
 * Thema mit erstem Beitrag und die Antwort. Getrennt gepflegt, hätte der eine
 * Weg früher oder später wieder still geschrieben.
 */
function topic_post_add(int $topicId, array $author, string $text): void {
  q('INSERT INTO topic_posts (topic_id, user_id, text) VALUES (?,?,?)',
    [$topicId, (int) $author['id'], $text]);
  $titel = (string) (row('SELECT title FROM topics WHERE id = ?', [$topicId])['title'] ?? '');
  $wer = (string) $author['name'];
  // Der Anriss, nicht der ganze Text — eine Mitteilung ist kein Chatfenster.
  $anriss = mb_strlen($text) > 120 ? mb_substr($text, 0, 119) . '…' : $text;
  push_notify('topics', (int) $author['id'], fn(string $lang): array => [
    'title' => push_t($lang, 'push_chat_title') . ' · ' . $titel,
    'body'  => $wer . ': ' . $anriss,
    // „#neu" ist die Marke über dem ersten ungelesenen Beitrag. Wer alles
    // gelesen hat, landet oben — dort steht dann auch nichts Neues (#322).
    'url'   => '/intern/themen/' . $topicId . '#neu',
  ], 0, $topicId);
}

/**
 * Ungelesene Beiträge je Thema: Themennummer => Anzahl (#317).
 *
 * Eigene Beiträge zählen nie — man liest nicht, was man selbst geschrieben hat.
 * Wer ein Thema noch nie geöffnet hat, bekommt nicht die ganze Geschichte
 * aufgetischt: Dann gilt der Tag, an dem sein Konto entstand. Sonst stünde vor
 * einem neuen Mitglied am ersten Tag eine dreistellige Zahl.
 *
 * Konten von außen zählen nur, was sie auch sehen dürfen.
 */
function topic_unread(?array $user): array {
  $uid = (int) ($user['id'] ?? 0);
  if (!$uid) return [];
  $sichtbar = visible_topic_ids($user);
  if ($sichtbar === []) return [];
  $nurDiese = '';
  $werte = [$uid, $uid, $uid];
  if ($sichtbar !== null) {
    $nurDiese = ' AND p.topic_id IN (' . implode(',', array_fill(0, count($sichtbar), '?')) . ')';
    $werte = [...$werte, ...$sichtbar];
  }
  // Ein Beitrag ohne Verfasser (ausgetretenes Mitglied) bleibt ein fremder
  // Beitrag: „NULL <> 5" ist weder wahr noch falsch und fiele sonst heraus.
  //
  // Die beiden Grenzen sind bewusst verschieden: Ab dem Beitritt zählt alles
  // mit, was in derselben Sekunde oder später geschrieben wurde — wer dazukommt,
  // während jemand tippt, soll den Beitrag sehen. Nach dem Lesen zählt nur, was
  // danach kam, sonst stünde der gerade gelesene Beitrag gleich wieder als neu
  // da, wenn er in derselben Sekunde entstand.
  $zeilen = rows(
    'SELECT p.topic_id, COUNT(*) AS neu
       FROM topic_posts p
       LEFT JOIN topic_reads r ON r.topic_id = p.topic_id AND r.user_id = ?
       JOIN users u ON u.id = ?
      WHERE (p.user_id IS NULL OR p.user_id <> ?)
        AND ((r.seen_at IS NULL  AND p.created_at >= u.created_at)
          OR (r.seen_at IS NOT NULL AND p.created_at >  r.seen_at))' . $nurDiese
    . ' GROUP BY p.topic_id', $werte);
  $offen = [];
  foreach ($zeilen as $z) $offen[(int) $z['topic_id']] = (int) $z['neu'];
  return $offen;
}

/**
 * Der erste Beitrag, den dieses Konto in diesem Thema noch nicht gelesen hat,
 * oder 0 (#318). Damit springt der Weg aus der Übersicht genau an die Stelle,
 * an der man aufgehört hat, statt an den Anfang eines langen Verlaufs.
 *
 * Die Bedingungen sind dieselben wie beim Zählen — stünden sie hier anders,
 * zeigte die Marke irgendwann auf einen anderen Beitrag, als die Zahl meint.
 */
function topic_first_unread(?array $user, int $topicId): int {
  $uid = (int) ($user['id'] ?? 0);
  if (!$uid || !may_see_topic($user, $topicId)) return 0;
  $z = row('SELECT p.id
              FROM topic_posts p
              LEFT JOIN topic_reads r ON r.topic_id = p.topic_id AND r.user_id = ?
              JOIN users u ON u.id = ?
             WHERE p.topic_id = ?
               AND (p.user_id IS NULL OR p.user_id <> ?)
               AND ((r.seen_at IS NULL  AND p.created_at >= u.created_at)
                 OR (r.seen_at IS NOT NULL AND p.created_at >  r.seen_at))
             ORDER BY p.created_at, p.id LIMIT 1', [$uid, $uid, $topicId, $uid]);
  return (int) ($z['id'] ?? 0);
}

/** Dieses Thema ist bis jetzt gelesen. */
function topic_mark_read(int $topicId, int $userId): void {
  q('INSERT INTO topic_reads (user_id, topic_id, seen_at) VALUES (?,?,NOW(3))
     ON DUPLICATE KEY UPDATE seen_at = NOW(3)', [$userId, $topicId]);
}

/** Wer von außen zu diesem Thema geholt wurde, mit Namen. */
function topic_outsiders(int $topicId): array {
  return rows('SELECT u.id, u.name FROM topic_access a JOIN users u ON u.id = a.user_id
               WHERE a.topic_id = ? ORDER BY u.name', [$topicId]);
}

/** Die beiden Wege, einen Auftrag zu vereinbaren (#312). */
const CONTRACT_FLOWS = ['direkt', 'angebot'];

/**
 * Wird der Angebotsschritt überhaupt gebraucht?
 *
 * Nein, wenn die Band den Vertrag direkt schickt — dann verschwindet der
 * Bereich aus Menü und Hilfe, denn ein Bereich, den niemand benutzt, ist eine
 * Sache mehr, die erklärt werden muss.
 *
 * Doch, sobald schon ein Angebot existiert. Wer umstellt, soll nicht verlieren,
 * was er geschrieben hat; die Zeilen blieben sonst in der Datenbank und wären
 * über kein Menü mehr erreichbar.
 */
function quote_step_active(): bool {
  if (setting('contract_flow', 'direkt') === 'angebot') return true;
  static $vorhanden = null;
  if ($vorhanden === null) $vorhanden = row('SELECT 1 FROM quotes LIMIT 1') !== null;
  return $vorhanden;
}

// ---------- Verträge (#303) ----------

/** Was ein Vertrag durchläuft. Mehr Zustände braucht niemand. */
const CONTRACT_STATUSES = ['entwurf', 'verschickt', 'unterschrieben'];

/**
 * Die Platzhalter der Vertragsvorlage. Der Schlüssel steht im Text in
 * geschweiften Klammern, der Wert kommt aus Termin, Ort, Veranstalter und
 * Angebot. Was nicht bekannt ist, bleibt als Punktreihe stehen — ein Vertrag
 * mit einer sichtbaren Lücke ist besser als einer, der eine Lücke verschweigt.
 */
function contract_values(array $vertrag): array {
  $punkte = '.....................';
  $ort = trim(event_place($vertrag));
  $spiel = trim((string) $vertrag['play_from']) !== '' && trim((string) $vertrag['play_to']) !== ''
    ? $vertrag['play_from'] . '–' . $vertrag['play_to'] . ' Uhr' : $punkte;
  return [
    '{band}' => setting('band_name') ?: $punkte,
    '{veranstalter}' => trim((string) ($vertrag['promoter_name'] ?? '')) ?: $punkte,
    '{veranstalter_anschrift}' => trim(implode(', ', array_filter([
      trim((string) ($vertrag['promoter_street'] ?? '')),
      trim(trim((string) ($vertrag['promoter_postcode'] ?? '')) . ' ' . trim((string) ($vertrag['promoter_city'] ?? ''))),
    ]))) ?: $punkte,
    '{ort}' => $ort !== '' ? $ort : $punkte,
    '{datum}' => !empty($vertrag['event_date']) ? fmt_date($vertrag['event_date']) : $punkte,
    '{spielzeit}' => $spiel,
    '{einlass}' => trim((string) $vertrag['get_in']) !== '' ? $vertrag['get_in'] . ' Uhr' : $punkte,
    '{gage}' => (int) $vertrag['fee_cents'] > 0 ? fmt_money((int) $vertrag['fee_cents']) : $punkte,
    '{vertragsnummer}' => trim((string) $vertrag['contract_no']) ?: $punkte,
    '{heute}' => fmt_date(date('Y-m-d')),
  ];
}

/** Die Vorlage: was die Band eingetragen hat, sonst die mitgelieferte. */
function contract_template(): string {
  $eigen = (string) setting('contract_text');
  return trim($eigen) !== '' ? $eigen : t('contract_template');
}

/** Aus der Vorlage wird der Wortlaut dieses Vertrages. */
function contract_render(array $vertrag): string {
  return strtr(contract_template(), contract_values($vertrag));
}

/** Ein Vertrag mit allem, was sein Wortlaut braucht. */
function contract_full(int $id): ?array {
  return row('SELECT c.*, p.name AS promoter_name, p.contact_name AS promoter_contact,
                     p.email AS promoter_email, p.street AS promoter_street,
                     p.postcode AS promoter_postcode, p.city AS promoter_city,
                     e.title AS event_title, e.date AS event_date, ' . EVENT_PLACE_COLS . '
              FROM contracts c
              LEFT JOIN promoters p ON p.id = c.promoter_id
              LEFT JOIN events e ON e.id = c.event_id ' . EVENT_PLACE_JOIN . '
              WHERE c.id = ?', [$id]);
}

/** Der Vertragsstand je Termin, für die Terminkarte. */
function contract_status_by_event(array $eventIds): array {
  if (!$eventIds) return [];
  $marken = implode(',', array_fill(0, count($eventIds), '?'));
  $karte = [];
  foreach (rows("SELECT id, event_id, status FROM contracts WHERE event_id IN ($marken) ORDER BY id", $eventIds) as $z) {
    $karte[(int) $z['event_id']] = $z;
  }
  return $karte;
}

function contract_status_label(string $status): string {
  return t('contract_status_' . $status);
}

// ---------- Kalkulation (#302) ----------
//
// Gerechnet wird durchgehend in Cent. Ein Angebot, das sich um einen Cent nicht
// aufaddiert, nimmt ein Veranstalter nicht ernst — und mit Fließkomma passiert
// genau das.

/** Die Sätze der Preisliste, so wie sie in den Einstellungen stehen. */
const QUOTE_RATES = [
  'quote_base_cents', 'quote_hour_cents', 'quote_km_cents', 'quote_km_free',
  'quote_night_cents', 'quote_pa_cents', 'quote_min_cents', 'quote_discount_private',
];

/** „90" wird zu „1,5 Std", „45" zu „45 Min" — so, wie man es sagt. */
function quote_time_text(int $minuten): string {
  if ($minuten <= 0) return '';
  if ($minuten < 60) return $minuten . ' ' . t('quote_unit_min');
  $stunden = $minuten / 60;
  $text = fmod($stunden, 1.0) === 0.0 ? (string) (int) $stunden : number_format($stunden, 1, ',', '');
  return $text . ' ' . t('quote_unit_hour');
}

/**
 * Die Spielzeit eines Termins in Minuten, aus Beginn und Ende — dieselbe
 * Spanne, die später im Vertrag unter „Spieldauer" steht. Über Mitternacht
 * hinaus zählt der nächste Tag mit; ein Auftritt von 22 bis 1 Uhr dauert drei
 * Stunden und nicht minus einundzwanzig.
 */
function quote_minutes_from_event(array $event): int {
  $von = trim((string) ($event['time'] ?? ''));
  $bis = trim((string) ($event['time_end'] ?? ''));
  if ($von === '' || $bis === '') return 0;
  [$vs, $vm] = array_map('intval', array_pad(explode(':', $von), 2, 0));
  [$bs, $bm] = array_map('intval', array_pad(explode(':', $bis), 2, 0));
  $minuten = ($bs * 60 + $bm) - ($vs * 60 + $vm);
  return $minuten < 0 ? $minuten + 24 * 60 : $minuten;
}

/**
 * Die Posten, die sich aus der Preisliste ergeben.
 *
 * Die erste Stunde kostet den Grundpreis, egal ob gespielt oder nicht: Auf- und
 * Abbau, Soundcheck, Hin- und Rückfahrtzeit stecken darin, und die fallen auch
 * für einen 45-Minuten-Auftritt an. Alles darüber wird anteilig gerechnet —
 * eine halbe Stunde kostet die Hälfte, denn aufzurunden wäre eine
 * Preiserhöhung, die niemand vereinbart hat.
 */
function quote_standard_lines(array $a): array {
  $zeilen = [];
  $minuten = max(0, (int) ($a['play_minutes'] ?? 0));
  if ($minuten > 0) {
    $zeilen[] = ['label' => sprintf(t('quote_line_base'), quote_time_text(min(60, $minuten))),
                 'amount_cents' => (int) setting('quote_base_cents')];
    $weitere = $minuten - 60;
    if ($weitere > 0) {
      $zeilen[] = ['label' => sprintf(t('quote_line_hours'), quote_time_text($weitere)),
                   'amount_cents' => (int) round((int) setting('quote_hour_cents') * $weitere / 60)];
    }
  }
  // Der Weg zählt einfach, berechnet wird er doppelt — hin und zurück.
  $km = max(0, (int) ($a['km'] ?? 0));
  $frei = max(0, (int) setting('quote_km_free'));
  if ($km > $frei && (int) setting('quote_km_cents') > 0) {
    $zeilen[] = ['label' => sprintf(t('quote_line_travel'), $km - $frei),
                 'amount_cents' => ($km - $frei) * 2 * (int) setting('quote_km_cents')];
  }
  $naechte = max(0, (int) ($a['nights'] ?? 0));
  if ($naechte > 0 && (int) setting('quote_night_cents') > 0) {
    $zeilen[] = ['label' => sprintf(t('quote_line_nights'), $naechte),
                 'amount_cents' => $naechte * (int) setting('quote_night_cents')];
  }
  if (!empty($a['own_pa']) && (int) setting('quote_pa_cents') > 0) {
    $zeilen[] = ['label' => t('quote_line_pa'), 'amount_cents' => (int) setting('quote_pa_cents')];
  }
  return $zeilen;
}

/**
 * Der Steuersatz fürs Angebot. Unter der Kleinunternehmerregelung steht keine
 * Steuerzeile auf der Rechnung, sondern der Hinweis auf § 19 UStG — welcher
 * Satz sonst gilt, hat die Band in den Einstellungen hinterlegt. Entschieden
 * wird das von ihrer Steuerberatung, nicht von diesem Programm.
 */
function quote_vat_rate(): int {
  return setting('tax_small_business', '0') === '1' ? 0 : (int) setting('tax_vat_rate', '19');
}

/**
 * Was ein Rabatt in Cent ausmacht — eingegeben als Prozentsatz oder als die
 * Endsumme, die der Kunde zahlt. Die Endsumme versteht sich einschließlich
 * Steuer, denn genau die Zahl nennt man am Telefon.
 *
 * Gibt [Cent, Fehlerschlüssel|null] zurück. Eine Endsumme über dem Listenpreis
 * ist kein Rabatt: Dann stünden im Angebot ermäßigte Preise, die zusammen mehr
 * ergeben als die Preisliste.
 */
function quote_discount_cents(string $modus, string $prozent, string $ziel, int $vorRabatt): array {
  if ($modus === 'percent') {
    $wert = (float) str_replace(',', '.', trim($prozent));
    if ($wert <= 0) return [0, null];
    if ($wert > 100) return [0, 'fl_quote_discount_high'];
    return [(int) round($vorRabatt * $wert / 100), null];
  }
  if ($modus === 'total') {
    $zielCents = price_to_cents($ziel);
    if ($zielCents === null || $zielCents < 0) return [0, 'fl_quote_total_unclear'];
    $satz = quote_vat_rate();
    $zielNetto = $satz > 0 ? (int) round($zielCents * 100 / (100 + $satz)) : $zielCents;
    if ($zielNetto > $vorRabatt) return [0, 'fl_quote_total_above'];
    return [$vorRabatt - $zielNetto, null];
  }
  return [0, null];
}

/**
 * Verteilt einen Rabatt auf die Posten.
 *
 * Gebraucht, wenn der Rabatt nicht ausgewiesen werden soll: Eine kleinere
 * Endsumme unter unveränderten Posten fällt jedem auf, der nachrechnet, und
 * lässt die Band schlampig aussehen. Der Restcent landet auf dem größten
 * Posten — irgendwo muss er hin, und dort fällt er am wenigsten auf.
 */
function quote_spread_discount(array $posten, int $rabatt): array {
  $summe = array_sum(array_map(fn($p) => (int) $p['amount_cents'], $posten));
  if ($rabatt <= 0 || $summe <= 0) return $posten;

  $groesster = 0;
  foreach ($posten as $i => $p) {
    if ((int) $p['amount_cents'] > (int) $posten[$groesster]['amount_cents']) $groesster = $i;
  }
  $verteilt = 0;
  foreach ($posten as $i => $p) {
    $anteil = (int) floor($rabatt * (int) $p['amount_cents'] / $summe);
    $posten[$i]['amount_cents'] = (int) $p['amount_cents'] - $anteil;
    $verteilt += $anteil;
  }
  $posten[$groesster]['amount_cents'] -= $rabatt - $verteilt;
  return $posten;
}

/**
 * Alle Summen eines Angebots. Der Zuschlag zählt wie ein Posten, der Rabatt
 * geht von allem ab, und die Steuer kommt zuletzt obendrauf.
 */
function quote_totals(array $angebot, array $posten): array {
  $zwischen = array_sum(array_map(fn($p) => (int) $p['amount_cents'], $posten));
  $zuschlag = (int) round($zwischen * (float) ($angebot['surcharge_percent'] ?? 0) / 100);
  $vorRabatt = $zwischen + $zuschlag;
  $rabatt = max(0, min($vorRabatt, (int) ($angebot['discount_cents'] ?? 0)));
  $netto = $vorRabatt - $rabatt;
  $satz = quote_vat_rate();
  $ust = (int) round($netto * $satz / 100);
  return [
    'items' => $zwischen, 'surcharge' => $zuschlag, 'before_discount' => $vorRabatt,
    'discount' => $rabatt, 'net' => $netto, 'vat' => $ust, 'total' => $netto + $ust,
    'vat_rate' => $satz,
    'discount_percent' => $vorRabatt > 0 ? round($rabatt * 100 / $vorRabatt, 1) : 0.0,
  ];
}

/**
 * Die Zeilen, wie sie im Angebot stehen — mit dem Zuschlag als eigener Zeile
 * und, wenn der Rabatt nicht ausgewiesen wird, bereits ermäßigten Preisen.
 */
function quote_display_lines(array $angebot, array $posten): array {
  $summen = quote_totals($angebot, $posten);
  $zeilen = array_map(fn($p) => ['label' => (string) $p['label'], 'amount_cents' => (int) $p['amount_cents']], $posten);
  if ($summen['surcharge'] !== 0) {
    $zeilen[] = ['label' => ($angebot['surcharge_label'] ?? '') !== '' ? (string) $angebot['surcharge_label'] : t('quote_surcharge'),
                 'amount_cents' => $summen['surcharge']];
  }
  return empty($angebot['discount_show']) ? quote_spread_discount($zeilen, $summen['discount']) : $zeilen;
}

/**
 * Schreibt die Posten eines Angebots neu: erst die aus der Preisliste
 * gerechneten, dann die von Hand eingetragenen. Sie werden bei jedem Speichern
 * neu gebildet und bleiben danach stehen — ändert die Band später ihre Preise,
 * sieht ein verschicktes Angebot trotzdem aus wie beim Verschicken.
 */
function quote_save_items(int $quoteId, array $angebot, array $frei): void {
  q('DELETE FROM quote_items WHERE quote_id = ?', [$quoteId]);
  $sort = 0;
  foreach ([...quote_standard_lines($angebot), ...$frei] as $zeile) {
    $text = trim((string) ($zeile['label'] ?? ''));
    if ($text === '') continue;
    q('INSERT INTO quote_items (quote_id, label, amount_cents, sort) VALUES (?,?,?,?)',
      [$quoteId, mb_substr($text, 0, 190), (int) ($zeile['amount_cents'] ?? 0), $sort++]);
  }
}

/** Die von Hand eingetragenen Zeilen aus dem Formular, leere fallen weg. */
function quote_free_lines_from_post(array $post): array {
  $frei = [];
  foreach ((array) ($post['extra_label'] ?? []) as $i => $text) {
    $text = trim((string) $text);
    $betrag = price_to_cents((string) (($post['extra_amount'] ?? [])[$i] ?? ''));
    if ($text === '' || $betrag === null) continue;
    $frei[] = ['label' => $text, 'amount_cents' => $betrag];
  }
  return $frei;
}

/** Die Posten eines Angebots in ihrer Reihenfolge. */
function quote_items(int $quoteId): array {
  return rows('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort, id', [$quoteId]);
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
/**
 * Der Kurzhinweis aus den Song-Notizen für den Ausdruck — oder nichts.
 *
 * Genommen wird die ERSTE Zeile: Notizen wachsen nach unten (die Herkunft eines
 * Covers, ein Hinweis von letztem Jahr, was auch immer), und was auf der Bühne
 * gilt, schreibt man oben hin. Eine Herkunftszeile („Original: Alphaville,
 * 1984") ist Dokumentation und kein Zuruf — die gehört nicht aufs Notenpult
 * (#251). Über 40 Zeichen bleibt es leer, weil es die Zeile sprengt.
 */
function song_note_cue(string $notes): string {
  $erste = trim((preg_split('~\R~', $notes) ?: [''])[0]);
  if ($erste === '' || mb_strlen($erste) > 40) return '';
  // „Original:" schreibt die Band selbst — deshalb auch die Wörter der anderen
  // Sprachen, in denen die Anwendung bedient wird.
  return preg_match('~^(original|originale|origineel)\s*:~ui', $erste) ? '' : $erste;
}

/**
 * Zu welchen dieser Lieder gibt es überhaupt einen Notizzettel? [song_id => true]
 *
 * Für die Symbole in Liste und Setliste: Ein Knopf, der auf eine leere Seite
 * führt, ist schlimmer als kein Knopf (#250). Eine Abfrage für alle Lieder der
 * Seite, nicht eine je Zeile.
 */
function songs_with_chords(array $songIds): array {
  $ids = array_values(array_unique(array_map('intval', array_filter($songIds))));
  if (!$ids) return [];
  $platzhalter = implode(',', array_fill(0, count($ids), '?'));
  $out = [];
  foreach (rows("SELECT DISTINCT song_id FROM song_chords
                 WHERE TRIM(content) <> '' AND song_id IN ($platzhalter)", $ids) as $r) {
    $out[(int) $r['song_id']] = true;
  }
  return $out;
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
  // PLZ und Ort gehören zusammen in ein Feld: „34549 Edertal" ist eine Angabe,
  // und getrennt durch Komma sucht die Karten-App zwei.
  $ort = trim(trim((string) ($v['postcode'] ?? '')) . ' ' . trim((string) ($v['city'] ?? '')));
  return navi_dest($v['name'] ?? '', $v['address'] ?? '', $ort);
}

/**
 * Wie oft an einem Ort etwas anstand: gespielt, geplant, angefragt, abgesagt
 * (#315). Erst mit den Absagen wird die Zahl ehrlich — ein Ort, der dreimal
 * anfragt und dreimal absagt, sähe sonst aus wie einer ohne Geschichte.
 * „reserviert" und „blockiert" zählen nicht mit: Da steht noch nicht fest, ob
 * es überhaupt um diesen Ort geht.
 * Leere Zählstände fallen weg, damit an einem Ort mit einem einzigen Auftritt
 * nicht drei Nullen stehen.
 */
function venue_stats(array $events, string $today): array {
  $zahlen = ['played' => 0, 'planned' => 0, 'asked' => 0, 'cancelled' => 0];
  foreach ($events as $ev) {
    $feld = match ($ev['status']) {
      'abgesagt'   => 'cancelled',
      'angefragt'  => 'asked',
      'bestaetigt' => $ev['date'] < $today ? 'played' : 'planned',
      default      => null,
    };
    if ($feld !== null) $zahlen[$feld]++;
  }
  return array_filter($zahlen);
}

/**
 * Der Weg zu einer Terminkarte — mit den Filtern, die sie überhaupt erst
 * sichtbar machen (#322). Die Liste zeigt ohne Zutun weder Vergangenes noch
 * Abgesagtes; ein Anker auf eine Karte, die gar nicht auf der Seite steht,
 * führt stillschweigend an den Listenanfang. Und genau das sind die beiden
 * häufigen Fälle: ein Kommentar zum Auftritt vom letzten Wochenende und eine
 * Absage.
 */
function event_url(array $ev): string {
  $filter = [];
  if ((string) $ev['date'] < date('Y-m-d')) $filter[] = 'alle=1';
  if ((string) ($ev['status'] ?? '') === 'abgesagt') $filter[] = 'abgesagt=1';
  return '/intern/termine' . ($filter ? '?' . implode('&', $filter) : '') . '#ev' . (int) $ev['id'];
}

/**
 * Der Ort eines Termins in einer Zeile, für Ausgaben, die außerhalb der App
 * gelesen werden — Kalendereintrag, öffentliche Seite (#286). Bevorzugt den
 * hinterlegten Veranstaltungsort mit Anschrift („Markthalle Hamburg,
 * Klosterwall 11, 20095 Hamburg"); erst wenn keiner verknüpft ist, den
 * Freitext. Erwartet die Ort-Spalten als venue_* an der Termin-Zeile.
 */
function event_place(array $ev): string {
  if (trim((string) ($ev['venue_name'] ?? '')) !== '') {
    $ort = trim(trim((string) ($ev['venue_postcode'] ?? '')) . ' ' . trim((string) ($ev['venue_city'] ?? '')));
    return implode(', ', array_filter([trim($ev['venue_name']), trim((string) ($ev['venue_address'] ?? '')), $ort]));
  }
  return trim((string) ($ev['location'] ?? ''));
}

/** Die Ort-Spalten für event_place(), zum Anhängen an „FROM events e". */
const EVENT_PLACE_JOIN = 'LEFT JOIN venues v ON v.id = e.venue_id';
const EVENT_PLACE_COLS = 'v.name AS venue_name, v.address AS venue_address, v.postcode AS venue_postcode, v.city AS venue_city, v.lat AS venue_lat, v.lng AS venue_lng';

/**
 * Trennt aus einem gewachsenen Adresstext die PLZ heraus.
 *
 * Zurück kommt [Rest, PLZ, Ort]. Getrennt wird an Zeilenumbrüchen und Kommas —
 * mit beidem trennt man „Straße" von „PLZ Ort". Herausgeholt wird aber nur ein
 * Stück, das für sich genommen zweifelsfrei eine PLZ-Angabe ist: „34549",
 * „34549 Edertal", „D-34549 Edertal". Alles andere bleibt unangetastet — lieber
 * ungeteilt als falsch geteilt.
 */
function address_split_postcode(string $address): array {
  $teile = preg_split('~\R|,~', $address) ?: [];
  foreach ($teile as $i => $teil) {
    if (!preg_match('~^\s*(?:[A-Z]{1,2}-)?(\d{4,5})\s*([^,\d]*)$~u', $teil, $m)) continue;
    unset($teile[$i]);
    $rest = implode("\n", array_filter(array_map('trim', $teile), fn($z) => $z !== ''));
    return [$rest, $m[1], trim($m[2])];
  }
  return [trim($address), '', ''];
}

// Web-Fallback für den Navi-Link (Desktop und ohne JavaScript): OpenStreetMap
// zeigt den Ort — bewusst nicht Google. Auf dem Handy ersetzt route.js den Link
// durch die native Karten-App: iPhone → Apple Karten, Android → die als Standard
// eingestellte App (geo:). Die Anwendung selbst ruft dabei nichts ab.
function navi_web(string $dest): string {
  return $dest === '' ? '' : 'https://www.openstreetmap.org/search?query=' . rawurlencode($dest);
}

/**
 * Ein Navi-Link für Texte, die außerhalb der App gelesen werden — der
 * Kalendereintrag vor allem (#286). Dort kann niemand fragen, welche App
 * navigieren soll (das tut route.js nur in der App); es muss ein Link sein,
 * der auf Android wie iPhone in eine Navigation führt. Das kann von den
 * Web-Adressen nur die von Google Maps: Sie öffnet die App, wo sie ist, und
 * sonst die Karte im Browser. Geöffnet wird erst, wenn jemand tippt — die
 * Anwendung selbst ruft nichts ab.
 */
function navi_directions(string $dest): string {
  return $dest === '' ? '' : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest);
}

// Adresse → Treffer mit Koordinaten, über OpenStreetMap/Nominatim. Eine Anfrage
// je Aufruf, mit User-Agent, wie es die Nominatim-Richtlinie verlangt (ohne den
// antwortet der Dienst mit 403). Fehler (Dienst weg, Timeout) ergeben eine leere
// Liste — die Oberfläche meldet dann „keine Treffer". Aufgerufen nur, wenn die
// Band Geocoding erlaubt hat.
function geocode_request(array $params): array {
  $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=3&'
    . http_build_query($params);
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
    // Aus den Bestandteilen die einzelnen Felder bauen, damit ein Treffer
    // Straße, PLZ und Ort getrennt füllen kann (#249). Ohne Bestandteile bleibt
    // der Anzeigename als Adresse — besser als ein leeres Feld.
    $a = is_array($r['address'] ?? null) ? $r['address'] : [];
    $city = (string) ($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? '');
    $street = trim(($a['road'] ?? '') . ' ' . ($a['house_number'] ?? ''));
    $out[] = [
      'name' => (string) $r['display_name'],
      'address' => $street !== '' ? $street : (string) $r['display_name'],
      'postcode' => (string) ($a['postcode'] ?? ''),
      'city' => $city,
      'lat' => (string) $r['lat'],
      'lng' => (string) $r['lon'],
      'searched' => geocode_label($params),
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
  $treffer = geocode_request(['q' => $q]);
  $worte = preg_split('~[\s,]+~u', $q) ?: [];
  for ($i = 0; !$treffer && $i < GEO_RETRIES && count($worte) > 1; $i++) {
    array_shift($worte);
    // Abstand halten, statt drei Anfragen in einem Wimpernschlag zu stellen.
    usleep(1100000);
    $treffer = geocode_request(['q' => implode(' ', $worte)]);
  }
  return $treffer;
}

/**
 * Suche mit den Adressfeldern, jedes im Feld, in das es gehört (#249).
 *
 * Nominatim kennt neben dem Freitext eine feldweise Suche (street, postalcode,
 * city). Die ist hier die richtige: Der Saalname muss dann gar nicht mitgesucht
 * werden, und die PLZ wird als PLZ verstanden statt als Wort, das irgendwo
 * treffen muss. Gemessen an „Zündstoff, Am Weiher 1, 34549 Edertal": als
 * Freitext kein Treffer, feldweise ohne den Namen genau einer.
 *
 * Der Rückzug lässt weg, was am ehesten falsch ist — erst die Hausnummer samt
 * Straße, dann die PLZ. Ein Treffer auf Ortsebene ist zum Hinfahren genug.
 */
function geocode_address(string $street, string $postcode, string $city): array {
  $street = trim(preg_replace(['~\R~u', '~\s+~u'], [', ', ' '], $street) ?? '');
  $postcode = trim($postcode);
  $city = trim($city);
  $stufen = [];
  if ($street !== '' && ($postcode !== '' || $city !== '')) {
    $stufen[] = ['street' => $street, 'postalcode' => $postcode, 'city' => $city];
    if ($city !== '' && $postcode !== '') $stufen[] = ['street' => $street, 'city' => $city];
  }
  if ($postcode !== '' && $city !== '') $stufen[] = ['postalcode' => $postcode, 'city' => $city];
  if ($city !== '') $stufen[] = ['city' => $city];
  elseif ($postcode !== '') $stufen[] = ['postalcode' => $postcode];
  foreach (array_slice($stufen, 0, 1 + GEO_RETRIES) as $i => $stufe) {
    // Abstand halten: Nominatim erlaubt etwa eine Anfrage je Sekunde.
    if ($i > 0) usleep(1100000);
    $treffer = geocode_request(array_filter($stufe, fn($v) => $v !== ''));
    if ($treffer) return $treffer;
  }
  return [];
}

/** Wonach gefragt wurde, in einer Zeile — steht am Treffer, damit niemand den Ort für den Saal hält. */
function geocode_label(array $params): string {
  return implode(', ', array_filter([
    $params['q'] ?? '', $params['street'] ?? '',
    trim(($params['postalcode'] ?? '') . ' ' . ($params['city'] ?? '')),
  ]));
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
 * Die Bilder des Erscheinungsbilds: Formularfeld => Einstellung, Schlagwort in
 * der Galerie. Logo, Hintergrund und Favicon stehen auf der öffentlichen Seite,
 * das Begrüßungsbild neben der Anrede auf der Übersicht (#267). Die letzten
 * zwei gehören aufs Papier: ein Logo für weißen Grund und das Wasserzeichen
 * hinter dem Text. Beide gab es als Einstellung schon, nur ohne Bedienung —
 * setzen ließen sie sich bisher nur von Hand in der Datenbank (#304).
 */
const BRANDING_SLOTS = [
  'logo'       => ['key' => 'logo_file',           'tag' => 'logo'],
  'background' => ['key' => 'background_file',     'tag' => 'hintergrund'],
  'favicon'    => ['key' => 'favicon_file',        'tag' => 'favicon'],
  'welcome'    => ['key' => 'welcome_file',        'tag' => 'begrüßung'],
  'printlogo'  => ['key' => 'print_logo_file',     'tag' => 'druck-logo'],
  'watermark'  => ['key' => 'print_watermark_file', 'tag' => 'wasserzeichen'],
];

/**
 * Die Druckbögen, die es gibt. Der Schlüssel steht im Bogen selbst und in den
 * beiden Listen unten — mehr braucht ein neues Dokument nicht, um Logo und
 * Wasserzeichen zu erben.
 */
/**
 * Das Zeichen eines Bereichs. Es steht im Menü und auf der Hilfeseite — an
 * einer Stelle, damit beide dasselbe zeigen: Wer im Menü nach dem Zelt sucht,
 * findet in der Hilfe sonst eine Eintrittskarte und glaubt, er sei falsch.
 */
const MODULE_ICONS = [
  'termine' => '📅', 'songs' => '🎵', 'setlists' => '🎤', 'orte' => '📍',
  'abwesenheiten' => '🏖', 'aufgaben' => '✅', 'themen' => '💬', 'kasse' => '💰',
  'equipment' => '🎛', 'rider' => '📋', 'fotos' => '📷', 'post' => '✉',
  'musik' => '🎬', 'downloads' => '⬇', 'mitglieder' => '👥', 'gaeste' => '🎟',
  'angebote' => '🧮', 'vertraege' => '✍', 'mailversand' => '📨',
];

/**
 * „Ich möchte …" — der Einstieg in die Hilfe für alle, die nicht wissen, wie
 * der Bereich heißt, in dem ihre Frage wohnt (#305). Je Eintrag: der Bereich,
 * dessen Recht man dafür braucht, und die Sprungmarke.
 *
 * Wer ein Recht nicht hat, sieht den Eintrag nicht — sonst schickt die Hilfe
 * jemanden zu einer Seite, die ihm verschlossen ist.
 */
const HELP_TASKS = [
  ['termine', 'help_task_answer', 'hilfe-termine'],
  ['termine', 'help_task_newgig', 'hilfe-termine'],
  ['setlists', 'help_task_setlist', 'hilfe-setlists'],
  ['rider', 'help_task_rider', 'hilfe-rider'],
  ['angebote', 'help_task_quote', 'hilfe-angebote'],
  ['gaeste', 'help_task_guest', 'hilfe-gaeste'],
  ['kasse', 'help_task_money', 'hilfe-kasse'],
  ['abwesenheiten', 'help_task_absence', 'hilfe-abwesenheiten'],
  ['fotos', 'help_task_photo', 'hilfe-fotos'],
  ['mitglieder', 'help_task_member', 'hilfe-mitglieder'],
  ['', 'help_task_app', 'hilfe-app'],
  ['', 'help_task_notify', 'hilfe-push'],
];

const PRINT_DOCS = ['setlist', 'rider', 'tax', 'gema', 'quote', 'contract', 'help'];

/**
 * Trägt dieser Bogen Logo beziehungsweise Wasserzeichen? Je Dokument
 * entscheidbar, weil ein Formular für die GEMA kein Bild hinter den Zahlen
 * verträgt und eine Steuerübersicht erst recht nicht.
 */
function print_brand_on(string $doc, string $art): bool {
  $liste = explode(',', (string) setting('print_' . $art . '_docs'));
  return in_array($doc, array_map('trim', $liste), true);
}

/**
 * Die Logo-Ecke eines Druckbogens. Fürs Papier zählt das Druck-Logo (dunkel auf
 * weiß); fehlt es, tut es das Logo der Website, und fehlt auch das, steht der
 * Bandname da. Leer bleibt die Ecke nie — ein Blatt ohne Absender ist auf dem
 * Pult eines fremden Technikers wertlos.
 */
function print_logo_html(string $doc): string {
  if (!print_brand_on($doc, 'logo')) return '';
  $datei = (string) (setting('print_logo_file') ?: setting('logo_file'));
  $name = (string) setting('band_name');
  $inhalt = $datei !== ''
    ? '<img src="/uploads/' . e($datei) . '" alt="' . e($name) . '">'
    : '<span class="bandname">' . e($name) . '</span>';
  return '<div class="logo">' . $inhalt . '</div>';
}

/** Das Wasserzeichen eines Blattes. Gehört in das Blatt, nicht davor. */
function print_watermark_html(string $doc): string {
  $datei = (string) setting('print_watermark_file');
  if ($datei === '' || !print_brand_on($doc, 'watermark')) return '';
  return '<div class="watermark"><img src="/uploads/' . e($datei) . '" alt=""></div>';
}

/**
 * Setzt eines der vier Bilder aus einer Datei — hochgeladen oder aus der
 * Galerie. Immer eine Kopie: Galeriefoto und Erscheinungsbild bleiben
 * unabhängig, wer das eine löscht, verliert das andere nicht (#289).
 */
function branding_set_file(string $slot, string $quelle): bool {
  if (!isset(BRANDING_SLOTS[$slot]) || !is_file($quelle)) return false;
  $ext = strtolower(pathinfo($quelle, PATHINFO_EXTENSION));
  // Eine hochgeladene Datei heißt phpXXXX — ihre Endung sagt nichts.
  if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true)) {
    $ext = match (mime_content_type($quelle) ?: '') {
      'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp',
      'image/svg+xml' => 'svg', 'image/vnd.microsoft.icon', 'image/x-icon' => 'ico',
      default => 'png',
    };
  }
  $name = $slot . '_' . time() . '.' . $ext;
  if (!copy($quelle, UPLOADS_DIR . '/' . $name)) return false;
  // Die Bilder stehen nach außen — Aufnahmedaten haben in der Kopie nichts zu suchen.
  photo_strip_exif(UPLOADS_DIR . '/' . $name);
  $key = BRANDING_SLOTS[$slot]['key'];
  $old = setting($key);
  if ($old && $old !== $name) @unlink(UPLOADS_DIR . '/' . $old);
  set_setting($key, $name);
  return true;
}

/**
 * Legt eine Kopie einer Bilddatei als Foto in die Galerie — so, wie es nach
 * einem Upload über das Fotoformular aussähe: Zufallsname, Prüfsumme vor dem
 * Entfernen der Aufnahmedaten, Maße, Schlagwörter. Zurück kommt die Foto-ID,
 * 0 wenn die Datei kein Bild ist oder nicht kopiert werden konnte.
 */
function photo_add_copy(string $quelle, string $caption, array $tags, ?int $wer, string $herkunft): int {
  global $db;
  if (!is_file($quelle)) return 0;
  $info = @getimagesize($quelle);
  if (!$info) return 0;
  $ext = match ($info['mime']) {
    'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', default => 'png',
  };
  $name = 'foto_' . bin2hex(random_bytes(16)) . '.' . $ext;
  if (!copy($quelle, UPLOADS_DIR . '/' . $name)) return 0;
  $summe = (string) (hash_file('sha256', UPLOADS_DIR . '/' . $name) ?: '');
  photo_strip_exif(UPLOADS_DIR . '/' . $name);
  q('INSERT INTO photos (filename, caption, is_public, uploaded_by, source, checksum, img_w, img_h)
     VALUES (?,?,0,?,?,?,?,?)',
    [$name, mb_substr($caption, 0, 500), $wer, mb_substr($herkunft, 0, 400), $summe, (int) $info[0], (int) $info[1]]);
  $id = (int) $db->lastInsertId();
  foreach ($tags as $tag) {
    $tag = tag_norm($tag);
    if ($tag !== '') q('INSERT IGNORE INTO photo_tags (photo_id, tag) VALUES (?,?)', [$id, $tag]);
  }
  return $id;
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
/**
 * Ein Start-Passwort: zwölf Zeichen ohne die verwechselbaren (0/O, 1/l/I).
 * Es wird vorgelesen, abgetippt und weitergesagt — da zählt Lesbarkeit mehr
 * als das letzte Bit Entropie, zumal es beim ersten Login gewechselt wird.
 */
/**
 * So lange gilt ein Start-Passwort. Es steht im Klartext in einer Mail und
 * liegt danach in jedem Postfach, durch das sie gelaufen ist — ein Zugang, der
 * ein halbes Jahr später noch aufgeht, ist genau die Falle. Sieben Tage sind
 * lang genug für einen Urlaub und kurz genug, dass die Mail veraltet (#274).
 */
const START_PW_DAYS = 7;

/** Ist dieses Start-Passwort abgelaufen? Ohne Stempel gilt es weiter. */
function start_pw_expired(?array $user): bool {
  if (empty($user['must_change_pw']) || empty($user['start_pw_at'])) return false;
  return strtotime($user['start_pw_at']) < time() - START_PW_DAYS * 86400;
}

/** Merkt sich, dass dieses Konto gerade angemeldet wurde. */
function login_stamp(int $uid): void {
  q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$uid]);
}

/**
 * War dieses Konto nachweislich noch nie angemeldet? Nur dann, wenn es ein
 * Start-Passwort bekommen hat und keine Anmeldung zeigt. Ein Konto ohne beides
 * ist bloß älter als der Anmeldestempel (v1.244.0) und hat womöglich längst
 * ein eigenes Passwort — dem darf niemand ungefragt ein neues geben (#290).
 */
function never_signed_in(array $user): bool {
  return $user['last_login_at'] === null && !empty($user['start_pw_at']);
}

/**
 * Neues Start-Passwort setzen und die Zugangsdaten schicken — beim Nachsenden
 * wie beim Ändern der Adresse eines Kontos, das nie angekommen ist (#273, #290).
 * Das alte Passwort ist nur als Prüfsumme da und lässt sich nicht wiederholen,
 * also entsteht ein neues; das gehört ins Protokoll, weil es einem fremden
 * Konto sein Passwort nimmt. Zurück kommt [Mail raus?, Start-Passwort] — ging
 * die Mail nicht hinaus, zeigt der Aufrufer das Passwort selbst an.
 */
function access_send(array $ziel, int $durch): array {
  $startPw = start_password();
  q('UPDATE users SET password_hash = ?, must_change_pw = 1, start_pw_at = NOW() WHERE id = ?',
    [password_hash($startPw, PASSWORD_DEFAULT), (int) $ziel['id']]);
  error_log('Bandregie: Zugangsdaten neu verschickt für Konto ' . (int) $ziel['id'] . ' durch Konto ' . $durch);
  return [welcome_mail((string) $ziel['email'], $ziel['first_name'] ?: $ziel['name'], $startPw, (int) $ziel['id']), $startPw];
}

function start_password(): string {
  $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $pw = '';
  for ($i = 0; $i < 12; $i++) $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  return $pw;
}

/**
 * Die Willkommensmail mit den Zugangsdaten. Einmal hier, weil sie an zwei
 * Stellen gebraucht wird: beim Anlegen eines Kontos und wenn sie noch einmal
 * geschickt werden soll (#273). Absender von der eigenen Domain wegen SPF,
 * Antworten gehen an die Kontaktadresse der Band.
 */
function welcome_mail(string $email, string $vorname, string $startPw, ?int $userId = null): bool {
  $band = setting('band_name');
  $body = "Hallo " . trim($vorname) . ",\n\n"
    . "für dich wurde ein Zugang zum Bandbereich von $band angelegt.\n\n"
    . 'Login: ' . absolute_url('/login') . "\n"
    . "E-Mail: $email\n"
    . "Start-Passwort: $startPw\n\n"
    . "Beim ersten Login musst du ein eigenes Passwort vergeben.\n"
    . 'Das Start-Passwort gilt ' . START_PW_DAYS . ' Tage, also bis zum '
    . date('d.m.Y', time() + START_PW_DAYS * 86400) . ". Danach lass dir neue Zugangsdaten schicken.\n\n"
    . "Viele Grüße\n$band";
  return band_mail_send($email, 'Dein Zugang zum Bandbereich von ' . mail_header_value($band, 120), $body, 'einladung', $userId);
}

/**
 * Eine Mail im Namen der Band hinaus: Absender, Antwortadresse, eigene
 * Message-ID und der Eintrag in mail_log an einer Stelle — für Einladungen an
 * Mitglieder wie an Gäste (#293, #294). Zurück kommt nur, ob der eigene
 * Mailserver die Nachricht genommen hat; was der Empfänger daraus machte,
 * trägt bin/mail-status.php anhand der Message-ID nach.
 */
function band_mail_send(string $to, string $subject, string $body, string $kind, ?int $userId, string $detail = ''): bool {
  $from = mail_from_address();
  $antwortAn = mail_header_value(setting('contact_email'));
  $replyTo = $antwortAn !== '' ? "\r\nReply-To: " . $antwortAn : '';
  // Eine eigene Message-ID, damit sich die Mail im Log des Mailservers
  // wiederfinden lässt. Sonst vergibt der Server eine, die nur er kennt.
  $messageId = 'bandregie-' . bin2hex(random_bytes(16)) . '@' . substr($from, strpos($from, '@') + 1);
  // @: Ohne erreichbares Sendmail warnt mail() mitten in die Seite — das
  // Ergebnis steht ohnehin als Zeile in mail_log, samt Grund.
  $ok = (bool) @mail($to, $subject, $body,
    "From: $from$replyTo\r\nMessage-ID: <$messageId>\r\nContent-Type: text/plain; charset=UTF-8", '-f' . $from);
  $grund = $ok ? $detail : trim('mail() hat die Nachricht nicht angenommen · ' . $detail, ' ·');
  q('INSERT INTO mail_log (user_id, to_email, kind, message_id, status, status_at, detail) VALUES (?,?,?,?,?,?,?)',
    [$userId, mb_substr($to, 0, 190), $kind, $messageId, $ok ? 'queued' : 'failed', $ok ? null : date('Y-m-d H:i:s'), $grund]);
  return $ok;
}

/**
 * Eine Rücksetzung für eine Adresse, zu der es kein Konto gibt (#327).
 *
 * Nach außen ändert das nichts: Der Anfragende bekommt dieselbe Antwort wie
 * jeder andere, sonst verriete die Seite, welche Adressen es gibt. Nach innen
 * steht die Zeile da, und die Band sieht, warum bei jemandem nie eine Mail
 * ankam — meistens, weil die eingegebene Adresse nicht die hinterlegte ist.
 *
 * Nur syntaktisch gültige Adressen landen hier; sonst füllt sich das Protokoll
 * mit allem, was jemand eintippt. Die Menge deckelt die Wiederholsperre der
 * Route (fünf je Adresse und Stunde).
 *
 * Die Message-ID ist erfunden, weil keine Mail entstanden ist — die Spalte ist
 * eindeutig, und ohne Wert käme keine zweite Zeile hinein.
 */
function mail_log_unknown(string $email): void {
  q("INSERT INTO mail_log (user_id, to_email, kind, message_id, status, status_at, detail)
     VALUES (NULL, ?, 'passwort', ?, 'unbekannt', NOW(), ?)",
    [mb_substr($email, 0, 190), 'unbekannt-' . bin2hex(random_bytes(16)),
     'Rücksetzung angefragt, kein Konto zu dieser Adresse']);
}

/**
 * Die letzten Rücksetzungen auf unbekannte Adressen (#327) — für den Hinweis
 * in der Mitgliederliste. Sieben Tage, weil ältere niemandem mehr helfen.
 */
function mail_unknown_recent(int $tage = 7): array {
  return rows("SELECT to_email, status_at FROM mail_log
                WHERE user_id IS NULL AND status = 'unbekannt'
                  AND status_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY id DESC LIMIT 5", [$tage]);
}

// ---------- Gäste (#294) ----------

const GUEST_STATUSES = ['angefragt', 'zugesagt', 'abgesagt', 'storniert'];

function guest_status_label(string $status): string {
  return t('guest_st_' . (in_array($status, GUEST_STATUSES, true) ? $status : 'angefragt'));
}

/**
 * Ort und Navi-Ziel eines Gast-Termins: der hinterlegte Veranstaltungsort mit
 * Anschrift, sonst der Freitext. Einmal hier, weil Einladungsmail und
 * Gastseite dasselbe sagen müssen.
 */
function guest_event_place(array $b): array {
  $venue = !empty($b['venue_id']) ? row('SELECT * FROM venues WHERE id = ?', [$b['venue_id']]) : null;
  if ($venue) {
    return ['ort' => event_place(['venue_name' => $venue['name'], 'venue_address' => $venue['address'],
                                  'venue_postcode' => $venue['postcode'], 'venue_city' => $venue['city']]),
            'navi' => venue_dest($venue)];
  }
  $frei = trim((string) ($b['location'] ?? ''));
  return ['ort' => $frei, 'navi' => navi_dest($frei)];
}

/**
 * Bis wann ein Gast nach seinem Termin noch hineinsieht: Mittag des Folgetags.
 * Ein Gig endet nach Mitternacht, abgebaut wird nachts — um null Uhr wäre der
 * Zugang genau dann weg, wenn jemand noch die Kanalliste braucht.
 */
function guest_access_until(string $eventDate): string {
  return date('Y-m-d 12:00:00', strtotime($eventDate . ' +1 day'));
}

/** Die Buchung zu einem Token — nur, wenn sie noch gilt. Sonst null. */
function guest_booking_by_token(string $token): ?array {
  if (!preg_match('~^[a-f0-9]{64}$~', $token)) return null;
  $b = row('SELECT b.*, g.name AS guest_name, g.email AS guest_email, e.title, e.date, e.time, e.time_meet, e.time_end,
                   e.location, e.support_act, e.setlist_id, e.venue_id, e.status AS event_status
            FROM guest_bookings b JOIN guests g ON g.id = b.guest_id JOIN events e ON e.id = b.event_id
            WHERE b.token = ?', [$token]);
  if (!$b) return null;
  // Absage und Stornierung nehmen den Schlüssel sofort, das Datum am Folgetag.
  if (in_array($b['status'], ['abgesagt', 'storniert'], true)) return null;
  if (strtotime($b['access_until']) < time()) return null;
  return $b;
}

/**
 * Bewertungen je Buchung: Schnitt, Anzahl, eigene Sterne und die Zeilen dazu.
 * Eine Abfrage für alle Buchungen einer Seite.
 */
function guest_ratings_map(array $bookingIds, int $meId): array {
  if (!$bookingIds) return [];
  $ph = implode(',', array_fill(0, count($bookingIds), '?'));
  $out = [];
  foreach (rows("SELECT r.*, u.name FROM guest_ratings r LEFT JOIN users u ON u.id = r.user_id
                 WHERE r.booking_id IN ($ph) ORDER BY r.created_at", $bookingIds) as $r) {
    $b = (int) $r['booking_id'];
    $out[$b] ??= ['sum' => 0, 'n' => 0, 'mine' => 0, 'rows' => []];
    $out[$b]['sum'] += (int) $r['stars'];
    $out[$b]['n']++;
    if ((int) $r['user_id'] === $meId) $out[$b]['mine'] = (int) $r['stars'];
    $out[$b]['rows'][] = $r;
  }
  foreach ($out as &$o) $o['avg'] = $o['n'] ? round($o['sum'] / $o['n'], 1) : 0;
  unset($o);
  return $out;
}

/** Sterne als Text, „★★★★☆", für Liste und Karte. */
function guest_stars(float $avg): string {
  $voll = (int) round($avg);
  return str_repeat('★', max(0, min(5, $voll))) . str_repeat('☆', 5 - max(0, min(5, $voll)));
}

/**
 * Eine Telefonnummer, wie sie WhatsApp und die SMS-App brauchen: nur Ziffern,
 * mit Ländervorwahl ohne Plus. „0170 12345" wird zu „4917012345" — die 49 ist
 * die Vorgabe, weil die Anwendung von deutschen Bands ausgeht; wer anderswo
 * spielt, schreibt die Nummer mit +Vorwahl, und die bleibt, wie sie ist.
 */
function phone_digits_intl(string $roh): string {
  $z = preg_replace('~[^\d+]~', '', $roh) ?? '';
  if (str_starts_with($z, '+')) return substr($z, 1);
  if (str_starts_with($z, '00')) return substr($z, 2);
  if (str_starts_with($z, '0')) return '49' . substr($z, 1);
  return $z;
}

/**
 * Die Einladung eines Gasts als Nachricht fürs Handy (#299): Links, die auf
 * dem Gerät des Mitglieds WhatsApp oder die SMS-App mit fertigem Text öffnen.
 * Verschickt wird dort erst auf Tippen — der Server redet mit niemandem, es
 * kostet nichts, und es braucht kein Konto bei irgendwem. Ohne Nummer leer.
 */
/**
 * Zwei Links, die auf dem eigenen Handy WhatsApp oder die Kurznachricht mit
 * fertigem Text öffnen. Gesendet wird dort, von der eigenen Nummer — das
 * kostet nichts, braucht keinen fremden Dienst und niemand muss abtippen.
 *
 * Ohne Nummer gibt es nichts zurück; der Aufrufer zeigt dann eben keine Knöpfe.
 */
function share_links(string $rohNummer, string $text): array {
  $nummer = phone_digits_intl($rohNummer);
  if ($nummer === '') return [];
  return [
    'whatsapp' => 'https://wa.me/' . $nummer . '?text=' . rawurlencode($text),
    'sms' => 'sms:+' . $nummer . '?body=' . rawurlencode($text),
  ];
}

function guest_share_links(array $b): array {
  return share_links((string) ($b['guest_phone'] ?? ''),
    sprintf(t('guest_share_msg'), trim((string) $b['guest_name']), setting('band_name'),
            fmt_date($b['date']), $b['title'], absolute_url('/gast/' . $b['token'])));
}

/**
 * Ein frischer Link, mit dem ein Mitglied sein Kennwort setzt (#307).
 *
 * Für Mitglieder ohne Mailadresse: Ihr Zugang lässt sich sonst nur bekannt
 * geben, indem jemand ein Kennwort vorliest. Ein Link ist das kleinere Übel —
 * er gilt eine Stunde und genau einmal, ein vorgelesenes Kennwort dagegen so
 * lange, bis es jemand ändert.
 *
 * Dass er durch einen Messenger geht, bleibt eine Abwägung: Dort liegt er in
 * einer fremden App. Deshalb die kurze Frist, und deshalb sagt der Text sie an.
 */
function member_reset_link(int $userId): string {
  $token = bin2hex(random_bytes(32));
  q('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?',
    [$token, $userId]);
  return absolute_url('/passwort-reset/' . $token);
}

/**
 * Die beiden Knöpfe für den Zugangslink eines Mitglieds.
 *
 * Der Link kommt von außen herein und wird hier nicht erzeugt: Ein Token
 * entsteht beim Drücken eines Knopfes, nicht beim Anzeigen einer Liste. Sonst
 * bekäme jedes Mitglied bei jedem Seitenaufruf einen neuen, und der eben
 * verschickte wäre tot, bevor ihn jemand öffnet.
 */
function member_share_links(array $mitglied, string $link): array {
  return share_links((string) ($mitglied['mobile'] ?? ''), sprintf(t('mem_share_msg'),
    trim((string) ($mitglied['first_name'] ?: $mitglied['name'])), setting('band_name'), $link));
}

/** Buchungen je Termin, für die Karten: Gastname, Funktion, Status. */
function guest_bookings_map(array $eventIds): array {
  if (!$eventIds) return [];
  $ph = implode(',', array_fill(0, count($eventIds), '?'));
  $out = [];
  foreach (rows("SELECT b.*, g.name AS guest_name, g.email AS guest_email, g.phone AS guest_phone, e.title, e.date FROM guest_bookings b
                 JOIN events e ON e.id = b.event_id
                 JOIN guests g ON g.id = b.guest_id WHERE b.event_id IN ($ph) ORDER BY g.name", $eventIds) as $b) {
    $out[(int) $b['event_id']][] = $b;
  }
  return $out;
}

/**
 * Die Einladung an einen Gast: ein Link, der zuerst die Antwort entgegennimmt
 * und danach — bei Zusage — sein Zugang für diesen einen Termin ist. Landet
 * wie die Mitglieder-Einladung in mail_log (#293), Art „gast".
 */
function guest_invite_mail(array $b): bool {
  $band = setting('band_name');
  $ort = guest_event_place($b)['ort'];
  $body = 'Hallo ' . trim((string) $b['guest_name']) . ",\n\n"
    . "$band fragt dich für einen Termin an:\n\n"
    . '  ' . fmt_date($b['date']) . ($b['time'] ? ' · ' . $b['time'] . ' Uhr' : '') . "\n"
    . '  ' . $b['title'] . "\n"
    . ($ort !== '' ? "  $ort\n" : '')
    . ($b['function_name'] !== '' ? '  Deine Aufgabe: ' . $b['function_name'] . "\n" : '')
    . "\nBitte sag hier zu oder ab:\n" . absolute_url('/gast/' . $b['token']) . "\n\n"
    . "Nach deiner Zusage ist derselbe Link dein Zugang zu allem, was du für den Abend brauchst — "
    . "Ablauf, Rider, Setliste. Er ist persönlich und gilt bis zum Mittag nach dem Termin.\n\n"
    . "Viele Grüße\n$band";
  $ok = band_mail_send((string) $b['guest_email'], mail_header_value("$band: Anfrage für " . fmt_date($b['date']), 120),
                       $body, 'gast', null, 'Buchung ' . (int) $b['id']);
  if ($ok) q('UPDATE guest_bookings SET invited_at = NOW() WHERE id = ?', [$b['id']]);
  return $ok;
}

/**
 * Der Stand der letzten Einladung je Mitglied — für die Mitgliederliste (#293).
 * Eine Abfrage für alle: die jüngste Zeile je Konto.
 */
function mail_status_by_user(): array {
  $out = [];
  foreach (rows('SELECT m.* FROM mail_log m
                 JOIN (SELECT user_id, MAX(id) AS id FROM mail_log WHERE user_id IS NOT NULL GROUP BY user_id) j ON j.id = m.id') as $r) {
    $out[(int) $r['user_id']] = $r;
  }
  return $out;
}

/**
 * Zeilen aus einem Postfix-Log gegen mail_log abgleichen (#293). Bekommt die
 * Zeilen, nicht die Datei: Das Log gehört root, die Anwendung nicht — der Cron
 * reicht die Zeilen durch (tail … | php bin/mail-status.php). Zurück kommt die
 * Zahl der geänderten Einträge.
 *
 * Postfix schreibt je Nachricht mehrere Zeilen mit derselben Queue-ID — kurz
 * als Hex, mit enable_long_queue_ids als gemischte Buchstaben und Ziffern:
 *   cleanup[…]: 21087C13DA: message-id=<bandregie-…@tonrausch.app>
 *   smtp[…]:    21087C13DA: to=<x@gmail.com>, relay=…, dsn=2.0.0, status=sent (250 …)
 * Die erste verbindet die Queue-ID mit unserer Message-ID, die zweite sagt,
 * was daraus wurde.
 */
function mail_status_apply(iterable $zeilen): int {
  // Queue-ID => Message-ID, für alle Nachrichten im Ausschnitt. Ob eine davon
  // uns gehört, entscheidet die Tabelle beim UPDATE — so lassen sich auch
  // Mails nachtragen, die vor der eigenen Message-ID verschickt wurden.
  $queue = [];
  $geaendert = 0;
  foreach ($zeilen as $z) {
    if (preg_match('~postfix/cleanup\[\d+\]: ([0-9A-Za-z]+): message-id=<([^>]+)>~', $z, $m)) {
      $queue[$m[1]] = $m[2];
      // Die Queue-ID an die Zeile heften, solange die cleanup-Zeile noch im
      // Ausschnitt steht — ein späterer Zustellversuch nennt nur noch sie (#296).
      q("UPDATE mail_log SET queue_id = ? WHERE message_id = ? AND queue_id = ''", [$m[1], $m[2]]);
      continue;
    }
    if (!preg_match('~^(\w{3} +\d+ \d\d:\d\d:\d\d) \S+ postfix/(?:smtp|local|error|pipe|virtual)\[\d+\]: ([0-9A-Za-z]+): to=<([^>]+)>,(?: orig_to=<[^>]*>,)? relay=([^,]+),.*? dsn=([\d.]+), status=(\w+) \((.*)\)~', $z, $m)) {
      continue;
    }
    [, $wann, $qid, $an, $relay, $dsn, $status, $grund] = $m;
    // Erst der Ausschnitt, dann das Gedächtnis: Eine Queue-ID, die hier nicht
    // mehr vorkommt, steht vielleicht seit gestern an der Zeile.
    if (!isset($queue[$qid])) {
      $bekannt = row('SELECT message_id FROM mail_log WHERE queue_id = ? AND to_email = ?', [$qid, strtolower($an)]);
      if (!$bekannt) continue;
      $queue[$qid] = $bekannt['message_id'];
    }
    // Syslog kennt kein Jahr; ein Datum in der Zukunft war letztes Jahr.
    $ts = strtotime($wann . ' ' . date('Y')) ?: time();
    if ($ts > time() + 86400) $ts = strtotime($wann . ' ' . (date('Y') - 1)) ?: $ts;
    $detail = mb_substr(preg_replace('~\s+~', ' ', preg_replace('~^host \S+ said: ~', '', $grund)), 0, 200);
    $relayKurz = preg_replace('~\[.*$~', '', $relay);
    $geaendert += q('UPDATE mail_log SET status = ?, status_at = ?, detail = ?
                     WHERE message_id = ? AND to_email = ? AND (status_at IS NULL OR status_at <= ?)',
      [$status, date('Y-m-d H:i:s', $ts), "$dsn · $relayKurz · $detail", $queue[$qid], strtolower($an), date('Y-m-d H:i:s', $ts)])->rowCount();
  }
  set_setting('mail_status_checked_at', date('Y-m-d H:i:s'));
  return $geaendert;
}

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
  // Die Version gehört in den Namen: Ändert sich die Regel, nach der gezeichnet
  // wird, muss auch ein unverändertes Logo ein neues Symbol ergeben. Sonst
  // behielte eine bestehende Installation ihr altes Bild bis zum nächsten
  // Logowechsel (#313).
  $name = 'icon-' . $size . '-'
        . substr(sha1($source . filemtime($source) . BANDREGIE_VERSION), 0, 12) . '.png';
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

  // Ein Symbol, das schon eine gefüllte Kachel ist, wird nicht noch einmal
  // gerahmt (#313). Sonst sitzt ein schwarzes Quadrat in einem hellen Rahmen,
  // und das sieht auf dem Startbildschirm aus wie ein Bild an der Wand statt
  // wie ein Symbol. Erkannt wird es daran, dass der Rand nirgends durchsichtig
  // ist und das Bild quadratisch.
  $randlos = app_icon_full_bleed($img);
  $rand = $randlos ? 0 : (int) round($size * 0.12);
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
 * Füllt die Zeichnung ihre Fläche schon vollständig aus? (#313)
 *
 * Geprüft werden die vier Ränder: Ist dort nichts durchsichtig und ist das Bild
 * quadratisch, dann ist es bereits eine fertige Kachel und braucht weder
 * Hintergrund noch Rand.
 */
function app_icon_full_bleed($img): bool {
  $breite = imagesx($img); $hoehe = imagesy($img);
  if ($breite < 16 || $hoehe < 16) return false;
  $verhaeltnis = $breite / max(1, $hoehe);
  if ($verhaeltnis < 0.95 || $verhaeltnis > 1.05) return false;
  $schritt = max(1, (int) (max($breite, $hoehe) / 48));
  for ($x = 0; $x < $breite; $x += $schritt) {
    foreach ([0, $hoehe - 1] as $y) {
      if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 64) return false;
    }
  }
  for ($y = 0; $y < $hoehe; $y += $schritt) {
    foreach ([0, $breite - 1] as $x) {
      if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 64) return false;
    }
  }
  return true;
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
 * Klammern einer Setliste in Ordnung bringen (#242, #245).
 *
 * Die Nummer ist nur eine Kennung. Nach jeder Änderung wird neu gezählt: 1..n von
 * oben nach unten, damit in der Oberfläche keine Löcher stehen.
 *
 * Und die Klammer wird zusammengehalten, denn sie ist ein Bereich auf dem Blatt.
 * Die Regel steht an einer EINZELNEN Lücke, nicht an ihrer Summe:
 *
 *   • Eine Lücke von einer Zeile wird geschlossen. Wer einen Song zwischen zwei
 *     geklammerte zieht, will ihn drin haben — vorher zerfiel die Klammer dabei.
 *   • Eine größere Lücke trennt. Das ist der andere Fall: Ein Mitglied wurde weit
 *     weg gezogen. Dann bleibt der längste zusammenhängende Lauf, und das
 *     Davongelaufene wird freigegeben.
 *
 * Zuerst stand hier die Summe aller Lücken gegen die Zahl der Mitglieder — damit
 * schluckte eine Klammer aus vier Titeln beim Herausziehen eines Mitglieds drei
 * fremde Zeilen mit. Je Lücke gemessen, trifft die Regel beide Fälle.
 *
 * Ein Lauf aus einer einzigen Zeile ist keine Klammer und verschwindet.
 *
 * @return int Zahl der Klammern danach
 */
const BRACE_MAX_GAP = 1;

function setlist_braces_normalize(int $setlistId): int {
  $zeilen = rows('SELECT id, position, bracket, bracket_note FROM setlist_songs
                  WHERE setlist_id = ? ORDER BY position', [$setlistId]);
  $mitglieder = [];
  foreach ($zeilen as $i => $z) {
    if ($z['bracket'] !== null) $mitglieder[(int) $z['bracket']][] = $i;
  }

  $bereiche = [];
  foreach ($mitglieder as $idx) {
    // In Läufe schneiden: eine Lücke bis BRACE_MAX_GAP hält zusammen.
    $laeufe = [];
    foreach ($idx as $i) {
      $letzter = $laeufe ? array_key_last($laeufe) : null;
      if ($letzter !== null && $i - end($laeufe[$letzter]) <= BRACE_MAX_GAP + 1) {
        $laeufe[$letzter][] = $i;
      } else {
        $laeufe[] = [$i];
      }
    }
    // Der längste Lauf gewinnt; die anderen Mitglieder werden freigegeben.
    usort($laeufe, fn($a, $b) => count($b) <=> count($a));
    $lauf = $laeufe[0];
    if (count($lauf) < 2) continue;                     // eine Zeile ist keine Klammer
    $text = '';
    foreach ($idx as $i) {
      if ((string) $zeilen[$i]['bracket_note'] !== '') { $text = (string) $zeilen[$i]['bracket_note']; break; }
    }
    $bereiche[] = [min($lauf), max($lauf), $text];
  }
  usort($bereiche, fn($a, $b) => $a[0] <=> $b[0]);

  // Erst alles lösen, dann die Bereiche neu schreiben — so bleibt nichts stehen,
  // was nicht mehr gilt.
  q("UPDATE setlist_songs SET bracket = NULL, bracket_note = '' WHERE setlist_id = ?", [$setlistId]);
  $neu = 0;
  foreach ($bereiche as [$von, $bis, $text]) {
    $neu++;
    for ($i = $von; $i <= $bis; $i++) {
      q('UPDATE setlist_songs SET bracket = ?, bracket_note = ? WHERE id = ?',
        [$neu, $i === $von ? $text : '', (int) $zeilen[$i]['id']]);
    }
  }
  return $neu;
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
/**
 * Zu welchen Terminen wird dieses Konto überhaupt gefragt? (#314)
 *
 * Sehen und gefragt werden sind zwei Fragen. Die Bandleitung muss jeden Termin
 * sehen, um ihn zu führen — deshalb nimmt visible_event_ids() sie aus. Ob
 * jemand zusagen soll, hängt aber nicht an seinen Rechten, sondern daran, ob er
 * mitspielt. Ein Ersatzmann spielt mit, wenn er gerufen wird, und wird deshalb
 * nur zu den Terminen gefragt, für die ihn jemand angefragt hat. Auch dann,
 * wenn er zugleich die Bandleitung ist.
 *
 * null heißt: zu allen.
 */
function events_to_answer(?array $user): ?array {
  if (!$user || !is_substitute($user)) return null;
  return array_map('intval', array_column(
    rows('SELECT event_id FROM substitute_requests WHERE user_id = ?', [$user['id']]), 'event_id'));
}

function open_votes(array $user): array {
  $sichtbar = events_to_answer($user);
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
 * Was am App-Symbol steht: eigene offene Aufgaben, fehlende Rückmeldungen und
 * ungelesene Beiträge im Chat (#317).
 *
 * Alle drei Teile kommen aus denselben Funktionen, aus denen die Listen im
 * Überblick gebaut werden — so können Zahl und Liste nicht auseinanderlaufen.
 * Wer einen Bereich nicht sehen darf, zählt ihn auch nicht mit.
 */
function open_items_count(array $user): int {
  $offen = (int) row("SELECT COUNT(*) c FROM tasks WHERE assigned_to = ? AND status = 'offen'",
                     [(int) $user['id']])['c'];
  $chat = perm_allows($user, 'themen') ? array_sum(topic_unread($user)) : 0;
  return $offen + count(open_votes($user)) + $chat;
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
            'absences', 'topic_reads', 'seen_marks'] as $table) {
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
/**
 * Alle Adressen, die zu einem Auftritt gehören: Setliste mit Druckansicht, jedes
 * Lied darin mit Bühne und Noten, Rider, Patchliste und die Anhänge.
 *
 * Einmal hier, weil drei Stellen dieselbe Antwort brauchen: der Knopf am Termin,
 * die Automatik und das Aufräumen — und nur wenn alle drei dieselbe Liste
 * bilden, räumt das Aufräumen genau das weg, was das Mitnehmen geholt hat (#277).
 */
/**
 * Seiten, die zu jedem Auftritt gehören und zu keinem besonders. Sie stehen
 * getrennt, weil das Aufräumen sie niemals wegwerfen darf: Sie gehören dem
 * Gerät, nicht dem vergangenen Termin (#278).
 */
const OFFLINE_BASE_PAGES = ['/intern', '/intern/termine', '/intern/songs',
                            '/intern/stagerider', '/intern/stagerider/print', '/intern/kanaele'];

function event_offline_urls(array $ev): array {
  $urls = OFFLINE_BASE_PAGES;
  $songIds = [];
  if ($ev['setlist_id']) {
    $sl = (int) $ev['setlist_id'];
    $urls[] = '/intern/setlists';
    $urls[] = '/intern/setlists/' . $sl;
    $urls[] = '/intern/setlists/' . $sl . '/print';
    $songIds = array_map('intval', array_column(
      rows('SELECT song_id FROM setlist_songs WHERE setlist_id = ? AND song_id IS NOT NULL', [$sl]), 'song_id'));
    // Bühne und Noten mit der Setliste im Rücken (?sl) — ohne diese Adressen
    // käme der Teleprompter offline gar nicht erst hoch.
    foreach ($songIds as $song) {
      $urls[] = '/intern/songs/' . $song;
      $urls[] = '/intern/songs/' . $song . '/buehne?sl=' . $sl;
      $urls[] = '/intern/songs/' . $song . '/noten?sl=' . $sl;
    }
  }
  // Je Art einzeln einsammeln statt die drei Ergebnisse zu vereinigen:
  // files_map() schlüsselt nach Entitäts-Kennung, und „+" behält bei gleicher
  // Kennung den linken Wert. Termin 1 mit Anhang und Lied 1 mit Noten haben
  // beide die 1 — die Noten fielen still heraus, ausgerechnet das, wofür das
  // Mitnehmen da ist (#278).
  $anhaenge = [
    ['event', [(int) $ev['id']]],
    ['setlist', $ev['setlist_id'] ? [(int) $ev['setlist_id']] : []],
    ['song', $songIds],
  ];
  foreach ($anhaenge as [$art, $wen]) {
    foreach (files_map($art, $wen) as $liste) {
      foreach ($liste as $datei) $urls[] = '/intern/datei/' . (int) $datei['id'];
    }
  }
  return array_values(array_unique($urls));
}

/**
 * Woran sich erkennen lässt, ob dieser Auftritt wirklich auf dem Gerät liegt.
 *
 * Eine einzelne Adresse genügt nicht: Die Druckansicht der Setliste legt der
 * Service Worker auch beim normalen Blättern ab. Wer sie einmal offen hatte,
 * bekäme „offline verfügbar" zu lesen, während Texte, Noten und Rider fehlen —
 * und merkt es auf der Bühne (#278). Deshalb eine Stichprobe quer durch das,
 * was nur das Mitnehmen holt; erst wenn alles davon da ist, gilt es.
 *
 * Höchstens zwölf Adressen — sie reisen im HTML jeder Karte mit.
 */
function event_offline_check(array $ev): array {
  if (!$ev['setlist_id']) return [];
  $sl = (int) $ev['setlist_id'];
  $proben = ['/intern/setlists/' . $sl . '/print'];
  $lieder = array_map('intval', array_column(
    rows('SELECT song_id FROM setlist_songs WHERE setlist_id = ? AND song_id IS NOT NULL
          ORDER BY position', [$sl]), 'song_id'));
  // Vom Ende her: Wer im Set blättert, kommt vorn zuerst an; die letzten Lieder
  // liegen nur im Speicher, wenn wirklich alles geholt wurde.
  foreach (array_slice(array_reverse($lieder), 0, 11) as $song) {
    $proben[] = '/intern/songs/' . $song . '/buehne?sl=' . $sl;
  }
  return $proben;
}

/**
 * Was weg darf: Adressen vergangener Termine, die kein kommender Termin und
 * keine gewählte Offline-Auswahl mehr braucht. Die Differenz ist der Punkt —
 * ein Lied aus dem Set von gestern steht oft auch im Set von morgen (#277).
 */
function offline_stale_urls(array $user): array {
  $sichtbar = visible_event_ids($user);
  [$wo, $args] = visible_clause($sichtbar);
  $heute = date('Y-m-d');

  // Welche Setlisten gehören zu vergangenen, welche zu kommenden Terminen? Nur
  // die Differenz ist tot — dieselbe Setliste steht oft wieder an (#278).
  $frueher = array_column(rows("SELECT DISTINCT setlist_id FROM events
                                WHERE date < ? AND setlist_id IS NOT NULL$wo", [$heute, ...$args]), 'setlist_id');
  $kuenftig = array_column(rows("SELECT DISTINCT setlist_id FROM events
                                 WHERE date >= ? AND setlist_id IS NOT NULL$wo", [$heute, ...$args]), 'setlist_id');
  $tot = array_values(array_diff(array_map('intval', $frueher), array_map('intval', $kuenftig)));

  $weg = [];
  if ($tot) {
    $in = implode(',', array_fill(0, count($tot), '?'));
    foreach ($tot as $sl) {
      $weg[] = '/intern/setlists/' . (int) $sl;
      $weg[] = '/intern/setlists/' . (int) $sl . '/print';
    }
    // Die Lied-Adressen mit Setlist im Rücken gehören dieser einen Setliste;
    // die Seite ohne ?sl bleibt, sie gehört dem Lied.
    foreach (rows("SELECT DISTINCT setlist_id, song_id FROM setlist_songs
                   WHERE setlist_id IN ($in) AND song_id IS NOT NULL", $tot) as $z) {
      $weg[] = '/intern/songs/' . (int) $z['song_id'] . '/buehne?sl=' . (int) $z['setlist_id'];
      $weg[] = '/intern/songs/' . (int) $z['song_id'] . '/noten?sl=' . (int) $z['setlist_id'];
    }
  }

  // Anhänge vergangener Termine. Sie hängen an genau diesem Termin, anders als
  // die Noten eines Liedes, das im Repertoire bleibt.
  $alteTermine = array_column(rows("SELECT id FROM events WHERE date < ?$wo", [$heute, ...$args]), 'id');
  $kommende = array_column(rows("SELECT id FROM events WHERE date >= ?$wo", [$heute, ...$args]), 'id');
  if ($alteTermine) {
    $behaltenDateien = [];
    foreach (files_map('event', $kommende) as $liste) {
      foreach ($liste as $d) $behaltenDateien[(int) $d['id']] = true;
    }
    foreach (files_map('event', $alteTermine) as $liste) {
      foreach ($liste as $d) {
        if (!isset($behaltenDateien[(int) $d['id']])) $weg[] = '/intern/datei/' . (int) $d['id'];
      }
    }
  }

  // Und nichts wegwerfen, was die gewählte Auswahl dieses Mitglieds braucht.
  return array_values(array_diff(array_unique($weg), offline_urls($user), OFFLINE_BASE_PAGES));
}

function offline_urls(array $user): array {
  $bereiche = offline_scope($user);
  // Die Automatik steht für sich: Wer alle Bereiche abgewählt hat, aber die
  // kommenden Termine dabeihaben will, bekommt genau die (#277).
  $automatik = !empty($user['offline_auto']);
  if (!$bereiche && !$automatik) return [];

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

  // Die kommenden Termine der Übersicht, wenn die Automatik an ist — dieselben,
  // die dort stehen, mit allem, was der Knopf am Termin auch holen würde.
  if ($automatik) {
    foreach (dashboard_events(visible_event_ids($user), date('Y-m-d')) as $ev) {
      $urls = array_merge($urls, event_offline_urls($ev));
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

/**
 * Satz und Bild für die Begrüßung auf der Übersicht (#267).
 *
 * Kein gespeicherter Satz heißt „der mitgelieferte" — sonst verschwände die
 * Zeile bei jeder bestehenden Installation, die nie etwas eingestellt hat.
 * Ein gespeicherter leerer Satz heißt „keine Zeile": Wer sie nicht will, soll
 * sie abschalten können, ohne dass der Standardtext zurückkommt.
 */
/**
 * Alles, was die Terminkarte braucht — für eine beliebige Menge Termine.
 *
 * Die Übersicht zeigt denselben Ausschnitt wie die Terminliste (#277), und
 * dieselbe Karte braucht dieselben Daten. Zweimal dieselben zwölf Abfragen
 * nebeneinander zu pflegen ginge eine Weile gut und dann nicht mehr.
 */
function event_view_data(array $events, array $me): array {
  // Geschwärzt wird hier, an der einen Stelle, an der alle Terminansichten ihre
  // Daten holen (#309). Danach sind die Zeilen gefahrlos, und keine Ansicht
  // muss sich merken, dass sie etwas verbergen soll.
  [$events, $verdeckt] = events_redact($events, $me);
  $ids = array_column($events, 'id');
  $comments = [];
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (rows("SELECT c.*, u.name AS author FROM comments c LEFT JOIN users u ON u.id = c.user_id
                   WHERE c.event_id IN ($in) ORDER BY c.created_at", $ids) as $c) {
      $comments[$c['event_id']][] = $c;
    }
  }
  // Abwesenheiten sind Zeiträume; welcher Termin hineinfällt, entscheidet sich
  // hier und nicht in SQL — es sind wenige Zeilen und der Vergleich ist einfach.
  $absentByEvent = [];
  if ($events) {
    $ranges = rows('SELECT a.user_id, a.date_from, a.date_to, a.note, u.name
                    FROM absences a JOIN users u ON u.id = a.user_id');
    foreach ($events as $ev) {
      foreach ($ranges as $r) {
        if ($ev['date'] >= $r['date_from'] && $ev['date'] <= $r['date_to']) {
          $absentByEvent[$ev['id']][] = $r['name'];
        }
      }
    }
  }
  $venues = rows('SELECT * FROM venues ORDER BY name');
  // Gebuchte Gäste je Termin und ihre Bewertungen (#294).
  $guestsByEvent = guest_bookings_map($ids);
  $guestBookingIds = [];
  foreach ($guestsByEvent as $liste) foreach ($liste as $b) $guestBookingIds[] = (int) $b['id'];
  // Die Nebendaten eines verdeckten Termins sind genauso vertraulich wie er
  // selbst: ein Kommentar, eine Zusage, ein Dateiname verraten dasselbe.
  $ohne = static fn(array $karte): array => $verdeckt ? array_diff_key($karte, array_flip($verdeckt)) : $karte;
  return [
    'events' => $events,
    'members' => rows('SELECT id, name FROM users ORDER BY name'),
    'setlists' => rows('SELECT id, name FROM setlists ORDER BY name'),
    'venues' => $venues,
    'venueMap' => array_column($venues, null, 'id'),
    'absentByEvent' => $ohne($absentByEvent),
    'equipment' => rows('SELECT id, name, category, parent_id FROM equipment
                         WHERE disposed_on IS NULL ORDER BY category, name'),
    'gearByEvent' => $ohne(event_gear_map($ids)),
    'gearConflicts' => $ohne(event_gear_conflicts($ids)),
    'filesByEvent' => $ohne(files_map('event', $ids)),
    'comments' => $ohne($comments),
    'attendance' => $ohne(attendance_map($ids)),
    'mine' => $ohne(my_attendance($ids, (int) $me['id'])),
    'substitutes' => rows('SELECT id, name, substitute_for FROM users WHERE substitute_for IS NOT NULL'),
    'subRequests' => $ohne(substitute_requests_map($ids)),
    // Gebuchte Gäste je Termin und die Kontaktliste fürs Buchen (#294).
    'guestsByEvent' => $ohne($guestsByEvent),
    'guestList' => perm_allows($me, 'gaeste') ? rows('SELECT id, name, function_name, email FROM guests ORDER BY name') : [],
    'guestRatings' => guest_ratings_map($guestBookingIds, (int) $me['id']),
    // Der Vertragsstand je Termin (#303): Nur wer Verträge sehen darf,
    // bekommt die Abfrage überhaupt — sonst fragt die Terminliste Zeilen
    // ab, die der Lesende nie zu Gesicht bekommt.
    'contractByEvent' => perm_allows($me, 'vertraege') ? $ohne(contract_status_by_event($ids)) : [],
    // Der Kartenkopf nennt den Verantwortlichen beim Namen.
    'memberNames' => array_column(rows('SELECT id, name FROM users'), 'name', 'id'),
    // Was dieses Mitglied an Terminen noch nicht gesehen hat (#321). Auch das
    // geht durch $ohne: Ein verdeckter Termin darf nicht als „neu" auftauchen
    // und damit verraten, dass es ihn gibt.
    'unseenEvents' => $ohne(items_unseen($me, 'event')),
    // Die Dateien brauchen kein $ohne: Sie werden über filesByEvent angezeigt,
    // und das ist schon geschwärzt — zu einem verdeckten Termin steht keine
    // Datei da, die eine Marke tragen könnte.
    'unseenFiles' => items_unseen($me, 'file'),
  ];
}

function dashboard_welcome(): array {
  $s = all_settings();
  $wahl = $s['welcome_image'] ?? 'flagge';
  $datei = match ($wahl) {
    'eigen' => $s['welcome_file'] ?? '',
    'logo' => $s['logo_file'] ?? '',
    default => '',
  };
  return ['text' => $s['welcome_text'] ?? t('dash_diversity'), 'bild' => $wahl, 'datei' => $datei];
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
