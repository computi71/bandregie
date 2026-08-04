-- A "new" mark until it has been seen (#195), and upload limits read from the
-- server instead of typed into a sentence (#194).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','photo_new','NEW'),
('en','photos_upload_lbl_lim','Pictures (max. %1 each, %2 at a time)'),
('en','fl_photo_stored','%1 photos stored.'),
('en','fl_photo_skipped_big','%1 were larger than %2 and did not arrive.'),
('en','fl_photo_skipped_nonimage','%1 were not pictures.'),
('en','fl_photo_skipped_error','%1 went wrong in transfer.'),
('en','fl_photo_cap','The server takes only %1 files per submission — pick the rest in a second batch.'),
('en','fl_photo_too_big_request','Together the submission was larger than %1 and the server threw it away — nothing arrived. Take fewer pictures at once.'),

('nl','photo_new','NIEUW'),
('nl','photos_upload_lbl_lim','Afbeeldingen (max. %1 per bestand, %2 tegelijk)'),
('nl','fl_photo_stored','%1 foto''s opgeslagen.'),
('nl','fl_photo_skipped_big','%1 waren groter dan %2 en zijn niet aangekomen.'),
('nl','fl_photo_skipped_nonimage','%1 waren geen afbeeldingen.'),
('nl','fl_photo_skipped_error','Bij %1 ging bij het overdragen iets mis.'),
('nl','fl_photo_cap','De server neemt maar %1 bestanden per verzending — kies de rest in een tweede ronde.'),
('nl','fl_photo_too_big_request','De verzending was samen groter dan %1 en de server heeft die weggegooid — er is niets aangekomen. Neem minder afbeeldingen tegelijk.'),

('fr','photo_new','NOUVEAU'),
('fr','photos_upload_lbl_lim','Images (max. %1 par fichier, %2 à la fois)'),
('fr','fl_photo_stored','%1 photos enregistrées.'),
('fr','fl_photo_skipped_big','%1 dépassaient %2 et ne sont pas arrivées.'),
('fr','fl_photo_skipped_nonimage','%1 n''étaient pas des images.'),
('fr','fl_photo_skipped_error','Pour %1, le transfert a échoué.'),
('fr','fl_photo_cap','Le serveur n''accepte que %1 fichiers par envoi — prenez le reste en deuxième fois.'),
('fr','fl_photo_too_big_request','L''envoi dépassait %1 au total et le serveur l''a rejeté : rien n''est arrivé. Prenez moins d''images à la fois.'),

('es','photo_new','NUEVO'),
('es','photos_upload_lbl_lim','Imágenes (máx. %1 por archivo, %2 a la vez)'),
('es','fl_photo_stored','%1 fotos guardadas.'),
('es','fl_photo_skipped_big','%1 superaban %2 y no llegaron.'),
('es','fl_photo_skipped_nonimage','%1 no eran imágenes.'),
('es','fl_photo_skipped_error','En %1 algo falló al transferir.'),
('es','fl_photo_cap','El servidor acepta solo %1 archivos por envío: coge el resto en una segunda tanda.'),
('es','fl_photo_too_big_request','El envío superaba %1 en total y el servidor lo descartó: no llegó nada. Coge menos imágenes a la vez.'),

('it','photo_new','NUOVO'),
('it','photos_upload_lbl_lim','Immagini (max %1 per file, %2 alla volta)'),
('it','fl_photo_stored','%1 foto salvate.'),
('it','fl_photo_skipped_big','%1 superavano %2 e non sono arrivate.'),
('it','fl_photo_skipped_nonimage','%1 non erano immagini.'),
('it','fl_photo_skipped_error','Per %1 il trasferimento è andato storto.'),
('it','fl_photo_cap','Il server accetta solo %1 file per invio: prendi il resto in un secondo giro.'),
('it','fl_photo_too_big_request','L''invio superava %1 in tutto e il server l''ha scartato: non è arrivato nulla. Prendi meno immagini alla volta.')
ON DUPLICATE KEY UPDATE value = value;
