<?php
declare(strict_types=1);

/**
 * Tabellen, Migrationen, Grunddaten und Seeds (#328).
 *
 * Diese Datei läuft, sie definiert nicht nur: Sie legt fehlende Tabellen an,
 * zieht Spalten nach und spielt mitgelieferte Übersetzungen ein. Deshalb wird
 * sie nur eingebunden, wenn am Schema etwas zu tun ist — das entscheidet das
 * Tor in bootstrap.php.
 *
 * Genau deshalb gehören hier nur DDL und einmalige Datenmigrationen hin,
 * nichts, was eine laufende Anfrage liest oder beantwortet: Bei
 * geschlossenem Tor läuft diese Datei die meiste Zeit gar nicht mit, und jede
 * Zeile hier, die $_SESSION, $_COOKIE, $_GET, $_POST oder $_SERVER anfasst,
 * würde dann schweigend nicht mehr ausgeführt (#328 — genau das ist einer
 * frühen Fassung des Tors mit der Sitzungswiederherstellung passiert).
 *
 * Erwartet: $db, q(), row(), rows(), setting(), set_setting().
 */
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

  // id, created_at und updated_at tragen die Marken (#331): Die Marken sprechen
  // jede Zeile über i.id an, und items_unseen() vergleicht updated_at mit
  // created_at, um "neu" von "geändert" zu unterscheiden. AUTO_INCREMENT ist
  // erlaubt, weil die Spalte an erster Stelle eines Schlüssels steht - der
  // bisherige Primärschlüssel bleibt, er hält weiter eine Zusage je Person.
  "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT UNIQUE,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(3) NULL,
    updated_by INT NULL,
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
foreach (['events', 'songs', 'setlists', 'quotes', 'contracts',
          // #331: Orte, Abwesenheiten und Aufgaben markieren mit
          'venues', 'absences', 'tasks', 'equipment', 'finances', 'guests'] as $markiert) {
  if (!column_exists($markiert, 'updated_at')) {
    $db->exec("ALTER TABLE `$markiert` ADD COLUMN updated_at DATETIME NULL,
                                       ADD COLUMN updated_by INT NULL");
  }
}

// Zusagen bekommen Marken (#331). Die Tabelle hatte als einzige keinen
// eigenen Schlüssel und keinen Zeitstempel, deshalb steht sie hier statt in
// der Schleife darüber.
if (!column_exists('attendance', 'id')) {
  $db->exec('ALTER TABLE attendance ADD COLUMN id INT AUTO_INCREMENT UNIQUE FIRST');
}
if (!column_exists('attendance', 'created_at')) {
  $db->exec('ALTER TABLE attendance ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
}
if (!column_exists('attendance', 'updated_at')) {
  $db->exec('ALTER TABLE attendance ADD COLUMN updated_at DATETIME(3) NULL,
                                    ADD COLUMN updated_by INT NULL');
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
    // Vorherige Lücke behoben (falls vermerkt) — sonst zeigt der Systemcheck
    // weiter eine Störung an, die längst nicht mehr besteht.
    set_setting('schema_luecke', '');
  } catch (PDOException $e) {
    // Schon vorhandene Doppelbuchungen verhindern den Schlüssel. Das ist kein
    // Grund, die Seite anzuhalten — aber es gehört ins Log, damit es auffällt.
    error_log('Bandregie: uniq_order_date nicht angelegt, vermutlich wegen vorhandener '
      . 'Doppelbuchungen — bitte prüfen: ' . $e->getMessage());
    // Marke nicht setzen: Ohne den Schlüssel bucht ein gleichzeitig geöffneter
    // Dauerauftrag die Miete weiterhin doppelt. Beim nächsten Aufruf soll das
    // Tor erneut versuchen statt das erst mit dem nächsten Release zu tun.
    $schemaLueckenhaft = true;
    // Vermerken, welche Lücke es ist — sonst kann der Systemcheck nur "irgendwas
    // ist offen" melden, nicht was ein Admin davon lesen soll.
    set_setting('schema_luecke', 'uniq_order_date');
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
  // Kein $schemaLueckenhaft hier: Das hielte das Tor bei jedem Aufruf offen,
  // sobald irgendeine Sprache je einen fehlerhaften Seed hatte — schlimmer
  // als die verlorene Übersetzung selbst. Stattdessen die Marke lokal
  // zurückhalten, damit derselbe Fehlschlag beim nächsten Release erneut
  // versucht wird statt für immer als „erledigt" zu gelten.
  $seedFehlgeschlagen = false;
  foreach ($seedFiles as $seedFile) {
    try {
      $db->exec((string) file_get_contents($seedFile));
    } catch (PDOException $seedError) {
      // Ein Seed darf fehlschlagen, ohne die Seite mitzureißen — aber nicht
      // lautlos. Ein Tippfehler in einer Zeichenkette lässt sonst den halben
      // Rest der Datei aus, und niemand merkt es, bis eine Sprache Lücken hat.
      error_log('Bandregie: Seed ' . basename($seedFile) . ' abgebrochen: ' . $seedError->getMessage());
      $seedFehlgeschlagen = true;
      set_setting('seed_fehler', basename($seedFile));
    }
  }
  if ($seedFehlgeschlagen) {
    // Marke bewusst nicht setzen: Bei geschlossenem Tor läuft diese Datei
    // sonst nie wieder, und der fehlgeschlagene Seed bliebe für immer draußen.
  } else {
    set_setting('translations_seed', $seedStamp);
    // Ein früherer Fehlschlag ist mit diesem Durchlauf erledigt.
    set_setting('seed_fehler', '');
  }
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

