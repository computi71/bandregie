# Date choice: one request, several possible dates

Status: approved approach, design awaiting approval
Date: 2026-11-25
Version at time of writing: 2.59.0
Issue: #443

## What is wrong

Requests often come with more than one date ("Pfeffermintz, 10.4. or 17.4.").
The band's own events do too (year-end party: which Saturday?). Today the band
settles this in WhatsApp with ticks per name before anything goes into the app,
because the app knows one date per event. The answers, the absences check and
the reminders the app already has are not used for the one question that
precedes the event.

## Goal

An event can be entered with several possible dates. Everyone answers per date
with the existing yes/maybe/no. The app shows per date who can, highlights a
date where everyone can, and the person allowed to edit events picks one with a
button. The other dates disappear.

## Non-goals

- No automatic decision. For a gig the promoter decides; the band reports which
  dates work. For the band's own events the band decides. Both end with the same
  button.
- No generic polls (non-date questions). Not asked for.
- No public visibility of a pending choice.

## Anti-goals

- A selection that sends one push per date on creation, or one "all answered"
  push per date.
- Losing what was attached to a losing date (comments, contract, quote) when
  another one is chosen.
- Two ways of answering. A date in a selection is answered exactly like any
  event.

## Approaches considered

1. **Each date is a normal event, linked as one selection (chosen).** Answers,
   open-answer list, reminders, completion push, absences check, calendar and
   iCal work per date unchanged. Cost: shared fields must be kept in step
   across the siblings, and the list must group them.
2. One event with a table of date options. One entry, one edit. But answering,
   reminders, absences, calendar and iCal all need a second path for options.
   Much more code and two ways to answer.
3. A poll under "Themen" that becomes an event later. Reusable for other
   questions, but answers are not tied to the event, no absences check, and
   everyone answers twice.

Chosen: 1, because it builds on what the band already uses daily and adds one
new button.

## Design

### Data

