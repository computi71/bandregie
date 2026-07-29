-- A public demo publishes its own credentials, so anything a later visitor
-- cannot undo has to be refused: passwords, accounts, outgoing mail. These are
-- the strings that say so.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_demo_locked','Not possible in the demo: the logins are public, and changing them locks everybody else out. Everything else is yours to try.'),
('en','demo_locked_hint','Locked in the demo — the logins are public and shared by every visitor at the same time.'),
('en','demo_badge','Demo'),

('nl','fl_demo_locked','Niet mogelijk in de demo: de inloggegevens zijn openbaar, en wie ze wijzigt sluit alle anderen buiten. Al het overige mag je uitproberen.'),
('nl','demo_locked_hint','Geblokkeerd in de demo — de inloggegevens zijn openbaar en gelden tegelijk voor elke bezoeker.'),
('nl','demo_badge','Demo'),

('fr','fl_demo_locked','Impossible dans la démo : les identifiants sont publics, et les changer exclut tous les autres. Tout le reste est à essayer.'),
('fr','demo_locked_hint','Verrouillé dans la démo — les identifiants sont publics et valent pour tous les visiteurs en même temps.'),
('fr','demo_badge','Démo'),

('es','fl_demo_locked','No es posible en la demo: los datos de acceso son públicos, y quien los cambia deja fuera a todos los demás. Todo lo demás puedes probarlo.'),
('es','demo_locked_hint','Bloqueado en la demo — los datos de acceso son públicos y valen a la vez para todos los visitantes.'),
('es','demo_badge','Demo'),

('it','fl_demo_locked','Non è possibile nella demo: le credenziali sono pubbliche e chi le cambia esclude tutti gli altri. Tutto il resto puoi provarlo.'),
('it','demo_locked_hint','Bloccato nella demo — le credenziali sono pubbliche e valgono per tutti i visitatori nello stesso momento.'),
('it','demo_badge','Demo')
ON DUPLICATE KEY UPDATE value = value;
