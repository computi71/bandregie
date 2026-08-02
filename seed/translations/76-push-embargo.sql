-- After three dismissals a browser stops asking for a week. It shows nothing,
-- and the site appears in no block list — unguessable from the outside.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_push_open','The permission question was never answered.'),
('en','prof_push_open_bell','Was there a small bell icon on the right of the address bar? Chrome and Edge often show the question only that way instead of as a dialog. Open it, allow, then activate here again.'),
('en','prof_push_open_embargo','If nothing appeared at all, the browser has stopped asking for this site: dismiss such a question three times and it will not be asked again for a week — with no notice, and the site sits in no block list while it lasts. The way around it: in the browser settings under notifications, next to "Allowed to send notifications", use "Add site" and enter this site''s address. Then activate here again.'),
('fr','prof_push_open','La demande d''autorisation est restée sans réponse.'),
('fr','prof_push_open_bell','Une petite cloche est-elle apparue à droite de la barre d''adresse ? Chrome et Edge n''affichent souvent la question que de cette façon, plutôt qu''en fenêtre. Ouvrez-la, autorisez, puis activez de nouveau ici.'),
('fr','prof_push_open_embargo','Si rien n''est apparu du tout, le navigateur a cessé de poser la question pour ce site : écartez-la trois fois et elle ne revient pas pendant une semaine — sans avertissement, et le site ne figure alors dans aucune liste de blocage. Le contournement : dans les réglages du navigateur, sous les notifications, à côté de « Autorisé à envoyer des notifications », utilisez « Ajouter un site » et saisissez l''adresse de ce site. Puis activez de nouveau ici.'),
('es','prof_push_open','La pregunta por el permiso quedó sin responder.'),
('es','prof_push_open_bell','¿Apareció una campanita a la derecha de la barra de direcciones? Chrome y Edge a menudo muestran la pregunta solo así, en lugar de como ventana. Ábrela, permite y actívalo aquí de nuevo.'),
('es','prof_push_open_embargo','Si no apareció nada en absoluto, el navegador ha dejado de preguntar por este sitio: descarta esa pregunta tres veces y no volverá a hacerla durante una semana — sin aviso, y mientras tanto el sitio no figura en ninguna lista de bloqueo. La salida: en la configuración del navegador, en avisos, junto a «Permitido enviar avisos», usa «Añadir sitio» e introduce la dirección de este sitio. Después actívalo aquí de nuevo.'),
('nl','prof_push_open','De vraag om toestemming is nooit beantwoord.'),
('nl','prof_push_open_bell','Verscheen er een klein belletje rechts in de adresbalk? Chrome en Edge tonen de vraag vaak alleen zo in plaats van als venster. Open het, sta toe en activeer het hier opnieuw.'),
('nl','prof_push_open_embargo','Verscheen er helemaal niets, dan vraagt de browser het voor deze site niet meer: wie zo''n vraag drie keer wegklikt, krijgt hem een week lang niet opnieuw — zonder melding, en de site staat ondertussen in geen enkele blokkeerlijst. De omweg: in de browserinstellingen bij meldingen, naast „Mag meldingen sturen", op „Site toevoegen" klikken en het adres van deze site invoeren. Activeer het daarna hier opnieuw.'),
('it','prof_push_open','La richiesta di autorizzazione non ha avuto risposta.'),
('it','prof_push_open_bell','È comparsa una campanella a destra nella barra degli indirizzi? Chrome ed Edge spesso mostrano la domanda solo così invece che come finestra. Aprila, consenti e attiva di nuovo qui.'),
('it','prof_push_open_embargo','Se non è comparso proprio nulla, il browser ha smesso di chiedere per questo sito: chiudi quella domanda tre volte e non verrà più posta per una settimana — senza avviso, e nel frattempo il sito non figura in alcun elenco di blocco. La scorciatoia: nelle impostazioni del browser, sotto le notifiche, accanto a «Autorizzati a inviare notifiche», usa «Aggiungi sito» e inserisci l''indirizzo di questo sito. Poi attiva di nuovo qui.')
ON DUPLICATE KEY UPDATE value = value;
