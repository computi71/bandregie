-- Sprung an die erste ungelesene Stelle (#318), Absagen am Ort einklappen (#319)
INSERT INTO translations (lang, tkey, value) VALUES
('en','topic_new_from_here','New from here'),
('en','venues_events_cancelled','Cancelled events'),
('nl','topic_new_from_here','Vanaf hier nieuw'),
('nl','venues_events_cancelled','Afgezegde afspraken'),
('fr','topic_new_from_here','Nouveau à partir d''ici'),
('fr','venues_events_cancelled','Dates annulées'),
('es','topic_new_from_here','Nuevo a partir de aquí'),
('es','venues_events_cancelled','Eventos cancelados'),
('it','topic_new_from_here','Nuovo da qui'),
('it','venues_events_cancelled','Date annullate')
ON DUPLICATE KEY UPDATE value = value;
