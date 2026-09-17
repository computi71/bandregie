-- Bebilderte Hilfe: Bildunterschrift für die Bildschirmfotos (#305)
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_shot_alt','Screenshot: %s'),
('nl','help_shot_alt','Schermafbeelding: %s'),
('fr','help_shot_alt','Capture d''écran : %s'),
('es','help_shot_alt','Captura de pantalla: %s'),
('it','help_shot_alt','Schermata: %s')
ON DUPLICATE KEY UPDATE value = value;
