-- Translations for hiding a device's price from everyone but its owner.
SET NAMES utf8mb4;

-- eq_total_partial now covers prices the viewer may not see, not only prices
-- nobody entered. Seeds never overwrite, so the narrower wording goes first —
-- and only where it is still the seeded text.
DELETE FROM translations WHERE tkey = 'eq_total_partial' AND value IN (
  'devices without a price not counted',
  'appareils sans prix non comptés',
  'sin los aparatos sin precio',
  'apparaten zonder prijs niet meegeteld',
  'esclusi gli apparecchi senza prezzo'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_total_partial','without the devices whose price you cannot see'),
('en','eq_value_own_only','band property and your own devices only'),
('en','eq_price_hidden','The purchase price and date are visible to the owner of the device.'),

('fr','eq_total_partial','sans les appareils dont le prix ne vous est pas visible'),
('fr','eq_value_own_only','uniquement le matériel du groupe et vos appareils'),
('fr','eq_price_hidden','Le prix et la date d''achat ne sont visibles que par le propriétaire de l''appareil.'),

('es','eq_total_partial','sin los aparatos cuyo precio no puedes ver'),
('es','eq_value_own_only','solo el material del grupo y tus aparatos'),
('es','eq_price_hidden','El precio y la fecha de compra solo los ve el dueño del aparato.'),

('nl','eq_total_partial','zonder de apparaten waarvan je de prijs niet ziet'),
('nl','eq_value_own_only','alleen bandeigendom en je eigen apparaten'),
('nl','eq_price_hidden','De aankoopprijs en -datum ziet alleen de eigenaar van het apparaat.'),

('it','eq_total_partial','senza gli apparecchi di cui non vedi il prezzo'),
('it','eq_value_own_only','solo il materiale della band e i tuoi apparecchi'),
('it','eq_price_hidden','Il prezzo e la data di acquisto li vede solo il proprietario dell''apparecchio.')
ON DUPLICATE KEY UPDATE value = value;
