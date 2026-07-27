-- Translation for the note that ownership fields are restricted.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','eq_owner_locked','Price, owner and purchase date can only be changed by the owner and by an admin. Moving the device counts too — through its parent the owner would change with it.'),
('fr','eq_owner_locked','Le prix, le propriétaire et la date d''achat ne peuvent être modifiés que par le propriétaire et par un administrateur. Déplacer l''appareil en fait partie : via l''appareil parent, le propriétaire changerait avec lui.'),
('es','eq_owner_locked','El precio, el propietario y la fecha de compra solo los pueden cambiar el propietario y un administrador. Mover el aparato también cuenta: a través del aparato superior cambiaría el propietario con él.'),
('nl','eq_owner_locked','Prijs, eigenaar en aankoopdatum kunnen alleen door de eigenaar en een beheerder worden gewijzigd. Verplaatsen hoort daarbij — via het bovenliggende apparaat zou de eigenaar meeveranderen.'),
('it','eq_owner_locked','Prezzo, proprietario e data di acquisto li cambiano solo il proprietario e un amministratore. Spostare l''apparecchio ne fa parte: tramite l''apparecchio superiore cambierebbe anche il proprietario.')
ON DUPLICATE KEY UPDATE value = value;
