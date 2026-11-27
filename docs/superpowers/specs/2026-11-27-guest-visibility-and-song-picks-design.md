# Guests: choose what they see, and let them mark what they can play

Status: approved
Date: 2026-11-27
Version at time of writing: 2.61.2
Issues: #449, #450, #451, #452

## What is wrong

A guest is booked for one gig and gets a link. What that link shows is fixed:
date, venue with navigation, meeting time, his part — and after he says yes,
the rider, the setlist and the lyrics of its songs. The band cannot decide to
show less, and it cannot decide to show more.

"More" is the case the band has right now: a guitarist plays the next gig and
may well play again after that. Before anybody can build a setlist, somebody
has to find out which songs he can play. Today that happens in a messenger,
song by song, and the answer is gone by the next gig.

## Goal

1. When booking a guest, the band ticks what his link shows.
2. One of those ticks opens the song list to him: he marks per song whether he
   can play it. What he marks belongs to **him**, not to the booking, so it is
   still there when he is booked again.
3. When a member builds the setlist, they can narrow the song picker to what a
   booked guest can play.

## Non-goals

- No login for guests. The token of a booking stays their only key.
- No link that outlives the booking. He reaches the song list through whichever
  booking is currently valid; the list itself survives, the key does not.
- No ratings, skills levels or instrument matrix. Three answers per song, the
  same three the band already uses for song proposals.
- No menu work. "Gäste" is already in the menu under 🗂 Planung for everyone
  with the `gaeste` permission, and guests are created on that page. Nothing to
  build; written down here because it was asked for.

## Anti-goals

- A notification per answered song. Twenty songs would be twenty pushes.
- A second place where the band maintains "who can play what". Members already
  have song proposals; this is about people who are not members.
- A guest who suddenly sees the whole repertoire because the feature was built
  without the band deciding. The song list is off unless ticked.
- Existing bookings changing what they show when the update runs.

## Approaches considered

1. **On the guest page, list stored per guest (chosen).** His page gets a
   section "Was kannst du spielen?" with the band's songs and three buttons per
   song. Nothing new to send, no second link, and the next booking finds the
   answers already there. Cost: he sees the whole repertoire — which is exactly
   why it is one of the ticks.
2. **Make him a member with the role `ersatz`.** A real login, read access to
   events and songs. Fits someone who belongs to the band. Cost: an account with
   a password for an outsider, a row in the member list, and he sees more than
   the songs. Too much for "may play more often".
3. **A separate guest access without an expiry date.** A link only for the song
   list, independent of a gig. Cost: a second kind of link beside the guest
   link — two things that do the same job, and one more thing that never
   expires.

Chosen: 1.

## Design

### Data

`guest_bookings` gets three columns, each `TINYINT(1) NOT NULL`:

| Column | Default | What it opens |
|---|---|---|
| `show_rider` | 1 | the rider button |
| `show_setlist` | 1 | the setlist, its songs and their lyrics |
| `show_songs` | 0 | the new song list to tick |

The defaults are what a booking shows today, so the update changes nothing for
bookings that already exist. The defaults are written into the migration as
literal values, not read from a constant — a constant that changes later must
not rewrite history.

Always visible, never a tick: date, title, venue with navigation, meeting time,
his part. That is the invitation itself.

New table, built like `song_votes` on purpose — the guest presses the same three
buttons the band presses, so the same thing stands behind them:

```
guest_songs (
  guest_id INT NOT NULL,
  song_id  INT NOT NULL,
  status   VARCHAR(10) NOT NULL,     -- 'yes' | 'maybe' | 'no'
  updated_at DATETIME(3) NULL,
  PRIMARY KEY (guest_id, song_id),
  INDEX idx_song (song_id)
)
```

No row means not answered. An invented "no answer" row would be a statement
nobody made.

The three statuses read, for a guest: **kann ich** · **mit Vorbereitung** ·
**kenne ich nicht**.

### Booking

