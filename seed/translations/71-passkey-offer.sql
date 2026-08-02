-- Nobody should hit a dead end: a passkey attempt that finds nothing points
-- at the form, and after signing in the offer to create one follows.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','pk_none_here','There is no passkey on this device yet. Sign in with e-mail and password — afterwards you can create one for this device in your profile.'),
('en','pk_offer_title','Next time without a password?'),
('en','pk_offer','You can set up a passkey on this device. Signing in then takes your face, your fingerprint or the device code — your password stays alongside it and keeps working.'),
('en','pk_offer_yes','Create a passkey'),
('en','pk_offer_later','Later'),
('fr','pk_none_here','Il n''y a pas encore de clé d''accès sur cet appareil. Connectez-vous avec votre e-mail et votre mot de passe — vous pourrez ensuite en créer une pour cet appareil dans votre profil.'),
('fr','pk_offer_title','La prochaine fois sans mot de passe ?'),
('fr','pk_offer','Vous pouvez créer une clé d''accès sur cet appareil. La connexion se fera alors avec votre visage, votre empreinte ou le code de l''appareil — votre mot de passe reste à côté et continue de fonctionner.'),
('fr','pk_offer_yes','Créer une clé d''accès'),
('fr','pk_offer_later','Plus tard'),
('es','pk_none_here','En este dispositivo todavía no hay ninguna clave de acceso. Inicia sesión con correo y contraseña — después podrás crear una para este dispositivo en tu perfil.'),
('es','pk_offer_title','¿La próxima vez sin contraseña?'),
('es','pk_offer','Puedes crear una clave de acceso en este dispositivo. Iniciar sesión bastará entonces con tu cara, tu huella o el código del dispositivo — tu contraseña se mantiene al lado y sigue funcionando.'),
('es','pk_offer_yes','Crear clave de acceso'),
('es','pk_offer_later','Más tarde'),
('nl','pk_none_here','Op dit apparaat staat nog geen passkey. Meld je aan met e-mail en wachtwoord — daarna kun je er in je profiel een voor dit apparaat aanmaken.'),
('nl','pk_offer_title','Volgende keer zonder wachtwoord?'),
('nl','pk_offer','Je kunt op dit apparaat een passkey aanmaken. Aanmelden gaat dan met je gezicht, je vingerafdruk of de toegangscode van het apparaat — je wachtwoord blijft ernaast bestaan en werkt gewoon door.'),
('nl','pk_offer_yes','Passkey aanmaken'),
('nl','pk_offer_later','Later'),
('it','pk_none_here','Su questo dispositivo non c''è ancora una passkey. Accedi con e-mail e password — poi potrai crearne una per questo dispositivo nel profilo.'),
('it','pk_offer_title','La prossima volta senza password?'),
('it','pk_offer','Su questo dispositivo puoi creare una passkey. Per accedere basteranno allora il volto, l''impronta o il codice del dispositivo — la password resta accanto e continua a funzionare.'),
('it','pk_offer_yes','Crea una passkey'),
('it','pk_offer_later','Più tardi')
ON DUPLICATE KEY UPDATE value = value;
