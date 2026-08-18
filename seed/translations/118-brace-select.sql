-- Auswahl setzt, aendert und loest die Klammer (#246)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_brace_hint_pick','Tick rows, then set or release. Everything between the first and the last ticked row belongs to the brace — if the selection touches an existing one, that brace is re-spanned (larger, smaller, or two merged into one). An empty field keeps the cue that is already there.'),
('en','fl_brace_pick_first','Tick rows first, then release.'),
('en','fl_brace_none_there','There is no brace in that selection.'),
('en','fl_brace_released','%1 brace(s) released.'),
('nl','sl_brace_hint_pick','Vink regels aan en zet of haal weg. Alles tussen de eerste en de laatste aangevinkte regel hoort bij de accolade — raakt de selectie een bestaande, dan wordt die opnieuw gespannen. Een leeg veld laat de bestaande aanwijzing staan.'),
('nl','fl_brace_pick_first','Vink eerst regels aan, dan weghalen.'),
('nl','fl_brace_none_there','In die selectie zit geen accolade.'),
('nl','fl_brace_released','%1 accolade(s) weggehaald.'),
('fr','sl_brace_hint_pick','Coche des lignes, puis pose ou retire. Tout ce qui se trouve entre la premiere et la derniere ligne cochee appartient a l''accolade — si la selection touche une accolade existante, celle-ci est retendue. Un champ vide conserve la consigne deja presente.'),
('fr','fl_brace_pick_first','Coche d''abord des lignes, puis retire.'),
('fr','fl_brace_none_there','Il n''y a pas d''accolade dans cette selection.'),
('fr','fl_brace_released','%1 accolade(s) retiree(s).'),
('es','sl_brace_hint_pick','Marca filas y luego pon o quita. Todo lo que quede entre la primera y la ultima fila marcada pertenece a la llave; si la seleccion toca una existente, esa llave se vuelve a tender. Un campo vacio conserva la indicacion que ya hay.'),
('es','fl_brace_pick_first','Marca primero filas y luego quita.'),
('es','fl_brace_none_there','En esa seleccion no hay ninguna llave.'),
('es','fl_brace_released','%1 llave(s) quitada(s).'),
('it','sl_brace_hint_pick','Spunta delle righe, poi metti o togli. Tutto cio che sta fra la prima e l''ultima riga spuntata appartiene alla graffa; se la selezione tocca una graffa esistente, quella viene ritesa. Un campo vuoto lascia l''indicazione che c''e gia.'),
('it','fl_brace_pick_first','Spunta prima delle righe, poi togli.'),
('it','fl_brace_none_there','In quella selezione non c''e nessuna graffa.'),
('it','fl_brace_released','%1 graffa/e tolta/e.')
ON DUPLICATE KEY UPDATE value = value;

-- Blockgrenze ueber die Auswahl (#246)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_block_hint_pick','Announcement: with nothing ticked it is appended at the end, a ticked row puts it behind that row, and a ticked announcement takes the text from the field.'),
('en','fl_block_changed','%1 announcement(s) changed.'),
('nl','sl_block_hint_pick','Praatpauze: zonder vinkje komt die achteraan, een aangevinkte regel zet die erachter, en een aangevinkte praatpauze neemt de tekst uit het veld over.'),
('nl','fl_block_changed','%1 praatpauze(s) gewijzigd.'),
('fr','sl_block_hint_pick','Annonce : sans coche elle s''ajoute a la fin, une ligne cochee la place apres cette ligne, et une annonce cochee reprend le texte du champ.'),
('fr','fl_block_changed','%1 annonce(s) modifiee(s).'),
('es','sl_block_hint_pick','Presentacion: sin marcar nada se anade al final, una fila marcada la coloca detras de esa fila, y una presentacion marcada toma el texto del campo.'),
('es','fl_block_changed','%1 presentacion(es) cambiada(s).'),
('it','sl_block_hint_pick','Presentazione: senza spunte va in fondo, una riga spuntata la mette dopo quella riga, e una presentazione spuntata prende il testo dal campo.'),
('it','fl_block_changed','%1 presentazione/i modificata/e.')
ON DUPLICATE KEY UPDATE value = value;