`events.choice_of INT NULL` plus an index. All dates of one selection carry the
id of the first one (the first one carries its own id). NULL = an ordinary
event. Not `alternative_for` (#403): that means "replacement after a
cancellation" and drives a reminder in `app/push.php`; reusing it would make
that reminder fire for selections.

Migration: add the column only; no data change.

### Creating

The event form gets "Weitere mögliche Daten": a list of date inputs with
"+ Datum". On create, the posted event is inserted as usual, then one copy per
extra date with the same values (gear and songs copied too), all with
`choice_of` = the first id. Dates equal to the main date or to each other are
ignored. A selection forces status `angefragt` on all its dates, whatever the
form said: a date that is "Findet statt" is not up for choice.

One push for the whole selection on create (topic `events`): "Neue Anfrage zur
Wahl: <title> · 10.4. / 17.4." instead of one per date.

The same field on the edit form adds dates to an existing event, turning an
ordinary event into a selection (or growing one). New dates get the same
values as the edited one. *(Builder-check call)* The field is offered only
while the event is `angefragt` or `reserviert`: a confirmed, public or
contracted event is not up for choice any more, and turning it back would
silently take a gig off the public page. Adding dates sets the selection to
`angefragt` and sends the same "zur Wahl" push, naming only the new dates.

### Status inside a selection *(builder-check call)*

The status field of a date in a selection is not editable; it reads "Zur
Wahl". The two things one would do with it have their own way:

- **A date is out** (the promoter ruled out 10.4.): delete that date. If one
  date remains, it becomes an ordinary event.
- **A date is confirmed:** that is choosing it ("Dieses Datum nehmen"), after
  which the status can be set to "Findet statt" as usual.

So every date in a selection is always `angefragt`, and grouping, completion
push and choosing need no per-status rules.

### Past dates *(builder-check call)*

A date whose day has passed drops out of its selection: the next read that
touches the selection (list, choose, completion check) clears its `choice_of`
and leaves it as an ordinary past `angefragt` event, like any request nobody
followed up. If that leaves one date, it is unlinked too. Sync on edit never
touches past dates.

### Editing a date in a selection

Saving one date applies every field except `date` and `status` to all dates of
the selection. The update logic moves from the route into a function
`event_update()` that takes the event id and a flag to suppress its pushes.
Answer reset (#402) and time proposal (#413) run per date, but the pushes go
out once for the selection *(builder-check call)*: one "please answer again"
naming the dates, one time proposal. Three dates must not mean three pushes
per save.

Changing the date of one sibling changes only that date.

### Showing it

- **Event list and overview:** the dates of a selection are shown as one block
  at the position of its earliest date: head line "Zur Wahl" with the title,
  then one line per date with its answer counts (✔ yes, ? maybe, ✘ no,
  unanswered) and a "Dieses Datum nehmen" button for those with write access.
  A date where every answerer (`event_answerers`) said yes is highlighted. Each
  date still opens its own card to answer, comment and see details.
- **Open answers (`open_votes`)** stay per date, each marked "zur Wahl" so
  people see why the same title appears twice.
- **Calendar and iCal:** every date stays visible as "Angefragt" until the
  choice is made. Intended: nobody books something else on those days
  meanwhile.
- **Public page:** dates with `choice_of` set are never public, even if the
  flag is ticked.

### Completion push

For a selection, the "all answered" push (#382) is sent once, when every date
of the selection is complete, to the creator, with one line per date:
"10.4.: ✔6 · 17.4.: ✔4 ✘2 (Andi, Micha)". Per-date completion pushes are
suppressed for dates in a selection.

### Choosing

`POST /intern/termine/{id}/waehlen`, write permission for events, same
`may_see_event` and lock checks as update/delete.

1. Everything attached to the losing dates that must not be lost moves to the
   chosen date: comments, contracts, quotes, sales invoices, photos, OneDrive
   folders, guest bookings, files on the card (`files`, entity_type `event`),
   linked mails (`post_messages.event_id`, often the promoter's request) and
   finance entries (`finances.event_id`). Substitute requests move too, unless
   the same person already has one on the chosen date *(builder-check call:
   a stand-in asked for the losing date would otherwise lose access)*.
   Answers and gear/songs of the losing dates are dropped (they belong to that
   date).
2. The losing dates are deleted with the existing delete logic, extracted into
   one function `event_delete()` that the delete route also uses.
3. The chosen date loses `choice_of` and keeps its status (`angefragt`). For a
   gig, "Findet statt" is set once the promoter confirms, as today.
4. One push to the band (topic `events`): "Entschieden: <title> am 17.4.".

If deleting dates by hand leaves one date in a selection, its `choice_of` is
cleared: a choice of one is an ordinary event.

### "Suggest another date" prompts *(builder-check call)*

A "no" to one date of a selection is not a cancellation; there are other dates
on the table. The #401 prompt after answering and the reminder in
`push_reminders_run` skip dates in a selection, unless the person has said no
to every date of it. Then they behave as for an ordinary event, attached to
the earliest date.

### Interaction with #442

Dates in a selection are `angefragt`, so the dropout reset never applies. The
dropout badge in the head works per date as for any event.

### Help and translations

A new help paragraph under "Termine" (next free `help_termine_N`), all new
strings in de and in the seed for en/nl/fr/es/it.

### Checks

A `bin/terminwahl-pruefen.php` in the style of the existing check scripts:
creating with extra dates (count, link, forced status, one push), duplicate
dates ignored, editing one date syncs the others but not the date, the answer
reset runs per date, grouping in the view data, highlight when everyone said
yes, the completion push once per selection, choosing moves the attachments,
deletes the others and clears the link, a lone remaining date is unlinked,
past dates drop out, the status of a date in a selection cannot be changed,
adding dates is refused for a confirmed event, one push per save instead of
one per date, no "suggest another date" prompt until every date is a "no",
nothing public, and the route rejects users without write access.

## Left to the builder

- Exact markup and CSS of the block, following `_event_card.php` and
  `_event_kopf.php`.
- Whether gear and songs are synced on edit like the other fields (default:
  yes, same as all other fields).
- Push wording in the other languages.
