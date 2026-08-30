-- Meldung, wenn in der Demo ein oeffentlich sichtbares Feld gesperrt ist (#269)
INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_demo_public_locked','Not possible in the demo: the band name, the contact address, the address of this installation and the legally required pages appear on a public address of this project. Everything else is yours to try.'),
('nl','fl_demo_public_locked','Niet mogelijk in de demo: de bandnaam, het contactadres, het adres van deze installatie en de wettelijk verplichte pagina''s staan op een openbaar adres van dit project. Al het overige mag je uitproberen.'),
('fr','fl_demo_public_locked','Impossible dans la démo : le nom du groupe, l''adresse de contact, l''adresse de cette installation et les pages légales obligatoires figurent sur une adresse publique de ce projet. Tout le reste est à votre disposition.'),
('es','fl_demo_public_locked','No es posible en la demo: el nombre de la banda, la dirección de contacto, la dirección de esta instalación y las páginas legales obligatorias aparecen en una dirección pública de este proyecto. Todo lo demás puedes probarlo.'),
('it','fl_demo_public_locked','Non possibile nella demo: il nome della band, l''indirizzo di contatto, l''indirizzo di questa installazione e le pagine obbligatorie per legge compaiono su un indirizzo pubblico di questo progetto. Tutto il resto puoi provarlo.')
ON DUPLICATE KEY UPDATE value = value;
