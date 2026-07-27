-- Translation of the new 'Ausschüttung' (member payout) treasury category.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','fincat_ausschuettung','Payout'),
('nl','fincat_ausschuettung','Uitkering'),
('fr','fincat_ausschuettung','Répartition'),
('es','fincat_ausschuettung','Reparto'),
('it','fincat_ausschuettung','Ripartizione')
ON DUPLICATE KEY UPDATE value = value;
