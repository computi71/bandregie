-- "Preis rechnen" at the event, and the sheet named after the flow (#355).
--
-- "Calculation", never "invoice": the word invoice is reserved for the paper
-- that goes to the promoter.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','quote_calc_title','Calculation'),
('en','quote_calc_new','New calculation'),
('en','quote_calc_btn','Work out the price'),
('en','quote_calc_open','Calculation'),
('en','quote_calc_intro','What a performance should cost, worked out from your price list instead of in your head. The total goes into the contract for the same date as the fee.'),
('en','quote_calc_none','Nothing worked out yet.'),

('nl','quote_calc_title','Calculatie'),
('nl','quote_calc_new','Nieuwe calculatie'),
('nl','quote_calc_btn','Prijs berekenen'),
('nl','quote_calc_open','Calculatie'),
('nl','quote_calc_intro','Wat een optreden moet kosten, berekend uit jullie prijslijst in plaats van uit je hoofd. Het totaal gaat als gage naar het contract bij dezelfde afspraak.'),
('nl','quote_calc_none','Nog niets berekend.'),

('fr','quote_calc_title','Calcul'),
('fr','quote_calc_new','Nouveau calcul'),
('fr','quote_calc_btn','Calculer le prix'),
('fr','quote_calc_open','Calcul'),
('fr','quote_calc_intro','Ce qu''une prestation doit coûter, calculé à partir de votre grille tarifaire plutôt que de tête. Le total rejoint le contrat de la même date en tant que cachet.'),
('fr','quote_calc_none','Rien de calculé pour l''instant.'),

('es','quote_calc_title','Cálculo'),
('es','quote_calc_new','Nuevo cálculo'),
('es','quote_calc_btn','Calcular el precio'),
('es','quote_calc_open','Cálculo'),
('es','quote_calc_intro','Lo que debe costar una actuación, calculado a partir de vuestra lista de precios y no a ojo. El total pasa al contrato de la misma cita como caché.'),
('es','quote_calc_none','Todavía no se ha calculado nada.'),

('it','quote_calc_title','Calcolo'),
('it','quote_calc_new','Nuovo calcolo'),
('it','quote_calc_btn','Calcola il prezzo'),
('it','quote_calc_open','Calcolo'),
('it','quote_calc_intro','Quanto deve costare un concerto, calcolato dal vostro listino invece che a mente. Il totale entra nel contratto dello stesso appuntamento come compenso.'),
('it','quote_calc_none','Non è ancora stato calcolato nulla.')
ON DUPLICATE KEY UPDATE value = value;
