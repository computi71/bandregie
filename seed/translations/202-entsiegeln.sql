-- Der Weg zurück aus der Anhangsverschlüsselung (#411).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','set_crypt_unseal_hint','Moving to another server, changing the key, or handing the installation on? Then the attachments have to lie open again, or nobody can take them along.'),
('en','set_crypt_unseal_now','Remove encryption (%d files)'),
('en','set_crypt_unseal_confirm','After this all attachments lie in the clear on disk again. Backups stay encrypted. Continue?'),
('en','fl_crypt_unsealed','%d attachments lie open again.'),
('en','fl_crypt_unsealed_some','%d attachments opened, %d failed — details in the error log.'),
('nl','set_crypt_unseal_hint','Verhuizen naar een andere server, de sleutel wisselen of de installatie overdragen? Dan moeten de bijlagen weer open liggen, anders neemt niemand ze mee.'),
('nl','set_crypt_unseal_now','Versleuteling opheffen (%d bestanden)'),
('nl','set_crypt_unseal_confirm','Daarna liggen alle bijlagen weer leesbaar op de schijf. Back-ups blijven versleuteld. Doorgaan?'),
('nl','fl_crypt_unsealed','%d bijlagen liggen weer open.'),
('nl','fl_crypt_unsealed_some','%d bijlagen geopend, %d mislukt — details in het foutenlogboek.'),
('fr','set_crypt_unseal_hint','Changement de serveur, changement de clé ou passation de l’installation ? Alors les pièces jointes doivent redevenir lisibles, sinon personne ne pourra les emporter.'),
('fr','set_crypt_unseal_now','Lever le chiffrement (%d fichiers)'),
('fr','set_crypt_unseal_confirm','Ensuite, toutes les pièces jointes seront de nouveau en clair sur le disque. Les sauvegardes restent chiffrées. Continuer ?'),
('fr','fl_crypt_unsealed','%d pièces jointes sont de nouveau lisibles.'),
('fr','fl_crypt_unsealed_some','%d pièces jointes ouvertes, %d en échec — détails dans le journal des erreurs.'),
('es','set_crypt_unseal_hint','¿Mudanza a otro servidor, cambio de clave o entrega de la instalación? Entonces los adjuntos tienen que quedar abiertos otra vez, o nadie podrá llevárselos.'),
('es','set_crypt_unseal_now','Quitar el cifrado (%d archivos)'),
('es','set_crypt_unseal_confirm','Después todos los adjuntos quedan de nuevo en claro en el disco. Las copias de seguridad siguen cifradas. ¿Continuar?'),
('es','fl_crypt_unsealed','%d adjuntos vuelven a estar abiertos.'),
('es','fl_crypt_unsealed_some','%d adjuntos abiertos, %d fallidos — detalles en el registro de errores.'),
('it','set_crypt_unseal_hint','Trasloco su un altro server, cambio di chiave o passaggio dell’impianto? Allora gli allegati devono tornare in chiaro, altrimenti nessuno se li porta dietro.'),
('it','set_crypt_unseal_now','Togliere la cifratura (%d file)'),
('it','set_crypt_unseal_confirm','Dopo, tutti gli allegati tornano in chiaro sul disco. I backup restano cifrati. Continuare?'),
('it','fl_crypt_unsealed','%d allegati sono di nuovo in chiaro.'),
('it','fl_crypt_unsealed_some','%d allegati aperti, %d non riusciti — dettagli nel registro degli errori.')
ON DUPLICATE KEY UPDATE value = value;
