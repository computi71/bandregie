-- Translations for the configurable default language and upload error messages.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_default_lang','Default language'),
('en','set_default_lang_hint','Visitors automatically get their browser language if it is enabled here. If none matches, this default applies. Logged-in members see the language from their profile.'),
('en','fl_upload_server_limit','The file was too large for the server. Maximum possible:'),
('en','fl_upload_failed','The upload did not work — please try again.'),
('nl','set_default_lang','Standaardtaal'),
('nl','set_default_lang_hint','Bezoekers krijgen automatisch hun browsertaal, mits die hier is ingeschakeld. Past er geen, dan geldt deze standaardtaal. Ingelogde leden zien de taal uit hun profiel.'),
('nl','fl_upload_server_limit','Het bestand was te groot voor de server. Maximaal mogelijk:'),
('nl','fl_upload_failed','Het uploaden is mislukt — probeer het opnieuw.'),
('fr','set_default_lang','Langue par défaut'),
('fr','set_default_lang_hint','Les visiteurs obtiennent automatiquement la langue de leur navigateur si elle est activée ici. Si aucune ne convient, cette langue par défaut s''applique. Les membres connectés voient la langue de leur profil.'),
('fr','fl_upload_server_limit','Le fichier était trop volumineux pour le serveur. Maximum possible :'),
('fr','fl_upload_failed','L''envoi a échoué — merci de réessayer.'),
('es','set_default_lang','Idioma predeterminado'),
('es','set_default_lang_hint','Los visitantes reciben automáticamente el idioma de su navegador si está activado aquí. Si ninguno coincide, se aplica este idioma predeterminado. Los miembros con sesión ven el idioma de su perfil.'),
('es','fl_upload_server_limit','El archivo era demasiado grande para el servidor. Máximo posible:'),
('es','fl_upload_failed','La subida no funcionó — inténtalo de nuevo.'),
('it','set_default_lang','Lingua predefinita'),
('it','set_default_lang_hint','I visitatori ricevono automaticamente la lingua del browser, se abilitata qui. Se nessuna corrisponde, vale questa lingua predefinita. I membri connessi vedono la lingua del loro profilo.'),
('it','fl_upload_server_limit','Il file era troppo grande per il server. Massimo possibile:'),
('it','fl_upload_failed','Il caricamento non è riuscito — riprova.')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Correction: the English wording read awkwardly in front of the band name.
UPDATE translations SET value = 'For members of' WHERE lang = 'en' AND tkey = 'login_only_members';

INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_csrf','That action expired — please reload the page and try again.'),
('en','fl_throttled','Too many failed attempts. Please wait 15 minutes.'),
('nl','fl_csrf','Deze actie is verlopen — herlaad de pagina en probeer het opnieuw.'),
('nl','fl_throttled','Te veel mislukte pogingen. Wacht 15 minuten.'),
('fr','fl_csrf','L''action a expiré — recharge la page et réessaie.'),
('fr','fl_throttled','Trop de tentatives échouées. Merci d''attendre 15 minutes.'),
('es','fl_csrf','La acción caducó — recarga la página e inténtalo de nuevo.'),
('es','fl_throttled','Demasiados intentos fallidos. Espera 15 minutos.'),
('it','fl_csrf','L''azione è scaduta — ricarica la pagina e riprova.'),
('it','fl_throttled','Troppi tentativi falliti. Attendi 15 minuti.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
