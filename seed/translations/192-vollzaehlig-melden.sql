-- Eine Meldung, wenn alle auf einen Termin geantwortet haben (#382).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_topic_vollzaehlig','When everyone has answered on an event of mine'),
('en','push_ev_complete','Everyone has answered: %1'),
('en','push_ev_complete_body','%1 in, %2 maybe, %3 out'),
('nl','push_topic_vollzaehlig','Als iedereen op een afspraak van mij heeft geantwoord'),
('nl','push_ev_complete','Iedereen heeft geantwoord: %1'),
('nl','push_ev_complete_body','%1 toegezegd, %2 misschien, %3 afgezegd'),
('fr','push_topic_vollzaehlig','Quand tout le monde a répondu à une de mes dates'),
('fr','push_ev_complete','Tout le monde a répondu : %1'),
('fr','push_ev_complete_body','%1 oui, %2 peut-être, %3 non'),
('es','push_topic_vollzaehlig','Cuando todos han respondido a una cita mía'),
('es','push_ev_complete','Todos han respondido: %1'),
('es','push_ev_complete_body','%1 sí, %2 quizá, %3 no'),
('it','push_topic_vollzaehlig','Quando tutti hanno risposto a un mio appuntamento'),
('it','push_ev_complete','Hanno risposto tutti: %1'),
('it','push_ev_complete_body','%1 sì, %2 forse, %3 no')
ON DUPLICATE KEY UPDATE value = value;
