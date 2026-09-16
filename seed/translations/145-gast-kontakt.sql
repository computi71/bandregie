-- Gäste: E-Mail oder Telefon Pflicht; Einladung per WhatsApp/SMS vom eigenen Handy (#299)
INSERT INTO translations (lang, tkey, value) VALUES
('en','guest_contact_hint','Email address or phone number — one of the two is needed, otherwise the invitation reaches nobody.'),
('en','fl_guest_contact_required','An email address or a phone number is needed — otherwise the invitation reaches nobody.'),
('en','guest_send_whatsapp','by WhatsApp'),('en','guest_send_sms','by SMS'),
('en','guest_share_msg','Hi %1$s, %2$s is asking you for %3$s (%4$s). Please accept or decline here: %5$s'),
('nl','guest_contact_hint','E-mailadres of telefoonnummer — een van beide is nodig, anders bereikt de uitnodiging niemand.'),
('nl','fl_guest_contact_required','Een e-mailadres of een telefoonnummer is nodig — anders bereikt de uitnodiging niemand.'),
('nl','guest_send_whatsapp','via WhatsApp'),('nl','guest_send_sms','via sms'),
('nl','guest_share_msg','Hallo %1$s, %2$s vraagt je voor %3$s (%4$s). Zeg hier toe of af: %5$s'),
('fr','guest_contact_hint','Adresse e-mail ou numéro de téléphone — il en faut un des deux, sinon l''invitation n''atteint personne.'),
('fr','fl_guest_contact_required','Il faut une adresse e-mail ou un numéro de téléphone — sinon l''invitation n''atteint personne.'),
('fr','guest_send_whatsapp','par WhatsApp'),('fr','guest_send_sms','par SMS'),
('fr','guest_share_msg','Bonjour %1$s, %2$s te sollicite pour le %3$s (%4$s). Accepte ou décline ici : %5$s'),
('es','guest_contact_hint','Correo o número de teléfono: hace falta uno de los dos, si no la invitación no llega a nadie.'),
('es','fl_guest_contact_required','Hace falta un correo o un número de teléfono; si no, la invitación no llega a nadie.'),
('es','guest_send_whatsapp','por WhatsApp'),('es','guest_send_sms','por SMS'),
('es','guest_share_msg','Hola %1$s, %2$s te solicita para el %3$s (%4$s). Confirma o rechaza aquí: %5$s'),
('it','guest_contact_hint','Indirizzo e-mail o numero di telefono: ne serve uno dei due, altrimenti l''invito non raggiunge nessuno.'),
('it','fl_guest_contact_required','Serve un indirizzo e-mail o un numero di telefono, altrimenti l''invito non raggiunge nessuno.'),
('it','guest_send_whatsapp','via WhatsApp'),('it','guest_send_sms','via SMS'),
('it','guest_share_msg','Ciao %1$s, %2$s ti chiede per il %3$s (%4$s). Conferma o rifiuta qui: %5$s')
ON DUPLICATE KEY UPDATE value = value;
