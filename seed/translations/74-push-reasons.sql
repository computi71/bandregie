-- Three reasons a subscription fails, three different ways out. One message
-- for all of them named a cause that often was not the actual one.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_push_denied','The browser blocks notifications for this site. That cannot be changed from here: tap the lock or info icon in the address bar, set notifications to "Allow" and reload the page.'),
('en','prof_push_open','The permission question was never answered. Chrome and Edge often no longer show it as a dialog, only as a small bell icon on the right of the address bar — open that and allow. Then activate here again.'),
('en','prof_push_failed','The subscription could not be created. Permission is there, something else is stuck — reload and try again.'),
('fr','prof_push_denied','Le navigateur bloque les notifications pour ce site. Cela ne se change pas d''ici : touchez l''icône de cadenas ou d''information dans la barre d''adresse, réglez les notifications sur « Autoriser » et rechargez la page.'),
('fr','prof_push_open','La demande d''autorisation est restée sans réponse. Chrome et Edge ne l''affichent souvent plus comme une fenêtre, mais seulement comme une petite cloche à droite de la barre d''adresse — ouvrez-la et autorisez. Puis activez de nouveau ici.'),
('fr','prof_push_failed','L''abonnement n''a pas pu être créé. L''autorisation est là, le blocage est ailleurs — rechargez et réessayez.'),
('es','prof_push_denied','El navegador bloquea los avisos para este sitio. Eso no se cambia desde aquí: toca el icono de candado o de información en la barra de direcciones, pon los avisos en «Permitir» y recarga la página.'),
('es','prof_push_open','La pregunta por el permiso quedó sin responder. Chrome y Edge a menudo ya no la muestran como ventana, sino solo como una campanita a la derecha de la barra de direcciones — ábrela y permite. Después actívalo aquí de nuevo.'),
('es','prof_push_failed','No se pudo crear la suscripción. El permiso está, el problema es otro — recarga e inténtalo otra vez.'),
('nl','prof_push_denied','De browser blokkeert meldingen voor deze site. Dat is hiervandaan niet te wijzigen: tik op het slot- of infopictogram in de adresbalk, zet meldingen op „Toestaan" en laad de pagina opnieuw.'),
('nl','prof_push_open','De vraag om toestemming is nooit beantwoord. Chrome en Edge tonen die vaak niet meer als venster, maar alleen als een klein belletje rechts in de adresbalk — open dat en sta toe. Activeer het daarna hier opnieuw.'),
('nl','prof_push_failed','Het abonnement kon niet worden aangemaakt. De toestemming is er, het hapert ergens anders — herlaad en probeer het nog eens.'),
('it','prof_push_denied','Il browser blocca le notifiche per questo sito. Da qui non si può cambiare: tocca l''icona del lucchetto o delle informazioni nella barra degli indirizzi, imposta le notifiche su «Consenti» e ricarica la pagina.'),
('it','prof_push_open','La richiesta di autorizzazione non ha avuto risposta. Chrome ed Edge spesso non la mostrano più come finestra, ma solo come una campanella a destra nella barra degli indirizzi — aprila e consenti. Poi attiva di nuovo qui.'),
('it','prof_push_failed','Non è stato possibile creare l''abbonamento. Il permesso c''è, si blocca altrove — ricarica e riprova.')
ON DUPLICATE KEY UPDATE value = value;
