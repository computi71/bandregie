-- Translations for purchase data, bulk creation and the part editing dialog.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','close','Close'),
('en','eq_inherit_hint','A part takes its owner and storage location from the device it belongs to.'),
('en','eq_purchased','Purchase date'),('en','eq_price','Purchase price'),
('en','eq_price_each','Purchase price (each)'),
('en','eq_count','Quantity'),
('en','eq_count_hint','More than one creates several numbered devices at once — handy for cables.'),
('en','eq_value_sum','Purchase value'),
('en','fl_eq_saved_n','%d devices created.'),

('fr','close','Fermer'),
('fr','eq_inherit_hint','Un composant reprend le propriétaire et le lieu de rangement de l''appareil auquel il appartient.'),
('fr','eq_purchased','Date d''achat'),('fr','eq_price','Prix d''achat'),
('fr','eq_price_each','Prix d''achat (l''unité)'),
('fr','eq_count','Quantité'),
('fr','eq_count_hint','Plus d''un crée plusieurs appareils numérotés d''un coup — pratique pour les câbles.'),
('fr','eq_value_sum','Valeur d''achat'),
('fr','fl_eq_saved_n','%d appareils créés.'),

('es','close','Cerrar'),
('es','eq_inherit_hint','Un componente hereda el propietario y el lugar de almacenamiento del aparato al que pertenece.'),
('es','eq_purchased','Fecha de compra'),('es','eq_price','Precio de compra'),
('es','eq_price_each','Precio de compra (por unidad)'),
('es','eq_count','Cantidad'),
('es','eq_count_hint','Más de uno crea varios aparatos numerados a la vez — práctico para cables.'),
('es','eq_value_sum','Valor de compra'),
('es','fl_eq_saved_n','%d aparatos creados.'),

('nl','close','Sluiten'),
('nl','eq_inherit_hint','Een onderdeel neemt eigenaar en opbergplek over van het apparaat waar het bij hoort.'),
('nl','eq_purchased','Aankoopdatum'),('nl','eq_price','Aankoopprijs'),
('nl','eq_price_each','Aankoopprijs (per stuk)'),
('nl','eq_count','Aantal'),
('nl','eq_count_hint','Meer dan één maakt in één keer meerdere genummerde apparaten aan — handig voor kabels.'),
('nl','eq_value_sum','Aankoopwaarde'),
('nl','fl_eq_saved_n','%d apparaten aangemaakt.'),

('it','close','Chiudi'),
('it','eq_inherit_hint','Un componente eredita proprietario e luogo di conservazione dall''apparecchio a cui appartiene.'),
('it','eq_purchased','Data di acquisto'),('it','eq_price','Prezzo di acquisto'),
('it','eq_price_each','Prezzo di acquisto (l''uno)'),
('it','eq_count','Quantità'),
('it','eq_count_hint','Più di uno crea subito più apparecchi numerati — comodo per i cavi.'),
('it','eq_value_sum','Valore di acquisto'),
('it','fl_eq_saved_n','%d apparecchi creati.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
