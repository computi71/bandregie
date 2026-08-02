-- A device with notifications switched off says nothing about it — the app
-- simply stays quiet. The dashboard now says so, and links to the switch.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_off_here','Notifications are off on this device'),
('en','push_off_here_hint','You will not hear about a new date or somebody answering — the app simply stays quiet. Tap to switch them on.'),
('fr','push_off_here','Les notifications sont désactivées sur cet appareil'),
('fr','push_off_here_hint','Vous ne saurez rien d''une nouvelle date ni d''une réponse — l''application reste simplement silencieuse. Touchez pour les activer.'),
('es','push_off_here','Los avisos están desactivados en este dispositivo'),
('es','push_off_here_hint','No te enterarás de una fecha nueva ni de una respuesta — la aplicación simplemente se queda callada. Toca para activarlos.'),
('nl','push_off_here','Meldingen staan uit op dit apparaat'),
('nl','push_off_here_hint','Je hoort niets van een nieuwe datum of van een antwoord — de app blijft gewoon stil. Tik om ze aan te zetten.'),
('it','push_off_here','Le notifiche sono disattivate su questo dispositivo'),
('it','push_off_here_hint','Non saprai di una nuova data né di una risposta — l''app resta semplicemente in silenzio. Tocca per attivarle.')
ON DUPLICATE KEY UPDATE value = value;
