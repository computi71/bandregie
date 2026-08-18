-- Blockgrenzen mit Anweisung in der Setliste (#241)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_block','Block'),('en','sl_block_word','Block'),('en','sl_block_add','Block break'),
('en','sl_block_note_ph','Cue, e.g. "Andi retunes"'),('en','sl_block_note_edit','Change the cue'),
('nl','sl_block','Blok'),('nl','sl_block_word','Blok'),('nl','sl_block_add','Blokgrens'),
('nl','sl_block_note_ph','Aanwijzing, bijv. "Andi stemt"'),('nl','sl_block_note_edit','Aanwijzing wijzigen'),
('fr','sl_block','Bloc'),('fr','sl_block_word','Bloc'),('fr','sl_block_add','Fin de bloc'),
('fr','sl_block_note_ph','Consigne, p. ex. « Andi raccorde »'),('fr','sl_block_note_edit','Modifier la consigne'),
('es','sl_block','Bloque'),('es','sl_block_word','Bloque'),('es','sl_block_add','Fin de bloque'),
('es','sl_block_note_ph','Indicacion, p. ej. "Andi afina"'),('es','sl_block_note_edit','Cambiar la indicacion'),
('it','sl_block','Blocco'),('it','sl_block_word','Blocco'),('it','sl_block_add','Fine blocco'),
('it','sl_block_note_ph','Indicazione, es. "Andi accorda"'),('it','sl_block_note_edit','Cambia indicazione')
ON DUPLICATE KEY UPDATE value = value;
