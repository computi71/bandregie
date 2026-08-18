-- Adresssuche: Hinweis ohne Treffer und "gesucht als" (#234)
INSERT INTO translations (lang, tkey, value) VALUES
('en','geo_none_hint','No hits. OpenStreetMap knows addresses but hardly any venue names — a street and a town will find something.'),
('en','geo_searched_as','searched as: %1'),
('nl','geo_none_hint','Geen resultaat. OpenStreetMap kent adressen, maar bijna geen zaalnamen — met een straat en een plaats vindt het wel iets.'),
('nl','geo_searched_as','gezocht als: %1'),
('fr','geo_none_hint','Aucun résultat. OpenStreetMap connaît les adresses, mais presque aucun nom de salle — une rue et une ville donneront un résultat.'),
('fr','geo_searched_as','recherché comme : %1'),
('es','geo_none_hint','Sin resultados. OpenStreetMap conoce direcciones, pero casi ningun nombre de sala: con una calle y una localidad si encuentra algo.'),
('es','geo_searched_as','buscado como: %1'),
('it','geo_none_hint','Nessun risultato. OpenStreetMap conosce gli indirizzi, ma quasi nessun nome di sala: con una via e un comune trova qualcosa.'),
('it','geo_searched_as','cercato come: %1')
ON DUPLICATE KEY UPDATE value = value;
