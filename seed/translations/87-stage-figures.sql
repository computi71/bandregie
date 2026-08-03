-- The figure labels never had translations, and an empty choice now means "the
-- profile photo if there is one" — so an explicit neutral needs its own name (#187).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','stage_figure_auto','Photo, if there is one'),
('en','stage_figure_neutral','Neutral'),
('en','stage_figure_w','Female'),
('en','stage_figure_m','Male'),
('en','stage_figure_avatar','My photo'),

('nl','stage_figure_auto','Foto, als er een is'),
('nl','stage_figure_neutral','Neutraal'),
('nl','stage_figure_w','Vrouwelijk'),
('nl','stage_figure_m','Mannelijk'),
('nl','stage_figure_avatar','Mijn foto'),

('fr','stage_figure_auto','Photo, s''il y en a une'),
('fr','stage_figure_neutral','Neutre'),
('fr','stage_figure_w','Féminine'),
('fr','stage_figure_m','Masculine'),
('fr','stage_figure_avatar','Ma photo'),

('es','stage_figure_auto','Foto, si hay una'),
('es','stage_figure_neutral','Neutra'),
('es','stage_figure_w','Femenina'),
('es','stage_figure_m','Masculina'),
('es','stage_figure_avatar','Mi foto'),

('it','stage_figure_auto','Foto, se c''è'),
('it','stage_figure_neutral','Neutra'),
('it','stage_figure_w','Femminile'),
('it','stage_figure_m','Maschile'),
('it','stage_figure_avatar','La mia foto')
ON DUPLICATE KEY UPDATE value = value;
