-- Schema auf Wunsch erneut prüfen (#328)
INSERT INTO translations (lang, tkey, value) VALUES
('en','set_schema_recheck','Check the schema'),
('en','set_schema_recheck_hint','Walks every table and column again on the next page load. After an update this happens once by itself; by hand it is only needed when somebody worked directly in the database.'),
('en','fl_schema_recheck','The schema will be checked on the next page load.'),
('nl','set_schema_recheck','Schema controleren'),
('nl','set_schema_recheck_hint','Loopt bij de volgende paginaweergave alle tabellen en kolommen opnieuw na. Na een update gebeurt dat één keer van zelf; met de hand is het alleen nodig als iemand direct in de database heeft gewerkt.'),
('nl','fl_schema_recheck','Het schema wordt bij de volgende paginaweergave gecontroleerd.'),
('fr','set_schema_recheck','Vérifier le schéma'),
('fr','set_schema_recheck_hint','Repasse toutes les tables et colonnes au prochain chargement. Après une mise à jour cela se fait une fois tout seul ; à la main, ce n''est utile que si quelqu''un a travaillé directement dans la base.'),
('fr','fl_schema_recheck','Le schéma sera vérifié au prochain chargement de page.'),
('es','set_schema_recheck','Comprobar el esquema'),
('es','set_schema_recheck_hint','Repasa todas las tablas y columnas en la siguiente carga. Tras una actualización ocurre una vez por sí solo; a mano solo hace falta si alguien trabajó directamente en la base de datos.'),
('es','fl_schema_recheck','El esquema se comprobará en la siguiente carga de página.'),
('it','set_schema_recheck','Controlla lo schema'),
('it','set_schema_recheck_hint','Ripassa tutte le tabelle e le colonne al prossimo caricamento. Dopo un aggiornamento avviene una volta da sé; a mano serve solo se qualcuno ha lavorato direttamente nel database.'),
('it','fl_schema_recheck','Lo schema verrà controllato al prossimo caricamento di pagina.')
ON DUPLICATE KEY UPDATE value = value;
