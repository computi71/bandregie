# Mail, address search and push

Everything that leaves the server. Each of these is off until somebody turns it
on, and each says so in the privacy policy the application ships — if you add a
service, add its paragraph in the same change.

## Mail

Member invitations and password resets use PHP's `mail()`. The host must be
able to deliver mail for your domain; send from an address on that domain
(the app uses `no-reply@<your-domain>`) so SPF checks pass.

## Address search, navigation and photo metadata

**Address search** is off by default. When switched on in the settings, looking
up an address sends it once — from the server, not the browser — to
OpenStreetMap's Nominatim to fetch coordinates, so navigation can be precise.
The app honours Nominatim's usage policy: one request per click (no typeahead),
with a proper User-Agent. Without the switch nothing leaves the server.

**Navigation** opens the device's own maps app: `geo:` on Android (the user's
configured default), a small chooser on iPhone (Apple Maps, Google Maps, Waze,
OpenStreetMap — iOS does not expose the system default to web pages), and an
OpenStreetMap link on the desktop. The app fetches nothing; the destination
goes to whichever app the member picks, under that vendor's terms.

**Photo metadata**: on upload the capture date and GPS coordinates are read
from the file (needs the `exif` extension) and stored in the database for
suggesting which event a photo belongs to. The metadata is then **removed from
the stored file** (needs the `gd` extension), so coordinates —
a rehearsal room is often somebody's home — do not travel with a published
photo. Only originals straight from a device carry metadata at all; copies
shared through messengers or social platforms have already lost it.

**The photo library** organises itself around three facts a picture brings
along: its origin path (folder = gig, subfolder = photographer), its capture
date, and its content checksum. A whole origin folder can be assigned to an event in one step.
Instead of deleting
there is an archive — out of every gallery, including the public page and
direct file access, but never destroyed. Tags, press picks (fit to hand out —
deliberately separate from website visibility) and hand-linked people make
pictures findable through one search field that also looks into the archive.
Exact duplicate files show up side by side on the cleanup page; recompressed
messenger copies deliberately do not, because a checksum cannot vouch for
them. From linked OneDrive folders only an 800-px preview is stored locally;
the original stays at OneDrive, linked on the tile with camera and true
dimensions, and its checksum from Graph makes a re-uploaded original
recognisable as a duplicate without downloading anything.

**The band's mailbox** is fetched by IMAP on the same pattern as the backup and
the OneDrive check: one due-check, two triggers — a page view, or
`bin/post-fetch.php` as a cron. Read-only, one configured mailbox
only, never a member's private one, and nothing is marked as read on the
server: whoever also has it on their phone finds it untouched. Messages are
recognised by the server's UID, so nothing is stored twice.

From the text the application proposes an event — date, times, place, fee —
and shows for each field where in the text it found it, because a number
without provenance has to be believed. It refuses to guess: no date from "next
Saturday", no amount without a currency, no address out of prose. The proposal
only fills the form; the event is created on click, from what stands in the
form. The request travels into the event's notes, so next year nobody wonders
what was agreed. Replies go to the sender of the message — never to an address
from the form — and are filed with it. Attachments are listed but not downloaded: a stage plan or a contract is fetched when somebody takes it over, and then it lands in the event's files by the same path an upload takes — same size limit, same encryption at rest, same table. It needs an event first, which is the point: the file is being filed, not collected.

Needs the `imap` extension; without it the feature says so instead of failing
quietly. Note that PHP 8.4 moved that extension out of the core.

**Privacy policy**: the shipped template covers every one of these processing
activities, including the optional ones, with bracket placeholders to fill in.
If you add an outgoing service, add its paragraph in the same change.

## Push notifications (on by default, switchable)

Push is available out of the box; an administrator can switch it off under
*Settings → Outgoing connections*, where everything this installation can do
towards the outside sits together. All three topics are preselected per
member, so members untick rather than tick — but nothing is sent until a
member registers a device from their profile, where the browser asks for
permission itself. Recipients only ever get notifications for events
they may see in the member area. Works on Android and, for the
installed home-screen app, on iOS 16.4+. The VAPID key pair is generated
server-side on first use (stored sealed when an encryption key is
configured); no third-party service and no library involved — messages are
encrypted per RFC 8291 and sent directly to the browser vendors' push
endpoints. Browsers without push support simply never see the buttons.

Four switches stand between a message and a screen — the per-device
registration, the site permission, the browser's master switch, and the
operating system's own notification setting — and every one of them refuses in
silence. Chromium adds a fifth that is invisible: dismiss the question three
times and the origin is embargoed for a week, `requestPermission()` resolves
to `default` with no dialog, and the site turns up in no block list. The
in-app help walks all of them from the inside out, because from the outside a
blocked notification and a working one look exactly alike.
