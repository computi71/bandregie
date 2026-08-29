-- Angemeldet bleiben (#262)
INSERT INTO translations (lang, tkey, value) VALUES
('en','login_stay','Stay signed in'),
('en','login_stay_hint','Stay signed in on this device for 90 days. Without it the app signs itself out after a short while — and the number on the icon stops hearing about anything.'),
('nl','login_stay','Ingelogd blijven'),
('nl','login_stay_hint','Blijf 90 dagen ingelogd op dit apparaat. Zonder dit logt de app zichzelf na korte tijd uit — en het getal op het pictogram krijgt niets meer mee.'),
('fr','login_stay','Rester connecté'),
('fr','login_stay_hint','Rester connecté 90 jours sur cet appareil. Sans cela, l''application se déconnecte d''elle-même au bout d''un moment, et le nombre sur l''icône n''apprend plus rien.'),
('es','login_stay','Seguir conectado'),
('es','login_stay_hint','Sigue conectado en este dispositivo durante 90 días. Sin esto la aplicación se desconecta sola al poco tiempo, y el número del icono deja de enterarse de nada.'),
('it','login_stay','Resta connesso'),
('it','login_stay_hint','Resta connesso su questo dispositivo per 90 giorni. Senza, l''applicazione si disconnette da sola dopo poco e il numero sull''icona non viene più aggiornato.')
ON DUPLICATE KEY UPDATE value = value;
