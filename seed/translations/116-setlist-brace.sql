-- Klammer ueber zusammengehoerende Titel (#242)
--
-- sl_brace_from/-to sind mit der Checkbox-Auswahl entfallen (#245); die alten
-- Zeilen muessen einmal weg, sonst blieben sie als Waisen in der Tabelle stehen.
DELETE FROM translations WHERE tkey IN ('sl_brace_from','sl_brace_to')
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'sl_brace_pick_v1') = 0;
INSERT INTO settings (`key`, value) VALUES ('sl_brace_pick_v1', '1')
  ON DUPLICATE KEY UPDATE value = value;
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_brace','Brace'),('en','sl_brace_add','Add brace'),('en','sl_brace_remove','Remove brace'),
('en','sl_brace_pick','for the brace'),('en','sl_brace_hint_pick','Tick the rows, type the cue, set the brace. Everything between the first and the last ticked row belongs to it.'),
('en','sl_brace_hint','A brace ties together songs played as one run — like the bracket drawn on the paper sheet. The cue beside it applies to all songs inside, "drop D" for instance.'),
('en','fl_brace_set','Brace set over %1 songs.'),('en','fl_brace_bad','Those positions do not make a brace.'),
('nl','sl_brace','Accolade'),('nl','sl_brace_add','Accolade zetten'),('nl','sl_brace_remove','Accolade weghalen'),
('nl','sl_brace_pick','voor de accolade aanvinken'),('nl','sl_brace_hint_pick','Vink de regels aan, typ de aanwijzing, zet de accolade. Alles tussen de eerste en de laatste aangevinkte regel hoort erbij.'),
('nl','sl_brace_hint','Een accolade bindt nummers samen die aan een stuk gespeeld worden — zoals de haak op het papieren blad. De aanwijzing ernaast geldt voor alles erbinnen, bijvoorbeeld "drop D".'),
('nl','fl_brace_set','Accolade over %1 nummers gezet.'),('nl','fl_brace_bad','Die posities vormen geen accolade.'),
('fr','sl_brace','Accolade'),('fr','sl_brace_add','Poser une accolade'),('fr','sl_brace_remove','Retirer l''accolade'),
('fr','sl_brace_pick','cocher pour l''accolade'),('fr','sl_brace_hint_pick','Coche les lignes, saisis la consigne, pose l''accolade. Tout ce qui se trouve entre la première et la dernière ligne cochée en fait partie.'),
('fr','sl_brace_hint','Une accolade relie des morceaux joués d''affilée, comme le crochet tracé sur la feuille. La consigne à côté vaut pour tous les morceaux dedans, « drop D » par exemple.'),
('fr','fl_brace_set','Accolade posée sur %1 morceaux.'),('fr','fl_brace_bad','Ces positions ne forment pas une accolade.'),
('es','sl_brace','Llave'),('es','sl_brace_add','Poner llave'),('es','sl_brace_remove','Quitar la llave'),
('es','sl_brace_pick','marcar para la llave'),('es','sl_brace_hint_pick','Marca las filas, escribe la indicacion y pon la llave. Todo lo que quede entre la primera y la ultima fila marcada forma parte.'),
('es','sl_brace_hint','La llave agrupa canciones que se tocan seguidas, como el corchete dibujado en la hoja. La indicacion al lado vale para todas las de dentro, por ejemplo "drop D".'),
('es','fl_brace_set','Llave puesta sobre %1 canciones.'),('es','fl_brace_bad','Esas posiciones no forman una llave.'),
('it','sl_brace','Graffa'),('it','sl_brace_add','Metti la graffa'),('it','sl_brace_remove','Togli la graffa'),
('it','sl_brace_pick','spunta per la graffa'),('it','sl_brace_hint_pick','Spunta le righe, scrivi l''indicazione, metti la graffa. Tutto cio che sta fra la prima e l''ultima riga spuntata ne fa parte.'),
('it','sl_brace_hint','La graffa unisce i pezzi suonati di seguito, come la parentesi disegnata sul foglio. L''indicazione accanto vale per tutti quelli dentro, per esempio "drop D".'),
('it','fl_brace_set','Graffa messa su %1 pezzi.'),('it','fl_brace_bad','Quelle posizioni non formano una graffa.')
ON DUPLICATE KEY UPDATE value = value;
