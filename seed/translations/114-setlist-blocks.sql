-- Blockgrenzen mit Anweisung in der Setliste (#241)
--
-- „Block" heisst seit #248 Sprechpause: gespielt wird nicht, geredet schon. Weil
-- der frueheste Seed gewinnt (ON DUPLICATE KEY UPDATE value = value), muss der
-- alte Wortlaut aller betroffenen Schluessel einmal weg — auch der aus 115 und
-- 118, das spaeter laedt und seine neuen Werte dann selbst setzt. sl_block,
-- sl_block_note_edit und sl_block_hint sind ganz entfallen: unbenutzt, und ein
-- Text, den niemand anzeigt, ist eine Zeile, die beim naechsten Lesen aufhaelt.
DELETE FROM translations WHERE tkey IN
  ('sl_block','sl_block_word','sl_block_add','sl_block_note_ph','sl_block_note_edit',
   'sl_block_hint','sl_block_hint_pick','fl_block_changed')
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'sl_block_sprechpause_v1') = 0;
INSERT INTO settings (`key`, value) VALUES ('sl_block_sprechpause_v1', '1')
  ON DUPLICATE KEY UPDATE value = value;
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_block_word','Announcement'),('en','sl_block_add','Announcement'),
('en','sl_block_note_ph','Announcement, e.g. "band intro"'),
('nl','sl_block_word','Praatpauze'),('nl','sl_block_add','Praatpauze'),
('nl','sl_block_note_ph','Aankondiging, bijv. "bandvoorstelling"'),
('fr','sl_block_word','Annonce'),('fr','sl_block_add','Annonce'),
('fr','sl_block_note_ph','Annonce, p. ex. « presentation du groupe »'),
('es','sl_block_word','Presentacion'),('es','sl_block_add','Presentacion'),
('es','sl_block_note_ph','Anuncio, p. ej. "presentar la banda"'),
('it','sl_block_word','Presentazione'),('it','sl_block_add','Presentazione'),
('it','sl_block_note_ph','Annuncio, es. "presentare la band"')
ON DUPLICATE KEY UPDATE value = value;
