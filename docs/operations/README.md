# Bandregie — operator's handbook

These pages are for **running** an instance. What the application does and how to
install it for the first time is in the
[README](https://github.com/computi71/bandregie#readme); repeating it here would
only give it a second place to go stale.

What belongs here is the knowledge you need *after* the first install: how a
change reaches production, what runs on a schedule, and what to look at when
something behaves oddly.

## Pages

- **[Running an instance](running-an-instance.md)** — the release path, version
  numbers, the service worker, and the rules that keep translations from
  fighting each other.
- **[Scheduled jobs](scheduled-jobs.md)** — what runs by itself, what needs a cron,
  and how to tell whether it ran.
- **[Troubleshooting](troubleshooting.md)** — the symptoms that have actually come
  up, with the cause behind each one.

## House rules

- **Nothing private in here.** These pages are as public as the repository. No host
  names, no addresses, no keys, no band data — describe the pattern, not your
  server.
- **English**, like the rest of the repository. The application's interface is
  German plus five translations; the documentation is not.
- If you change behaviour, change the page that describes it **in the same
  change**. A handbook that lies is worse than no handbook.
