-- Begruessungszeile auf der Uebersicht (#266)
INSERT INTO translations (lang, tkey, value) VALUES
('en','dash_diversity','Welcome, colourful diversity.'),
('nl','dash_diversity','Welkom, bonte verscheidenheid.'),
('fr','dash_diversity','Bienvenue, diversité colorée.'),
('es','dash_diversity','Bienvenida, diversidad de colores.'),
('it','dash_diversity','Benvenuta, colorata diversità.'),
('en','dash_flag_alt','Rainbow flag'),
('nl','dash_flag_alt','Regenboogvlag'),
('fr','dash_flag_alt','Drapeau arc-en-ciel'),
('es','dash_flag_alt','Bandera arcoíris'),
('it','dash_flag_alt','Bandiera arcobaleno')
ON DUPLICATE KEY UPDATE value = value;
