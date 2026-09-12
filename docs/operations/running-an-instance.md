# Running an instance

## The release path

A change goes out in one direction, and each stop is a chance to notice
something before a band does:

```
git push  →  staging  →  test  →  demo  →  test  →  production
```

Skipping the middle is how a broken page reaches people who are about to go on
stage. Test on staging with the data that lives there; the demo is the last
place where a mistake is merely embarrassing.

**A checkout is not a deployment.** Plesk's git extension has two separate
steps, and fetching alone changes nothing on disk:

```
plesk ext git --fetch  -domain <domain> -name <repo>
plesk ext git --deploy -domain <domain> -name <repo>
```

Afterwards read `VERSION` from the deployed directory — not the tag you pushed,
the file that is actually lying there. That is the only proof that the deploy
step did what its name says.

## Version numbers

`MAJOR.MINOR.FIX`, and the middle digit is the one that carries meaning:

| change | digit |
|---|---|
| new behaviour, a feature, anything a user would notice as new | **minor** |
| a defect repaired, wording corrected, a regression undone | **fix** |

Every release bumps `VERSION` **and** gets a git tag plus a GitHub release with
notes that say *why*, not what the lines do. A version without a tag is a
version nobody can go back to.

## The service worker

`httpdocs/sw.js` carries `const VERSION = 'bandregie-vNN'`. **Raise it whenever
a cached asset changes** — stylesheet, script, icon. The version is part of the
cache names, so raising it retires the old caches on the next activation.

One cache deliberately does not carry the version: the state cache that holds
the badge count. It belongs to the person, not to the release, and is cleared
only on sign-out. Wiping it with every update is how a badge disappears after
every deploy.

## Translations and seeds

German lives in `UI_STRINGS` in `app/bootstrap.php`. The other languages come
from `seed/translations/NN-*.sql`, replayed on every boot, and they only ever
*add* what is missing — a text a band corrected in the application is never
overwritten.

Three consequences worth knowing before you edit one:

1. **The earliest seed wins.** Files are read in `glob()` order, which is
   alphabetical, so `112-` comes before `16-`. Number new files accordingly.
2. **Changing a shipped text takes two steps**: correct it in the original seed
   *and* add a guarded `DELETE` in a new file that removes only the old shipped
   value, so existing databases pick the new one up. Guard it by value, so a
   band's own wording survives.
3. **A new area needs its help text.** The help page renders one section per
   permission area; a missing text is printed as its own key, in plain sight.
   Each area may have a second paragraph under `help_<area>_2` — that is the
   cheap way to add a topic without rewriting a grown text six times.

## Before you call it done

- Run the page in a browser, not only in your head. Server-rendered HTML proves
  nothing about whether a thing can be tapped.
- Check the phone width. 375 px is the yardstick; a control people press
  repeatedly wants roughly 44 px.
- Check the failure case too: wrong input, missing right, empty list, no signal.
- Remove your test data. Accounts, rows, files — all of it.
