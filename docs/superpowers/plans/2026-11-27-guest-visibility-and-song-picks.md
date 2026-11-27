# Guest visibility and song picks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The band ticks per booking what a guest's link shows; behind one of those ticks the guest marks which songs he can play, and that list belongs to him, not to the booking.

**Architecture:** A new library `app/gaeste.php` takes the guest helpers out of `app/bootstrap.php` and holds everything new. Three `TINYINT(1)` columns on `guest_bookings` decide what the token page draws. A new table `guest_songs`, built like `song_votes`, keyed on `guest_id`, holds the answers. Members read them in the setlist editor and on `/intern/gaeste`.

**Tech Stack:** PHP 8 / MariaDB, server-rendered views, check scripts in `bin/` (no test framework).

**Spec:** `docs/superpowers/specs/2026-11-27-guest-visibility-and-song-picks-design.md` (issues #449, #450, #451, #452)

## Global Constraints

- UI texts German in `app/strings/de.php`; en/nl/fr/es/it in `seed/translations/218-gaeste.sql`, `INSERT … ON DUPLICATE KEY UPDATE value = value`. The earliest seed wins: a text that already exists elsewhere is changed in its origin seed with a guarded `DELETE`, never duplicated here.
- Commits, issue comments, release notes in English; no co-author line; each commit message says why. Code comments in German, explaining why, in the style of the neighbouring code; reference the issue.
- Migrations idempotent (`column_exists`, `table_exists` guards), never read live constants — the defaults `1, 1, 0` are written as literals.
- All SQL with placeholders. Every new internal route checks its module with `perm_allows` and calls `deny_in_demo` where it writes.
- Every new token route behaves like the existing ones: invalid, cancelled, declined or expired token → the same dead end, with no hint which it was.
- Any change under `httpdocs/assets/` bumps `VERSION` in `httpdocs/sw.js` (currently `bandregie-v87`).
- Check script `bin/gast-pruefen.php <dir> --schreibt-testdaten`, prefix `ZZGA ` for test rows, pre-cleanup of leftovers, cleanup at the end; runs on staging as `www-data` only, never on a band instance.
- Guest names are behind `gaeste` read everywhere a member can see them.

## Review Focus

1. The band unticks `show_songs` after the guest has answered → the POST route refuses, the answers stay, the section is gone. Test in Task 3.
2. A cancelled, declined or expired booking's token on `/lieder` and `/lied/{id}` → dead end, nothing written. Test in Task 3.
3. One guest booked at two events that use the same setlist → he stands in the filter once. Test in Task 5.
4. A song is answered, archived, then set active again → it counts again, no orphan row, no total above the song count. Test in Task 4.
5. A name search with `%`, `_` or a two-character query → empty result, no error, no wildcard leaking into the `LIKE`. Test in Task 6.

---

### Task 1: Move the guest helpers into their own library

A pure move, so the later tasks add to one file instead of growing `bootstrap.php` further. Nothing changes behaviour.

**Files:**
- Create: `app/gaeste.php`
- Modify: `app/bootstrap.php` (remove the moved functions; `require_once` the new file next to the other library requires, ~lines 38-57)

**Interfaces:**
- Produces: the existing functions unchanged — `guest_status_label`, `guest_event_place`, `guest_access_until`, `guest_booking_by_token`, `guest_ratings_map`, `guest_stars`, `guest_share_links`, `guest_bookings_map`, `guest_invite_mail`.
- `share_links()` and the `member_*` helpers stay in `bootstrap.php`: they are not about guests.

- [ ] **Step 1: Move the nine functions** from `app/bootstrap.php` (the block around lines 3693-3880) into `app/gaeste.php`, with a file header in the style of `app/terminwahl.php` saying what a guest is and that the token of a booking is his only key.
- [ ] **Step 2: Require it** in `app/bootstrap.php` next to the other library requires.
- [ ] **Step 3: Verify nothing else moved**

Run: `git diff -M --stat` — Expected: `app/gaeste.php` added, `app/bootstrap.php` shrunk by about the same number of lines, no other file touched.

- [ ] **Step 4: Run the route check**

Run: `php bin/routen-pruefen.php` — Expected: unchanged result, no "undefined function".

- [ ] **Step 5: Commit**

```bash
git commit -m "Give the guest helpers their own file before they grow (#449)"
```

---

### Task 2: Decide per booking what the link shows

**Files:**
- Modify: `app/schema.php` (new columns, after the `choice_of` block ~line 2085)
- Modify: `app/gaeste.php` (new helper)
- Modify: `httpdocs/index.php` (booking route `/intern/gaeste/buchen` ~3309; new route `/intern/gaeste/buchung/{id}/sicht`; guest page block ~404-455)
- Modify: `app/views/intern/_event_gaeste.php` (booking form, per-row form)
- Modify: `app/views/public/gast.php` (draw each section only when allowed)
- Modify: `app/views/intern/einstellungen.php` (`$privacyDefault`, §14)
- Modify: `app/strings/de.php`, create `seed/translations/218-gaeste.sql`
- Create: `bin/gast-pruefen.php` (structure as `bin/abstimmung-pruefen.php`: `$pruefe`, helpers, sections, cleanup)

**Interfaces:**
- Produces: `guest_may_see(array $b, string $teil): bool` — `$teil` is `'rider' | 'setlist' | 'songs'`; false for anything else.
- Consumes: nothing from Task 1 beyond the moved functions.

- [ ] **Step 1: Write the failing checks** (section "1. Was der Link zeigt")

```php
$pruefe('Alte Buchung zeigt Rider und Setliste', guest_may_see($alt, 'rider') && guest_may_see($alt, 'setlist'));
$pruefe('Alte Buchung zeigt keine Liederliste', !guest_may_see($alt, 'songs'));
$pruefe('Unbekannter Teil ist zu', !guest_may_see($alt, 'kasse'));
$pruefe('Gastseite ohne Rider nennt ihn nicht', !str_contains($seite, '/rider'));
```

The page text comes from a request to `/gast/{token}` the way the existing check scripts fetch a page.

- [ ] **Step 2: Run to verify it fails**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten` on staging after pushing the branch and `git pull` there.
Expected: FAIL, `guest_may_see` undefined.

- [ ] **Step 3: Migration**

In `app/schema.php`, guarded by `column_exists('guest_bookings', 'show_rider')`:
`ALTER TABLE guest_bookings ADD COLUMN show_rider TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_setlist TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN show_songs TINYINT(1) NOT NULL DEFAULT 0` — literals, with a comment saying why the defaults may never follow a constant.

- [ ] **Step 4: Implement `guest_may_see(array $b, string $teil): bool`** in `app/gaeste.php`, mapping the three names to the three columns and returning false for anything unknown.
- [ ] **Step 5: Booking form and booking route**

Three checkboxes in `_event_gaeste.php` (rider and setlist ticked, songs not); the route writes them into the `INSERT`. Per booking row a `<details>` with the same three and a save button, for `perm_allows($user, 'gaeste', 'write')`, not demo, booking neither `abgesagt` nor `storniert`.

- [ ] **Step 6: New route** `POST /intern/gaeste/buchung/(\d+)/sicht` beside the existing `stornieren|erneut` route: `deny_in_demo`, write the three flags, `flash`, `back('/intern/termine')`.
- [ ] **Step 7: Guest page** draws the rider button behind `guest_may_see($b, 'rider')` and the setlist block behind `'setlist'`; the `/rider`, `/setliste` and `/song/{id}` routes refuse the same way, so a guessed URL gains nothing.
- [ ] **Step 8: Texts and privacy**

New German strings; `218-gaeste.sql` with all five other languages. In `$privacyDefault` §14, replace the fixed list ("Ablauf, Stagerider und Setliste") with the sentence that the band decides per booking what the link shows.

- [ ] **Step 9: Run the check script**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: section 1 all `ok`.

- [ ] **Step 10: Commit**

```bash
git commit -m "Let the band decide per booking what a guest's link shows (#449)"
```

---

### Task 3: The guest marks what he can play

**Files:**
- Modify: `app/schema.php` (new table), `app/gaeste.php`, `httpdocs/index.php` (guest routes block ~404-455)
- Create: `app/views/public/gast_lieder.php`
- Modify: `app/views/public/gast.php` (the new section), `app/strings/de.php`, `seed/translations/218-gaeste.sql`, `bin/gast-pruefen.php`

**Interfaces:**
- Consumes: `guest_may_see()` from Task 2.
- Produces:
  - `guest_songs_list(): array` — rows `id, title, artist, song_key, tempo, duration_sec` with `status IN ('aktiv','in_arbeit')`, ordered by title
  - `guest_song_answers(int $guestId): array` — `song_id => 'yes'|'maybe'|'no'`
  - `guest_song_set(int $guestId, int $songId, string $status): bool` — false when the status is not one of the three or the song is not in `guest_songs_list()`
  - `guest_song_counts(int $guestId): array` — `['yes'=>int,'maybe'=>int,'no'=>int,'answered'=>int,'total'=>int]`, counting only songs from `guest_songs_list()`

- [ ] **Step 1: Write the failing checks** (section "2. Liederliste")

```php
$pruefe('Antwort landet beim Gast', guest_song_set($gast, $lied, 'yes') && guest_song_answers($gast)[$lied] === 'yes');
$pruefe('Erfundener Stand wird abgelehnt', !guest_song_set($gast, $lied, 'vielleicht-schon'));
$pruefe('Vorschlag ist nicht wählbar', !guest_song_set($gast, $vorschlagLied, 'yes'));
$pruefe('Archiviertes Lied zählt nicht mit', guest_song_counts($gast)['total'] === $aktiveLieder);
$pruefe('Antwort überlebt die Buchung', guest_song_answers($gast)[$lied] === 'yes');   // nach Ablauf und neuer Buchung
$pruefe('Ohne Haken keine Liste', guest_fetch($tokenOhneHaken, '/lieder') === 'weg');
$pruefe('Ohne Haken keine Antwort', !guest_post($tokenOhneHaken, '/lied/' . $lied, 'no'));
$pruefe('Stornierte Buchung erreicht nichts', guest_fetch($tokenStorniert, '/lieder') === 'weg');
$pruefe('Abgesagte Buchung erreicht nichts', guest_fetch($tokenAbgesagt, '/lieder') === 'weg');
$pruefe('Abgelaufene Buchung erreicht nichts', guest_fetch($tokenAbgelaufen, '/lieder') === 'weg');
$pruefe('Wieder aktiv zählt wieder', guest_song_counts($gast)['yes'] === 1);           // archiviert, dann wieder aktiv
```

- [ ] **Step 2: Run to verify it fails**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: FAIL, `guest_song_set` undefined.

- [ ] **Step 3: Migration** — `guest_songs` exactly as the design states, guarded by `table_exists`.
- [ ] **Step 4: Implement the four functions** in `app/gaeste.php`. `guest_song_set` validates the status against `['yes','maybe','no']` and the song against the live list before writing `INSERT … ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW(3)`.
- [ ] **Step 5: Routes**

`GET /gast/{token}/lieder` and `POST /gast/{token}/lied/{id}` inside the existing token block, after the `status === 'zugesagt'` gate that already protects `rider`, `setliste` and `song`, plus `guest_may_see($b, 'songs')`. The POST writes one answer and redirects back to the list.

- [ ] **Step 6: Views**

`gast_lieder.php` in the style of `gast.php` (same shell, no internal links): per song title, artist, key, tempo, length and three buttons in one form each, with `csrf_field()`. In `gast.php` the new section behind `guest_may_see($b, 'songs')` with the progress line and the link.

- [ ] **Step 7: Delete cascade** — deleting a song deletes its `guest_songs` rows, next to where the song's other dependants go.
- [ ] **Step 8: Texts** in `de.php` and `218-gaeste.sql`.
- [ ] **Step 9: Run the check script**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: sections 1 and 2 all `ok`.

- [ ] **Step 10: Commit**

```bash
git commit -m "Let a guest say which songs he can play, and keep it past the booking (#450)"
```

---

### Task 4: What the band sees of it, and how he hears about it

**Files:**
- Modify: `app/gaeste.php`, `httpdocs/index.php` (`/intern/gaeste` route ~3242, new erase route, `erneut` route), `app/views/intern/gaeste.php`, `app/views/intern/_event_gaeste.php`, `app/strings/de.php`, `seed/translations/218-gaeste.sql`, `bin/gast-pruefen.php`

**Interfaces:**
- Consumes: `guest_song_counts`, `guest_song_answers` from Task 3.
- Produces:
  - `guest_songs_clear(int $guestId): int` — rows removed
  - `guest_songs_map(array $guestIds): array` — `guest_id => counts`, one query for a page

- [ ] **Step 1: Write the failing checks** (section "3. Was die Band sieht")

```php
$pruefe('Antworten löschen leert die Liste', guest_songs_clear($gast) === 2 && guest_song_answers($gast) === []);
$pruefe('Antworten löschen lässt die Buchung stehen', (bool) row('SELECT 1 FROM guest_bookings WHERE guest_id = ?', [$gast]));
$pruefe('Zahlen je Gast in einer Abfrage', guest_songs_map([$gast])[$gast]['yes'] === 1);
```

- [ ] **Step 2: Run to verify it fails**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: FAIL, `guest_songs_clear` undefined.

- [ ] **Step 3: Implement the two functions.**
- [ ] **Step 4: Event card** — the guest's row shows `🎵 23 ✔ · 4 ? · 2 ✘` when he has answered at all, from `guest_songs_map`. No push, no mark per song.
- [ ] **Step 5: `/intern/gaeste`** — per guest a `<details>` with his answers grouped by status, and the button "Antworten löschen" (`POST /intern/gaeste/{id}/antworten-loeschen`, `gaeste` write, `deny_in_demo`, `data-confirm`).
- [ ] **Step 6: Reaching him again** — the resend button and the WhatsApp/SMS links in `_event_gaeste.php` also appear while the booking is `zugesagt` and `show_songs` is on; `guest_invite_mail()` and `guest_share_links()` take a second wording for that case, pointing at the song list instead of asking for an answer.
- [ ] **Step 7: Texts and privacy** — new strings in `de.php` and `218-gaeste.sql`. In `$privacyDefault` §14 the second new sentence: a guest's answers about songs are kept beyond the single engagement so he is not asked again, and are erased on request. It belongs in this commit, not a later one — the data starts outliving the booking here.
- [ ] **Step 8: Run the check script**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: sections 1-3 all `ok`.

- [ ] **Step 9: Commit**

```bash
git commit -m "Show the band what a guest answered, and let it ask him again (#450)"
```

---

### Task 5: Filter and marks in the setlist

**Files:**
- Modify: `app/gaeste.php`, `httpdocs/index.php` (setlist edit route ~1690), `app/views/intern/setlist_edit.php`, `app/strings/de.php`, `seed/translations/218-gaeste.sql`, `bin/gast-pruefen.php`

**Interfaces:**
- Consumes: `guest_song_answers` from Task 3.
- Produces: `setlist_guests(int $setlistId): array` — one row per counting guest of the events using this setlist, `guest_id => ['name' => string, 'answers' => [song_id => status]]`, each guest once, ordered by name. Counting = booking neither `abgesagt` nor `storniert`, event date `>= CURDATE()`, at least one answer.

- [ ] **Step 1: Write the failing checks** (section "4. Setliste")

```php
$pruefe('Gast der Setliste steht im Filter', array_key_exists($gast, setlist_guests($sl)));
$pruefe('Zweimal gebucht heißt einmal im Filter', count(setlist_guests($sl)) === 1);
$pruefe('Stornierte Buchung nicht im Filter', !array_key_exists($gastStorniert, setlist_guests($sl)));
$pruefe('Vergangener Termin nicht im Filter', !array_key_exists($gastGestern, setlist_guests($sl)));
$pruefe('Ohne Antwort nicht im Filter', !array_key_exists($gastStumm, setlist_guests($sl)));
```

Plus, against the rendered page: the picker under `?gast=<id>` contains the `yes` and `maybe` songs and not the `no` one; the mark appears on an entry the guest answered `no`; neither filter nor mark appears for an account with `setlists` but without `gaeste`; the print page contains no mark.

- [ ] **Step 2: Run to verify it fails**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: FAIL, `setlist_guests` undefined.

- [ ] **Step 3: Implement `setlist_guests`** — one query over `events` (`setlist_id = ?`), `guest_bookings` and `guests`, plus the answers; `GROUP BY` the guest so two bookings give one row.
- [ ] **Step 4: Filter** — the setlist edit route reads `?gast=<guest_id>`, checks `perm_allows($me, 'gaeste')`, and narrows `$available` to that guest's `yes` and `maybe`. The view draws a `<form method="get">` with a `<select>` above the picker; `maybe` songs carry their marker in the option text. No script.
- [ ] **Step 5: Marks on the entries** — each entry shows a quiet mark naming the guest when a counting guest answered `maybe` or `no` for it. Editing view only: `setlist_print.php` and the guest's `/gast/{token}/setliste` stay untouched.
- [ ] **Step 6: Texts** in `de.php` and `218-gaeste.sql`.
- [ ] **Step 7: Run the check script**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: sections 1-4 all `ok`.

- [ ] **Step 8: Commit**

```bash
git commit -m "Build a setlist against what a booked guest can play (#451)"
```

---

### Task 6: Look before a second guest row is written

**Files:**
- Modify: `app/gaeste.php`, `httpdocs/index.php` (new search route, booking route ~3309), `app/views/intern/_event_gaeste.php`, `httpdocs/sw.js`, `app/strings/de.php`, `seed/translations/218-gaeste.sql`, `bin/gast-pruefen.php`
- Create: `httpdocs/assets/gastsuche.js`

**Interfaces:**
- Produces: `guests_by_name(string $q, int $limit = 5): array` — `id, name, function_name` of guests whose name contains `$q`, case-insensitive, ordered by name; `[]` for a query shorter than three characters after trimming. `%` and `_` are escaped before the `LIKE`.

- [ ] **Step 1: Write the failing checks** (section "5. Doppelte Gäste")

```php
$pruefe('Drei Zeichen finden den Gast', guests_by_name('ZZG')[0]['id'] === $gast);
$pruefe('Zwei Zeichen finden nichts', guests_by_name('ZZ') === []);
$pruefe('Prozentzeichen ist kein Platzhalter', guests_by_name('%%%') === []);
$pruefe('Unterstrich ist kein Platzhalter', guests_by_name('Z_G') === []);
$pruefe('Höchstens fünf', count(guests_by_name('ZZG', 5)) === 5);
```

Plus, against the route: booking the existing name again creates no second `guests` row, and `/intern/gaeste/suche` without `gaeste` read answers nothing.

- [ ] **Step 2: Run to verify it fails**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: FAIL, `guests_by_name` undefined.

- [ ] **Step 3: Implement `guests_by_name`.**
- [ ] **Step 4: Search route** `GET /intern/gaeste/suche` — `perm_allows($me, 'gaeste')`, returns names and function only, as JSON, no contact data.
- [ ] **Step 5: Guard in the booking route** — a typed name equal to an existing guest's name (case-insensitive, trimmed) creates no row; the form comes back with the hint and that guest preselected, and the band decides.
- [ ] **Step 6: `gastsuche.js`** — from the third character, ask the route and draw the hint with a button that picks the guest in the dropdown. Without the script the server guard still holds; bump `VERSION` in `httpdocs/sw.js` to `bandregie-v88`.
- [ ] **Step 7: Texts** in `de.php` and `218-gaeste.sql`.
- [ ] **Step 8: Run the check script**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: sections 1-5 all `ok`.

- [ ] **Step 9: Commit**

```bash
git commit -m "Find an existing guest before booking a second row for the same person (#452)"
```

---

### Task 7: Help, demo and the whole picture

**Files:**
- Modify: `app/views/intern/hilfe.php` (the `gaeste` and `setlists` sections), `app/demo.php`, `app/strings/de.php`, `seed/translations/218-gaeste.sql`, `bin/gast-pruefen.php`

- [ ] **Step 1: Help** — the `gaeste` section gains the ticks, the song list, what happens when a name is already known, and "Antworten löschen"; the setlist section gains the filter and the mark. The help must match the real state, so it is written against the finished feature, not against this plan.
- [ ] **Step 2: Demo data** — the demo guitarist gets a handful of answered songs through the demo's own row tracking, so the filter and the marks have something to show.
- [ ] **Step 3: Run the text check**

Run: `php bin/texte-pruefen.php`
Expected: no German string without its five translations.

- [ ] **Step 4: Run the help check**

Run: `php bin/hilfe-pruefen.php`
Expected: `ok`.

- [ ] **Step 5: Run the schema and route checks**

Run: `php bin/schema-pruefen.php && php bin/routen-pruefen.php && php bin/sicht-pruefen.php`
Expected: all `ok` — `sicht-pruefen` covers the rights matrix the new routes must satisfy.

- [ ] **Step 6: Run the full guest check**

Run: `sudo -u www-data php bin/gast-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten`
Expected: every section `ok`, no `ZZGA` row left behind.

- [ ] **Step 7: Commit**

```bash
git commit -m "Explain the guest ticks, the song list and the setlist filter (#449, #450, #451, #452)"
```

---

### After the plan

Not part of a task, because it is deployment and not code:

- The live privacy texts are per-instance settings, not code. After the merge the §14 passage is updated on each instance and in each language it has, matching the new template.
- Deploy order: branch → staging (`git pull`) → test → `main` with `VERSION` 2.62.0, tag and GitHub release → demo → test → first band instance → second band instance. The second check after deploying may still see the previous version — repeat it once.
