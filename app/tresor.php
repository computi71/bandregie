<?php
declare(strict_types=1);

/**
 * Verschlüsselung ruhender Daten.
 *
 * Art. 32 DSGVO verlangt Maßnahmen nach dem Stand der Technik — und nennt
 * Verschlüsselung ausdrücklich als eine davon. Was eine Band hier verwaltet,
 * ist personenbezogen: wer wann wo gespielt hat, wer wie viel eingezahlt hat,
 * Telefonnummern, Adressen auf Rechnungen. Eine Sicherung davon verlässt das
 * Haus — auf ein NAS, zu einem FTP-Ziel, in eine Cloud —, und was das Haus
 * verlässt, gehört verschlüsselt.
 *
 * Verwendet wird XChaCha20-Poly1305 aus libsodium: authentifiziert, das heißt
 * eine veränderte Datei fällt beim Entschlüsseln auf und wird nicht etwa
 * halb eingespielt. Für große Dateien der secretstream, damit nie das ganze
 * Archiv im Speicher liegt.
 *
 * Der Schlüssel steht als 'data_key' in app/config.php und nirgends sonst —
 * insbesondere nicht in der Datenbank, denn dort läge er in jeder Sicherung
 * gleich mit bei, die er schützen soll. Wer ihn verliert, verliert die
 * Sicherungen: das steht in der Anleitung, und die Einstellungen sagen es auch.
 */

/** Erkennungszeichen am Anfang jeder verschlüsselten Datei. */
const CRYPT_MAGIC = "BRDT1\n";

/** Wie viel auf einmal verschlüsselt wird — groß genug, dass es zügig geht. */
const CRYPT_CHUNK = 1048576;

/**
 * Der Schlüssel dieser Installation, binär, oder null.
 *
 * Er kommt aus der Konfiguration und wird nur einmal je Anfrage geprüft: ein
 * krummer Schlüssel soll nicht bei jeder Datei neu auffallen, sondern gar
 * nicht erst benutzt werden.
 */
function crypt_key(): ?string {
  global $config;
  static $key = false;
  if ($key !== false) return $key;
  $key = null;
  $hex = trim((string) ($config['data_key'] ?? ''));
  if ($hex !== '') {
    $raw = @hex2bin($hex);
    if (is_string($raw) && strlen($raw) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
      $key = $raw;
    } else {
      error_log('Bandroadie: data_key ist kein 64-stelliger Hex-Schlüssel, die Verschlüsselung bleibt aus');
    }
  }
  return $key;
}

/** Kann verschlüsselt werden — also: libsodium vorhanden und Schlüssel gesetzt? */
function crypt_available(): bool {
  return function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')
      && crypt_key() !== null;
}

