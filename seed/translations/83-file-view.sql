-- Attachment view with a way back: the installed app has no address bar, so a
-- PDF taking over the window is a dead end (#183).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','file_back','Back'),
('en','file_no_preview','This file cannot be shown here — save it or open it in a new tab.'),
('en','file_save','Save'),
('en','file_open_tab','Open in a new tab'),

('nl','file_back','Terug'),
('nl','file_no_preview','Dit bestand kan hier niet worden weergegeven — bewaar het of open het in een nieuw tabblad.'),
('nl','file_save','Opslaan'),
('nl','file_open_tab','In een nieuw tabblad openen'),

('fr','file_back','Retour'),
('fr','file_no_preview','Ce fichier ne peut pas être affiché ici — enregistrez-le ou ouvrez-le dans un nouvel onglet.'),
('fr','file_save','Enregistrer'),
('fr','file_open_tab','Ouvrir dans un nouvel onglet'),

('es','file_back','Volver'),
('es','file_no_preview','Este archivo no se puede mostrar aquí: guárdalo o ábrelo en una pestaña nueva.'),
('es','file_save','Guardar'),
('es','file_open_tab','Abrir en una pestaña nueva'),

('it','file_back','Indietro'),
('it','file_no_preview','Questo file non può essere mostrato qui: salvalo oppure aprilo in una nuova scheda.'),
('it','file_save','Salva'),
('it','file_open_tab','Apri in una nuova scheda')
ON DUPLICATE KEY UPDATE value = value;
