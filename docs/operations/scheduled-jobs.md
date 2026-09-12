# Scheduled jobs

Three things run without anybody asking: the **automatic backup**, the **daily
look into linked OneDrive folders**, and the **mailbox fetch**. Each has one
due-check and two possible triggers — a page view, or the matching script from
`bin/` as a cron.

## Why a page view is enough, usually

The due-check sits in front of the login gate, so **any** request counts, the
public band page included. That is deliberate: a band that does not open the
application for a week used to get no backup, no look into the folders and no
notice about new pictures — exactly in the week when it would have mattered.

Requests for images, files and assets are skipped, so a gallery with fifty
thumbnails does not ask the same question fifty times, and each job claims its
slot before starting, so two simultaneous requests do not both run it. The work
happens after the page has been delivered; nobody waits for a mail server.

## When you need a cron after all

An instance whose public page gets no visitors — a closed band area, a site
nobody browses — has no trigger. Give it one:

| script | what it does | a sensible cadence |
|---|---|---|
| `bin/od-refresh.php` | re-checks linked OneDrive folders, notifies about new pictures | daily |
| `bin/post-fetch.php` | fetches the configured mailbox, read-only | every few minutes to hourly |
| `bin/update.sh` | pulls and deploys a new version | only if you want unattended updates |
| `bin/demo-reset.sh` | resets a demo instance to its seed state | hourly, demo only |

Run them as the **web user**, not as root: files they create must stay readable
and writable by the application.

## How to tell whether something ran

- The backup list in *Settings → Backup* shows every run with its result.
- OneDrive folders show when they were last looked at, and what was found.
- The mailbox shows the last fetch and how many messages were new.
- Push delivery leaves a trace of its own: a successful delivery stamps the
  subscription, which is also what keeps it from being pruned as stale.

If a job never seems to run, check the trigger before the code: an instance
with no visitors and no cron is doing exactly what it was told.
