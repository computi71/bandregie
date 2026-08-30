-- Keine Anmeldung bekannt (#275)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_login_unknown','No sign-in on record'),
('nl','mem_login_unknown','Geen aanmelding bekend'),
('fr','mem_login_unknown','Aucune connexion enregistrée'),
('es','mem_login_unknown','Sin inicio de sesión registrado'),
('it','mem_login_unknown','Nessun accesso registrato')
ON DUPLICATE KEY UPDATE value = value;
