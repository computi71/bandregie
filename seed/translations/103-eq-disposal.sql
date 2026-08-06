-- Abgang ohne Erloes bucht keine Null (#224)
INSERT INTO translations (lang, tkey, value) VALUES
('en','fl_eq_disposed_booked','Disposed of, and %1 booked as income.'),
('en','fl_eq_disposed_free','Disposed of - no proceeds, so nothing was booked.'),
('nl','fl_eq_disposed_booked','Afgestoten en %1 als inkomsten geboekt.'),
('nl','fl_eq_disposed_free','Afgestoten - geen opbrengst, dus niets geboekt.'),
('fr','fl_eq_disposed_booked','Cédé, et %1 comptabilisé en recette.'),
('fr','fl_eq_disposed_free','Cédé - sans recette, donc rien de comptabilisé.'),
('es','fl_eq_disposed_booked','Dado de baja y %1 registrado como ingreso.'),
('es','fl_eq_disposed_free','Dado de baja - sin ingresos, por eso no se registró nada.'),
('it','fl_eq_disposed_booked','Dismesso e %1 registrato come entrata.'),
('it','fl_eq_disposed_free','Dismesso - senza ricavo, quindi non è stato registrato nulla.')
ON DUPLICATE KEY UPDATE value = value;
