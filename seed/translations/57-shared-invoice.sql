-- One invoice can belong to several devices; it is stored once either way.
SET NAMES utf8mb4;

-- Die erste Fassung hing an jedem Geraet und hiess entsprechend; sie steht
-- jetzt einmal fuer die Seite. Seeds ueberschreiben nie, deshalb hier weg.
DELETE FROM translations WHERE tkey IN ('files_also', 'files_also_hint');

INSERT INTO translations (lang, tkey, value) VALUES
('en','files_multi','One invoice, several devices'),
('en','files_multi_hint','An invoice rarely lists a single device. Upload it once here and tick what it belongs to — it is still stored once, but it shows up on every device you tick.'),
('en','files_multi_pick','Belongs to'),
('fr','files_multi','Une facture, plusieurs appareils'),
('fr','files_multi_hint','Une facture ne liste que rarement un seul appareil. Téléverse-la une fois ici et coche ce à quoi elle appartient : elle n''est stockée qu''une fois, mais apparaît sur chaque appareil coché.'),
('fr','files_multi_pick','Appartient à'),
('es','files_multi','Una factura, varios aparatos'),
('es','files_multi_hint','Una factura raras veces enumera un solo aparato. Súbela aquí una vez y marca a qué pertenece: se guarda una sola vez, pero aparece en cada aparato marcado.'),
('es','files_multi_pick','Pertenece a'),
('nl','files_multi','Eén factuur, meerdere apparaten'),
('nl','files_multi_hint','Een factuur noemt zelden één apparaat. Upload hem hier één keer en vink aan waar hij bij hoort — hij wordt maar één keer opgeslagen, maar staat bij elk aangevinkt apparaat.'),
('nl','files_multi_pick','Hoort bij'),
('it','files_multi','Una fattura, più apparecchi'),
('it','files_multi_hint','Una fattura elenca di rado un solo apparecchio. Caricala qui una volta e spunta a che cosa appartiene: viene salvata una volta sola, ma compare su ogni apparecchio spuntato.'),
('it','files_multi_pick','Appartiene a')
ON DUPLICATE KEY UPDATE value = value;
