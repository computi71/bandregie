-- Mitteilung, wenn sich der Stand eines Termins ändert (#324)
INSERT INTO translations (lang, tkey, value) VALUES
('en','push_ev_status','Event changed'),
('nl','push_ev_status','Afspraak gewijzigd'),
('fr','push_ev_status','Date modifiée'),
('es','push_ev_status','Evento modificado'),
('it','push_ev_status','Data modificata')
ON DUPLICATE KEY UPDATE value = value;
