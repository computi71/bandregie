# Backups, encryption and restoring

The part nobody thinks about until the evening it matters.

## Backups

Back up the database and the `data/` folder. Updating the code never touches
either, but never overwrite `data/` or `app/config.php` when deploying.

Every run reads its own archive back before recording success: the gzip stream
has to end cleanly, every tar header has to be plausible, the two closing
blocks have to be there, and `database.sql` has to be present and non-empty.
With encryption on, the sealed file is decrypted a second time to confirm it
opens and carries its final tag. A run that cannot do that records an error
instead of a size — which matters because the retention window counts only
successful runs, and a plausible-looking archive that nobody ever read back
used to push a real backup out of it.

Restoring verifies before it touches anything. A database cannot be rolled
back — MySQL has no transaction for `DROP TABLE` — so an incomplete archive
has to be refused while everything is still intact, not discovered halfway
through. Truncated, unreadable, or missing `database.sql`: all three stop with
the tables untouched, and the message says which of them it was.

Restore from the command line, which keeps working when the site does not:

```bash
php app/backup.php restore data/backups/bandregie-2026-08-03-131335.tar.gz.enc
```

## Plesk: close the statistics directory

Plesk publishes AWStats reports under `/plesk-stat/`, and by default anybody
who knows the address can read them. They contain the IP addresses of your
visitors, the pages they asked for and where they came from — personal data
under GDPR Art. 4, sitting in front of no login at all.

*Websites & Domains → Hosting & DNS → Web Statistics* → tick **accessible via
password-protected directory**, or:

```bash
plesk bin domain --update yourdomain.tld -webstat-protdir-access true
```

The system check tests this on a Plesk installation and says so when the
directory answers to everyone.

## Encryption at rest

A backup travels — to a NAS, an FTP target, a cloud — and the band's treasury
travels with it. Set an encryption key and it does not travel in the clear:

```bash
php app/backup.php key
```

Put the line it prints into `app/config.php` as `data_key`. From then on
backups are written as `.tar.gz.enc` and attachments are sealed on disk;
existing attachments can be sealed afterwards under *Settings → Encryption at
rest*. XChaCha20-Poly1305 from libsodium, authenticated, so a tampered archive
is refused rather than half-restored.

**Keep the key where you keep the database password — and not inside the
backup it protects.** Without it an encrypted backup cannot be opened by
anyone, including you.

What is *not* encrypted, and why: the live database, because the server has to
sort and sum in it; and `data/uploads`, because the web server hands those
files out directly. Attachments under `data/files` go through a permission
check and are sealed.

The system check verifies that the encryption actually works — it seals,
opens, then flips a byte and confirms the result is refused. GDPR Art. 32(1)(d)
asks for effectiveness to be tested, not intended.

## Restoring on a new server

The key is in `app/config.php`, and `app/config.php` is not part of the
backup. On a fresh machine, in this order:

1. Install the code and create the database (steps 1–3 above).
2. Write `app/config.php` — database credentials **and the same `data_key`**
   as the old server.
3. Copy the archive to `data/backups/`, or upload it under *Settings →
   Backup*.
4. Restore:

   ```bash
   php app/backup.php restore bandregie-YYYY-MM-DD-HHMMSS.tar.gz.enc
   ```

The restore refuses to start if the archive cannot be opened — with no key,
or with the wrong one, nothing is touched and the message says which of the
two it was. It also writes a safety copy of the current state before replacing
anything.

Walk both paths once on a spare installation before you need them: a restore
nobody has tried is a hope rather than a plan.