The booking form in `_event_gaeste.php` gets the three checkboxes, pre-ticked
rider and setlist, song list unticked.

The band may change its mind after the link has gone out — the link stays the
same, so this has to be changeable. Each booking row gets a small `<details>`
with the same three checkboxes and a save button, for anyone who may book
(`gaeste` write, not demo, booking not cancelled or declined).

### The guest's page

Each section is drawn only when its column says so: rider button behind
`show_rider`, setlist and song links behind `show_setlist`.

Behind `show_songs` a new section "Was kannst du spielen?" with a line of
progress ("12 von 48 beantwortet") and a link to `/gast/{token}/lieder`.

`GET /gast/{token}/lieder` lists the songs with `status IN ('aktiv',
'in_arbeit')` — the same set the setlist picker offers — with title, artist, key,
tempo and length, and three buttons per song. No lyrics and no chords: those
belong to the setlist and have their own tick.

`POST /gast/{token}/lied/{id}` writes one answer, with the CSRF token the other
guest form already uses, and returns to the list. The song id must be in the
allowed set; anything else goes back to the start of the link, the way the
existing song route does.

Both routes require `status = 'zugesagt'`, like every other part behind the
link. He answers the invitation first, then gets the material.

*(builder-check call)* Requiring the yes means the band cannot ask "can you play
these?" before he commits. Accepted: the booking is for one evening, and a guest
who has not said yes has nothing to prepare.

### Telling the guest there is something to answer

The tick defaults to off, so it is normally switched on **after** the
invitation went out — the band reacts to a guitarist who has already said yes.
Without a way to reach him again, the feature would die where the messenger
started: somebody types him a message by hand.

So the two ways that exist stay available while a booking is alive, not only
while it is `angefragt`: the resend button and the WhatsApp/SMS share links from
`guest_share_links()`. Both get a second text, used when the booking shows the
song list, that points at it instead of asking for an answer. No new mail
machinery — the same send path, one more wording.

### Which bookings count

For everything a member sees, a guest counts when his booking is neither
`abgesagt` nor `storniert` and belongs to an event **from today onwards**. An
expired `access_until` does not disqualify him: the question is who is booked,
not who can still open the link. Without the date limit a guest who played a
gig two years ago would stand in the filter of every setlist that gig used.

### What members see

