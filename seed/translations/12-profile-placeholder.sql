-- Translation of the stage name placeholder on the profile page.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_stage_name_ph','Name on stage'),
('nl','prof_stage_name_ph','Naam op het podium'),
('fr','prof_stage_name_ph','Nom de scène'),
('es','prof_stage_name_ph','Nombre en el escenario'),
('it','prof_stage_name_ph','Nome sul palco')
ON DUPLICATE KEY UPDATE value = VALUES(value);
