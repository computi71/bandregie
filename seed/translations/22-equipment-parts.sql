-- Translations for equipment grouping and attachments.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_parent','Belongs to'),('en','eq_parent_none','– standalone –'),
('en','eq_slot','Slot / channel'),('en','eq_slot_ph','e.g. channel 1'),
('en','eq_parts','Parts'),('en','eq_part_of','Part of'),('en','eq_images','Images and documents'),
('nl','eq_parent','Hoort bij'),('nl','eq_parent_none','– losstaand –'),
('nl','eq_slot','Slot / kanaal'),('nl','eq_slot_ph','bijv. kanaal 1'),
('nl','eq_parts','Onderdelen'),('nl','eq_part_of','Onderdeel van'),('nl','eq_images','Afbeeldingen en documenten'),
('fr','eq_parent','Rattaché à'),('fr','eq_parent_none','– autonome –'),
('fr','eq_slot','Emplacement / canal'),('fr','eq_slot_ph','p. ex. canal 1'),
('fr','eq_parts','Composants'),('fr','eq_part_of','Composant de'),('fr','eq_images','Images et documents'),
('es','eq_parent','Pertenece a'),('es','eq_parent_none','– independiente –'),
('es','eq_slot','Ranura / canal'),('es','eq_slot_ph','p. ej. canal 1'),
('es','eq_parts','Componentes'),('es','eq_part_of','Componente de'),('es','eq_images','Imágenes y documentos'),
('it','eq_parent','Appartiene a'),('it','eq_parent_none','– autonomo –'),
('it','eq_slot','Slot / canale'),('it','eq_slot_ph','es. canale 1'),
('it','eq_parts','Componenti'),('it','eq_part_of','Componente di'),('it','eq_images','Immagini e documenti')
ON DUPLICATE KEY UPDATE value = value;
