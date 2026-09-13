# Troubleshooting

Symptoms that have come up in this project, and what was behind each. Roughly
in the order of how often.

## The number on the app icon disappears

It can only change two ways: a push arrives, or somebody opens the application.
If neither happened, nothing repainted it — and iOS clears the badge on its own
in quiet stretches. A restart does it, and so does swiping the notifications
away.

What to check, in this order:

1. **Was anything sent at all?** A change written straight into the database
   bypasses the notification layer on purpose and sends nothing.
2. **Is the session still alive?** Without a session the app fetches no count,
   refreshes no push subscription and never learns that the browser rotated one.
   PHP's default `session.gc_maxlifetime` is 1440 seconds — 24 minutes of
   inactivity. *Stay signed in* is the answer; it lasts 90 days and slides on
   every use.
3. **Is the subscription alive?** A successful delivery stamps it. One that has
   not been delivered to for a long time is usually a device whose push channel
   the platform discarded — allowing notifications again on that device creates
   a fresh one.

A web application cannot refresh a badge in the background on iOS: every push
must show a visible notification, and silent refresh pushes are not allowed. A
daily reminder, sent only when something is actually open, is the honest way to
keep the number standing.

## A push is not delivered

The log names the count per attempt, not per device. Status codes decide what
happens to the subscription: 404 and 410 mean the endpoint is gone and the row
is deleted; 400, 401, 403 and 413 are treated the same way, because a permanent
refusal is not going to become temporary. Anything else leaves the row alone —
a channel that merely timed out gets another chance.

## Somebody cannot sign in with the password that was mailed to them

A **start password expires after seven days**. It travels in plain text and
stays in every mailbox the mail passed through. An expired one is refused
*after* the password check — refusing earlier would tell a stranger which
address has an account — and the message says what to do: an administrator uses
*Zugangsdaten senden* in the member list, which mints a new one.

That button only appears for accounts that have **never signed in**. After a
first sign-in it would not be a resend but a reset, and the password field next
to it already does that.

## An administrator cannot see the cash box or the mailbox

That is deliberate. Two areas are never handed out by rank: running a band is
not the same as running its money or reading its post. They have to be granted
per account in the member list — including to administrators, including to
whoever installed the thing.

A freshly created administrator therefore starts with neither. That is the
point of marking them explicit, and it is worth saying out loud when somebody
new joins.

## The help page shows something like `help_something`

A permission area without a help text. The help page renders one section per
area and prints the key when the text is missing. Add the text; see
[Running an instance](Running-an-instance).

## The demo instance refuses to save a setting

Anything that shows up publicly on the demo's own address is locked there: band
name, contact address, the address of the installation, the legally required
pages, and the branding images. Everything else stays open to try out, and the
hourly reset cleans up after the visitors.

## After an update the browser still shows the old page

The service worker version was not raised, so the old caches were kept. Raise
`VERSION` in `httpdocs/sw.js` whenever a cached asset changes.
