<?php
/**
 * „Wichtig" — und die Mail, die sofort hinausgeht (#333).
 *
 * Die Tagesmail ist für fast alles richtig. Für einen Fall ist sie falsch: Der
 * Auftritt ist verlegt, die Probe fällt aus, der Bus ist weg. Das kann nicht
 * bis 18:00 warten.
 *
 * Deshalb kann ein Eintrag als wichtig gekennzeichnet werden, und das schickt
 * sofort eine Mail an alle, die ihn sehen dürfen. Die Band entscheidet, ob es
 * das überhaupt gibt und wer es darf; das einzelne Mitglied kann es nicht
 * abstellen — sonst taugt es nicht zum Durchdringen.
 */

/** Verschickt diese Installation Sofortmails? Die Band entscheidet das. */
function wichtig_mail_an(): bool {
  return setting('important_mail', '1') === '1';
}

/**
 * Wer darf kennzeichnen? Drei Stufen, damit das Ausrufezeichen nicht zum
 * Alltag wird.
 *
 * 'write'  — wer den Eintrag auch ändern dürfte (Vorgabe)
 * 'admin'  — nur die Bandleitung
 * 'alle'   — jeder, der ihn sehen darf
 */
function wichtig_wer(): string {
  $w = setting('important_who', 'write');
  return in_array($w, ['alle', 'write', 'admin'], true) ? $w : 'write';
}

/**
 * Darf dieses Konto diesen Eintrag kennzeichnen?
 *
 * Sehen muss man ihn immer — alles andere wäre eine Mail über etwas, das man
 * selbst nicht kennt. Der Bereich für die Schreibprüfung kommt aus
 * perm_module_for(item_url(...)): Das ist dieselbe Zuordnung, die auch die
 * Routen benutzen, und damit keine zweite Liste, die auseinanderläuft.
 */
function wichtig_darf(?array $user, string $kind, int $id): bool {
  if (!$user || !item_visible($user, $kind, $id)) return false;
  return match (wichtig_wer()) {
    'alle'  => true,
    'admin' => ($user['role'] ?? '') === 'admin',
    default => (static function () use ($user, $kind, $id): bool {
      $pfad = (string) parse_url(item_url($kind, $id), PHP_URL_PATH);
      $modul = perm_module_for($pfad);
      return $modul === null || perm_allows($user, $modul, 'write');
    })(),
  };
}

/** Ist dieser Eintrag als wichtig gekennzeichnet? Gibt die Zeile oder null. */
function wichtig_of(string $kind, int $id): ?array {
  return row('SELECT * FROM important_flags WHERE kind = ? AND item_id = ?', [$kind, $id]);
}

