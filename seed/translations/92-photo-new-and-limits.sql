-- A "new" mark until it has been seen (#195), and upload limits read from the
-- server instead of typed into a sentence (#194).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','photo_new','NEW'),
('en','photos_upload_lbl_lim','Pictures (max. %1 each, %2 at a time)'),
('en','fl_photo_stored','Stored: %1'),
('en','fl_photo_skipped_big','Too large for %2, did not arrive: %1'),
('en','fl_photo_skipped_nonimage','Not pictures: %1'),
('en','fl_photo_skipped_error','Failed in transfer: %1'),
('en','fl_photo_cap','The server takes only %1 files per submission — pick the rest in a second batch.'),
('en','fl_photo_too_big_request','Together the submission was larger than %1 and the server threw it away — nothing arrived. Take fewer pictures at once.'),

('nl','photo_new','NIEUW'),
('nl','photos_upload_lbl_lim','Afbeeldingen (max. %1 per bestand, %2 tegelijk)'),
('nl','fl_photo_stored','Opgeslagen: %1'),
('nl','fl_photo_skipped_big','Te groot voor %2, niet aangekomen: %1'),
('nl','fl_photo_skipped_nonimage','Geen afbeeldingen: %1'),
('nl','fl_photo_skipped_error','Mislukt bij overdracht: %1'),
('nl','fl_photo_cap','De server neemt maar %1 bestanden per verzending — kies de rest in een tweede ronde.'),
('nl','fl_photo_too_big_request','De verzending was samen groter dan %1 en de server heeft die weggegooid — er is niets aangekomen. Neem minder afbeeldingen tegelijk.'),

('fr','photo_new','NOUVEAU'),
('fr','photos_upload_lbl_lim','Images (max. %1 par fichier, %2 à la fois)'),
('fr','fl_photo_stored','Enregistrées : %1'),
('fr','fl_photo_skipped_big','Trop volumineuses pour %2, non arrivées : %1'),
('fr','fl_photo_skipped_nonimage','Pas des images : %1'),
('fr','fl_photo_skipped_error','Échec du transfert : %1'),
('fr','fl_photo_cap','Le serveur n''accepte que %1 fichiers par envoi — prenez le reste en deuxième fois.'),
('fr','fl_photo_too_big_request','L''envoi dépassait %1 au total et le serveur l''a rejeté : rien n''est arrivé. Prenez moins d''images à la fois.'),

('es','photo_new','NUEVO'),
('es','photos_upload_lbl_lim','Imágenes (máx. %1 por archivo, %2 a la vez)'),
('es','fl_photo_stored','Guardadas: %1'),
('es','fl_photo_skipped_big','Demasiado grandes para %2, no llegaron: %1'),
('es','fl_photo_skipped_nonimage','No son imágenes: %1'),
('es','fl_photo_skipped_error','Fallo al transferir: %1'),
('es','fl_photo_cap','El servidor acepta solo %1 archivos por envío: coge el resto en una segunda tanda.'),
('es','fl_photo_too_big_request','El envío superaba %1 en total y el servidor lo descartó: no llegó nada. Coge menos imágenes a la vez.'),

('it','photo_new','NUOVO'),
('it','photos_upload_lbl_lim','Immagini (max %1 per file, %2 alla volta)'),
('it','fl_photo_stored','Salvate: %1'),
('it','fl_photo_skipped_big','Troppo grandi per %2, non arrivate: %1'),
('it','fl_photo_skipped_nonimage','Non sono immagini: %1'),
('it','fl_photo_skipped_error','Trasferimento fallito: %1'),
('it','fl_photo_cap','Il server accetta solo %1 file per invio: prendi il resto in un secondo giro.'),
('it','fl_photo_too_big_request','L''invio superava %1 in tutto e il server l''ha scartato: non è arrivato nulla. Prendi meno immagini alla volta.')
ON DUPLICATE KEY UPDATE value = value;

-- Ordner je Termin (#196)
INSERT INTO translations (lang, tkey, value) VALUES
('en','photo_folder_none','Not assigned to an event yet'),
('en','photo_folder_count','Pictures: %1'),
('nl','photo_folder_none','Nog niet aan een agendapunt toegewezen'),
('nl','photo_folder_count','Afbeeldingen: %1'),
('fr','photo_folder_none','Pas encore affectées à une date'),
('fr','photo_folder_count','Images : %1'),
('es','photo_folder_none','Aún sin asignar a una fecha'),
('es','photo_folder_count','Imágenes: %1'),
('it','photo_folder_none','Non ancora assegnate a una data'),
('it','photo_folder_count','Immagini: %1')
ON DUPLICATE KEY UPDATE value = value;