/** Ein frischer Schlüssel als Hex-Zeichenkette, zum Eintragen in config.php. */
function crypt_new_key(): string {
  return bin2hex(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
}

/** Trägt die Datei unser Erkennungszeichen? */
function crypt_is_sealed(string $path): bool {
  $fh = @fopen($path, 'rb');
  if (!$fh) return false;
  $head = (string) fread($fh, strlen(CRYPT_MAGIC));
  fclose($fh);
  return $head === CRYPT_MAGIC;
}

/** Dasselbe für einen Inhalt, den man schon in der Hand hat. */
function crypt_looks_sealed(string $blob): bool {
  return str_starts_with($blob, CRYPT_MAGIC);
}

/**
 * Eine Datei verschlüsseln. Geschrieben wird zuerst daneben und erst am Ende
 * umbenannt — ein abgebrochener Lauf hinterlässt sonst eine halbe Datei, die
 * aussieht wie eine ganze.
 */
function crypt_seal_file(string $source, string $target): bool {
  $key = crypt_key();
  if ($key === null) return false;
  $in = @fopen($source, 'rb');
  if (!$in) return false;
  $tmp = $target . '.part';
  $out = @fopen($tmp, 'wb');
  if (!$out) { fclose($in); return false; }

  [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
  fwrite($out, CRYPT_MAGIC);
  fwrite($out, $header);
  while (!feof($in)) {
    $chunk = (string) fread($in, CRYPT_CHUNK);
    if ($chunk === '' && feof($in)) break;
    $last = feof($in);
    $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
      $state, $chunk, '',
      $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0);
    fwrite($out, pack('N', strlen($cipher)));
    fwrite($out, $cipher);
  }
  fclose($in);
  fclose($out);
  sodium_memzero($state);
  return @rename($tmp, $target);
}

/**
 * Zurück in den Klartext. Schlägt die Prüfung eines Stücks fehl, bricht das
 * Ganze ab: eine veränderte Sicherung wird nicht halb eingespielt.
 */
function crypt_open_file(string $source, string $target): bool {
  $key = crypt_key();
  if ($key === null) return false;
  $in = @fopen($source, 'rb');
  if (!$in) return false;
  if (fread($in, strlen(CRYPT_MAGIC)) !== CRYPT_MAGIC) { fclose($in); return false; }
  $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
  $state = @sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
  if ($state === false) { fclose($in); return false; }

  $tmp = $target . '.part';
  $out = @fopen($tmp, 'wb');
  if (!$out) { fclose($in); return false; }
  $ok = true;
  while (!feof($in)) {
    $lenRaw = (string) fread($in, 4);
    if (strlen($lenRaw) < 4) break;
    $len = unpack('N', $lenRaw)[1] ?? 0;
    if ($len <= 0 || $len > CRYPT_CHUNK * 2) { $ok = false; break; }
    $cipher = (string) fread($in, $len);
    $res = @sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
    if ($res === false) { $ok = false; break; }
    fwrite($out, $res[0]);
    if ($res[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) break;
  }
  fclose($in);
  fclose($out);
  if (!$ok) { @unlink($tmp); return false; }
  return @rename($tmp, $target);
}

/** Ein kurzer Text — Beschreibung, Betrag — als versiegelte Zeichenkette. */
function crypt_seal(string $plain): ?string {
  $key = crypt_key();
  if ($key === null) return null;
  [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
  $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
    $state, $plain, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
  sodium_memzero($state);
  return CRYPT_MAGIC . base64_encode($header . $cipher);
}

/** Und zurück. null heißt: falscher Schlüssel oder verändert. */
function crypt_open(?string $blob): ?string {
  $key = crypt_key();
  if ($key === null || $blob === null || !crypt_looks_sealed($blob)) return null;
  $raw = base64_decode(substr($blob, strlen(CRYPT_MAGIC)), true);
  if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) return null;
  $header = substr($raw, 0, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
  $state = @sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
  if ($state === false) return null;
  $res = @sodium_crypto_secretstream_xchacha20poly1305_pull(
    $state, substr($raw, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES));
  return $res === false ? null : $res[0];
}

/**
 * Wirkt die Verschlüsselung wirklich? Art. 32 Abs. 1 Buchst. d DSGVO verlangt
 * nicht nur Maßnahmen, sondern deren regelmäßige Überprüfung — und eine
 * Verschlüsselung, die niemand je zurückgelesen hat, ist ein Versprechen und
 * keine Maßnahme.
 *
 * Geprüft wird der ganze Weg: versiegeln, wieder öffnen, und ob eine
 * veränderte Datei auffällt.
 *
 * @return array{ok: bool, message: string}
 */
function crypt_selftest(): array {
  if (!crypt_available()) return ['ok' => false, 'message' => 'kein Schlüssel gesetzt'];

  $probe = 'Bandroadie ' . bin2hex(random_bytes(16));
  if (crypt_open(crypt_seal($probe)) !== $probe) {
    return ['ok' => false, 'message' => 'Text kam nicht heil zurück'];
  }

  $dir = sys_get_temp_dir();
  $plain = tempnam($dir, 'brt');
  $sealed = $plain . '.enc';
  $back = $plain . '.out';
  try {
    file_put_contents($plain, str_repeat($probe, 1000));
    if (!crypt_seal_file($plain, $sealed)) return ['ok' => false, 'message' => 'Datei ließ sich nicht versiegeln'];
    if (!crypt_is_sealed($sealed)) return ['ok' => false, 'message' => 'Versiegelte Datei ohne Erkennungszeichen'];
    if (!crypt_open_file($sealed, $back)) return ['ok' => false, 'message' => 'Datei ließ sich nicht öffnen'];
    if (file_get_contents($back) !== file_get_contents($plain)) {
      return ['ok' => false, 'message' => 'Inhalt kam verändert zurück'];
    }
    // Ein Byte kippen: das muss auffallen, sonst ist es keine Prüfung.
    $bytes = (string) file_get_contents($sealed);
    $at = strlen($bytes) - 5;
    $bytes[$at] = chr(ord($bytes[$at]) ^ 0x01);
    file_put_contents($sealed, $bytes);
    if (crypt_open_file($sealed, $back)) {
      return ['ok' => false, 'message' => 'Eine veränderte Datei wurde angenommen'];
    }
    return ['ok' => true, 'message' => 'versiegelt, geöffnet, Veränderung erkannt'];
  } finally {
    foreach ([$plain, $sealed, $back, $back . '.part', $sealed . '.part'] as $leftover) @unlink($leftover);
  }
}

/**
 * Eine schon abgelegte Datei an Ort und Stelle versiegeln. Zuerst daneben
 * schreiben, dann tauschen: bricht es ab, liegt weiterhin die unversehrte
 * Fassung da — ein halb verschlüsselter Anhang wäre unwiederbringlich.
 */
function file_seal_at_rest(string $path): bool {
  if (!crypt_available() || !is_file($path) || crypt_is_sealed($path)) return false;
  $tmp = $path . '.sealing';
  if (!crypt_seal_file($path, $tmp)) { @unlink($tmp); return false; }
  if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
  @chmod($path, 0600);
  return true;
}

/**
 * Alle noch offenen Anhänge versiegeln — für die Dateien aus der Zeit vor dem
 * Schlüssel. Läuft auch mehrfach: versiegelte werden übersprungen.
 *
 * @return array{done: int, failed: int, left: int}
 */
function files_seal_all(int $limit = 0): array {
  $done = 0; $failed = 0; $left = 0;
  foreach (glob(FILES_DIR . '/*') ?: [] as $path) {
    if (!is_file($path) || crypt_is_sealed($path)) continue;
    if ($limit > 0 && $done + $failed >= $limit) { $left++; continue; }
    file_seal_at_rest($path) ? $done++ : $failed++;
  }
  return ['done' => $done, 'failed' => $failed, 'left' => $left];
}

/**
 * Und zurück: alle Anhänge entsiegeln. Wer den Schlüssel aus der Hand geben
 * oder umziehen will, braucht diesen Weg — eine Verschlüsselung ohne Ausgang
 * ist eine Falle.
 *
 * @return array{done: int, failed: int}
 */
function files_unseal_all(): array {
  $done = 0; $failed = 0;
  foreach (glob(FILES_DIR . '/*') ?: [] as $path) {
    if (!is_file($path) || !crypt_is_sealed($path)) continue;
    $tmp = $path . '.opening';
    if (crypt_open_file($path, $tmp) && @rename($tmp, $path)) { $done++; continue; }
    @unlink($tmp);
    $failed++;
  }
  return ['done' => $done, 'failed' => $failed];
}
