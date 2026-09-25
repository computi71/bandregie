-- Der Haken am Termin heißt jetzt nach dem, was er tut (#368).
--
-- „Rechnung benötigt" beschrieb einen Zustand, der Haken führt aber eine
-- Handlung aus: Er legt die Rechnung aus dem Vertrag sofort an. Wer „benötigt"
-- liest, hakt an und will später schreiben — und findet die Rechnung schon da.
--
-- DELETE davor, weil die INSERTs mit ON DUPLICATE KEY UPDATE value=value
-- laufen: Ohne das Löschen bliebe in jeder bestehenden Anlage der alte Text.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'ev_needs_invoice';

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_needs_invoice','Create invoice'),
('nl','ev_needs_invoice','Factuur aanmaken'),
('fr','ev_needs_invoice','Créer la facture'),
('es','ev_needs_invoice','Crear factura'),
('it','ev_needs_invoice','Crea fattura')
ON DUPLICATE KEY UPDATE value = value;
