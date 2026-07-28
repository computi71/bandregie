-- Translations for the fixed site address setting.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_site_url','Fixed address of this installation'),
('en','set_site_url_hint','Used for links in emails and in the calendar. Leave empty to take it from the request — filling it in is safer.'),
('fr','set_site_url','Adresse fixe de cette installation'),
('fr','set_site_url_hint','Utilisée pour les liens dans les e-mails et le calendrier. Vide, elle est prise dans la requête — la renseigner est plus sûr.'),
('es','set_site_url','Dirección fija de esta instalación'),
('es','set_site_url_hint','Se usa para los enlaces en los correos y en el calendario. Vacía se toma de la petición; rellenarla es más seguro.'),
('nl','set_site_url','Vast adres van deze installatie'),
('nl','set_site_url_hint','Wordt gebruikt voor links in e-mails en in de agenda. Leeg betekent: uit het verzoek overnemen — invullen is veiliger.'),
('it','set_site_url','Indirizzo fisso di questa installazione'),
('it','set_site_url_hint','Usato per i link nelle e-mail e nel calendario. Vuoto viene preso dalla richiesta: indicarlo è più sicuro.')
ON DUPLICATE KEY UPDATE value = value;
