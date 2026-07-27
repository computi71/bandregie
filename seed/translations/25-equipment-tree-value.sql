-- Translations for the total value of a device including everything inside it.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_total','Total value'),('en','eq_total_partial','devices without a price not counted'),
('fr','eq_total','Valeur totale'),('fr','eq_total_partial','appareils sans prix non comptés'),
('es','eq_total','Valor total'),('es','eq_total_partial','sin los aparatos sin precio'),
('nl','eq_total','Totale waarde'),('nl','eq_total_partial','apparaten zonder prijs niet meegeteld'),
('it','eq_total','Valore totale'),('it','eq_total_partial','esclusi gli apparecchi senza prezzo')
ON DUPLICATE KEY UPDATE value = value;
