-- The rider shows the port the promoter patches into; the band's own channel
-- number stays in the internal list, where whoever mixes works by channel.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'ch_patch' AND value IN (
  'Input', 'Entrée', 'Entrada', 'Ingang', 'Ingresso'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','ch_patch','Port'), ('en','ch_input','Input'),
('fr','ch_patch','Port'), ('fr','ch_input','Entrée'),
('es','ch_patch','Puerto'), ('es','ch_input','Entrada'),
('nl','ch_patch','Poort'), ('nl','ch_input','Ingang'),
('it','ch_patch','Porta'), ('it','ch_input','Ingresso')
ON DUPLICATE KEY UPDATE value = value;
