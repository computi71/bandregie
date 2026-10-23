-- Überschrift der Übersichtskarte, wenn nur Zusagen darin stehen (#387).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','dash_open_votes','Answers still missing'),
('nl','dash_open_votes','Openstaande reacties'),
('fr','dash_open_votes','Réponses en attente'),
('es','dash_open_votes','Respuestas pendientes'),
('it','dash_open_votes','Risposte mancanti')
ON DUPLICATE KEY UPDATE value = value;
