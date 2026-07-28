# Translation seeds

German is the source language and lives in `UI_STRINGS` (`app/bootstrap.php`).
These files carry the translations for the other languages (EN, NL, FR, ES, IT).

**You normally do not need to run these manually.** Bandroadie imports every
file in this folder automatically — on a fresh installation, and again after an
update that brings new ones.

To re-import or update after pulling a new version:

```bash
for f in seed/translations/*.sql; do mysql bandroadie < "$f"; done
```

The statements only ever add missing keys (`ON DUPLICATE KEY UPDATE value =
value`), so re-running them is safe and leaves edits made in the band area
under *Intern → Übersetzungen* alone. Wording that a later version replaces is
removed by an explicit `DELETE` in the file that supersedes it.

Band-specific content (about text, tagline, booking text, legal pages) is not
seeded here; it is edited in the settings and stored per language in the same
`translations` table with a `content_` prefix.