/** Die Kennzeichnungen mehrerer Einträge einer Sorte — für Listen. */
function wichtig_map(string $kind, array $ids): array {
  if (!$ids) return [];
  $in = implode(',', array_map('intval', $ids));
  $karte = [];
  foreach (rows("SELECT f.*, u.name AS wer FROM important_flags f
                 LEFT JOIN users u ON u.id = f.set_by
                 WHERE f.kind = ? AND f.item_id IN ($in)", [$kind]) as $z) {
    $karte[(int) $z['item_id']] = $z;
  }
  return $karte;
}

/**
 * Kennzeichnen und die Mail auslösen.
 *
 * Der eindeutige Schlüssel auf (kind, item_id) ist die Bremse: Zweimal auf
 * denselben Knopf schickt keine zweite Mail, weil die zweite Einfügung nichts
 * einfügt. Deshalb entscheidet die Zeilenzahl darüber, ob gesendet wird — und
 * nicht eine vorherige Abfrage, zwischen der und dem Schreiben jemand anderes
 * sein kann.
 */
function wichtig_setzen(string $kind, int $id, string $notiz, array $von): bool {
  $neu = q('INSERT IGNORE INTO important_flags (kind, item_id, note, set_by, set_at)
            VALUES (?,?,?,?,NOW(3))',
           [$kind, $id, mb_substr(trim($notiz), 0, 200), (int) $von['id']])->rowCount();
  if ($neu !== 1) return false;
  if (wichtig_mail_an()) wichtig_mail($kind, $id, mb_substr(trim($notiz), 0, 200), $von);
  return true;
}

/** Die Kennzeichnung zurücknehmen. Das schickt nichts — ein Widerruf ist keine Nachricht. */
function wichtig_loesen(string $kind, int $id): void {
  q('DELETE FROM important_flags WHERE kind = ? AND item_id = ?', [$kind, $id]);
}

/**
 * Die Sofortmail an alle, die den Eintrag sehen dürfen.
 *
 * Gesendet wird nach der Antwort, wie beim Push: Niemand wartet beim Klick auf
 * „wichtig" auf den Mailserver.
 *
 * Wer gekennzeichnet hat, bekommt nichts — er weiß es ja. Und jeder Empfänger
 * wird einzeln gegen item_visible() gehalten: Eine Mail ist der bequemste Weg,
 * eine Sichtbarkeit zu umgehen, die die Seiten sorgfältig einhalten.
 *
 * Die Empfängerliste steht als eigene Funktion da, damit sie sich prüfen lässt,
 * ohne dass dabei Post entsteht.
 */
function wichtig_empfaenger(string $kind, int $id, array $von): array {
  $empfaenger = [];
  foreach (rows("SELECT * FROM users WHERE email <> '' AND id <> ?", [(int) $von['id']]) as $u) {
    if (item_visible($u, $kind, $id)) $empfaenger[] = $u;
  }
  return $empfaenger;
}

function wichtig_mail(string $kind, int $id, string $notiz, array $von): void {
  $empfaenger = wichtig_empfaenger($kind, $id, $von);
  if (!$empfaenger) return;

  // Die Bezeichnung entsteht je Empfaenger, nicht einmal fuer alle (#349):
  // Sie enthaelt bei Terminen ein Datum, und ein Datum hat eine Sprache.
  $url = item_url($kind, $id);
  $wer = (string) $von['name'];
  $band = setting('band_name');

  register_shutdown_function(static function () use ($empfaenger, $kind, $id, $url, $notiz, $wer, $band): void {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    foreach ($empfaenger as $u) {
      $lang = array_key_exists($u['pref_lang'] ?? '', LANGS) ? $u['pref_lang'] : 'de';
      // Der ganze Aufbau läuft in der Sprache des Empfängers (#353) — damit
      // gilt sie auch für alles, was hier nur mittelbar vorkommt, etwa das
      // Datum, das item_label() an einen Termin hängt.
      with_lang($lang, static function () use ($u, $lang, $kind, $id, $url, $notiz, $wer, $band): void {
      $bezeichnung = item_label($kind, $id, $lang);
      $betreff = push_t($lang, 'wichtig_subject') . ': ' . $bezeichnung;
      $zeilen = [
        str_replace('%1', (string) $u['name'], push_t($lang, 'digest_hello')),
        '',
        str_replace(['%1', '%2'], [$wer, push_t($lang, 'itemkind_' . $kind)],
                    push_t($lang, 'wichtig_intro')),
        '',
        '  ' . $bezeichnung,
      ];
      if ($notiz !== '') $zeilen[] = '  ' . $notiz;
      $zeilen[] = '';
      $zeilen[] = '  ' . absolute_url('/login?weiter=' . rawurlencode($url));
      $zeilen[] = '';
      $zeilen[] = push_t($lang, 'wichtig_footer');
      $zeilen[] = '';
      $zeilen[] = '-- ';
      $zeilen[] = $band;
      band_mail_send((string) $u['email'], $betreff,
                     implode("\n", $zeilen), 'wichtig', (int) $u['id']);
      });
    }
  });
}
