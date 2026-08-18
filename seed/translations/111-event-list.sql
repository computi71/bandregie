-- Uebersichtlichere Terminliste: Monatsnamen, Zaehlzeile, Abgesagte (#233)
INSERT INTO translations (lang, tkey, value) VALUES
('en','months','January,February,March,April,May,June,July,August,September,October,November,December'),
('en','ev_show_cancelled','Cancelled (%1)'),
('en','ev_hide_cancelled','Hide cancelled'),
('en','ev_count','%1 dates'),
('en','ev_count_requested','%1 requested'),
('en','ev_count_cancelled','%1 cancelled, hidden'),

('nl','months','januari,februari,maart,april,mei,juni,juli,augustus,september,oktober,november,december'),
('nl','ev_show_cancelled','Afgezegd (%1)'),
('nl','ev_hide_cancelled','Afgezegde verbergen'),
('nl','ev_count','%1 afspraken'),
('nl','ev_count_requested','%1 aangevraagd'),
('nl','ev_count_cancelled','%1 afgezegd, verborgen'),

('fr','months','janvier,février,mars,avril,mai,juin,juillet,août,septembre,octobre,novembre,décembre'),
('fr','ev_show_cancelled','Annulées (%1)'),
('fr','ev_hide_cancelled','Masquer les annulées'),
('fr','ev_count','%1 dates'),
('fr','ev_count_requested','%1 demandées'),
('fr','ev_count_cancelled','%1 annulées, masquées'),

('es','months','enero,febrero,marzo,abril,mayo,junio,julio,agosto,septiembre,octubre,noviembre,diciembre'),
('es','ev_show_cancelled','Canceladas (%1)'),
('es','ev_hide_cancelled','Ocultar canceladas'),
('es','ev_count','%1 fechas'),
('es','ev_count_requested','%1 solicitadas'),
('es','ev_count_cancelled','%1 canceladas, ocultas'),

('it','months','gennaio,febbraio,marzo,aprile,maggio,giugno,luglio,agosto,settembre,ottobre,novembre,dicembre'),
('it','ev_show_cancelled','Annullate (%1)'),
('it','ev_hide_cancelled','Nascondi le annullate'),
('it','ev_count','%1 date'),
('it','ev_count_requested','%1 richieste'),
('it','ev_count_cancelled','%1 annullate, nascoste')
ON DUPLICATE KEY UPDATE value = value;
