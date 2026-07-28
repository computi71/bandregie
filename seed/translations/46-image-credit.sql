-- Credit for the bundled demo background, shown while it is in use.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','legal_credits','Image credit'),
('en','legal_credit_background','Background image: concert audience,'),
('fr','legal_credits','Crédit photo'),
('fr','legal_credit_background','Image de fond : public de concert,'),
('es','legal_credits','Crédito de la imagen'),
('es','legal_credit_background','Imagen de fondo: público de concierto,'),
('nl','legal_credits','Beeldverantwoording'),
('nl','legal_credit_background','Achtergrondafbeelding: concertpubliek,'),
('it','legal_credits','Crediti immagine'),
('it','legal_credit_background','Immagine di sfondo: pubblico di un concerto,')
ON DUPLICATE KEY UPDATE value = value;
