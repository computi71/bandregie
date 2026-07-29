-- One invoice can belong to several devices; it is stored once either way.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','files_also','Belongs to other devices too'),
('en','files_also_hint','An invoice rarely lists a single device. Tick the others here — the file is still stored once, but it shows up on every device you tick.'),
('fr','files_also','Concerne aussi d''autres appareils'),
('fr','files_also_hint','Une facture ne liste que rarement un seul appareil. Coche les autres ici : le fichier n''est stocké qu''une fois, mais il apparaît sur chaque appareil coché.'),
('es','files_also','También pertenece a otros aparatos'),
('es','files_also_hint','Una factura raras veces enumera un solo aparato. Marca aquí los demás: el archivo se guarda una sola vez, pero aparece en cada aparato marcado.'),
('nl','files_also','Hoort ook bij andere apparaten'),
('nl','files_also_hint','Een factuur noemt zelden één apparaat. Vink de andere hier aan — het bestand wordt maar één keer opgeslagen, maar staat bij elk aangevinkt apparaat.'),
('it','files_also','Riguarda anche altri apparecchi'),
('it','files_also_hint','Una fattura elenca di rado un solo apparecchio. Spunta qui gli altri: il file viene salvato una volta sola, ma compare su ogni apparecchio spuntato.')
ON DUPLICATE KEY UPDATE value = value;
