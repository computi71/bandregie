# Date choice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An event can be entered with several possible dates; the band answers per date, and whoever may edit events picks one.

**Architecture:** Each possible date is an ordinary `events` row in status `angefragt`; `events.choice_of` links the rows of one selection. A new library `app/terminwahl.php` holds everything selection-specific; routes and views call it. Existing per-event machinery (answers, reminders, absences, calendar) keeps working per date.

**Tech Stack:** PHP 8 / MariaDB, server-rendered views, check scripts in `bin/` (no test framework).

**Spec:** `docs/superpowers/specs/2026-11-25-date-choice-design.md` (issue #443)

## Global Constraints

- UI texts German in `app/strings/de.php`; en/nl/fr/es/it in `seed/translations/217-terminwahl.sql`, `INSERT … ON DUPLICATE KEY UPDATE value = value`.
- Commits, issue comments, release notes in English; no co-author line; each commit message says why.
- Code comments in German, explaining why, in the style of the neighbouring code; reference `(#443)`.
- Migrations idempotent (`column_exists`, `index_exists` style guards), never read live constants.
- Any change to a file under `httpdocs/assets/` bumps `VERSION` in `httpdocs/sw.js` (currently `bandregie-v85`).
- All SQL with placeholders; every new route checks `may_see_event`, `event_locked` and `perm_allows($me, 'termine', 'write')`.
- Check script `bin/terminwahl-pruefen.php <dir> --schreibt-testdaten`, prefix `ZZTW ` for test rows, pre-cleanup of leftovers, cleanup at the end; runs on staging as www-data only.
- Status labels: "Angefragt" (`angefragt`), "Zur Wahl" is a display label, not a status value.

## Review Focus

1. The first date of a selection (whose id is the `choice_of` value) is deleted by hand → the other dates must still form a selection. Test in Task 4.
2. A stand-in asked only for one date → `choice_map` counts that date with `event_answerers` of that date, and choosing moves the request. Test in Task 4.
3. "Dieses Datum nehmen" sent twice (double tap, two people) → second call finds `choice_of` NULL, changes nothing, flashes `fl_choice_not_open`. Test in Task 4.
4. Extra date equal to the main date, repeated, in the past or malformed (`2027-02-30`) → ignored, no row created. Test in Task 1.
5. One date of the selection has passed when someone chooses → the past date is pruned first and never deleted. Test in Task 4.

---

### Task 1: Data and selection core

**Files:**
- Create: `app/terminwahl.php` (required from `app/bootstrap.php` next to the other `require_once` lines ~38-57)
- Modify: `app/schema.php` (after the `migr_dropout_reset` block)
- Create: `bin/terminwahl-pruefen.php` (structure as `bin/absage-pruefen.php`: `$pruefe`, `$konto`, `$termin`, sections, cleanup)

**Interfaces:**
- Produces:
  - `choice_add_dates(int $sourceId, array $dates, int $by): array` — new event ids
  - `choice_prune(int $groupId): void`
  - `choice_dates(int $eventId): array` — rows `id, date, title, status` of all dates of its selection after pruning, ordered by date; `[]` if not in one
  - `choice_map(array $eventIds): array` — `groupId => ['dates' => [ ['id'=>int,'date'=>string,'yes'=>int,'maybe'=>int,'no'=>int,'open'=>int,'all_yes'=>bool], … ]]`, keyed by the `choice_of` value, only for ids in a selection
  - `choice_group(int $eventId): int` — its `choice_of`, 0 if none

- [ ] **Step 1: Write the failing checks** (section "1. Anlegen")
  - `choice_add_dates($e, ['2027-04-17'], 1)` on an event dated 2027-04-10 → returns 1 id; both rows have `choice_of = $e`; both `status = 'angefragt'` even if `$e` was `bestaetigt`; title, type, venue_id, time, notes equal; gear and songs copied.
  - dates `['2027-04-10', '2027-04-17', '2027-04-17', '2020-01-01', '2027-02-30', 'x']` → exactly one new row (17.4.).
  - `choice_dates($neu)` lists both, ordered 10.4., 17.4.
  - `choice_map([$e, $neu])` has one group with two dates; after a member answers yes on 17.4. and every answerer yes there, that date has `all_yes = true`, 10.4. has not.
  - past date: set one row's date to yesterday, `choice_prune($e)` → its `choice_of` is NULL; with only one left, that one is NULL too.
- [ ] **Step 2: Run** `sudo -u www-data php bin/terminwahl-pruefen.php /var/www/bandroadie-oss --schreibt-testdaten` on staging (after `git pull` of the branch). Expected: fails, `choice_add_dates` undefined.
- [ ] **Step 3: Implement**
  - Schema: `ALTER TABLE events ADD COLUMN choice_of INT NULL, ADD INDEX idx_choice_of (choice_of)` under `if (!column_exists('events', 'choice_of'))`.
  - `choice_add_dates`: validate with `DateTime::createFromFormat('!Y-m-d')` round-trip, skip dates `< today`, equal to the source's or any sibling's date, or repeated. Copy the row with `INSERT … SELECT` of all columns except `id, date, created_at, answers_complete, time_proposed, time_meet_proposed, time_end_proposed, time_proposed_by, time_proposed_at, choice_of, alternative_for`; copy `event_equipment` and `event_songs` rows; `item_new('event', $id, $by)` per new row; then `UPDATE events SET choice_of = <group>, status = 'angefragt'` for the source and new rows, where `<group>` is the source's existing `choice_of` or its own id.
  - `choice_prune`: clear `choice_of` where `date < CURDATE()`; if ≤ 1 row remains with that group, clear it.
  - `choice_map`: per date use `event_answerers()` for the expected set and `attendance` for answers; `all_yes` = every answerer has `yes` and there is at least one answerer.
- [ ] **Step 4: Run the check script.** Expected: section 1 all `ok`.
- [ ] **Step 5: Commit** `git commit -m "Let an event carry several possible dates as one selection (#443)"`

### Task 2: Creating, adding dates and the locked status

**Files:**
- Modify: `httpdocs/index.php` create route (~1094-1145), update route (~1180-1260)
- Modify: `app/views/intern/termine.php` (new-event form, after the date field), `app/views/intern/_event_card.php` (edit form, date and status fields ~275-279)
- Modify: `httpdocs/assets/eventfields.js` ("+ Datum" clones the last `extra_dates[]` input), `httpdocs/sw.js` (v86)
- Modify: `app/strings/de.php`, `seed/translations/217-terminwahl.sql`

**Interfaces:**
- Consumes: `choice_add_dates`, `choice_group`, `choice_dates`
- Produces: form field `extra_dates[]`; strings `ev_extra_dates` ("Weitere mögliche Daten"), `ev_extra_dates_add` ("+ Datum"), `ev_choice` ("Zur Wahl"), `push_ev_choice_new` ("Zur Wahl: %1"), `push_ev_choice_new_body` ("%1 – wann könnt ihr?", %1 = dates joined with " / "), `fl_choice_dates_closed` ("Weitere Daten gehen nur bei angefragten oder reservierten Terminen.")

- [ ] **Step 1: Write the failing checks** (section "2. Formular und Route")
  - Route wiring by regex like `absage-pruefen.php` does: create route calls `choice_add_dates(` with `$_POST['extra_dates']`; update route calls it only after the status guard.
  - Status guard as a function so it can be checked without HTTP: `choice_may_add_dates(array $ev): bool` → true for `angefragt`/`reserviert` with future date, false for `bestaetigt`, `abgesagt`, past.
  - Update of a date in a selection with `status = 'bestaetigt'` posted keeps `angefragt`: check `choice_forced_status(int $eventId, string $posted): string` returns `'angefragt'` when `choice_group > 0`, else `$posted`.
  - Rendering: `_event_card.php` for a selection date contains `ev-choice` and no `<select name="status"`.
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement**
  - Create route: after the insert, if `extra_dates` yields new ids → one `push_notify('events', $me['id'], …)` with `push_ev_choice_new` and the dates (in recipient language via `fmt_date_lang`), URL `answer_url('ev', $newEventId)`, **instead of** the ordinary new-event push.
  - Update route: `$_POST['status'] = choice_forced_status((int) $id, (string) ($_POST['status'] ?? ''))` before `event_values()`; extra dates only if `choice_may_add_dates`, else `flash(t('fl_choice_dates_closed'))`; new dates → same push naming only the new dates.
  - Views: the extra-dates input in both forms; on the edit form only when `choice_may_add_dates($ev)`. For a selection date the status select is replaced by a read-only `<span class="badge ev-choice">Zur Wahl</span>` plus hidden `status=angefragt`.
- [ ] **Step 4: Run.** Expected: sections 1-2 `ok`.
- [ ] **Step 5: Commit** `"Offer extra dates when entering or editing a request (#443)"`

### Task 3: Editing one date edits the selection

**Files:**
- Modify: `app/terminwahl.php`, `app/bootstrap.php` (`event_answers_reset` ~4688, `event_time_propose` ~4875), `httpdocs/index.php` update route

**Interfaces:**
- Consumes: `choice_dates`
- Produces: `choice_sync(int $sourceId, int $by): void`; `event_answers_reset(int $eventId, int $durch, bool $mitteilen = true): int`; `event_time_propose(int $eventId, array $zeiten, int $durch, bool $mitteilen = true): int`

- [ ] **Step 1: Write the failing checks** (section "3. Bearbeiten")
  - Change title and venue on 10.4., `choice_sync` → 17.4. has the new title and venue, its date unchanged.
  - 17.4. had answers; venue changed → its answers are gone (`attendance` empty) and `push_queue`/push log has no entry for 17.4. (only the edited date pushes).
  - A past sibling is untouched.
  - Gear/songs of 17.4. equal those of 10.4. after sync.
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement**
  - `$mitteilen = false` skips only the push block in both functions; existing callers unchanged.
  - `choice_sync`: for each other future date: take `event_answer_fingerprint` before; `UPDATE` the sibling from the source row (same column list as `choice_add_dates`, minus `status` too); replace gear/songs; if the source has an open time proposal → `event_time_propose($sib, <source proposed times>, $by, false)`; elseif fingerprint changed → `event_time_clear($sib)` + `event_answers_reset($sib, $by, false)`. Wrap each sibling in `item_update('event', $sib, …, $by)`.
  - Update route: call `choice_sync((int) $id, (int) $me['id'])` after `save_event_songs`, before the reset/proposal block for the edited date.
- [ ] **Step 4: Run.** Expected: sections 1-3 `ok`.
- [ ] **Step 5: Commit** `"Keep the dates of a selection in step when one is edited (#443)"`

### Task 4: Choosing a date

**Files:**
- Modify: `app/bootstrap.php` (new `event_delete`), `httpdocs/index.php` (delete route uses it; route regex gains `waehlen`), `app/terminwahl.php`
- Modify: strings and seed

**Interfaces:**
- Consumes: `choice_dates`, `choice_prune`
- Produces: `event_delete(int $id): void` (body moved unchanged from the delete route ~1258-1280); `choice_choose(int $chosenId, int $by): bool` (false if not in a selection); route `POST /intern/termine/{id}/waehlen`; strings `ev_choice_take` ("Dieses Datum nehmen"), `ev_choice_take_confirm` ("Die anderen Daten werden gelöscht. Weiter?"), `push_ev_choice_done` ("Entschieden: %1"), `push_ev_choice_done_body` ("am %1"), `fl_choice_taken`, `fl_choice_not_open`

- [ ] **Step 1: Write the failing checks** (section "4. Wählen")
  - Selection 10.4./17.4., a comment, contract, quote, sales invoice, photo, file (`files` entity `event`), post message, finance entry, guest booking and a substitute request on 10.4.; `choice_choose($17)` → all of them point at 17.4.; 10.4. row, its attendance and gear are gone; 17.4. has `choice_of` NULL and `status = 'angefragt'`; one push `push_ev_choice_done`.
  - Substitute request for the same person on both dates → exactly one remains, on 17.4.
  - Second `choice_choose($17)` → false, nothing changed (Review Focus 3).
  - Delete the first date (`event_delete($groupId)` + `choice_prune($groupId)`) of a three-date selection → the other two still share `choice_of` (Review Focus 1).
  - A past sibling in the group → `choice_choose` leaves it existing (Review Focus 5).
  - Stand-in invited only to 17.4.: `choice_map` counts him on 17.4., not on 10.4. (Review Focus 2).
  - Route: regex check that `waehlen` is in the action list, guarded by `perm_allows($me, 'termine', 'write')`.
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement**
  - `choice_choose`: `choice_prune` first; then for each other date: `UPDATE … SET event_id = ?` on `comments, contracts, quotes, sales_invoices, photos, od_folders, guest_bookings, post_messages, finances`, `files` (`entity_id` where `entity_type = 'event'`), substitute requests via `UPDATE IGNORE` then delete leftovers; then `event_delete`; finally clear `choice_of` on the chosen one and push to `events` topic.
  - Delete route: `$grp = choice_group($id); event_delete($id); if ($grp) choice_prune($grp);`
- [ ] **Step 4: Run.** Expected: sections 1-4 `ok`; `bin/routen-pruefen.php` 0 errors.
- [ ] **Step 5: Commit** `"Pick one date of a selection and fold the others into it (#443)"`

### Task 5: Showing the selection

**Files:**
- Create: `app/views/intern/_event_choice.php` (block head: "Zur Wahl", title, one line per date with ✔/?/✘/open counts, highlight `all_yes`, the choose form with `data-confirm`)
- Modify: `app/bootstrap.php` `event_view_data` (add `'choiceByEvent' => $ohne(choice_map($ids))` and a per-event group lookup), `app/views/intern/termine.php` and `dashboard.php` (order siblings behind their earliest date, render `_event_choice.php` once before the first), `dashboard.php` open votes (mark "zur Wahl", `data-own-date="0"` for selection dates), `httpdocs/assets/style.css`, `httpdocs/sw.js`

**Interfaces:**
- Consumes: `choice_map`
- Produces: `choice_order(array $events): array` in `app/terminwahl.php` — same rows, siblings moved directly after their earliest date

- [ ] **Step 1: Write the failing checks** (section "5. Ansicht")
  - `choice_order` with [A 10.4. grp, B 12.4., C 17.4. grp] → [A, C, B].
  - Rendering `_event_choice.php` with the view data contains both dates, `ev-choice-all` on the all-yes date, and the `/waehlen` form only for a user with write permission.
  - Public page query: a selection date with `is_public = 1` is not listed (status is `angefragt`; assert via the same query as `index.php:272`).
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement** as above; cards stay as they are.
- [ ] **Step 4: Run.** Expected: sections 1-5 `ok`. Browser check on staging is not possible (memory: staging not in browser) → after the demo deploy, check `/intern/termine` and `/intern` on the demo in the browser.
- [ ] **Step 5: Commit** `"Show the dates of a selection as one block (#443)"`

### Task 6: Pushes and prompts for a selection

**Files:**
- Modify: `app/bootstrap.php` `event_completion_notify` (~5090), `app/push.php` `push_reminders_run` (~587), `httpdocs/index.php` zusage route (`absage_tipp`, ~1345)

**Interfaces:**
- Consumes: `choice_group`, `choice_dates`
- Produces: `choice_all_no(int $eventId, int $userId): bool`

- [ ] **Step 1: Write the failing checks** (section "6. Mitteilungen")
  - Two dates, everyone answers 10.4. → no completion push; everyone answers 17.4. too → exactly one push to the creator, body with one line per date ("10.4.: ✔6 · 17.4.: ✔4 ✘2 (Ben, Lisa)").
  - A "no" on 10.4. only → `push_reminders_run()` queues nothing for it; "no" on both → one reminder, for the earliest date.
  - `choice_all_no` false after one "no", true after both.
- [ ] **Step 2: Run.** Expected: fails.
- [ ] **Step 3: Implement**
  - `event_completion_notify`: if `choice_group > 0`, complete only when every date of the selection is complete; set `answers_complete` on all dates; build the per-date body; else unchanged.
  - `push_reminders_run`: add `AND (e.choice_of IS NULL OR NOT EXISTS (SELECT 1 FROM events s WHERE s.choice_of = e.choice_of AND s.id <> e.id AND s.date >= CURDATE() AND NOT EXISTS (SELECT 1 FROM attendance x WHERE x.event_id = s.id AND x.user_id = a.user_id AND x.status = 'no')))` and only the earliest date of a selection.
  - Zusage route: set `absage_tipp` only if `!choice_group($id) || choice_all_no($id, $me['id'])`.
- [ ] **Step 4: Run.** Expected: all sections `ok`; `bin/vollzaehlig-pruefen.php`, `bin/warteschlange-pruefen.php`, `bin/absage-pruefen.php` still green.
- [ ] **Step 5: Commit** `"One completion push and no early prompts for a selection (#443)"`

### Task 7: Help, release, rollout

**Files:**
- Modify: `app/help.php` (`HELP_PARAGRAPHS` gets `'_16'`), `app/strings/de.php` (`help_termine_16`), `seed/translations/217-terminwahl.sql`, `VERSION` (2.60.0)

- [ ] **Step 1:** Help paragraph `help_termine_16` in de and the seed: entering extra dates, answering per date, the block and highlight, "Dieses Datum nehmen" and what moves, status "Zur Wahl", dates that pass drop out.
- [ ] **Step 2: Run** `bin/hilfe-pruefen.php`, `bin/texte-pruefen.php`, `bin/schema-pruefen.php`, `bin/routen-pruefen.php`, all other `bin/*-pruefen.php` on staging. Expected: all green.
- [ ] **Step 3:** Code review of the branch (memory rule: every 10 commits; this branch is a natural point).
- [ ] **Step 4:** Merge to main, bump `VERSION` to 2.60.0, tag `v2.60.0`, GitHub release; deploy demo → browser check → first band instance → second band instance (`plesk ext git --fetch` and `--deploy`); verify schema version and `seed_fehler []` per instance; close #443.
