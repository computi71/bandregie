-- Taking a gig along: setlist, sheet music, rider and patch list onto the
-- device, so the stage does not depend on a signal.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','off_stale','📴 No connection — this is the state from %1. Anything changed since is missing.'),
('en','off_take','Take this event along'),
('en','off_busy','fetching …'),
('en','off_done','%1 pages and files are on the device now.'),
('en','off_some','%1 fetched, %2 not — storage is probably tight.'),
('en','off_failed','That did not work. Try again where there is a signal.'),
('en','off_help','An event carries a button, "Take this event along": it fetches the setlist with its sheet music, the rider and the patch list onto the device. After that all of it is there without a signal — including what you never opened. Logging out clears it again.'),

('fr','off_stale','📴 Sans connexion — cet état date du %1. Ce qui a changé depuis manque.'),
('fr','off_take','Emporter cette date'),
('fr','off_busy','récupération …'),
('fr','off_done','%1 pages et fichiers sont maintenant sur l''appareil.'),
('fr','off_some','%1 récupérés, %2 non — la mémoire est probablement juste.'),
('fr','off_failed','Ça n''a pas marché. Réessaie là où il y a du réseau.'),
('fr','off_help','Sur une date, il y a un bouton « Emporter cette date » : il récupère la setlist avec ses partitions, le rider et la liste des entrées sur l''appareil. Ensuite, tout cela est là sans réseau — y compris ce que tu n''as jamais ouvert. À la déconnexion, tout est effacé.'),

('es','off_stale','📴 Sin conexión: este estado es del %1. Falta lo que haya cambiado desde entonces.'),
('es','off_take','Llevarse esta fecha'),
('es','off_busy','obteniendo …'),
('es','off_done','%1 páginas y archivos están ahora en el dispositivo.'),
('es','off_some','%1 obtenidos, %2 no: probablemente falte espacio.'),
('es','off_failed','No ha funcionado. Vuelve a intentarlo donde haya cobertura.'),
('es','off_help','En una fecha hay un botón, «Llevarse esta fecha»: trae al dispositivo la setlist con sus partituras, el rider y la lista de entradas. Después todo eso está ahí sin cobertura, incluso lo que nunca abriste. Al cerrar sesión se borra.'),

('nl','off_stale','📴 Geen verbinding — dit is de stand van %1. Wat daarna is veranderd ontbreekt.'),
('nl','off_take','Deze datum meenemen'),
('nl','off_busy','wordt gehaald …'),
('nl','off_done','%1 pagina''s en bestanden staan nu op het toestel.'),
('nl','off_some','%1 gehaald, %2 niet — de opslag is vermoedelijk krap.'),
('nl','off_failed','Dat lukte niet. Probeer het opnieuw waar bereik is.'),
('nl','off_help','Bij een datum staat een knop, "Deze datum meenemen": die haalt de setlist met de bladmuziek, de rider en de patchlijst naar het toestel. Daarna is dat er allemaal zonder bereik — ook wat je nooit hebt geopend. Bij het afmelden wordt het weer gewist.'),

('it','off_stale','📴 Senza connessione: questo è lo stato del %1. Manca ciò che è cambiato da allora.'),
('it','off_take','Porta con te questa data'),
('it','off_busy','recupero …'),
('it','off_done','%1 pagine e file sono ora sul dispositivo.'),
('it','off_some','%1 recuperati, %2 no: probabilmente lo spazio è poco.'),
('it','off_failed','Non ha funzionato. Riprova dove c''è segnale.'),
('it','off_help','Su una data c''è un pulsante, «Porta con te questa data»: porta sul dispositivo la scaletta con gli spartiti, il rider e la lista degli ingressi. Poi c''è tutto anche senza segnale, compreso quello che non hai mai aperto. Alla disconnessione viene cancellato.')
ON DUPLICATE KEY UPDATE value = value;
