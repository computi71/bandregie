-- Texte, die niemand mehr liest (#412).
--
-- Siebzehn Schlüssel ohne Fundstelle im Code — Reste von verschobenen oder
-- gelöschten Oberflächen. Der klarste Fall sind die drei set_ical*: Das
-- Kalender-Abo ist aus den Einstellungen auf eine eigene Seite gezogen und
-- hat dort eigene Texte bekommen; die alten Beschriftungen blieben liegen.
--
-- Die Marke in settings sorgt dafür, dass einmal gelöscht wird und nicht
-- bei jedem Bootstrap wieder — so wie in 94-onedrive-descend.sql.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey IN (
  'about_settings_hint',
  'bk_target_ftp',
  'bk_target_onedrive',
  'contract_from_quote',
  'demo_badge',
  'perm_open',
  'photo_person_add',
  'photo_search_none',
  'photo_tree_open',
  'quote_open',
  'set_contract_reset',
  'set_contract_reset_confirm',
  'set_ical',
  'set_ical_hint',
  'set_ical_link',
  'song_chords_none',
  'song_read'
) AND (SELECT COUNT(*) FROM settings WHERE `key` = 'texte_410_tot') = 0;
INSERT INTO settings (`key`, value) VALUES ('texte_410_tot', '1')
  ON DUPLICATE KEY UPDATE value = value;
