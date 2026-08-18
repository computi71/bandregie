-- Postleitzahl als eigenes Feld beim Veranstaltungsort (#249)
INSERT INTO translations (lang, tkey, value) VALUES
('en','postcode','Postcode'),
('nl','postcode','Postcode'),
('fr','postcode','Code postal'),
('es','postcode','Codigo postal'),
('it','postcode','CAP')
ON DUPLICATE KEY UPDATE value = value;
