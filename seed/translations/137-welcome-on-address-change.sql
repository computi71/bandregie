-- Willkommensmail, wenn die Adresse eines nie angemeldeten Kontos geändert wird (#290)
INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_member_updated_mail','Member updated — the access details went to the new address.'),
('en','fl_member_updated_nomail','Member updated. Mail could not be sent — please pass on this start password:'),
('nl','fl_member_updated_mail','Lid bijgewerkt — de toegangsgegevens zijn naar het nieuwe adres gestuurd.'),
('nl','fl_member_updated_nomail','Lid bijgewerkt. E-mail kon niet worden verzonden — geef dit startwachtwoord door:'),
('fr','fl_member_updated_mail','Membre mis à jour — les identifiants ont été envoyés à la nouvelle adresse.'),
('fr','fl_member_updated_nomail','Membre mis à jour. L''e-mail n''a pas pu partir — transmettez ce mot de passe initial :'),
('es','fl_member_updated_mail','Miembro actualizado: los datos de acceso se enviaron a la nueva dirección.'),
('es','fl_member_updated_nomail','Miembro actualizado. No se pudo enviar el correo; entrega esta contraseña inicial:'),
('it','fl_member_updated_mail','Membro aggiornato: i dati di accesso sono stati inviati al nuovo indirizzo.'),
('it','fl_member_updated_nomail','Membro aggiornato. Impossibile inviare l''e-mail: consegna questa password iniziale:')
ON DUPLICATE KEY UPDATE value = value;
