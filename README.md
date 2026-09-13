# Bandregie

Installation and update. Everything else is in [`docs/`](docs/).

**Requirements:** PHP 8.1+ with PDO, MariaDB or MySQL, a web server. No
framework, no build step, no package manager, no third-party script.

The full version of both chapters, with the web server configuration and the
pitfalls, is in the [wiki](https://github.com/computi71/bandregie/wiki).

## Install — the classic way

1. **Database.** Create a database and a user with rights on it.

   ```sql
   CREATE DATABASE bandregie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bandregie'@'localhost' IDENTIFIED BY '<password>';
   GRANT ALL ON bandregie.* TO 'bandregie'@'localhost';
   ```

2. **Files.** Copy the repository to the server and point the document root at
   `httpdocs/`. `data/` must belong to the user the web server runs as — uploads,
   attachments and backups are written there.

3. **Configuration.** Copy `app/config.example.php` to `app/config.php` and
   enter the database access. The file stays out of the repository and survives
   every update.

4. **Web server.** Everything that is not an existing file goes to
   `httpdocs/index.php`. The [wiki](https://github.com/computi71/bandregie/wiki/Installation)
   has the complete nginx and Apache configuration, the PHP upload limits and
   the TLS notes.

5. **First run.** Open the page. The database is created on the first request,
   and the first account is made in the browser.

## Install — over a git connection

The server holds a checkout and pulls instead of receiving copied files:

```
sudo -u www-data git clone https://github.com/computi71/bandregie.git /var/www/bandregie
```

`app/config.php` and `data/` are not in the repository; they stay on the server.
With Plesk's git extension it is two steps — fetching alone changes nothing on
disk:

```
plesk ext git --fetch  -domain <domain> -name <repo>
plesk ext git --deploy -domain <domain> -name <repo>
```

Details, including what to check afterwards:
[Installation over git](https://github.com/computi71/bandregie/wiki/Installation-over-git).

## Update

An update replaces files; the database follows by itself, because the
migrations run on the next request and may run twice.

```
sudo -u www-data git -C /var/www/bandregie pull      # git checkout
plesk ext git --fetch … && plesk ext git --deploy …  # Plesk
```

By hand: copy the new files over the old ones and leave `data/` and
`app/config.php` alone. Afterwards read `VERSION` from the served directory —
that is what proves which version is live.

More, including the way back:
[Updating](https://github.com/computi71/bandregie/wiki/Updating).

---

[`docs/`](docs/) · [CONTRIBUTING.md](CONTRIBUTING.md) · [SECURITY.md](SECURITY.md) · [LICENSE.md](LICENSE.md)
