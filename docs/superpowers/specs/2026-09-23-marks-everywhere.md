# The marks, in every area

Status: written from the approved design, not yet implemented
Date: 2026-09-23
Version at time of writing: 2.12.0
Issue: #331 — prerequisite for #332 (daily mail) and #333 (important flag)

## What is wrong

`ITEM_KINDS` holds six kinds: `event`, `song`, `setlist`, `quote`, `contract`,
`file`. The application has nineteen areas. A member therefore sees "new" and
"changed" on the events page and on the song list, and nothing at all on
tasks, venues, equipment, the mailbox, photos, the till.

There is no rule behind the split. Those six are simply the ones that existed
when #321 was built. A member cannot learn which areas mark themselves and
which do not, because the answer is a date, not a principle.

## Why it is worth doing now

The daily mail (#332) walks `ITEM_KINDS` and reports what it finds. Built on
six kinds and extended to eighteen afterwards, the same code is written twice
— and the second time against a mail that is already going out to two bands.
Extending the marks first means every area the application gains later shows
up in the mail without anybody touching the mail.

## Goal

Every area that holds content a member would want to notice carries the same
two marks, cleared the same way, through the same code path. No new mechanism.

## The mechanism that already exists

Nothing here is invented. The four moving parts are in place:

| part | where | what a new kind costs |
|---|---|---|
| the timestamp columns | `app/schema.php:713` — a `foreach` over table names | one name in the list |
| the description | `ITEM_KINDS`, `app/marks.php:26` | one row: table, `wann`, `wer`, `felder` |
| clearing a mark | `/intern/gesehen`, `httpdocs/index.php:2255` | one closure in the visibility map |
| setting a mark | `item_new()` / `item_update()` / `item_touch()` | one call per write site |

On the page, an entry becomes `<details data-seen="kind:id">`. Unfolding it is
what reports it seen — the pattern `_event_card.php` already uses and
`httpdocs/assets/seen.js` already drives.

**This matters for areas with no detail page.** Not one of the thirteen has a
route like `/intern/aufgaben/42`; they are all list pages. They need no detail
page either: a foldable row is the "opening", and the existing endpoint does
the rest.

## The thirteen areas

| area | table | what is missing |
|---|---|---|
| Orte | `venues` | `updated_at`, `updated_by` |
| Abwesenheiten | `absences` | `updated_at`, `updated_by` |
| Aufgaben | `tasks` | `updated_at`, `updated_by` |
| Kasse | `finances` | `updated_at`, `updated_by` |
| Equipment | `equipment` | `updated_at`, `updated_by` |
| Fotos | `photos` | `updated_at`, `updated_by` |
| Gäste | `guests` | `updated_at`, `updated_by` |
| Postfach | `post_messages` | `created_at` as well |
| Musik & Videos | `media_links` | `created_at` as well |
| Stagerider | `stage_items` | `created_at` as well |
| Kanäle | `channels` | `created_at` as well |
| Kommentare | `comments` | nothing — only the `ITEM_KINDS` row |
| Zu-/Absagen | `attendance` | nothing — only the `ITEM_KINDS` row |

Six existing plus thirteen new: **nineteen kinds**.

The `updated_at` column is `DATETIME(3)`, matching the five that already carry
it (`app/schema.php:1461`). Millisecond precision is not decoration here: a
mark set and read in the same second has to compare correctly.

## Two exclusions, both deliberate

**Mitglieder (`users`) gets no mark.** Every profile change — a new phone
number, a changed language — would become a notice, and none of them is news.
Decided by the user on 2026-09-23.

**Chat (`topic_posts`) keeps `topic_reads`.** That mechanism tracks unread per
*post*; a mark tracks per *item*. Folding the chat into the marks would make
it coarser, and the overview, the badge and the mail would all lose the
ability to say "three unread posts". Anything that wants both reads both — as
`open_items_count()` already does.

## Comments and attendance belong to their event

Both get their own kind, so a list can say "3 new comments on Sommerfest"
rather than only flagging the event as changed. But they are **cleared
together with their event**: unfolding the event card marks the event, its
comments and its attendance seen in one call.

Anything else would ask a member to separately acknowledge a comment they are
looking at. `items_mark_seen()` already takes a list of ids, so this is one
extra call in the event branch of `/intern/gesehen`, not a new idea.

## Three rules that make this safe

1. **One area per commit.** Thirteen areas in one diff is thirteen chances to
   miss a write site, in a change where a miss is invisible: a mark that never
   appears looks exactly like an area in which nothing happened.
2. **Every write site, or none.** Per area, the create, the update *and* the
   delete are handled together. A create without `item_new()` produces an
   entry nobody is told about; a delete without `item_forget()` leaves a mark
   pointing at a row that is gone.
3. **Migration only adds.** The columns are nullable additions; no existing
   row changes, and `marks_since()` keeps everything from before the cutoff
   out of the marks. Nobody's first login after the update drowns in
   eighteen areas' worth of "new".

## What can go wrong

| risk | handling |
|---|---|
| A write site is missed; the area never marks anything | `bin/marken-pruefen.php` is extended to walk **every** kind in `ITEM_KINDS` rather than only events — then a missing kind fails loudly instead of staying quiet |
| A delete leaves a mark behind | Same script: create, change, delete, and assert the mark is gone |
| The `/intern/gesehen` map lacks a kind, so the mark cannot be cleared | The route already rejects an unknown kind with 400 instead of a silent "ok" — the failure is visible the first time somebody unfolds the row |
| An area's list shows entries the member may not see | The visibility closure in the map is taken from the area's own list query, never written fresh |
| Eighteen kinds make the overview noisy | Out of scope here — the marks appear where the entries appear. Noise in the **mail** is handled in #332 by letting a member deselect areas |

## Verification

`bin/marken-pruefen.php` today drives one kind by hand. It becomes a loop over
`ITEM_KINDS`: for each kind, create a row, assert "new" for the other account,
change a field, assert "changed", clear it, assert it is gone, delete the row,
assert no mark remains. A kind that has no write path yet fails the run, which
is the point — the script is the list of what is done.

It writes test data, so it stays on staging, as its own header says.

Plus `bin/routen-pruefen.php` after each area: a `<details>` added to a list
page is a template change, and a broken template is a 500 on exactly one page.

## Order of work

Areas that need only an `ITEM_KINDS` row first (`comments`, `attendance`),
because they prove the loop in `marken-pruefen.php` before any migration
exists. Then the eight that need two columns, then the four that need three.

The version is bumped when the branch merges to `main`, not on the branch —
new behaviour, so the second digit: **2.13.0**.
