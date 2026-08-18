-- Die Anweisung gilt fuer den Block unter der Linie (#241)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_block_hint','The cue applies to the block below the line — "Drop D" above the first three songs means: play those three in drop D.'),
('nl','sl_block_hint','De aanwijzing geldt voor het blok onder de lijn — "Drop D" boven de eerste drie nummers betekent: die drie in drop D.'),
('fr','sl_block_hint','La consigne vaut pour le bloc sous la ligne : « Drop D » au-dessus des trois premiers morceaux veut dire ces trois-là en drop D.'),
('es','sl_block_hint','La indicacion vale para el bloque bajo la linea: "Drop D" encima de las tres primeras canciones significa esas tres en drop D.'),
('it','sl_block_hint','L''indicazione vale per il blocco sotto la linea: "Drop D" sopra i primi tre pezzi significa quei tre in drop D.')
ON DUPLICATE KEY UPDATE value = value;
