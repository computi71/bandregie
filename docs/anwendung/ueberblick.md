# What Bandregie is

[**bandregie.info**](https://bandregie.info) · [**Try the demo**](https://demo.bandregie.info) — a full installation with example data, reset every hour, so you can click through everything before installing anything.

![License: FSL-1.1-ALv2](https://img.shields.io/badge/license-FSL--1.1--ALv2-blue)

**Free for your own band.** Install it, run it, change it, use it for a band
that earns money with its gigs — all covered. The one thing reserved to the
author is offering Bandregie itself as a commercial product or hosted service.
Two years after each release, that restriction lapses and the version becomes
Apache 2.0. See [LICENSE.md](LICENSE.md).

A band of six runs on a group chat, three spreadsheets and one person who
remembers everything. Bandregie replaces that with one place: a public page
for promoters and fans, and an internal area for the work behind it.

## What it is for

**One band, not a platform.** An installation belongs to a single band. No
accounts to manage across bands, no tenant separation to get wrong, no
service in the middle that can disappear. The band owns the server, the
database and the data.

**Answers, not lists.** Who can play on the 14th? What did we play at that
venue last time? Does the rehearsal room cost more than we pay in? Which
microphone is in which case, and what did it cost? Every screen exists
because somebody had to ask that in a chat and wait for an answer.

**Nothing that has to be maintained to keep working.** No framework to
upgrade, no build step, no package lock, no third-party script. PHP and a
database. A band that installs this should still be able to run it in five
years without anyone touching it.

**What is private stays private.** What a member paid for their own
equipment, what they deposit, what they own — visible to them, not to the
band. Permissions are enforced in the route, never only in the interface,
and a stand-in sees the dates they were asked for and nothing else. Two areas
are never handed out by rank: the treasury and the mailbox have to be granted
even to an administrator, because running a band is not the same as running its
money or reading its post. Sending mail in the band's name is a decision of its
own again — whoever may read the mailbox need not be the one who answers it.

## What it does

Public band page plus an internal organization area: events with availability polling (✔/?/✘), status workflow, three times (meet / stage / end), fee tracking and per-event comments; songs with a lifecycle, live-play counters, lyrics and a guitarist's chord sheet — both readable on a full-screen stage teleprompter that scrolls by itself, with the sections colour-coded and the screen kept awake; setlists with pauses, announcements (the dashed line where the band talks instead of playing), braces that tie a run of songs together under one cue, encore markers, copy, a stage-ready print view — with the fields that go on the sheet chosen per print, a type size that fits itself to the paper, and a way back out of the preview — a teleprompter that starts from the setlist and moves on to the next song with lyrics by itself, and a locked history; venues with play history; absences with conflict warnings; tasks, the band's mailbox (fetched by IMAP, read-only, with a booking request turned into an event proposal you check before it is created, replies written and filed in place, and attachments taken over into the event they belong to), a photo library (a folder tree of year, gig and photographer, an archive instead of deleting, tags, press picks, hand-named people and one search field over all of it, duplicates found by checksum), file attachments, member management, a band treasury with standing orders, member deposits and a yearly tax overview, equipment with recurring deadlines — one record per device, numbered where two are identical, and a quantity field for consumables nobody tracks piece by piece — an invoice that can cover several devices at once, an iCal calendar feed, OneDrive folders that can be linked rather than copied — the files stay where they are, pictures come in as small previews with the original linked, a linked folder can be tied to an event so its pictures land there — the ones already taken over and the ones that arrive next week, with the event read out of the folder name where it says so — and what disappears there is marked as missing instead of quietly vanishing from the list, with a daily re-check (any page view, the public one included, or bin/od-refresh.php as a cron) that notifies members of new pictures by push — and a stage-ready offline mode: everything is on the phone unless a member takes it off again in their profile — events, setlists with print views, songs with lyrics and chord sheets, the rider, the patch list — and it keeps itself fresh on its own: on a schedule while a page stays open, and again the moment the browser reports a connection. A single event can be taken along with one button — on the event list and on the overview, which show the same card — and the button says *offline verfügbar* once the gig is really there, checked against a sample of what only the take-along fetches rather than against a note of our own. What belongs to dates that are over is released again, minus everything an upcoming date still needs. A switch in the profile does the whole thing by itself for the dates on the overview.

The overview greets a member with the next dates as real appointments — venue,
navigation link, linked setlist, meeting and stage times — under a line of
welcome text the band writes itself, with the picture beside it chosen from a
rainbow flag, the band logo, an upload of their own, or none.

**Online and offline is not a switch somebody flips.** A hall's wifi accepts the
connection and then answers nothing — that is the normal case, not the
exception. When a stored copy of the page exists, the network gets three
seconds; after that the copy is served and a line says how old it is. Without a
copy nothing changes, because there is nothing to fall back to.

**White-label:** band name, logo, background image and favicon are configured entirely in the settings — every band makes the instance its own.

**Multilingual:** the interface ships in German, English, Dutch, French, Spanish and Italian; band texts and the legal pages are maintained per language, with a fallback chain (selected language → default language → English → German). Which languages appear is up to the admin — only the default language stays switched on, because something has to be there when nothing else fits — and every string can be corrected in the band area.

**Stack:** PHP 8.1+ with MariaDB/MySQL (PDO), no framework, no build step, no dependencies.

## Screenshots

The demo data ships with the project, so a fresh installation looks like this
straight away — a fictional band with events, songs, a treasury and gear. The
gallery brings a handful of photographs along so the folder tree, the tags, the
press selection and the duplicate finder have something to stand on. Only
licences without conditions get in there, and where each file came from is
written down in `seed/demo/CREDITS.md`: this project is passed on to other
bands, and none of them should inherit an obligation nobody told them about.

| | |
|---|---|
| ![Public page](../screenshots/public-page.jpg) **Public page** — what promoters and fans see. Band name, logo and background come from the settings. | ![Events](../screenshots/events.jpg) **Events** — availability in one click, absences flagged before anyone drives anywhere. |
| ![Setlists](../screenshots/setlist.jpg) **Setlists** — breaks, announcements, braces and encores in place, with a print view for the stage. | ![Treasury](../screenshots/treasury.jpg) **Treasury** — standing orders book themselves; deposits are set against the rehearsal room rent. |
| ![Equipment](../screenshots/equipment.jpg) **Equipment** — cases within cases, purchase prices only the owner sees, deadlines for what needs testing. | ![Stage plot](../screenshots/stageplot.jpg) **Stage rider** — a plot the promoter can read, from the members and their instruments. |

More: [songs](../screenshots/songs.jpg) · [members and permissions](../screenshots/members.jpg)

## Roadmap

See the GitHub issues and milestones. What is being worked towards:

- **Attachments out of the mailbox into the event** — a stage plan arrives as a
  PDF in a booking mail, and it belongs with the gig rather than in somebody's
  inbox.
- **One shared app registration for OneDrive**, so a band can link its folders
  without first registering an application of its own at Microsoft.
- **Address search without Nominatim** — a self-hosted geocoder, so looking up a
  venue asks nobody outside the server.

Already done and no longer on this list: the band's mailbox with booking
requests read into event proposals, and cloud folders linked instead of copied.

No native app is planned — the installable web app does the same job without a
yearly fee, a review queue on every change, and a second codebase to keep alive.
