-- Translations for member deposits, the utilities category and the rent cover card.
SET NAMES utf8mb4;

-- ord_scope_hint changed its meaning when deposits arrived: it no longer
-- describes only private orders. Seeds never overwrite, so the superseded
-- wording is removed first — and only where it is still the seeded text, so a
-- band that reworded it keeps its own version.
DELETE FROM translations WHERE tkey = 'ord_scope_hint' AND value IN (
  'Your own standing orders are visible only to you.',
  'Vos ordres permanents personnels ne sont visibles que par vous.',
  'Tus órdenes permanentes personales solo las ves tú.',
  'Je eigen doorlopende opdrachten ziet alleen jij.',
  'I tuoi ordini permanenti personali li vedi solo tu.'
);

-- Same for the deposit category: it used to be a neutral "contribution" and is
-- now the money members put in for the rent, so it is named after that.
DELETE FROM translations WHERE tkey = 'fincat_einlage' AND value IN (
  'Contribution', 'Apport', 'Aportación', 'Inleg', 'Quota'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','fincat_einlage','Member deposits'),
('fr','fincat_einlage','Versements des membres'),
('es','fincat_einlage','Aportaciones de los miembros'),
('nl','fincat_einlage','Stortingen van de leden'),
('it','fincat_einlage','Versamenti dei membri'),
('en','fincat_nebenkosten','Utilities'),
('en','ord_scope_deposit','my deposit into the band treasury'),
('en','ord_scope_hint','A deposit is visible to everyone and counts as band income. What runs for yourself only you see.'),
('en','fin_rent_cover','Rehearsal room paid from deposits'),
('en','fin_rent_cover_hint','Member deposits are there first of all for the rent and its running costs.'),
('en','fin_rent_cost','Rent and utilities'),
('en','fin_rent_deposits','Member deposits'),
('en','fin_rent_gap','The band pays on top'),
('en','fin_rent_surplus','Left for the treasury'),

('fr','fincat_nebenkosten','Charges'),
('fr','ord_scope_deposit','mon versement dans la caisse du groupe'),
('fr','ord_scope_hint','Un versement est visible par tous et compte comme recette du groupe. Ce qui ne concerne que vous, vous seul le voyez.'),
('fr','fin_rent_cover','Local de répétition payé par les versements'),
('fr','fin_rent_cover_hint','Les versements des membres servent avant tout au loyer et aux charges.'),
('fr','fin_rent_cost','Loyer et charges'),
('fr','fin_rent_deposits','Versements des membres'),
('fr','fin_rent_gap','Le groupe complète de'),
('fr','fin_rent_surplus','Reste pour la caisse'),

('es','fincat_nebenkosten','Gastos corrientes'),
('es','ord_scope_deposit','mi aportación a la caja del grupo'),
('es','ord_scope_hint','Una aportación la ven todos y cuenta como ingreso del grupo. Lo que corre por tu cuenta solo lo ves tú.'),
('es','fin_rent_cover','Local de ensayo pagado con aportaciones'),
('es','fin_rent_cover_hint','Las aportaciones de los miembros son ante todo para el alquiler y sus gastos corrientes.'),
('es','fin_rent_cost','Alquiler y gastos corrientes'),
('es','fin_rent_deposits','Aportaciones de los miembros'),
('es','fin_rent_gap','El grupo pone además'),
('es','fin_rent_surplus','Queda para la caja'),

('nl','fincat_nebenkosten','Bijkomende kosten'),
('nl','ord_scope_deposit','mijn storting in de bandkas'),
('nl','ord_scope_hint','Een storting zien alle leden en telt als inkomst van de band. Wat voor jezelf loopt, zie alleen jij.'),
('nl','fin_rent_cover','Oefenruimte uit stortingen betaald'),
('nl','fin_rent_cover_hint','De stortingen van de leden zijn er in de eerste plaats voor de huur en de bijkomende kosten.'),
('nl','fin_rent_cost','Huur en bijkomende kosten'),
('nl','fin_rent_deposits','Stortingen van de leden'),
('nl','fin_rent_gap','De band legt bij'),
('nl','fin_rent_surplus','Blijft over voor de kas'),

('it','fincat_nebenkosten','Spese accessorie'),
('it','ord_scope_deposit','il mio versamento nella cassa della band'),
('it','ord_scope_hint','Un versamento lo vedono tutti e conta come entrata della band. Quello che riguarda solo te lo vedi solo tu.'),
('it','fin_rent_cover','Sala prove pagata con i versamenti'),
('it','fin_rent_cover_hint','I versamenti dei membri servono prima di tutto per l''affitto e le spese accessorie.'),
('it','fin_rent_cost','Affitto e spese accessorie'),
('it','fin_rent_deposits','Versamenti dei membri'),
('it','fin_rent_gap','La band ci mette in più'),
('it','fin_rent_surplus','Resta per la cassa')
ON DUPLICATE KEY UPDATE value = value;
