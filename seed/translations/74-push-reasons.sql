-- Three reasons a subscription fails, three different ways out. One message
-- for all of them named a cause that often was not the actual one.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_push_failed','The subscription could not be created. Permission is there, something else is stuck — reload and try again.'),
('fr','prof_push_failed','L''abonnement n''a pas pu être créé. L''autorisation est là, le blocage est ailleurs — rechargez et réessayez.'),
('es','prof_push_failed','No se pudo crear la suscripción. El permiso está, el problema es otro — recarga e inténtalo otra vez.'),
('nl','prof_push_failed','Het abonnement kon niet worden aangemaakt. De toestemming is er, het hapert ergens anders — herlaad en probeer het nog eens.'),
('it','prof_push_failed','Non è stato possibile creare l''abbonamento. Il permesso c''è, si blocca altrove — ricarica e riprova.')
ON DUPLICATE KEY UPDATE value = value;
