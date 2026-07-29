-- The tax export as a package: the table plus the receipts behind it.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','taxr_package','Package with receipts'),
('en','taxr_package_hint','The package also carries the receipts: the attachments of this year''s entries and the invoices of the equipment that is still being written off — that paper sits in the year of the purchase. An invoice covering several devices is enclosed once.'),
('en','fl_taxr_no_zip','The package needs PHP''s ZIP extension, which is missing. The table on its own still works.'),
('fr','taxr_package','Dossier avec justificatifs'),
('fr','taxr_package_hint','Le dossier contient aussi les justificatifs : les pièces jointes des écritures de l''année et les factures du matériel encore en amortissement — ce papier se trouve dans l''année de l''achat. Une facture portant sur plusieurs appareils n''est jointe qu''une fois.'),
('fr','fl_taxr_no_zip','Le dossier a besoin de l''extension ZIP de PHP, qui manque. Le tableau seul fonctionne toujours.'),
('es','taxr_package','Paquete con justificantes'),
('es','taxr_package_hint','El paquete lleva además los justificantes: los adjuntos de los apuntes de este año y las facturas de los aparatos que siguen amortizándose; ese papel está en el año de la compra. Una factura de varios aparatos se adjunta una sola vez.'),
('es','fl_taxr_no_zip','Al paquete le falta la extensión ZIP de PHP. La tabla por sí sola sigue funcionando.'),
('nl','taxr_package','Pakket met bonnen'),
('nl','taxr_package_hint','Het pakket bevat ook de bonnen: de bijlagen van de boekingen van dit jaar en de facturen van de apparatuur die nog afschrijft — dat papier ligt in het jaar van aankoop. Een factuur voor meerdere apparaten zit er één keer bij.'),
('nl','fl_taxr_no_zip','Voor het pakket ontbreekt de ZIP-extensie van PHP. De tabel alleen werkt wel.'),
('it','taxr_package','Pacchetto con le ricevute'),
('it','taxr_package_hint','Il pacchetto contiene anche le ricevute: gli allegati delle registrazioni di quest''anno e le fatture degli apparecchi ancora in ammortamento — quella carta sta nell''anno dell''acquisto. Una fattura per più apparecchi è allegata una volta sola.'),
('it','fl_taxr_no_zip','Al pacchetto manca l''estensione ZIP di PHP. La tabella da sola funziona comunque.')
ON DUPLICATE KEY UPDATE value = value;
