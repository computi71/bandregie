# Bundled typeface

## Saira Extra Condensed

Used by the printed setlist sheet (`app/views/intern/setlist_print.php`) for
everything except the song title: interpret, cue, BPM and length. The title
keeps the normal face — it is what gets read from a music stand two metres
away, while the rest only has to be findable.

Why bundled rather than asked of the system: the sheet needs a narrow face to
fit a band name beside the title at all. Windows and macOS have Arial Narrow,
Linux has Liberation Sans Narrow, Android has `sans-serif-condensed` — iOS has
none of them, and the same setlist then printed in a different shape depending
on which device held it. Bundling also means a sheet held up on stage does not
depend on the network for its own type; the files are in the service worker's
static cache.

| | |
|---|---|
| Source | https://fonts.google.com/specimen/Saira+Extra+Condensed (v15) |
| Project | https://github.com/Omnibus-Type/Saira |
| Files | `saira-extracondensed-400.woff2`, `saira-extracondensed-700.woff2` |
| Subset | latin (covers the German umlauts and ß) |
| Licence | SIL Open Font License 1.1 — see `OFL.txt` |

Both weights are shipped on purpose. The cue (“Ben in D”) is set bold, and a
synthesised bold on an extra-condensed face smears.

The files are redistributed unmodified. The OFL reserves the font name
“Saira”: a modified version may not be distributed under that name.

### Replacing it

Width measured as a share of Arial for a real metadata line, in case a less
extreme face is wanted later: Saira Extra Condensed 64%, Barlow Condensed 72%,
Saira Condensed 75%, PT Sans Narrow 76%, Archivo Narrow 82%, Roboto Condensed
86%.

Whatever replaces it has to come with its own licence file here, be named in
the `@font-face` block and in the stack in `setlist_print.php`, and be listed
in `STATIC_FILES` in `httpdocs/sw.js` — with the service worker version bumped,
or the old file stays cached.
