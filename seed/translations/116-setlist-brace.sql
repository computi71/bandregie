-- Klammer ueber zusammengehoerende Titel (#242)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_brace','Brace'),('en','sl_brace_add','Add brace'),('en','sl_brace_remove','Remove brace'),
('en','sl_brace_from','from position'),('en','sl_brace_to','to'),
('en','sl_brace_hint','A brace ties together songs played as one run — like the bracket drawn on the paper sheet. The cue beside it applies to all songs inside, "drop D" for instance.'),
('en','fl_brace_set','Brace set over %1 songs.'),('en','fl_brace_bad','Those positions do not make a brace.'),
('nl','sl_brace','Accolade'),('nl','sl_brace_add','Accolade zetten'),('nl','sl_brace_remove','Accolade weghalen'),
('nl','sl_brace_from','van positie'),('nl','sl_brace_to','tot'),
('nl','sl_brace_hint','Een accolade bindt nummers samen die aan een stuk gespeeld worden — zoals de haak op het papieren blad. De aanwijzing ernaast geldt voor alles erbinnen, bijvoorbeeld "drop D".'),
('nl','fl_brace_set','Accolade over %1 nummers gezet.'),('nl','fl_brace_bad','Die posities vormen geen accolade.'),
('fr','sl_brace','Accolade'),('fr','sl_brace_add','Poser une accolade'),('fr','sl_brace_remove','Retirer l''accolade'),
('fr','sl_brace_from','de la position'),('fr','sl_brace_to','à'),
('fr','sl_brace_hint','Une accolade relie des morceaux joués d''affilée, comme le crochet tracé sur la feuille. La consigne à côté vaut pour tous les morceaux dedans, « drop D » par exemple.'),
('fr','fl_brace_set','Accolade posée sur %1 morceaux.'),('fr','fl_brace_bad','Ces positions ne forment pas une accolade.'),
('es','sl_brace','Llave'),('es','sl_brace_add','Poner llave'),('es','sl_brace_remove','Quitar la llave'),
('es','sl_brace_from','de la posicion'),('es','sl_brace_to','a'),
('es','sl_brace_hint','La llave agrupa canciones que se tocan seguidas, como el corchete dibujado en la hoja. La indicacion al lado vale para todas las de dentro, por ejemplo "drop D".'),
('es','fl_brace_set','Llave puesta sobre %1 canciones.'),('es','fl_brace_bad','Esas posiciones no forman una llave.'),
('it','sl_brace','Graffa'),('it','sl_brace_add','Metti la graffa'),('it','sl_brace_remove','Togli la graffa'),
('it','sl_brace_from','dalla posizione'),('it','sl_brace_to','alla'),
('it','sl_brace_hint','La graffa unisce i pezzi suonati di seguito, come la parentesi disegnata sul foglio. L''indicazione accanto vale per tutti quelli dentro, per esempio "drop D".'),
('it','fl_brace_set','Graffa messa su %1 pezzi.'),('it','fl_brace_bad','Quelle posizioni non formano una graffa.')
ON DUPLICATE KEY UPDATE value = value;
