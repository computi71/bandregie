-- Translations for the installable app (PWA).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','app_description','The band''s events, setlists and tech — on the road too.'),
('en','app_install','Install it on your phone'),
('en','app_install_hint','Choose "Add to home screen" in the browser menu. Bandroadie then starts like an app, and events, setlists and songs are there even without a signal.'),
('fr','app_description','Les dates, setlists et la technique du groupe — même en route.'),
('fr','app_install','L''installer sur le téléphone'),
('fr','app_install_hint','Choisis « Ajouter à l''écran d''accueil » dans le menu du navigateur. Bandroadie démarre alors comme une app, et les dates, setlists et morceaux sont là même sans réseau.'),
('es','app_description','Las fechas, setlists y la técnica del grupo, también de camino.'),
('es','app_install','Instalar en el móvil'),
('es','app_install_hint','Elige «Añadir a la pantalla de inicio» en el menú del navegador. Bandroadie arranca entonces como una app, y las fechas, setlists y canciones están ahí aunque no haya cobertura.'),
('nl','app_description','De data, setlists en techniek van de band — ook onderweg.'),
('nl','app_install','Op je telefoon installeren'),
('nl','app_install_hint','Kies "Toevoegen aan beginscherm" in het browsermenu. Bandroadie start dan als een app, en data, setlists en nummers zijn er ook zonder bereik.'),
('it','app_description','Date, scalette e tecnica della band — anche in viaggio.'),
('it','app_install','Installala sul telefono'),
('it','app_install_hint','Scegli «Aggiungi alla schermata Home» nel menu del browser. Bandroadie parte come un''app e date, scalette e brani ci sono anche senza segnale.')
ON DUPLICATE KEY UPDATE value = value;
