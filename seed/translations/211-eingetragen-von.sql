-- Wer den Termin eingetragen hat (#425).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_created_by','Entered by'),
('nl','ev_created_by','Ingevoerd door'),
('fr','ev_created_by','Saisi par'),
('es','ev_created_by','Introducido por'),
('it','ev_created_by','Inserito da')
ON DUPLICATE KEY UPDATE value = value;
