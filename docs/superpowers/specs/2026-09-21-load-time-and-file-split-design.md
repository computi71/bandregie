# Load time and the cut through bootstrap.php

Status: approved design, not yet implemented
Date: 2026-09-21
Version at time of writing: 2.11.1

## What is wrong

Loading `app/bootstrap.php` costs **271 database queries** before a single
line of page content exists. Measured on staging, with the database on the
same machine:

```
bootstrap.php ready after   41.0 ms
queries to get there            271
memory afterwards            4.0 MB
user functions defined          386
UI_STRINGS                     1900 entries = 166 KB = 37 % of the file
```

The 271 are made up of **54 `CREATE TABLE IF NOT EXISTS`**, **60
`column_exists()` calls**, and **47 `setting()` guards** in front of one-time
migrations — and they run on every request, months after the last migration
had anything to do. On top of that the seed comparison does one `glob()` and
163 `filesize()` calls per request.

`setting()` issues one query per call and the code has **400 call sites**, 49
of them in views, so a page render adds more on top of the 271.

Two files carry most of the code: `app/bootstrap.php` with 7891 lines and
`httpdocs/index.php` with 5078. Both are past the size where a person — or a
language model — can hold them in view while changing them.

## What this is not about

`opcache.enable = 1` on the production server. The compiled code lives in
shared memory, so the 455 KB are not read and parsed per request. **Splitting
files buys almost nothing for load time.** It buys orientation, and it buys
one real saving: a file that is only required when it is needed is not loaded
at all. That applies to exactly one file here, `schema.php`.

Lazy-loading the rest per route was considered and rejected: it pays for a
cost opcache already absorbs, with a route-to-file table that breaks at the
first cross-area call.

Rewriting `httpdocs/index.php` is out of scope. It is its own undertaking with
its own risk — every request passes through it.

## Goal

1. One query for the settings, one for the session, one for the signed-in
   member — instead of 271 plus page work.
2. `bootstrap.php` down to roughly 1200 lines of core, the rest in files named
   after what they hold.
3. Every step deployable on its own; the application stays self-installing.

## Part 1 — The gate

Everything about the schema moves to **`app/schema.php`**: the 54
`CREATE TABLE IF NOT EXISTS`, the 60 `column_exists()` migrations, the 47
`migr_*` guards, the first-run defaults, and the seed import. Around 1700
lines that sit in the middle of `bootstrap.php` today.

What stays in `bootstrap.php` is the gate, right after the connection is up
and the settings are loaded:

```php
// One query instead of 271: if the database carries the same release as the
// disk, there is nothing to do to the schema — and schema.php is not even
// required. "dev" always opens, otherwise a migration added during
// development would never run.
if (($settings['schema_version'] ?? '') !== BANDREGIE_VERSION || BANDREGIE_VERSION === 'dev') {
  require __DIR__ . '/schema.php';
  set_setting('schema_version', BANDREGIE_VERSION);
}
```

**The first run needs its own path.** On a fresh database the `settings` table
does not exist yet, so reading it fails before the gate can judge anything. A
failed read counts as "schema unknown" and `schema.php` runs. That keeps the
application self-installing: upload the files, open the page, done — which
matters for everyone who deploys by copying files rather than by git.

**Two requests at once** after an update both pass the gate and migrate in
parallel. That is harmless, because every single migration is already
repeatable on its own (`IF NOT EXISTS`, `column_exists`, guarded `migr_*`).
No lock is built: a lock would be a new source of failure for a problem that
resolves itself.

**Second valve.** The system check gets a button that clears the marker, so a
forced migration is possible without a command line — for instance after
somebody edited the database by hand.

The development trap is real and named here on purpose: `VERSION` does not
change with every commit. A migration added without bumping the version would
never run. The `dev` valve covers local work, and the project's own rule —
bump `VERSION` per feature — covers the rest.

Expected for this part alone: **271 → about 3 queries**, and the 163
`filesize()` calls disappear in normal operation.

## Part 2 — Settings in one query

```php
function setting(string $key, string $fallback = ''): string {
  return settings_all()[$key] ?? $fallback;
}

function settings_all(): array {
  static $alle = null;
  if ($alle === null) {
    // One query for everything. A missing table is not an error here but the
    // answer "schema unknown" — which is what the gate above needs.
    try {
      $alle = array_column(rows('SELECT `key`, value FROM settings'), 'value', 'key');
    } catch (PDOException $e) {
      $alle = [];
    }
  }
  return $alle;
}
```

`set_setting()` writes through: database first, then the cache. Nobody reads a
stale value inside the same request — a case that really occurs, for instance
when the gate writes the schema marker and the page still needs it.

`all_settings()` becomes a wrapper around the same source. Until now it was a
second query for the view layer; afterwards there is **one** place settings
come from.

