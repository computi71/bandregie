-- For a musician the member comes second in the row, in place of a typed name;
-- a name field is left only for a guest without an account (#187).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','stage_guest','Name (guest without an account)'),
('en','stage_label_opt','not needed with a member'),

('nl','stage_guest','Naam (gast zonder account)'),
('nl','stage_label_opt','niet nodig bij een lid'),

('fr','stage_guest','Nom (invité sans compte)'),
('fr','stage_label_opt','inutile avec un membre'),

('es','stage_guest','Nombre (invitado sin cuenta)'),
('es','stage_label_opt','no hace falta con un miembro'),

('it','stage_guest','Nome (ospite senza account)'),
('it','stage_label_opt','non serve con un membro')
ON DUPLICATE KEY UPDATE value = value;
