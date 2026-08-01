-- The group holds photos, music and downloads. "Material" was vague for that,
-- and the languages disagreed — French already said "Médias".
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','inavg_material','Media'),
('fr','inavg_material','Médias'),
('es','inavg_material','Medios'),
('nl','inavg_material','Media'),
('it','inavg_material','Media')
ON DUPLICATE KEY UPDATE value = value;
