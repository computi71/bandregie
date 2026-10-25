-- Erinnerung nach einer Absage ohne Vorschlag (#403).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_topic_erinnerung','Reminder when I declined and proposed nothing'),
('en','push_remind_alt','Would you suggest another date?'),
('en','push_remind_alt_body','You could not make “%1” on %2 — and so far nobody has put in an alternative.'),
('nl','push_topic_erinnerung','Herinnering als ik heb afgezegd en niets heb voorgesteld'),
('nl','push_remind_alt','Wil je een andere datum voorstellen?'),
('nl','push_remind_alt_body','Je kon niet bij „%1” op %2 — en tot nu toe heeft niemand een alternatief ingevoerd.'),
('fr','push_topic_erinnerung','Rappel si j’ai décliné sans rien proposer'),
('fr','push_remind_alt','Voulez-vous proposer une autre date ?'),
('fr','push_remind_alt_body','Vous ne pouviez pas pour « %1 » le %2 — et jusqu’ici personne n’a proposé d’alternative.'),
('es','push_topic_erinnerung','Recordatorio si he rechazado y no he propuesto nada'),
('es','push_remind_alt','¿Quieres proponer otra fecha?'),
('es','push_remind_alt_body','No podías en «%1» el %2 — y hasta ahora nadie ha puesto una alternativa.'),
('it','push_topic_erinnerung','Promemoria se ho rifiutato senza proporre nulla'),
('it','push_remind_alt','Vuoi proporre un’altra data?'),
('it','push_remind_alt_body','Non potevi per «%1» il %2 — e finora nessuno ha inserito un’alternativa.')
ON DUPLICATE KEY UPDATE value = value;
