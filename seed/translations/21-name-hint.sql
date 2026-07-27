-- Translation for the display-name hint in the member forms.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','mem_name_hint','Shown as "first name last name" — or the stage name if one is set.'),
('nl','mem_name_hint','Weergegeven als "voornaam achternaam" — of de artiestennaam als die is ingevuld.'),
('fr','mem_name_hint','Affiché sous la forme « prénom nom » — ou le nom de scène s''il est renseigné.'),
('es','mem_name_hint','Se muestra como «nombre apellido» — o el nombre artístico si está definido.'),
('it','mem_name_hint','Mostrato come «nome cognome» — oppure il nome d''arte, se impostato.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
