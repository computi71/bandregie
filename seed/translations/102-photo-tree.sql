-- Baum aus Jahr, Termin und Fotograf (#216)
INSERT INTO translations (lang, tkey, value) VALUES
('en','photo_source_none','Without an origin folder'),
('en','photo_tree_hint','Year, gig, photographer - the shape of the linked folder.'),
('nl','photo_source_none','Zonder herkomstmap'),
('nl','photo_tree_hint','Jaar, optreden, fotograaf - de vorm van de gekoppelde map.'),
('fr','photo_source_none','Sans dossier d''origine'),
('fr','photo_tree_hint','Annee, concert, photographe - la forme du dossier lie.'),
('es','photo_source_none','Sin carpeta de origen'),
('es','photo_tree_hint','Ano, concierto, fotografo - la forma de la carpeta vinculada.'),
('it','photo_source_none','Senza cartella di origine'),
('it','photo_tree_hint','Anno, concerto, fotografo - la forma della cartella collegata.')
ON DUPLICATE KEY UPDATE value = value;
