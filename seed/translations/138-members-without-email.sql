-- Mitglieder ohne E-Mail-Adresse (#291)
INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_first_name_required','The first name is required.'),
('nl','fl_first_name_required','De voornaam is verplicht.'),
('fr','fl_first_name_required','Le prénom est obligatoire.'),
('es','fl_first_name_required','El nombre es obligatorio.'),
('it','fl_first_name_required','Il nome è obbligatorio.'),
('en','fl_member_created_noaccess','Member created — without an email address there is no access yet. As soon as the address is entered under "edit", the invitation goes out.'),
('en','fl_email_keep','An existing email address can be changed but not cleared — the member would be locked out.'),
('en','mem_no_email','No access — email address missing. Enter it and the invitation goes out.'),
('nl','fl_member_created_noaccess','Lid aangemaakt — zonder e-mailadres nog geen toegang. Zodra het adres onder „bewerken" staat, gaat de uitnodiging eruit.'),
('nl','fl_email_keep','Een bestaand e-mailadres kan worden gewijzigd, maar niet leeggemaakt — het lid zou buitengesloten zijn.'),
('nl','mem_no_email','Geen toegang — e-mailadres ontbreekt. Invullen, dan gaat de uitnodiging eruit.'),
('fr','fl_member_created_noaccess','Membre créé — sans adresse e-mail, pas encore d''accès. Dès que l''adresse est saisie sous « modifier », l''invitation part.'),
('fr','fl_email_keep','Une adresse e-mail existante peut être modifiée mais pas effacée — le membre serait bloqué.'),
('fr','mem_no_email','Pas d''accès — adresse e-mail manquante. Saisissez-la et l''invitation part.'),
('es','fl_member_created_noaccess','Miembro creado; sin dirección de correo aún no tiene acceso. En cuanto la dirección esté en «editar», sale la invitación.'),
('es','fl_email_keep','Una dirección de correo existente se puede cambiar, pero no vaciar; el miembro quedaría fuera.'),
('es','mem_no_email','Sin acceso: falta la dirección de correo. Introdúcela y sale la invitación.'),
('it','fl_member_created_noaccess','Membro creato: senza indirizzo e-mail non ha ancora accesso. Appena l''indirizzo è inserito in «modifica», parte l''invito.'),
('it','fl_email_keep','Un indirizzo e-mail esistente si può cambiare ma non cancellare: il membro resterebbe chiuso fuori.'),
('it','mem_no_email','Nessun accesso: manca l''indirizzo e-mail. Inseriscilo e parte l''invito.')
ON DUPLICATE KEY UPDATE value = value;
