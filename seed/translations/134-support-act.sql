-- Supportact als eigenes Feld am Termin (#287)
INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_support','Support act'),
('en','ev_support_ph','Opening band — or the headliner when you are supporting'),
('nl','ev_support','Voorprogramma'),
('nl','ev_support_ph','Voorband — of de hoofdact als jullie zelf support zijn'),
('fr','ev_support','Première partie'),
('fr','ev_support_ph','Groupe en première partie — ou la tête d''affiche si c''est vous qui ouvrez'),
('es','ev_support','Telonero'),
('es','ev_support_ph','Banda telonera — o el cabeza de cartel si los teloneros sois vosotros'),
('it','ev_support','Supporto'),
('it','ev_support_ph','Gruppo spalla — o l''headliner se la spalla siete voi')
ON DUPLICATE KEY UPDATE value = value;
