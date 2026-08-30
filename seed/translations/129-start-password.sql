-- Start-Passwoerter laufen nach sieben Tagen ab (#274)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_never_logged_in','Never signed in'),
('nl','mem_never_logged_in','Nog nooit aangemeld'),
('fr','mem_never_logged_in','Jamais connecté'),
('es','mem_never_logged_in','Nunca ha iniciado sesión'),
('it','mem_never_logged_in','Mai effettuato l''accesso'),
('en','mem_start_pw_until','Start password valid until'),
('nl','mem_start_pw_until','Startwachtwoord geldig tot'),
('fr','mem_start_pw_until','Mot de passe initial valable jusqu''au'),
('es','mem_start_pw_until','Contraseña inicial válida hasta'),
('it','mem_start_pw_until','Password iniziale valida fino al'),
('en','mem_start_pw_expired','Start password expired'),
('nl','mem_start_pw_expired','Startwachtwoord verlopen'),
('fr','mem_start_pw_expired','Mot de passe initial expiré'),
('es','mem_start_pw_expired','Contraseña inicial caducada'),
('it','mem_start_pw_expired','Password iniziale scaduta'),
('en','fl_start_pw_expired','The start password has expired. Ask for new access details — the member list has a button for it.'),
('nl','fl_start_pw_expired','Het startwachtwoord is verlopen. Vraag om nieuwe toegangsgegevens — in de ledenlijst zit daar een knop voor.'),
('fr','fl_start_pw_expired','Le mot de passe initial a expiré. Demandez de nouveaux accès — la liste des membres a un bouton pour cela.'),
('es','fl_start_pw_expired','La contraseña inicial ha caducado. Pide nuevos datos de acceso: la lista de miembros tiene un botón para ello.'),
('it','fl_start_pw_expired','La password iniziale è scaduta. Chiedi nuovi dati di accesso: nell''elenco dei membri c''è un pulsante apposito.')
ON DUPLICATE KEY UPDATE value = value;
