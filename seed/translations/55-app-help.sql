-- What the phone app is, and what it is not: an installable web app, no store,
-- offline pages, no notifications yet.
SET NAMES utf8mb4;

-- Der alte Hinweis nannte nur das Browsermenue und die Offline-Seiten; beides
-- steht jetzt ausfuehrlicher und getrennt. Seeds ueberschreiben nie, deshalb
-- hier ausdruecklich weg — und nur genau der alte Wortlaut.
DELETE FROM translations WHERE tkey = 'app_install_hint' AND value IN (
  'Choose "Add to home screen" in the browser menu. Bandregie then starts like an app, and events, setlists and songs are there even without a signal.',
  'Choisis « Ajouter à l''écran d''accueil » dans le menu du navigateur. Bandregie démarre alors comme une app, et les dates, setlists et morceaux sont là même sans réseau.',
  'Elige «Añadir a la pantalla de inicio» en el menú del navegador. Bandregie arranca entonces como una app, y las fechas, setlists y canciones están ahí aunque no haya cobertura.',
  'Kies "Toevoegen aan beginscherm" in het browsermenu. Bandregie start dan als een app, en data, setlists en nummers zijn er ook zonder bereik.',
  'Scegli «Aggiungi alla schermata Home» nel menu del browser. Bandregie parte come un''app e date, scalette e brani ci sono anche senza segnale.'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','app_install_hint','On an iPhone use the share icon, on Android the browser menu: "Add to home screen". Bandregie then has an icon of its own and starts without an address bar.'),
('en','app_install_offline','The dashboard, events, setlists and songs stay on the device and are there without a signal — on stage there often is none. Logging out clears them, so nobody finds the previous user''s events on a shared phone.'),
('en','app_install_store','There is no app in the App Store or on Google Play. Going through the browser does the same job — without a yearly fee, without a review for every change, and without a second build of the program to keep up.'),
('en','app_install_push','Notifications to your device live in your profile: pick what you want to hear about — new events, new comments, confirmations and cancellations — and switch them on per device. On iPhone that only works for the installed app.'),

('fr','app_install_hint','Sur iPhone par l''icône de partage, sur Android par le menu du navigateur : « Ajouter à l''écran d''accueil ». Bandregie a ensuite sa propre icône et démarre sans barre d''adresse.'),
('fr','app_install_offline','L''aperçu, les dates, les setlists et les morceaux restent sur l''appareil et sont là sans réseau — sur scène, il n''y en a souvent pas. À la déconnexion ils sont effacés, pour que personne ne retrouve les dates du précédent sur un téléphone partagé.'),
('fr','app_install_store','Il n''y a pas d''app sur l''App Store ni sur Google Play. Le passage par le navigateur fait la même chose — sans cotisation annuelle, sans validation à chaque modification et sans une deuxième version du programme à entretenir.'),
('fr','app_install_push','Les notifications sur ton appareil se règlent dans ton profil : choisis ce dont tu veux être informé — nouvelles dates, nouveaux commentaires, confirmations et désistements — et active-les appareil par appareil. Sur iPhone, cela ne fonctionne que pour l''app installée.'),

('es','app_install_hint','En el iPhone con el icono de compartir, en Android con el menú del navegador: «Añadir a la pantalla de inicio». Bandregie tiene entonces su propio icono y arranca sin barra de direcciones.'),
('es','app_install_offline','El resumen, las fechas, las setlists y las canciones se quedan en el dispositivo y están ahí sin cobertura, que en los escenarios suele faltar. Al cerrar sesión se borran, para que en un móvil compartido nadie encuentre las fechas del anterior.'),
('es','app_install_store','No hay app en la App Store ni en Google Play. La vía del navegador hace lo mismo: sin cuota anual, sin revisión en cada cambio y sin una segunda versión del programa que mantener.'),
('es','app_install_push','Los avisos en tu dispositivo están en tu perfil: elige de qué quieres enterarte — nuevas fechas, nuevos comentarios, confirmaciones y cancelaciones — y actívalos por dispositivo. En el iPhone solo funciona con la app instalada.'),

('nl','app_install_hint','Op de iPhone via het deel-symbool, op Android via het browsermenu: "Toevoegen aan beginscherm". Bandregie heeft daarna een eigen icoon en start zonder adresbalk.'),
('nl','app_install_offline','Het overzicht, de data, setlists en nummers blijven op het toestel en zijn er ook zonder bereik — op podia is dat vaak zo. Bij het afmelden worden ze gewist, zodat op een gedeelde telefoon niemand de data van de vorige vindt.'),
('nl','app_install_store','Een app uit de App Store of van Google Play is er niet. De weg via de browser doet hetzelfde — zonder jaarlijkse bijdrage, zonder keuring bij elke wijziging en zonder een tweede versie van het programma die onderhouden moet worden.'),
('nl','app_install_push','Meldingen op je apparaat staan in je profiel: kies waarover je bericht wilt — nieuwe afspraken, nieuwe reacties, toe- en afzeggingen — en zet ze per apparaat aan. Op de iPhone werkt dat alleen voor de geïnstalleerde app.'),

('it','app_install_hint','Su iPhone con l''icona di condivisione, su Android dal menu del browser: «Aggiungi alla schermata Home». Bandregie ha poi un''icona propria e parte senza barra degli indirizzi.'),
('it','app_install_offline','Panoramica, date, scalette e brani restano sul dispositivo e ci sono anche senza segnale — sul palco spesso manca. Alla disconnessione vengono cancellati, così su un telefono condiviso nessuno trova le date di chi c''era prima.'),
('it','app_install_store','Un''app su App Store o Google Play non c''è. La strada del browser fa lo stesso — senza quota annuale, senza revisione a ogni modifica e senza una seconda versione del programma da mantenere.'),
('it','app_install_push','Le notifiche sul tuo dispositivo si trovano nel profilo: scegli di cosa vuoi essere avvisato — nuove date, nuovi commenti, conferme e disdette — e attivale per dispositivo. Su iPhone funziona solo con l''app installata.')
ON DUPLICATE KEY UPDATE value = value;
