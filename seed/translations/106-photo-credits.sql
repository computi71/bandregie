-- Bildnachweis fuer die mitgelieferten Galeriebilder (#228)
INSERT INTO translations (lang, tkey, value) VALUES
('en','legal_credit_photos','Pictures in the gallery:'),
('nl','legal_credit_photos','Foto''s in de galerij:'),
('fr','legal_credit_photos','Images de la galerie :'),
('es','legal_credit_photos','Imagenes de la galeria:'),
('it','legal_credit_photos','Immagini nella galleria:')
ON DUPLICATE KEY UPDATE value = value;
