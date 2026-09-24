-- The delete button under the welcome image asked for a string that was never
-- written, so the settings page showed the key itself. Found by the new
-- bin/texte-pruefen.php on its first useful run (#348).
--
-- Worded like its neighbours set_logo_remove and set_bg_remove.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_welcome_remove','Remove welcome image'),
('nl','set_welcome_remove','Welkomstafbeelding verwijderen'),
('fr','set_welcome_remove','Supprimer l''image d''accueil'),
('es','set_welcome_remove','Quitar imagen de bienvenida'),
('it','set_welcome_remove','Rimuovi immagine di benvenuto')
ON DUPLICATE KEY UPDATE value = value;
