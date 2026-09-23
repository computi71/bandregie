# Tasks: several assignees, a quorum, and links to everything else

Status: approved design, not yet implemented
Date: 2026-09-23
Version at time of writing: 2.13.0
Issues: #334 (assignees + quorum), #335 (links from a task), #336 (the other direction)

## What is wrong

A task carries one `assigned_to`, and its `status` belongs to the task rather
than to a person. Two consequences, both of which show up in daily use.

"Put up the posters" is something three people do. It goes to one name, and the
other two live in the notes field or nowhere. And whoever ticks the box ticks it
for everybody, so there is no way to see who has actually done their part.

The second gap is context. "Send the rider to the promoter" belongs to an event;
"learn the new bridge" belongs to a song. Today that connection lives in the
wording of the title, which means it cannot be followed, counted, or shown from
the other side.

## Goal

A task can be assigned to several members, with a settable number of completions
before it counts as done; and a task can point at other entries, using the kind
registry that already exists.

## Part 1 — Assignees and the quorum (#334)

### Data

```sql
task_assignees (
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  done_at DATETIME(3) NULL,
  PRIMARY KEY (task_id, user_id)
)
```

plus one column:

```sql
tasks.required_done TINYINT UNSIGNED NOT NULL DEFAULT 0
```

**`required_done = 0` means "all of them".** This is not a convenience; it is
the only version that holds. With a literal `3` stored, removing one assignee
leaves a task that can never be completed, and nobody would connect the two
facts. Zero adjusts itself. The form offers `all`, then 1, 2, 3 up to the number
of assignees.

**`tasks.status` stays.** It becomes derived rather than toggled — recomputed
after every tick — but the column remains, because four things read it: the task
list's sort order, the dashboard's filter on open tasks, `open_items_count()`,
and the change mark, where `status` is one of the compared fields in
`ITEM_KINDS`. A derived column written once per tick is more honest here than
four places recomputing the same answer and drifting apart.

### The migration

Each existing `assigned_to` becomes one row in `task_assignees`. The column
itself stays and is no longer read — the same decision as `photos_seen_at` in
#331, for the same reason: dropping a column is the one migration that cannot be
taken back, and an unread column costs nothing.

`required_done` defaults to 0, so every existing task keeps behaving exactly as
it does now: one assignee, one tick, done.

### What ticking does

`done_at` is stamped on the assignee's own row. The route then counts the
stamped rows, compares against `required_done` (0 meaning the number of
assignees) and writes `tasks.status`.

**Once the number is reached the task is done for everybody**, including
assignees who did not tick. It leaves their badge and their overview; the list
shows who did it. The number says how many it would have taken, not how many
still owe something. Decided by the user on 2026-09-23, against the alternative
of a task staying personally open — which would have left the badge counting
things nobody needs to do any more.

**A task with no assignees stays what it is today:** anybody may tick it, and
one tick finishes it. The present toggle is open to everyone who may see the
area, and nothing about that is worth changing.

It needs no second code path either, which only became clear while planning.
Ticking an unassigned task **makes the ticker an assignee who has done it**:
one assignee, `required_done = 0` meaning all of them, one tick, done. The
behaviour is what it is today and the list gains something it never had — who
actually did it. Un-ticking removes the row again, leaving no assignees and an
open task.

### Tasks need an edit route, which they do not have

There is no `/intern/aufgaben/{id}/update` today — only create, toggle and
delete. Assignees and the required number have to be changeable, or the feature
dies at the first typo. The edit route is part of Part 1, not an extra.

### The two places that have to move with it

`open_items_count()` currently asks for tasks where `assigned_to` is the member
and the status is open. It becomes a join against `task_assignees`. Because the
quorum closes the task for everyone, it stays a single query — there is no
per-person state left to fold in.

`user_purge()` removes the member's `task_assignees` rows **and recomputes the
affected tasks**. Without the second half, a task sits at "2 of 3" forever once
the third account is gone, and nobody can finish it. That is the failure nobody
would notice for months, so it is named here rather than left to be discovered.

### Recomputation may close a task, never reopen one

Removing an assignee also removes their tick, which can drop the count below
the required number. A task that was already done would then reopen — because
an account was deleted, or because somebody edited the assignee list. That is
the wrong answer: the band finished the thing, and the record of who was on the
list does not change that.

So the rule is asymmetric, and it is a rule rather than a side effect:

