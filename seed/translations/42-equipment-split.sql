-- Translations for splitting one inventory row into several devices.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_split','Split into single devices'),
('en','eq_split_found','(looks like %d of them)'),
('en','eq_split_hint','If this row stands for several identical devices, this turns it into numbered single ones — each with its own price, its own deadline and its own tick on the packing list. The quantity in the name is dropped.'),
('en','fl_eq_split','Split into %d devices.'),
('en','fl_eq_split_impossible','Splitting works from two upwards and only for devices without parts.'),

('fr','eq_split','Séparer en appareils individuels'),
('fr','eq_split_found','(on dirait %d exemplaires)'),
('fr','eq_split_hint','Si cette ligne représente plusieurs appareils identiques, elle devient autant d''appareils numérotés — chacun avec son prix, son échéance et sa case sur la liste de chargement. La quantité disparaît du nom.'),
('fr','fl_eq_split','Séparé en %d appareils.'),
('fr','fl_eq_split_impossible','La séparation fonctionne à partir de deux et seulement pour les appareils sans composants.'),

('es','eq_split','Separar en aparatos individuales'),
('es','eq_split_found','(parecen %d unidades)'),
('es','eq_split_hint','Si esta línea representa varios aparatos iguales, se convierte en aparatos numerados por separado, cada uno con su precio, su plazo y su casilla en la lista de carga. La cantidad desaparece del nombre.'),
('es','fl_eq_split','Separado en %d aparatos.'),
('es','fl_eq_split_impossible','Separar funciona a partir de dos y solo con aparatos sin componentes.'),

('nl','eq_split','Opsplitsen in losse apparaten'),
('nl','eq_split_found','(lijkt op %d stuks)'),
('nl','eq_split_hint','Staat deze regel voor meerdere gelijke apparaten, dan worden het genummerde losse apparaten — elk met eigen prijs, eigen termijn en een eigen vinkje op de paklijst. Het aantal verdwijnt uit de naam.'),
('nl','fl_eq_split','Opgesplitst in %d apparaten.'),
('nl','fl_eq_split_impossible','Opsplitsen kan vanaf twee en alleen bij apparaten zonder onderdelen.'),

('it','eq_split','Dividere in apparecchi singoli'),
('it','eq_split_found','(sembrano %d pezzi)'),
('it','eq_split_hint','Se questa riga sta per più apparecchi uguali, diventa altrettanti apparecchi numerati — ognuno con il suo prezzo, la sua scadenza e la sua spunta sulla lista di carico. La quantità sparisce dal nome.'),
('it','fl_eq_split','Diviso in %d apparecchi.'),
('it','fl_eq_split_impossible','La divisione funziona da due in su e solo per apparecchi senza componenti.')
ON DUPLICATE KEY UPDATE value = value;
