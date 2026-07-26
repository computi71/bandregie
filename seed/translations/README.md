# Translation seeds

German is the source language and lives in `UI_STRINGS` (`app/bootstrap.php`).
These files carry the translations for the other languages (EN, NL, FR, ES, IT).

**You normally do not need to run these manually.** On a fresh installation
Bandroadie imports every file in this folder automatically, once, when the
`translations` table is still empty.

To re-import or update after pulling a new version:

```bash
for f in seed/translations/*.sql; do mysql bandroadie < "$f"; done
```

All statements use `ON DUPLICATE KEY UPDATE`, so re-running them is safe —
but note that it overwrites edits made in the band area under
*Intern → Übersetzungen*.

Band-specific content (about text, tagline, booking text, legal pages) is not
seeded here; it is edited in the settings and stored per language in the same
`translations` table with a `content_` prefix.
