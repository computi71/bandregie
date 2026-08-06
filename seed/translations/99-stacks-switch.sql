-- Serien abschaltbar (#212)
INSERT INTO translations (lang, tkey, value) VALUES
('en','set_stacks','Bursts: pictures from the same source taken close together share one tile'),
('en','set_stacks_hint','Switched off, the gallery shows every picture on its own. The bursts are not lost - switching back on regroups everything.'),
('nl','set_stacks','Reeksen: foto''s uit dezelfde bron die kort na elkaar zijn gemaakt, delen een tegel'),
('nl','set_stacks_hint','Uitgeschakeld toont de galerij elke foto apart. De reeksen gaan niet verloren - bij het weer inschakelen groepeert alles zich opnieuw.'),
('fr','set_stacks','Séries : les photos d''une même source prises à peu d''intervalle partagent une tuile'),
('fr','set_stacks_hint','Désactivé, la galerie montre chaque photo séparément. Les séries ne sont pas perdues - à la réactivation, tout se regroupe.'),
('es','set_stacks','Series: las fotos de la misma fuente tomadas con poco intervalo comparten una tesela'),
('es','set_stacks_hint','Desactivado, la galería muestra cada foto por separado. Las series no se pierden - al reactivarlo todo se reagrupa.'),
('it','set_stacks','Serie: le foto della stessa fonte scattate a breve distanza condividono una tessera'),
('it','set_stacks_hint','Disattivato, la galleria mostra ogni foto da sola. Le serie non vanno perse - alla riattivazione tutto si raggruppa di nuovo.')
ON DUPLICATE KEY UPDATE value = value;
