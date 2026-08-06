-- Serien abschaltbar (#212)
INSERT INTO translations (lang, tkey, value) VALUES
('en','set_stacks','Bursts: pictures from the same source taken close together share one tile'),
('en','set_stacks_hint','Off by default: the gallery shows every picture on its own, ordered by year, gig and photographer. Switched on, pictures from the same source taken close together share one tile - switching on regroups everything at once.'),
('nl','set_stacks','Reeksen: foto''s uit dezelfde bron die kort na elkaar zijn gemaakt, delen een tegel'),
('nl','set_stacks_hint','Standaard uit: de galerij toont elke foto apart, geordend op jaar, optreden en fotograaf. Ingeschakeld delen foto''s uit dezelfde bron die kort na elkaar zijn gemaakt een tegel - bij het inschakelen groepeert alles zich meteen.'),
('fr','set_stacks','Séries : les photos d''une même source prises à peu d''intervalle partagent une tuile'),
('fr','set_stacks_hint','Désactivé par défaut : la galerie montre chaque photo séparément, ordonnée par année, concert et photographe. Activé, les photos d''une même source prises à peu d''intervalle partagent une tuile - l''activation regroupe tout aussitôt.'),
('es','set_stacks','Series: las fotos de la misma fuente tomadas con poco intervalo comparten una tesela'),
('es','set_stacks_hint','Desactivado de forma predeterminada: la galería muestra cada foto por separado, ordenada por año, concierto y fotógrafo. Activado, las fotos de la misma fuente tomadas con poco intervalo comparten una tesela - al activarlo todo se reagrupa al instante.'),
('it','set_stacks','Serie: le foto della stessa fonte scattate a breve distanza condividono una tessera'),
('it','set_stacks_hint','Disattivato per impostazione predefinita: la galleria mostra ogni foto da sola, ordinata per anno, concerto e fotografo. Attivato, le foto della stessa fonte scattate a breve distanza condividono una tessera - all''attivazione tutto si raggruppa subito.')
ON DUPLICATE KEY UPDATE value = value;
