-- Adresssuche: Hinweis ohne Treffer und "gesucht als" (#234)
--
-- Der Hinweis nannte Strasse und Ort, weil im Freitext gesucht wurde. Seit #249
-- fragt die Suche feldweise, mit PLZ als eigenem Feld — also sagt der Hinweis das
-- auch. Weil der frueheste Seed gewinnt, muss der alte Wortlaut einmal weg, und
-- die Wache steht hier: in der Datei, die den neuen Wert selbst setzt.
DELETE FROM translations WHERE tkey = 'geo_none_hint'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'geo_none_hint_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('geo_none_hint_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;
INSERT INTO translations (lang, tkey, value) VALUES
('en','geo_none_hint','No hits. The search asks with street, postcode and town — a venue name is rarely in OpenStreetMap. An exact street with house number helps most.'),
('en','geo_searched_as','searched as: %1'),
('nl','geo_none_hint','Geen resultaat. Er wordt gezocht met straat, postcode en plaats — een zaalnaam staat zelden in OpenStreetMap. Een exacte straat met huisnummer helpt het meest.'),
('nl','geo_searched_as','gezocht als: %1'),
('fr','geo_none_hint','Aucun résultat. La recherche utilise la rue, le code postal et la ville — un nom de salle figure rarement dans OpenStreetMap. Une rue exacte avec numéro aide le plus.'),
('fr','geo_searched_as','recherché comme : %1'),
('es','geo_none_hint','Sin resultados. Se busca con calle, codigo postal y localidad: un nombre de sala casi nunca esta en OpenStreetMap. Lo que mas ayuda es una calle exacta con numero.'),
('es','geo_searched_as','buscado como: %1'),
('it','geo_none_hint','Nessun risultato. Si cerca con via, CAP e comune: un nome di sala e raramente in OpenStreetMap. Aiuta di piu una via esatta con numero civico.'),
('it','geo_searched_as','cercato come: %1')
ON DUPLICATE KEY UPDATE value = value;
