-- Translations for the drag-and-drop setlist editor.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_drag_hint','Drag the rows to reorder — on a phone use the arrows.'),('en','sl_saved','Order saved'),
('nl','sl_drag_hint','Sleep de regels om de volgorde te wijzigen — op de telefoon de pijlen gebruiken.'),('nl','sl_saved','Volgorde opgeslagen'),
('fr','sl_drag_hint','Fais glisser les lignes pour réordonner — sur mobile, utilise les flèches.'),('fr','sl_saved','Ordre enregistré'),
('es','sl_drag_hint','Arrastra las filas para reordenar — en el móvil usa las flechas.'),('es','sl_saved','Orden guardado'),
('it','sl_drag_hint','Trascina le righe per riordinare — su telefono usa le frecce.'),('it','sl_saved','Ordine salvato')
ON DUPLICATE KEY UPDATE value = value;
