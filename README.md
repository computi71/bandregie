# Bandroadie – Band Website & Organization Tool

Public band page plus an internal organization area for one band: events with availability polling (✔/?/✘), status workflow, three times (meet / stage / end), fee tracking and per-event comments; songs with a lifecycle and live-play counters; setlists with pauses, encore markers, copy, a stage-ready print view and a locked history; venues with play history; absences with conflict warnings; tasks, photos, file attachments, member management, a band treasury, equipment with recurring deadlines and an iCal calendar feed.

**White-label:** band name, logo, background image and favicon are configured entirely in the settings — every band makes the instance its own.

**Multilingual:** the interface ships in German, English, Dutch, French, Spanish and Italian; band texts and the legal pages are maintained per language, with a fallback chain (selected language → English → German). Which languages appear is up to the admin, and every string can be corrected in the band area.

**Stack:** PHP 8.1+ with MariaDB/MySQL (PDO), no framework, no build step, no dependencies.

## Quick start (local)

1. Create a database and user:
   ```sql
   CREATE DATABASE bandroadie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bandroadie'@'localhost' IDENTIFIED BY 'YOUR-PASSWORD';
   GRANT ALL PRIVILEGES ON bandroadie.* TO 'bandroadie'@'localhost';
   ```
2. Copy `app/config.example.php` to `app/config.php` and enter the credentials.
3. Run the dev server:
   ```
   php -S localhost:8090 -t httpdocs httpdocs/index.php
   ```

Open http://localhost:8090. On the first request all tables are created and the
translations are imported automatically.

**First login:** an administrator account is created with a random password,
written once to `data/INITIAL-PASSWORD.txt` (outside the webroot). Log in with
it, set your own password when prompted, change the email address under
*Intern → Profil*, then delete that file.

## Deployment (Plesk or plain Debian)

1. Create the domain/vhost and a MariaDB database with a dedicated user.
2. Deploy the repository so that only `httpdocs/` is the document root —
   `app/`, `data/` and `seed/` must stay outside the webroot.
3. Create `app/config.php` from `app/config.example.php`.
4. PHP 8.1+ with `pdo_mysql` and `fileinfo` (default on Plesk and on Debian's
   `php-fpm` + `php-mysql`).
5. Apache: the shipped `.htaccess` routes everything to `index.php`.
   nginx: `try_files $uri /index.php$is_args$args;`
6. Enable TLS (Let's Encrypt) — the calendar feed, logins and the member area
   should never run over plain HTTP.
7. Sending mail (member invitations, password reset) uses PHP's `mail()`; make
   sure the host can deliver mail from your domain.
8. Back up the database and the `data/` folder regularly. Never overwrite
   `data/` or `app/config.php` during code updates.

## Legal pages (Germany)

The public site ships with an imprint and a privacy policy, both editable per
language in the settings and excluded from search engines. Embedded YouTube and
Spotify players use a two-click consent flow by default (GDPR / § 25 TDDDG);
the admin can switch to loading them directly. The public site can also run in
redirect mode — visitors are forwarded to a social profile while the member
area, the calendar feed and the legal pages stay reachable.

Placeholders in square brackets in the imprint and privacy texts must be
replaced with your own details before going public. These templates are a
starting point, not legal advice.

## Layout

- `httpdocs/index.php` — router / all routes (single public entry point)
- `httpdocs/assets/` — stylesheet and small scripts (lightbox, password strength)
- `httpdocs/.htaccess` — URL rewriting
- `app/bootstrap.php` — database schema, migrations, helpers, auth, UI strings
- `app/config.php` — database credentials (not in git; template: `config.example.php`)
- `app/views/` — templates (public + internal)
- `seed/translations/` — translation seeds, imported automatically on first run
- `data/` — uploads and attachments (outside the webroot, created automatically)

## Contributing

German is the source language for the interface: new strings go into
`UI_STRINGS` in `app/bootstrap.php` and are then translated in
`seed/translations/`. Code comments and commit messages are English.

## Roadmap

See the GitHub issues and milestones: drag-and-drop setlist editor, per-event
PA and lighting planning, stage rider builder, XLS export, song ratings,
discussions, IMAP import of booking requests, mobile apps.
