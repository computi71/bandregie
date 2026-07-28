# Bandroadie – Band Website & Organization Tool

![License: FSL-1.1-ALv2](https://img.shields.io/badge/license-FSL--1.1--ALv2-blue)

**Free for your own band.** Install it, run it, change it, use it for a band
that earns money with its gigs — all covered. The one thing reserved to the
author is offering Bandroadie itself as a commercial product or hosted service.
Two years after each release, that restriction lapses and the version becomes
Apache 2.0. See [LICENSE.md](LICENSE.md).

Public band page plus an internal organization area for one band: events with availability polling (✔/?/✘), status workflow, three times (meet / stage / end), fee tracking and per-event comments; songs with a lifecycle and live-play counters; setlists with pauses, encore markers, copy, a stage-ready print view and a locked history; venues with play history; absences with conflict warnings; tasks, photos, file attachments, member management, a band treasury, equipment with recurring deadlines and an iCal calendar feed.

**White-label:** band name, logo, background image and favicon are configured entirely in the settings — every band makes the instance its own.

**Multilingual:** the interface ships in German, English, Dutch, French, Spanish and Italian; band texts and the legal pages are maintained per language, with a fallback chain (selected language → English → German). Which languages appear is up to the admin, and every string can be corrected in the band area.

**Stack:** PHP 8.1+ with MariaDB/MySQL (PDO), no framework, no build step, no dependencies.

## Installation

Requirements: PHP 8.1 or newer with `pdo_mysql` and `fileinfo`, MariaDB 10.4+
or MySQL 8, and a web server. The examples use nginx with PHP-FPM on Debian;
Apache works too (see below).

### 1. Database

```sql
CREATE DATABASE bandroadie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bandroadie'@'localhost' IDENTIFIED BY 'YOUR-PASSWORD';
GRANT ALL PRIVILEGES ON bandroadie.* TO 'bandroadie'@'localhost';
```

### 2. Code

Clone the repository somewhere outside the web root, for example
`/var/www/bandroadie`, then copy `app/config.example.php` to `app/config.php`
and fill in the credentials above.

Only `httpdocs/` may be served publicly. `app/` holds the code and your
database password, `data/` holds uploads and file attachments, `seed/` holds
the translation files — none of them belong in the document root.

### 3. nginx

```nginx
server {
    listen 80;
    server_name band.example.com;

    # The document root points *inside* the project: everything above
    # httpdocs (app/, data/, seed/, config.php) stays unreachable.
    root /var/www/bandroadie/httpdocs;
    index index.php;

    # Must be at least as large as the biggest upload you want to allow
    # (attachments are capped at 20 MB in the app), plus form overhead.
    client_max_body_size 30m;

    # Single front controller: serve real files (CSS, JS) directly and hand
    # every other path to index.php, which does the routing. Uploaded images
    # are not files here — they are served by index.php from outside the root.
    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # match your PHP version
    }

    # Stylesheets and scripts carry the release in their address
    # (/assets/style.css?v=1.42.0), so the browser may keep them and still
    # gets the new file after an update. Without this it revalidates every
    # one of them on every page view. Apache installs get the same from the
    # .htaccess that ships in httpdocs/; nginx cannot read that file.
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Never expose version control or Apache leftovers
    location ~ /\.(ht|git) { deny all; }
}
```

Enable it and reload:

```bash
sudo ln -s /etc/nginx/sites-available/bandroadie /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 4. PHP upload limits

PHP's defaults (2 MB per file, 8 MB per request) are smaller than what the
app offers, and PHP discards oversized uploads before any code runs. Raise
them in your PHP-FPM pool or `php.ini`:

```ini
upload_max_filesize = 25M
post_max_size = 30M
```

Keep `client_max_body_size` in nginx at or above `post_max_size`. Bandroadie
reports rejected uploads instead of failing silently, but only the server
settings decide what actually gets through.

### 5. Permissions

The web server user needs write access to `data/`, and read access to
`app/config.php` — it holds the database password, so keep it off-limits for
everyone else:

```bash
sudo chown -R www-data:www-data /var/www/bandroadie/data
sudo chown root:www-data /var/www/bandroadie/app/config.php
sudo chmod 640 /var/www/bandroadie/app/config.php
```

Use the account that deploys the code as the owner if you pull with a
non-root user. Note that with OPcache enabled a permission mistake here can
stay hidden until the next PHP-FPM restart.

### 6. TLS

Logins, the member area and the calendar feed should never run over plain
HTTP: `sudo certbot --nginx -d band.example.com`.

### 7. First run

Open the site. All tables are created and the translations are imported
automatically. An administrator account is created with a random password,
written once to `data/INITIAL-PASSWORD.txt` (outside the web root). Log in with
it, set your own password when prompted, change the email address under
*Profil*, then delete that file.

Under *Settings → Demo data* one button fills the installation with a fictional
band so you can explore every feature, and a second button removes exactly
those rows again.

### Apache instead of nginx

Point the `DocumentRoot` at `httpdocs/`, allow `.htaccess` overrides
(`AllowOverride All`) and enable `mod_rewrite` — the shipped `.htaccess`
routes everything to `index.php`. Set `upload_max_filesize` and
`post_max_size` as described above.

### Mail

Member invitations and password resets use PHP's `mail()`. The host must be
able to deliver mail for your domain; send from an address on that domain
(the app uses `no-reply@<your-domain>`) so SPF checks pass.

### Backups

Back up the database and the `data/` folder. Updating the code never touches
either, but never overwrite `data/` or `app/config.php` when deploying.

### Development

For quick local work PHP's built-in server is enough — it needs no web server
config, but it is single-threaded and not meant for production:

```bash
php -S localhost:8090 -t httpdocs httpdocs/index.php
```

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

By contributing you agree that your contribution is licensed under the
project's license and that the author may also use it under other terms,
including commercially. This keeps it possible to offer Bandroadie as a
service later without having to track down every contributor. Contributors are
credited in `CONTRIBUTORS` and shown in the member area.

## License

[Functional Source License 1.1 with Apache 2.0 future license](LICENSE.md)
(FSL-1.1-ALv2), copyright 2026 Michael Rothe.

In plain words: any use is permitted except a *competing use* — making
Bandroadie available to others as a commercial product or service that
substitutes for it. Running it for your own band, modifying it, redistributing
it and building on it are all fine. Each released version additionally becomes
available under Apache 2.0 two years after its release.

This is a source-available license, not an OSI-approved open source license,
because open source by definition may not restrict commercial use. GitHub
therefore displays it as "Other".

## Roadmap

See the GitHub issues and milestones: drag-and-drop setlist editor, per-event
PA and lighting planning, stage rider builder, XLS export, song ratings,
discussions, IMAP import of booking requests, mobile apps.
