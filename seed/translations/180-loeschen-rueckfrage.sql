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

-- Vier Rückfragen, die bisher nur „Wirklich löschen?" sagten, obwohl dahinter
-- ein verlorener Zugang oder eine ganze Historie steht.
INSERT INTO translations (lang, tkey, value) VALUES
('en','pk_delete_confirm','Remove this passkey? This device will sign in with a password again.'),
('en','totp_delete_confirm','Turn off the second factor? The password alone will be enough to sign in.'),
('en','mem_delete_confirm','Delete this account? Attendance, ratings, absences and access go with it. Comments stay, without a name.'),
('en','ev_delete_confirm','Delete this event? Attendance, comments, substitute requests and guest bookings go with it. Photos stay but lose their link.'),

('nl','pk_delete_confirm','Deze passkey verwijderen? Dit apparaat meldt zich daarna weer met een wachtwoord aan.'),
('nl','totp_delete_confirm','De tweede factor uitschakelen? Daarna volstaat het wachtwoord alleen.'),
('nl','mem_delete_confirm','Dit account verwijderen? Aan- en afmeldingen, beoordelingen, afwezigheden en toegang gaan mee. Reacties blijven staan, zonder naam.'),
('nl','ev_delete_confirm','Deze afspraak verwijderen? Aan- en afmeldingen, reacties, invalaanvragen en gastboekingen gaan mee. Foto''s blijven, maar verliezen hun koppeling.'),

('fr','pk_delete_confirm','Retirer cette clé d''accès ? Cet appareil se connectera de nouveau avec un mot de passe.'),
('fr','totp_delete_confirm','Désactiver le deuxième facteur ? Le mot de passe suffira ensuite.'),
('fr','mem_delete_confirm','Supprimer ce compte ? Présences, évaluations, absences et accès partent avec. Les commentaires restent, sans nom.'),
('fr','ev_delete_confirm','Supprimer cette date ? Présences, commentaires, demandes de remplaçant et réservations d''invités partent avec. Les photos restent mais perdent leur lien.'),

('es','pk_delete_confirm','¿Quitar esta clave de acceso? Este dispositivo volverá a entrar con contraseña.'),
('es','totp_delete_confirm','¿Desactivar el segundo factor? Después bastará con la contraseña.'),
('es','mem_delete_confirm','¿Borrar esta cuenta? Confirmaciones, valoraciones, ausencias y accesos se van con ella. Los comentarios quedan, sin nombre.'),
('es','ev_delete_confirm','¿Borrar esta cita? Confirmaciones, comentarios, peticiones de sustituto y reservas de invitados se van con ella. Las fotos quedan, pero pierden su vínculo.'),

('it','pk_delete_confirm','Rimuovere questa passkey? Questo dispositivo accederà di nuovo con la password.'),
('it','totp_delete_confirm','Disattivare il secondo fattore? Poi basterà la password.'),
('it','mem_delete_confirm','Eliminare questo account? Presenze, valutazioni, assenze e accessi se ne vanno con lui. I commenti restano, senza nome.'),
('it','ev_delete_confirm','Eliminare questa data? Presenze, commenti, richieste di sostituto e prenotazioni ospiti se ne vanno con lei. Le foto restano, ma perdono il collegamento.')
ON DUPLICATE KEY UPDATE value = value;
