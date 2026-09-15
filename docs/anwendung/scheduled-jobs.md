# Scheduled jobs

Three things run without anybody asking: the **automatic backup**, the **daily
look into linked OneDrive folders**, and the **mailbox fetch**. Each has one
due-check and two possible triggers — a page view, or the matching script from
`bin/` as a cron.

## Why a page view is usually enough

The due-check sits in front of the login gate, so **any** request counts, the
public band page included. That is deliberate: before, a week in which nobody
opened the application meant no backup, no look into the folders and no notice
about new pictures — exactly the week when it would have mattered.

Requests for images, files and assets are skipped, so a gallery with fifty
thumbnails does not ask the same question fifty times, and each job claims its
slot before starting, so two simultaneous requests do not both run it. The work
happens after the page has been delivered; nobody waits for a mail server.

## When a cron is needed anyway

An instance whose public page gets no visitors — a closed band area, a site
nobody browses — has no trigger. Give it one:

| script | what it does | how often |
|---|---|---|
| `bin/od-refresh.php` | re-checks linked OneDrive folders, notifies about new pictures | daily |
| `bin/post-fetch.php` | fetches the configured mailbox, read-only | every few minutes to hourly |
| `bin/update.sh` | pulls and deploys a new version | only for unattended updates |
| `bin/demo-reset.sh` | resets a demo instance to its seed state | hourly, demo only |
| `bin/mail-status.php` | reads what the mail server did with each invitation | every minute, see below |

Run them as the **web user**, not as root: files they create have to stay
readable and writable by the application.

## Delivery status of invitations

`mail()` only says that the local mail server accepted the message. Whether
Gmail, Yahoo or GMX took it or refused it a second later is written to the
mail server's log — and that log belongs to root. So the application never
reads the file; the cron hands it the lines and stays root only for the
`tail`:

```
* * * * *  root  tail -n 4000 /var/log/maillog | sudo -u <web-user> php /path/to/bandregie/bin/mail-status.php >/dev/null 2>&1
```

Every invitation carries its own `Message-ID`; the script matches the Postfix
lines (`cleanup … message-id=` → queue id → `smtp … status=sent|bounced`) and
writes status, time and the receiving server's reason into `mail_log`. The
members list then shows, for every account that has not signed in yet,
whether its invitation was *delivered*, *refused* (with the reason) or merely
*handed to the mail server*, together with the time of the last log check.
Without the cron the last one is all it can say — honest, and still more than
before.

Postfix log format only, for now. Other mail servers: the pattern is small and
lives in `mail_status_apply()`.

## How to see whether something ran

- The backup list under *Settings → Backup* shows every run with its result.
- OneDrive folders show when they were last looked at and what was found.
- The mailbox shows the last fetch and how many messages were new.
- Push delivery leaves its own trace: a successful delivery stamps the
  subscription, which is also what keeps it from being pruned as stale.

If a job never seems to run, check the trigger before the code. An instance
with no visitors and no cron is doing exactly what it was told.
