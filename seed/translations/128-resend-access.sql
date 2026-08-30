-- Zugangsdaten erneut senden (#273)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_send_access','Send access details'),
('nl','mem_send_access','Toegangsgegevens sturen'),
('fr','mem_send_access','Envoyer les accès'),
('es','mem_send_access','Enviar datos de acceso'),
('it','mem_send_access','Invia i dati di accesso'),
('en','mem_send_access_confirm','A new start password is generated and sent by e-mail. The member''s current password stops working.'),
('nl','mem_send_access_confirm','Er wordt een nieuw startwachtwoord aangemaakt en per e-mail verstuurd. Het huidige wachtwoord van dit lid werkt daarna niet meer.'),
('fr','mem_send_access_confirm','Un nouveau mot de passe initial est créé et envoyé par e-mail. Le mot de passe actuel de ce membre cesse de fonctionner.'),
('es','mem_send_access_confirm','Se genera una nueva contraseña inicial y se envía por correo. La contraseña actual de este miembro deja de funcionar.'),
('it','mem_send_access_confirm','Viene creata una nuova password iniziale e inviata per e-mail. La password attuale di questo membro smette di funzionare.'),
('en','fl_access_sent','Access details sent.'),
('nl','fl_access_sent','Toegangsgegevens verstuurd.'),
('fr','fl_access_sent','Accès envoyés.'),
('es','fl_access_sent','Datos de acceso enviados.'),
('it','fl_access_sent','Dati di accesso inviati.'),
('en','fl_access_nomail','The mail did not go out. Start password:'),
('nl','fl_access_nomail','De mail is niet verstuurd. Startwachtwoord:'),
('fr','fl_access_nomail','L''e-mail n''est pas parti. Mot de passe initial :'),
('es','fl_access_nomail','El correo no salió. Contraseña inicial:'),
('it','fl_access_nomail','L''e-mail non è partita. Password iniziale:')
ON DUPLICATE KEY UPDATE value = value;
