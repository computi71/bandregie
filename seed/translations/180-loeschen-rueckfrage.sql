-- Rückfragen vor dem Löschen (#375). Sie benennen, was verschwindet —
-- „Wirklich löschen?" beantwortet man im Zweifel mit ja, ohne zu wissen, was
-- man gerade wegwirft.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','abs_delete_confirm','Delete this absence?'),
('en','post_delete_confirm','Delete this post? The notification about it has already gone out.'),
('en','comment_delete_confirm','Delete this comment?'),
('en','sub_withdraw_confirm','Withdraw the substitute request?'),
('en','avatar_delete_confirm','Remove your picture?'),
('en','branding_delete_confirm','Remove this image? Getting it back means uploading it again.'),

('nl','abs_delete_confirm','Deze afwezigheid verwijderen?'),
('nl','post_delete_confirm','Dit bericht verwijderen? De melding erover is al verstuurd.'),
('nl','comment_delete_confirm','Deze reactie verwijderen?'),
('nl','sub_withdraw_confirm','De invalaanvraag intrekken?'),
('nl','avatar_delete_confirm','Je foto verwijderen?'),
('nl','branding_delete_confirm','Deze afbeelding verwijderen? Terughalen betekent opnieuw uploaden.'),

('fr','abs_delete_confirm','Supprimer cette absence ?'),
('fr','post_delete_confirm','Supprimer ce message ? La notification est déjà partie.'),
('fr','comment_delete_confirm','Supprimer ce commentaire ?'),
('fr','sub_withdraw_confirm','Retirer la demande de remplaçant ?'),
('fr','avatar_delete_confirm','Retirer votre photo ?'),
('fr','branding_delete_confirm','Retirer cette image ? Pour la récupérer, il faudra la téléverser à nouveau.'),

('es','abs_delete_confirm','¿Borrar esta ausencia?'),
('es','post_delete_confirm','¿Borrar este mensaje? El aviso ya se ha enviado.'),
('es','comment_delete_confirm','¿Borrar este comentario?'),
('es','sub_withdraw_confirm','¿Retirar la petición de sustituto?'),
('es','avatar_delete_confirm','¿Quitar tu foto?'),
('es','branding_delete_confirm','¿Quitar esta imagen? Para recuperarla habrá que subirla de nuevo.'),

('it','abs_delete_confirm','Eliminare questa assenza?'),
('it','post_delete_confirm','Eliminare questo messaggio? La notifica è già partita.'),
('it','comment_delete_confirm','Eliminare questo commento?'),
('it','sub_withdraw_confirm','Ritirare la richiesta di sostituto?'),
('it','avatar_delete_confirm','Rimuovere la tua foto?'),
('it','branding_delete_confirm','Rimuovere questa immagine? Per riaverla va caricata di nuovo.')
ON DUPLICATE KEY UPDATE value = value;