**Setlist builder.** Above the "add song" picker, a filter: *alle Lieder* (the
default) and one entry per counting guest who has answered at least once.
Choosing one narrows the picker to that guest's **kann ich** and **mit
Vorbereitung**, the latter marked as such in the option text. The filter is a
GET parameter on the page, no script — the page must work without JavaScript
like the print button does (#429).

**Setlist entries.** The filter only helps while the setlist is being built.
The commoner case is the other way round: the setlist stands, the guest is
booked afterwards, and nobody sees that he does not know four of its songs. So
every entry carries a quiet mark when a counting guest of that setlist's events
answered **mit Vorbereitung** or **kenne ich nicht** for it, naming the guest.
Only in the editing view: the print stays a setlist, and the guest's own copy of
it says nothing about him.

**Guest page (`/intern/gaeste`).** Per guest a `<details>` with what he marked,
grouped by status, newest first. This is where the band looks when no setlist
exists yet.

**Event card.** The guest's row gains a count when he has answered at all:
`🎵 23 ✔ · 4 ? · 2 ✘`. All three, because the band wants to see that he
answered, not only that he agreed. No push, no mark per song.

### Counting

Both the guest's progress line and the event card count only songs that are
currently `aktiv` or `in_arbeit` — the set he is shown. An answer for a song
that has since been archived stays in the table but counts nowhere, so
"48 von 48" stays reachable. A song that is deleted takes its answers with it.

### Permissions

The booking visibility form sits under `gaeste` write, as the booking form does.

The setlist filter and the mark on the entries name a guest, and guest names are
behind `gaeste` read everywhere else in the application — some roles have
`setlists` without it on purpose. So both need **`setlists` read and `gaeste`
read**; whoever lacks the second sees the setlist exactly as today. Closed by
default: a feature nobody sees is cheaper than a name that leaks.

The two guest routes are reached by token only and must behave exactly like the
existing ones: invalid token, cancelled, declined or expired → the same dead
end, with no hint which of those it was.

The permission matrix and the demo lock are checked in review, as for every new
route.

### Privacy and erasing

The guest now enters data himself, and it deliberately outlives the booking it
was entered through. That is an outward-facing change, so the privacy statement
moves in the same commit — the template in `einstellungen.php` and the live
texts in every language. §14 "Gäste" has to say two new things: that what the
link shows is decided per booking, and that a guest's answers about songs are
kept beyond the single engagement so he is not asked twice.

Kept data needs a way out. `/intern/gaeste` gets **"Antworten löschen"** per
guest, under `gaeste` write. It stays separate from deleting the guest, which
still refuses while bookings exist — the band must be able to honour "please
delete that" without losing the booking history.

Deleting a song removes its answers.

### The same person booked twice

Booking by typing a name creates a new guest row; there is no match against
existing guests. Today that is a cosmetic duplicate in the contact list. With
answers hanging off the guest, the duplicate also starts with an empty song
list, and everything he answered before is hidden behind the other row. So the
name field has to look before it writes.

**While typing**, from the third character, the form asks
`/intern/gaeste/suche?q=…` and shows what it finds: "Gibt es schon: Max
Mustermann (Gitarre)" with a button that picks him in the dropdown instead. The
search matches anywhere in the name, ignoring case, and returns at most five —
names only, no contact data, behind `gaeste` read.

**On submit**, the server looks again: a typed name that equals an existing
guest's name, ignoring case and outer spaces, does not create a second row. The
form comes back with the hint and the guest preselected, and the band decides.
Nothing is merged silently — two people really can share a name, and the band
confirms with one click either way.

The typed path stays: a guest nobody has booked before is still booked by typing
his name.

### Demo

The demo books guests already. The demo guitarist gets a handful of marked
songs so the filter has something to show, written through the demo's own row
tracking like the rest of the demo data.

### Help and translations

The help page `hilfe-gaeste` gains what is new: the ticks when booking, the song
list, what happens when a name is already known, and "Antworten löschen". The setlist help gains the filter and
the mark. New strings go into `app/strings/de.php` and into
`seed/translations/218-gast-lieder.sql` for all six languages, following the
seed rules — the earliest seed wins, so a text that changes later is changed in
its origin seed with a guarded `DELETE`.

### Checks

`bin/gast-lieder-pruefen.php` in the style of the existing check scripts:

- a booking with every tick off shows nothing but the invitation;
- a booking with `show_songs` off has no song list route;
- a song outside `aktiv`/`in_arbeit` cannot be answered;
- an answer survives when the booking expires and a new booking is made;
- the setlist filter offers only counting guests of events using that setlist,
  and not one whose booking was cancelled or whose gig is past;
- the filter's picker contains no song the guest marked "kenne ich nicht";
- the mark on a setlist entry appears in the editing view and in neither the
  print nor the guest's own setlist;
- a member with `setlists` but without `gaeste` sees neither filter nor mark;
- "Antworten löschen" empties the guest's rows and leaves his bookings;
- an archived song counts in no total, a deleted one takes its answers with it;
- a declined or cancelled booking reaches nothing;
- booking a name that already exists creates no second guest, and the search
  route answers nothing below three characters and nothing without `gaeste`.

No new asset means no service-worker version bump; if the stylesheet changes,
`SW-VERSION` moves with it.

## Left to the builder

- The exact wording of the three buttons and the section heading, in the voice
  the application already uses.
- Where the progress line sits on the guest page.
- Whether the `<details>` on `/intern/gaeste` lists titles or titles with the
  status symbol in front.
- The sort order of the song list for the guest (proposal: by title, like the
  setlist picker).
