-- Mitteilungen aus der Warteschlange, gesammelt (#383).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_att_many','%1 new replies to %2'),
('nl','push_att_many','%1 nieuwe antwoorden op %2'),
('fr','push_att_many','%1 nouvelles réponses pour %2'),
('es','push_att_many','%1 respuestas nuevas a %2'),
('it','push_att_many','%1 nuove risposte per %2')
ON DUPLICATE KEY UPDATE value = value;
