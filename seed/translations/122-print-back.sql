-- Zurueck aus der Druckvorschau (#262)
INSERT INTO translations (lang, tkey, value) VALUES
('en','back','Back'),
('nl','back','Terug'),
('fr','back','Retour'),
('es','back','Volver'),
('it','back','Indietro')
ON DUPLICATE KEY UPDATE value = value;
