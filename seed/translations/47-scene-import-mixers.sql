-- The scene import reads .scn files from an X32/M32. The WING writes
-- something else, and naming it sent people looking for a fault in their file.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'ch_import_hint' AND value IN (
  'Scene file from a Behringer X32/M32 or WING (.scn). Existing channels with the same number are updated, your own notes are kept.',
  'Scènebestand van een Behringer X32/M32 of WING (.scn). Bestaande kanalen met hetzelfde nummer worden bijgewerkt, eigen notities blijven staan.',
  'Fichier de scène d''une Behringer X32/M32 ou WING (.scn). Les canaux existants portant le même numéro sont mis à jour, vos notes sont conservées.',
  'Archivo de escena de una Behringer X32/M32 o WING (.scn). Los canales existentes con el mismo número se actualizan y vuestras notas se conservan.',
  'File di scena di un Behringer X32/M32 o WING (.scn). I canali esistenti con lo stesso numero vengono aggiornati, le vostre note restano.'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','ch_import_hint','Scene file from a Behringer X32 or Midas M32 (.scn). Existing channels with the same number are updated, your own notes are kept.'),
('nl','ch_import_hint','Scènebestand van een Behringer X32 of Midas M32 (.scn). Bestaande kanalen met hetzelfde nummer worden bijgewerkt, eigen notities blijven staan.'),
('fr','ch_import_hint','Fichier de scène d''une Behringer X32 ou Midas M32 (.scn). Les canaux existants portant le même numéro sont mis à jour, vos notes sont conservées.'),
('es','ch_import_hint','Archivo de escena de una Behringer X32 o Midas M32 (.scn). Los canales existentes con el mismo número se actualizan y vuestras notas se conservan.'),
('it','ch_import_hint','File di scena di un Behringer X32 o Midas M32 (.scn). I canali esistenti con lo stesso numero vengono aggiornati, le vostre note restano.')
ON DUPLICATE KEY UPDATE value = value;
