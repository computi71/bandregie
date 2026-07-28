-- The footer tells an administrator when a newer version exists.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','up_out','available'),
('fr','up_out','disponible'),
('es','up_out','disponible'),
('nl','up_out','beschikbaar'),
('it','up_out','disponibile')
ON DUPLICATE KEY UPDATE value = value;
