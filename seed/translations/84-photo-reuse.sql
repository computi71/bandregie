-- Adopt a photo that already exists in the inventory: identical devices are
-- separate records, so the second one starts without one (#184).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_photo_reuse','Adopt an existing photo'),
('en','eq_photo_reuse_hint','Identical devices are separate records, so the second one starts without a photo. These are the pictures already in the inventory, devices with the same article number first. The file is not copied — deleting the photo on one device leaves it on the other.'),
('en','eq_photo_take','Adopt photo'),
('en','fl_eq_photo_taken','Photo adopted.'),
('en','fl_eq_photo_failed','That photo could not be adopted.'),

('nl','eq_photo_reuse','Bestaande foto overnemen'),
('nl','eq_photo_reuse_hint','Dezelfde apparaten zijn afzonderlijke items, dus het tweede begint zonder foto. Dit zijn de afbeeldingen die al in de inventaris staan, apparaten met hetzelfde artikelnummer eerst. Het bestand wordt niet gekopieerd — verwijder je de foto bij het ene apparaat, dan blijft die bij het andere staan.'),
('nl','eq_photo_take','Foto overnemen'),
('nl','fl_eq_photo_taken','Foto overgenomen.'),
('nl','fl_eq_photo_failed','Die foto kon niet worden overgenomen.'),

('fr','eq_photo_reuse','Reprendre une photo existante'),
('fr','eq_photo_reuse_hint','Deux appareils identiques sont deux fiches, la seconde démarre donc sans photo. Voici les images déjà présentes dans l''inventaire, celles des appareils portant le même numéro d''article en premier. Le fichier n''est pas copié : supprimer la photo sur un appareil la laisse sur l''autre.'),
('fr','eq_photo_take','Reprendre la photo'),
('fr','fl_eq_photo_taken','Photo reprise.'),
('fr','fl_eq_photo_failed','Cette photo n''a pas pu être reprise.'),

('es','eq_photo_reuse','Adoptar una foto existente'),
('es','eq_photo_reuse_hint','Dos aparatos iguales son dos fichas, así que la segunda empieza sin foto. Estas son las imágenes que ya hay en el inventario, primero las de aparatos con el mismo número de artículo. El archivo no se copia: si borras la foto en un aparato, sigue estando en el otro.'),
('es','eq_photo_take','Adoptar la foto'),
('es','fl_eq_photo_taken','Foto adoptada.'),
('es','fl_eq_photo_failed','No se pudo adoptar esa foto.'),

('it','eq_photo_reuse','Riprendi una foto esistente'),
('it','eq_photo_reuse_hint','Due apparecchi identici sono due schede, quindi la seconda parte senza foto. Queste sono le immagini già presenti nell''inventario, prima quelle degli apparecchi con lo stesso codice articolo. Il file non viene copiato: se elimini la foto su un apparecchio, resta sull''altro.'),
('it','eq_photo_take','Riprendi la foto'),
('it','fl_eq_photo_taken','Foto ripresa.'),
('it','fl_eq_photo_failed','Non è stato possibile riprendere quella foto.')
ON DUPLICATE KEY UPDATE value = value;
