-- Filter nach Terminart in der Terminliste (#372).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_filter_type','Type'),
('en','ev_filter_all','All types (%1)'),
('en','ev_filter_apply','Show'),

('nl','ev_filter_type','Soort'),
('nl','ev_filter_all','Alle soorten (%1)'),
('nl','ev_filter_apply','Tonen'),

('fr','ev_filter_type','Type'),
('fr','ev_filter_all','Tous les types (%1)'),
('fr','ev_filter_apply','Afficher'),

('es','ev_filter_type','Tipo'),
('es','ev_filter_all','Todos los tipos (%1)'),
('es','ev_filter_apply','Mostrar'),

('it','ev_filter_type','Tipo'),
('it','ev_filter_all','Tutti i tipi (%1)'),
('it','ev_filter_apply','Mostra')
ON DUPLICATE KEY UPDATE value = value;
