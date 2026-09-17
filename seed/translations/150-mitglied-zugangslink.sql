-- Zugangslink per WhatsApp oder Kurznachricht für Mitglieder ohne Mailadresse (#307)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_share_title','Access without email'),
('en','mem_share_hint','This member has no email address. Send them the access link from your own phone. It is valid for one hour and can be used once.'),
('en','mem_share_msg','Hi %1$s, your access to the band area of %2$s: %3$s — the link is valid for one hour, after that just ask again.'),
('en','mem_share_none','A mobile number is missing for that.'),
('nl','mem_share_title','Toegang zonder e-mail'),
('nl','mem_share_hint','Dit lid heeft geen e-mailadres. Stuur de toegangslink vanaf je eigen telefoon. Hij geldt een uur en precies één keer.'),
('nl','mem_share_msg','Hallo %1$s, jouw toegang tot het bandgedeelte van %2$s: %3$s — de link geldt een uur, daarna vraag je gewoon opnieuw.'),
('nl','mem_share_none','Daarvoor ontbreekt het mobiele nummer.'),
('fr','mem_share_title','Accès sans e-mail'),
('fr','mem_share_hint','Ce membre n''a pas d''adresse e-mail. Envoyez-lui le lien d''accès depuis votre propre téléphone. Il vaut une heure et une seule fois.'),
('fr','mem_share_msg','Bonjour %1$s, ton accès à l’espace du groupe %2$s : %3$s — le lien vaut une heure, ensuite redemande simplement.'),
('fr','mem_share_none','Il manque le numéro de mobile pour cela.'),
('es','mem_share_title','Acceso sin correo'),
('es','mem_share_hint','Este miembro no tiene correo. Envíale el enlace de acceso desde tu propio móvil. Vale una hora y una sola vez.'),
('es','mem_share_msg','Hola %1$s, tu acceso al área del grupo %2$s: %3$s — el enlace vale una hora, después vuelve a pedirlo.'),
('es','mem_share_none','Para eso falta el número de móvil.'),
('it','mem_share_title','Accesso senza e-mail'),
('it','mem_share_hint','Questo membro non ha un indirizzo e-mail. Mandagli il link di accesso dal tuo telefono. Vale un’ora e una volta sola.'),
('it','mem_share_msg','Ciao %1$s, il tuo accesso all’area della band %2$s: %3$s — il link vale un’ora, poi richiedilo di nuovo.'),
('it','mem_share_none','Per questo manca il numero di cellulare.')
ON DUPLICATE KEY UPDATE value = value;
