-- A real quantity column instead of a count inside the display name (#185).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_quantity','Quantity'),
('en','eq_quantity_hint','Only for consumables and bulk goods — ten XLR boots are not ten records. Real devices stay at 1 and get a record per unit: a microphone is carried, lent and missed one at a time.'),
('en','eq_quantity_n','%1 pcs'),

('nl','eq_quantity','Aantal'),
('nl','eq_quantity_hint','Alleen voor kleine onderdelen en materiaal per meter — tien XLR-tulen zijn geen tien items. Echte apparaten blijven op 1 en krijgen per stuk een eigen item: een microfoon wordt afzonderlijk gedragen, uitgeleend en gemist.'),
('nl','eq_quantity_n','%1 stuks'),

('fr','eq_quantity','Quantité'),
('fr','eq_quantity_hint','Uniquement pour la petite quincaillerie et le câble au mètre — dix manchons XLR ne font pas dix fiches. Les vrais appareils restent à 1 et reçoivent une fiche par unité : un micro se transporte, se prête et se perd un par un.'),
('fr','eq_quantity_n','%1 pcs'),

('es','eq_quantity','Cantidad'),
('es','eq_quantity_hint','Solo para piezas pequeñas y material a metros: diez fundas XLR no son diez fichas. Los aparatos de verdad se quedan en 1 y tienen una ficha por unidad: un micrófono se transporta, se presta y se echa en falta de uno en uno.'),
('es','eq_quantity_n','%1 uds'),

('it','eq_quantity','Quantità'),
('it','eq_quantity_hint','Solo per minuteria e materiale a metro: dieci gommini XLR non sono dieci schede. Gli apparecchi veri restano a 1 e hanno una scheda per pezzo: un microfono si trasporta, si presta e si perde uno per uno.'),
('it','eq_quantity_n','%1 pz')
ON DUPLICATE KEY UPDATE value = value;
