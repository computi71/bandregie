-- The stagebox input is not the microphone: "A1" says where the signal is
-- plugged in, "SM57" says what produces it. A rider needs both.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'ch_source' AND value IN (
  'Source / microphone', 'Bron / microfoon', 'Source / microphone',
  'Fuente / micrófono', 'Sorgente / microfono'
);

INSERT INTO translations (lang, tkey, value) VALUES
('en','ch_source','Microphone / DI'), ('en','ch_patch','Input'), ('en','ch_patch_ph','e.g. A1'),
('fr','ch_source','Microphone / DI'), ('fr','ch_patch','Entrée'), ('fr','ch_patch_ph','p. ex. A1'),
('es','ch_source','Micrófono / DI'), ('es','ch_patch','Entrada'), ('es','ch_patch_ph','p. ej. A1'),
('nl','ch_source','Microfoon / DI'), ('nl','ch_patch','Ingang'), ('nl','ch_patch_ph','bijv. A1'),
('it','ch_source','Microfono / DI'), ('it','ch_patch','Ingresso'), ('it','ch_patch_ph','es. A1')
ON DUPLICATE KEY UPDATE value = value;
