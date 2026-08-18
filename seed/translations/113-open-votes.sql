-- Fehlende Rueckmeldung als offene Aufgabe (#236)
INSERT INTO translations (lang, tkey, value) VALUES
('en','dash_vote_missing','No answer yet'),
('nl','dash_vote_missing','Nog geen antwoord'),
('fr','dash_vote_missing','Pas encore répondu'),
('es','dash_vote_missing','Sin respuesta'),
('it','dash_vote_missing','Ancora senza risposta')
ON DUPLICATE KEY UPDATE value = value;
