-- Clicking the version in the footer asks whether a newer one exists.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','up_current','You are on the latest version.'),
('en','up_checking','Asking …'),
('en','up_failed','Could not ask — the server may have no way out to the internet right now.'),
('fr','up_current','Vous êtes à jour.'),
('fr','up_checking','Demande en cours …'),
('fr','up_failed','La demande a échoué — le serveur n''a peut-être pas accès à Internet.'),
('es','up_current','Estáis en la última versión.'),
('es','up_checking','Consultando …'),
('es','up_failed','No se pudo consultar: puede que el servidor no tenga salida a internet.'),
('nl','up_current','Jullie zijn bij.'),
('nl','up_checking','Bezig met opvragen …'),
('nl','up_failed','Opvragen lukte niet — misschien kan de server er even niet uit.'),
('it','up_current','Siete aggiornati.'),
('it','up_checking','Sto chiedendo …'),
('it','up_failed','Non è riuscito — forse il server non raggiunge internet.')
ON DUPLICATE KEY UPDATE value = value;
