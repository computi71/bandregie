-- Translations for song ratings and per-event PA/lighting sources.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prod_pa','PA'),('en','prod_light','Lighting'),
('en','prod_eigene','Own equipment'),('en','prod_leih','Rented'),('en','prod_vorhanden','Available on site'),
('en','prod_none','not set'),('en','prod_hint','Quotes and invoices go to the event as file attachments.'),
('en','rate_votes','votes'),('en','rate_none','not rated yet'),('en','rate_clear','Withdraw rating'),
('en','rate_hint','How much do you enjoy playing it? Only the average is visible to everyone.'),
('en','songs_col_rating','Rating'),
('nl','prod_pa','PA'),('nl','prod_light','Licht'),
('nl','prod_eigene','Eigen materiaal'),('nl','prod_leih','Gehuurd'),('nl','prod_vorhanden','Ter plaatse aanwezig'),
('nl','prod_none','niet ingesteld'),('nl','prod_hint','Offertes en facturen komen als bestand bij de afspraak.'),
('nl','rate_votes','stemmen'),('nl','rate_none','nog niet beoordeeld'),('nl','rate_clear','Beoordeling intrekken'),
('nl','rate_hint','Hoe graag spelen jullie het? Alleen het gemiddelde is voor iedereen zichtbaar.'),
('nl','songs_col_rating','Beoordeling'),
('fr','prod_pa','Sono'),('fr','prod_light','Lumière'),
('fr','prod_eigene','Matériel propre'),('fr','prod_leih','Loué'),('fr','prod_vorhanden','Disponible sur place'),
('fr','prod_none','non défini'),('fr','prod_hint','Devis et factures sont joints à l''événement comme fichiers.'),
('fr','rate_votes','votes'),('fr','rate_none','pas encore noté'),('fr','rate_clear','Retirer la note'),
('fr','rate_hint','Aimez-vous la jouer ? Seule la moyenne est visible par tous.'),
('fr','songs_col_rating','Note'),
('es','prod_pa','PA'),('es','prod_light','Luces'),
('es','prod_eigene','Equipo propio'),('es','prod_leih','Alquilado'),('es','prod_vorhanden','Disponible en el lugar'),
('es','prod_none','sin definir'),('es','prod_hint','Presupuestos y facturas se adjuntan al evento como archivos.'),
('es','rate_votes','votos'),('es','rate_none','sin valorar'),('es','rate_clear','Retirar valoración'),
('es','rate_hint','¿Cuánto os gusta tocarla? Solo la media es visible para todos.'),
('es','songs_col_rating','Valoración'),
('it','prod_pa','PA'),('it','prod_light','Luci'),
('it','prod_eigene','Attrezzatura propria'),('it','prod_leih','Noleggiata'),('it','prod_vorhanden','Disponibile sul posto'),
('it','prod_none','non definito'),('it','prod_hint','Preventivi e fatture si allegano all''evento come file.'),
('it','rate_votes','voti'),('it','rate_none','non ancora valutato'),('it','rate_clear','Ritira la valutazione'),
('it','rate_hint','Quanto vi piace suonarlo? Solo la media è visibile a tutti.'),
('it','songs_col_rating','Valutazione')
ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','rate_vote','vote'),('en','ev_export','Spreadsheet'),
('nl','rate_vote','stem'),('nl','ev_export','Spreadsheet'),
('fr','rate_vote','vote'),('fr','ev_export','Tableur'),
('es','rate_vote','voto'),('es','ev_export','Hoja de cálculo'),
('it','rate_vote','voto'),('it','ev_export','Foglio di calcolo')
ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','role_ersatz','Substitute'),('en','mem_first_name','First name'),('en','mem_last_name','Last name'),
('en','mem_mobile','Mobile'),('en','mem_substitute_for','Substitute for'),('en','mem_substitute_none','– nobody –'),
('en','mem_instrument_pick','pick from the equipment inventory'),('en','mem_instrument_free','or type freely'),
('en','ev_substitute_hint','Ask the substitute:'),
('nl','role_ersatz','Invaller'),('nl','mem_first_name','Voornaam'),('nl','mem_last_name','Achternaam'),
('nl','mem_mobile','Mobiel'),('nl','mem_substitute_for','Invaller voor'),('nl','mem_substitute_none','– niemand –'),
('nl','mem_instrument_pick','kies uit de apparatuurlijst'),('nl','mem_instrument_free','of vrij invullen'),
('nl','ev_substitute_hint','Vraag de invaller:'),
('fr','role_ersatz','Remplaçant'),('fr','mem_first_name','Prénom'),('fr','mem_last_name','Nom'),
('fr','mem_mobile','Portable'),('fr','mem_substitute_for','Remplaçant de'),('fr','mem_substitute_none','– personne –'),
('fr','mem_instrument_pick','choisir dans l''inventaire'),('fr','mem_instrument_free','ou saisir librement'),
('fr','ev_substitute_hint','Demander au remplaçant :'),
('es','role_ersatz','Suplente'),('es','mem_first_name','Nombre'),('es','mem_last_name','Apellido'),
('es','mem_mobile','Móvil'),('es','mem_substitute_for','Suplente de'),('es','mem_substitute_none','– nadie –'),
('es','mem_instrument_pick','elegir del inventario'),('es','mem_instrument_free','o escribir libremente'),
('es','ev_substitute_hint','Preguntar al suplente:'),
('it','role_ersatz','Sostituto'),('it','mem_first_name','Nome'),('it','mem_last_name','Cognome'),
('it','mem_mobile','Cellulare'),('it','mem_substitute_for','Sostituto di'),('it','mem_substitute_none','– nessuno –'),
('it','mem_instrument_pick','scegli dall''inventario'),('it','mem_instrument_free','oppure scrivi liberamente'),
('it','ev_substitute_hint','Chiedi al sostituto:')
ON DUPLICATE KEY UPDATE value = value;
