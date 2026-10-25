-- Voreinstellungen in der Sprache des Lesers (#371).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','default_band_name','My band'),
('en','default_tagline','Set band name, logo and background in the settings'),
('en','default_bio','The band description goes here.'),
('en','default_booking_text','Want to book us? Write to us!'),
('nl','default_band_name','Mijn band'),
('nl','default_tagline','Bandnaam, logo en achtergrond pas je aan in de instellingen'),
('nl','default_bio','Hier komt binnenkort de bandbeschrijving.'),
('nl','default_booking_text','Wil je ons boeken? Schrijf ons!'),
('fr','default_band_name','Mon groupe'),
('fr','default_tagline','Nom du groupe, logo et arrière-plan se règlent dans les paramètres'),
('fr','default_bio','La description du groupe viendra ici.'),
('fr','default_booking_text','Vous voulez nous engager ? Écrivez-nous !'),
('es','default_band_name','Mi banda'),
('es','default_tagline','Nombre, logotipo y fondo se ajustan en los ajustes'),
('es','default_bio','Aquí irá pronto la descripción de la banda.'),
('es','default_booking_text','¿Queréis contratarnos? ¡Escribidnos!'),
('it','default_band_name','La mia band'),
('it','default_tagline','Nome, logo e sfondo si impostano nelle impostazioni'),
('it','default_bio','Qui andrà presto la descrizione della band.'),
('it','default_booking_text','Volete scritturarci? Scriveteci!'),
('en','set_texts_default','Leave a field empty and the supplied sentence stands there — in the language of whoever is looking at the page. The moment you write something, yours applies.'),
('nl','set_texts_default','Laat je een veld leeg, dan staat daar de meegeleverde zin — in de taal van wie de pagina bekijkt. Zodra je er iets in schrijft, geldt het jouwe.'),
('fr','set_texts_default','Laissez un champ vide et la phrase fournie y figure — dans la langue de qui regarde la page. Dès que vous écrivez quelque chose, c’est le vôtre qui compte.'),
('es','set_texts_default','Si dejas un campo vacío, ahí aparece la frase incluida — en el idioma de quien mira la página. En cuanto escribas algo, vale lo tuyo.'),
('it','set_texts_default','Se lasci un campo vuoto, lì compare la frase fornita — nella lingua di chi guarda la pagina. Appena scrivi qualcosa, vale la tua.')
ON DUPLICATE KEY UPDATE value = value;
