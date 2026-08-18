-- Infozeile auf dem Druckblatt: Spielzeit zzgl. Pause (#243)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_print_songs','%1 songs'),('en','sl_print_min','%1 min'),
('en','sl_print_plus_break','plus break'),('en','sl_print_plus_breaks','plus %1 breaks'),
('nl','sl_print_songs','%1 nummers'),('nl','sl_print_min','%1 min'),
('nl','sl_print_plus_break','excl. pauze'),('nl','sl_print_plus_breaks','excl. %1 pauzes'),
('fr','sl_print_songs','%1 morceaux'),('fr','sl_print_min','%1 min'),
('fr','sl_print_plus_break','hors pause'),('fr','sl_print_plus_breaks','hors %1 pauses'),
('es','sl_print_songs','%1 canciones'),('es','sl_print_min','%1 min'),
('es','sl_print_plus_break','mas el descanso'),('es','sl_print_plus_breaks','mas %1 descansos'),
('it','sl_print_songs','%1 pezzi'),('it','sl_print_min','%1 min'),
('it','sl_print_plus_break','piu la pausa'),('it','sl_print_plus_breaks','piu %1 pause')
ON DUPLICATE KEY UPDATE value = value;