- **Removing an assignee, and `user_purge()`, may only ever close a task.** If
  the recomputation says "done", it is written. If it says "open" on a task
  that is already done, nothing happens.
- **An explicit un-tick may reopen it.** Somebody saying "I ticked that by
  mistake" is new information, and is the one case where going back to open is
  right.

## Part 2 — Links (#335)

```sql
task_links (
  task_id INT NOT NULL,
  kind VARCHAR(20) NOT NULL,
  item_id INT NOT NULL,
  PRIMARY KEY (task_id, kind, item_id)
)
```

`kind` is validated against **`ITEM_KINDS`**. After #331 that is nineteen kinds,
so "and whatever else makes sense" is not a list to be guessed at now — it is a
table, and an area added later becomes linkable without this feature being
touched.

Chat topics are the one addition. They sit deliberately outside `ITEM_KINDS`
(they track unread per post, not per item), so `topic` is allowed as one extra
kind, with `may_see_topic()` as its check.

### One visibility function, two callers

The per-kind visibility check sits today as a set of closures in the middle of
the `/intern/gesehen` route in `httpdocs/index.php`. Links need the same check,
for the same reason the marks did: a task must not reveal that an event exists
which the reader may not see.

So it moves into `item_visible($user, $kind, $id)` in `app/marks.php`, and both
callers use it. Two copies of an access rule is one too many — the second copy
is always the one that stops being updated.

This is the only refactoring in this design. It is here because the work
requires it, not because the code could be tidier.

### Dangling links are skipped when read

A linked entry can be deleted. Cleaning up on delete would mean nineteen delete
sites, each of which somebody will eventually forget — and a forgotten one
leaves a link pointing at a reused id, which is worse than a dangling row,
because it points at the wrong thing rather than at nothing.

So links are resolved when read, and one that no longer resolves is not
rendered. A dead row is harmless.

## Part 3 — The other direction (#336)

Deliberately not everywhere at once: on the **event** and on the **song**, the
two places a band looks before a gig or a rehearsal. Each shows its open tasks,
with who is on them and how many ticks are still needed, linking back to the
task.

The remaining seventeen areas can follow if anybody misses them. Doing all
nineteen first would mean nothing is usable until the end — the mistake #331
avoided by shipping one area at a time.

## Verification

`bin/aufgaben-pruefen.php`, new, after the pattern of `bin/marken-pruefen.php`.
The project has no test framework, and this is the cheapest thing that fails
loudly:

- A task with one assignee behaves exactly as before: one tick, done.
- Quorum not reached: two of three ticked with `required_done = 3` → still open.
- Quorum reached: `required_done = 2` → done, and gone from the third
  assignee's `open_items_count()`.
- `required_done = 0` with three assignees needs all three.
- An assignee who has already ticked is removed from an **unfinished** task →
  recomputed, not left stuck.
- The same removal on a **finished** task → stays finished. This is the one
  that will be got wrong by an implementation that simply recomputes.
- An explicit un-tick on a finished task → reopens.
- `user_purge()` on an assignee of an unfinished task → recomputed.
- A link to a deleted entry: not rendered, no error.
- A link to an entry the reader may not see: not rendered.
- An unknown `kind`: refused, not silently stored.

Plus `bin/routen-pruefen.php` after each step, because each step touches the
task list template. It runs as the web user — see its own header for why.

## Risks

| risk | handling |
|---|---|
| `status` and the ticks drift apart | One function recomputes and writes it; no route writes `status` directly |
| A task becomes impossible to complete | `required_done = 0` adjusts to the assignee count, and a stored number is clamped to that count on save |
| `user_purge()` leaves a task stuck at "2 of 3" | Named above, and in the check script |
| A finished task reopens because an account was deleted | Recomputation may only close, never reopen; only an explicit un-tick reopens |
| A link leaks the existence of a hidden entry | `item_visible()`, the same check the marks use — one copy |
| The migration changes behaviour for existing tasks | `required_done` defaults to 0, so one assignee means one tick, as today |

## Order of work

1. **Part 1** — own branch, own release. This is the core.
2. **Part 2** — own branch, including the `item_visible()` merge.
3. **Part 3** — worth asking whether it is wanted before building it. The value
   is clear on events and less obvious elsewhere.

Parts 1 and 2 are new behaviour, so the second version digit each time, bumped
at the merge to `main` and not on the branch.
