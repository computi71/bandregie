-- Translation for the message when an uploaded backup cannot be stored.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_bk_upload_failed','The file could not be stored — please check the permissions and free space of the backup folder. Nothing was recorded.'),
('fr','fl_bk_upload_failed','Le fichier n''a pas pu être enregistré — vérifiez les droits et l''espace libre du dossier de sauvegarde. Rien n''a été inscrit.'),
('es','fl_bk_upload_failed','No se pudo guardar el archivo: comprueba los permisos y el espacio libre de la carpeta de copias. No se ha registrado nada.'),
('nl','fl_bk_upload_failed','Het bestand kon niet worden opgeslagen — controleer de rechten en de vrije ruimte van de back-upmap. Er is niets vastgelegd.'),
('it','fl_bk_upload_failed','Non è stato possibile salvare il file: controlla i permessi e lo spazio libero della cartella dei backup. Non è stato registrato nulla.')
ON DUPLICATE KEY UPDATE value = value;
