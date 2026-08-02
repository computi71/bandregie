-- On a phone the QR code cannot work: the app sits on the same screen. Step 2
-- now splits the two cases and offers the otpauth link for the device at hand.
-- totp_setup_scan is re-seeded here with new wording; bootstrap deletes the old
-- rows once, since a seed never overwrites a row that already exists.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','totp_setup_scan','2. Hand the account to the app. Which way is right depends only on where the app is.'),
('en','totp_setup_here','📱 The app is on this device — the usual case when you are on your phone. Then no photo is needed: the button opens the app and creates the account there. If nothing happens, no authenticator app is installed yet.'),
('en','totp_setup_open_app','Open in the app'),
('en','totp_setup_other','💻 The app is on another device — the usual case when you are at a computer. Then choose "add account" in the app and photograph this code.'),
('fr','totp_setup_scan','2. Transmettre le compte à l''application. Le bon chemin dépend seulement de l''endroit où se trouve l''application.'),
('fr','totp_setup_here','📱 L''application est sur cet appareil — le cas habituel quand vous êtes sur votre téléphone. Alors aucune photo n''est nécessaire : le bouton ouvre l''application et y crée le compte. S''il ne se passe rien, aucune application d''authentification n''est encore installée.'),
('fr','totp_setup_open_app','Ouvrir dans l''application'),
('fr','totp_setup_other','💻 L''application est sur un autre appareil — le cas habituel quand vous êtes devant un ordinateur. Choisissez alors « ajouter un compte » dans l''application et photographiez ce code.'),
('es','totp_setup_scan','2. Pasar la cuenta a la aplicación. Qué camino es el adecuado depende solo de dónde esté la aplicación.'),
('es','totp_setup_here','📱 La aplicación está en este dispositivo — lo habitual si estás en el móvil. Entonces no hace falta ninguna foto: el botón abre la aplicación y crea allí la cuenta. Si no ocurre nada, todavía no hay ninguna aplicación de autenticación instalada.'),
('es','totp_setup_open_app','Abrir en la aplicación'),
('es','totp_setup_other','💻 La aplicación está en otro dispositivo — lo habitual si estás ante el ordenador. Entonces elige «añadir cuenta» en la aplicación y fotografía este código.'),
('nl','totp_setup_scan','2. Het account aan de app doorgeven. Welke weg de juiste is, hangt er alleen van af waar de app staat.'),
('nl','totp_setup_here','📱 De app staat op dit apparaat — het gewone geval als je op je telefoon zit. Dan is er geen foto nodig: de knop opent de app en maakt het account daar aan. Gebeurt er niets, dan is er nog geen authenticator-app geïnstalleerd.'),
('nl','totp_setup_open_app','In de app openen'),
('nl','totp_setup_other','💻 De app staat op een ander apparaat — het gewone geval als je aan de computer zit. Kies dan in de app „account toevoegen" en fotografeer deze code.'),
('it','totp_setup_scan','2. Passare l''account all''app. Quale strada sia quella giusta dipende solo da dove si trova l''app.'),
('it','totp_setup_here','📱 L''app è su questo dispositivo — il caso normale se sei al telefono. Allora non serve alcuna foto: il pulsante apre l''app e vi crea l''account. Se non succede nulla, non è ancora installata alcuna app di autenticazione.'),
('it','totp_setup_open_app','Apri nell''app'),
('it','totp_setup_other','💻 L''app è su un altro dispositivo — il caso normale se sei al computer. Scegli allora «aggiungi account» nell''app e fotografa questo codice.')
ON DUPLICATE KEY UPDATE value = value;
