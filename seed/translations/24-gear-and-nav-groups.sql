-- Translations for the event packing list and the grouped internal navigation.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prod_gear','What are you bringing?'),
('en','prod_gear_none','The inventory is still empty.'),
('en','ev_gear','Bring along'),
('en','ev_gear_conflict','booked twice on the same day'),
('en','inavg_planung','Planning'),('en','inavg_musik','Music'),('en','inavg_technik','Tech'),
('en','inavg_material','Material'),('en','inavg_band','Band'),('en','inavg_konto','Account'),

('fr','prod_gear','Qu''emportez-vous ?'),
('fr','prod_gear_none','L''inventaire est encore vide.'),
('fr','ev_gear','À emporter'),
('fr','ev_gear_conflict','prévu deux fois le même jour'),
('fr','inavg_planung','Organisation'),('fr','inavg_musik','Musique'),('fr','inavg_technik','Technique'),
('fr','inavg_material','Médias'),('fr','inavg_band','Groupe'),('fr','inavg_konto','Compte'),

('es','prod_gear','¿Qué lleváis?'),
('es','prod_gear_none','El inventario todavía está vacío.'),
('es','ev_gear','Llevar'),
('es','ev_gear_conflict','reservado dos veces el mismo día'),
('es','inavg_planung','Organización'),('es','inavg_musik','Música'),('es','inavg_technik','Técnica'),
('es','inavg_material','Material'),('es','inavg_band','Banda'),('es','inavg_konto','Cuenta'),

('nl','prod_gear','Wat nemen jullie mee?'),
('nl','prod_gear_none','De inventaris is nog leeg.'),
('nl','ev_gear','Meenemen'),
('nl','ev_gear_conflict','op dezelfde dag dubbel ingepland'),
('nl','inavg_planung','Planning'),('nl','inavg_musik','Muziek'),('nl','inavg_technik','Techniek'),
('nl','inavg_material','Materiaal'),('nl','inavg_band','Band'),('nl','inavg_konto','Account'),

('it','prod_gear','Cosa portate?'),
('it','prod_gear_none','L''inventario è ancora vuoto.'),
('it','ev_gear','Da portare'),
('it','ev_gear_conflict','previsto due volte lo stesso giorno'),
('it','inavg_planung','Organizzazione'),('it','inavg_musik','Musica'),('it','inavg_technik','Tecnica'),
('it','inavg_material','Materiale'),('it','inavg_band','Band'),('it','inavg_konto','Account')
ON DUPLICATE KEY UPDATE value = value;