**Two places bypass `set_setting()`**: two migrations delete rows directly
(`oauth_%` and the old `stacks_*`). Both run behind the gate, so the rule is
one line — **after `schema.php` has run, the cache is discarded once**. No
cleanup at 400 call sites, no exception to remember.

The cache lives for exactly one request. Extending it across requests (APCu)
is explicitly not done: that would require invalidation across process
boundaries, and a table with a few dozen rows is the wrong reason for that.

## Part 3 — The cut

How the 7891 lines are distributed today:

| Block | Lines |
|---|---:|
| Header, constants, `EVENT_STATUS`, languages | 105 |
| `UI_STRINGS` | 1651 |
| `PERM_MODULES`, `PERM_TEMPLATES`, `MODULE_ICONS` … | 361 |
| Schema, migrations, defaults, seeds | 1739 |
| Query helpers, auth, permissions, CSRF, throttle | 267 |
| Card and list data | 601 |
| Uploads and files | 451 |
| Contracts | 71 |
| Quote calculation | 1195 |
| "Guests" — in truth a catch-all: mail, events, guests, `open_items_count`, `user_purge` … | 1483 |

What it becomes:

| File | ≈ lines | loaded |
|---|---:|---|
| `app/strings/de.php` | 1650 | always |
| `app/schema.php` | 1700 | **only when the gate is open** |
| `app/quotes.php` | 1200 | always |
| `app/events.php` | 700 | always |
| `app/files.php` | 450 | always |
| `app/guests.php` | 400 | always |
| `app/contracts.php` | 250 | always |
| `app/mail.php` | 200 | always |
| `app/marks.php` | 200 | already extracted |
| `app/bootstrap.php` | ≈ 1200 | always |

The core keeps what every page needs and what belongs to no area: constants,
the connection, `q`/`row`/`rows`, `setting`, `t`/`e`, the session,
permissions, CSRF, the throttle — and the gate.

Every file gets the same header as `app/marks.php`: what it does, what it
needs from outside. All of them are required in **one** place at the top of
`bootstrap.php`; `schema.php` is the single exception, required by the gate.

### Three rules that make this safe

1. **Move only, change nothing.** No move commit carries a behaviour change.
   Anything noticed while moving is written down and fixed separately —
   otherwise a commit with 1700 moved lines hides one altered condition that
   nobody will ever find.
2. **One file per commit, every step deployable.** Order: `strings`, `schema`,
   then `quotes`, `guests`, `events`, `files`, `contracts`, `mail`. The two
   large ones first, because they are the purest case of moving.
3. **Prove mechanically that nothing is lost.** Before and after each step,
   compare the list of all defined functions and constants. Identical lists
   mean nothing vanished and nothing got defined twice — the exact failure
   that happened on 2026-09-21 when a patch script ran twice and produced two
   copies of `event_url()`. This belongs in a script, not in good intentions.

## Verification

Three measurements, each before and after:

1. **Queries per request.** `bin/ladeprofil.php` (new, alongside
   `bin/marken-pruefen.php`): requires bootstrap, reports elapsed time,
   `SHOW SESSION STATUS LIKE 'Questions'`, memory, and the number of defined
   functions. Target after parts 1 and 2: single digits.
2. **Nothing lost.** The same script dumps the sorted list of user functions
   and constants; the lists before and after a move must be identical.
3. **The application still works.** A route sweep on staging: sign in, walk
   every `/intern/*` page, confirm 200. This has been done by hand before and
   should become `bin/routen-pruefen.php` as part of this work — a move that
   breaks one page in one area is exactly what a sweep catches and a spot
   check does not. Plus `bin/marken-pruefen.php`, which covers the one feature
   whose failures are invisible.

A fresh install is tested explicitly on staging with an empty database — that
is the path the gate could break, and it is the one nobody notices until a
stranger tries to install the thing.

## Risks

| Risk | Handling |
|---|---|
| A migration added without a `VERSION` bump never runs | `dev` valve, the per-feature version rule, and the button in the system check |
| Somebody edits the database by hand; the marker still matches | Same button |
| Load order between the new files | They only define things; the function and constant comparison catches a genuine dependency immediately |
| A settings value read stale within a request | `set_setting()` writes through; the cache dies with the request |
| A move commit smuggles in a change | Rule 1, and a review of the diff with `--stat` plus a spot check that the moved block is byte-identical |

## Order of work

1. Part 2 (settings) — smallest, and part 1 depends on it.
2. Part 1 (gate + `schema.php`) — the measurable win. Release, then measure on
   staging and on both band instances.
3. Part 3, file by file, in the order given above.

Parts 1 and 2 are worth a release of their own; the cut can follow at its own
pace, because nobody notices it from outside.
