-- Rückfrage vor dem Abschicken eines Chatbeitrags (#380).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','topic_send_many','Send it? %1 people can read this post. Whoever has notifications switched on gets this on their phone:'),
('en','topic_send_one','Send it? One other person can read this post. If they have notifications switched on, this is what reaches their phone:'),
('en','topic_send_none','Send it? Right now nobody but you can read this post.'),
('nl','topic_send_many','Versturen? %1 mensen kunnen dit bericht lezen. Wie meldingen aan heeft, krijgt dit op de telefoon:'),
('nl','topic_send_one','Versturen? Eén andere persoon kan dit bericht lezen. Heeft die meldingen aan, dan komt dit op de telefoon aan:'),
('nl','topic_send_none','Versturen? Op dit moment kan niemand behalve jij dit bericht lezen.'),
('fr','topic_send_many','Envoyer ? %1 personnes peuvent lire ce message. Qui a les notifications activées reçoit ceci sur son téléphone :'),
('fr','topic_send_one','Envoyer ? Une autre personne peut lire ce message. Si elle a les notifications activées, voici ce qui arrive sur son téléphone :'),
('fr','topic_send_none','Envoyer ? Pour l’instant, personne d’autre que vous ne peut lire ce message.'),
('es','topic_send_many','¿Enviar? %1 personas pueden leer esta publicación. Quien tenga los avisos activados recibe esto en el móvil:'),
('es','topic_send_one','¿Enviar? Otra persona puede leer esta publicación. Si tiene los avisos activados, esto es lo que le llega al móvil:'),
('es','topic_send_none','¿Enviar? Ahora mismo nadie aparte de ti puede leer esta publicación.'),
('it','topic_send_many','Inviare? %1 persone possono leggere questo messaggio. Chi ha le notifiche attive riceve questo sul telefono:'),
('it','topic_send_one','Inviare? Un’altra persona può leggere questo messaggio. Se ha le notifiche attive, sul telefono le arriva questo:'),
('it','topic_send_none','Inviare? Al momento nessuno tranne te può leggere questo messaggio.'),
('en','help_themen_5','Before it goes out the app asks once more — every time, not by a rule nobody can see. It tells you how many people can read the post, and shows exactly the opening that lands as a notification on their phones: the first 120 characters with your name in front. That is the part most of them will read, and the only part you otherwise never get to see beforehand. Mistyped? Cancel stops the send and you can keep writing.'),
('nl','help_themen_5','Voor het versturen vraagt de app nog één keer na — elke keer, niet volgens een regel die niemand ziet. Ze zegt hoeveel mensen het bericht kunnen lezen en laat precies het begin zien dat als melding op hun telefoon terechtkomt: de eerste 120 tekens met jouw naam ervoor. Dat is het deel dat de meesten lezen, en het enige deel dat je anders vooraf niet te zien krijgt. Vertypt? Met annuleren stopt het versturen en kun je verder schrijven.'),
('fr','help_themen_5','Avant l’envoi, l’application demande encore une fois — chaque fois, pas selon une règle que personne ne voit. Elle vous dit combien de personnes peuvent lire le message et montre exactement le début qui arrive comme notification sur leurs téléphones : les 120 premiers caractères avec votre nom devant. C’est la partie que la plupart liront, et la seule que vous ne voyez sinon jamais à l’avance. Une faute de frappe ? Annuler interrompt l’envoi et vous pouvez continuer à écrire.'),
('es','help_themen_5','Antes de enviarlo la aplicación vuelve a preguntar — cada vez, no según una regla que nadie ve. Te dice cuántas personas pueden leer la publicación y muestra exactamente el comienzo que llega como aviso a sus móviles: los primeros 120 caracteres con tu nombre delante. Esa es la parte que la mayoría leerá, y la única que de otro modo nunca ves antes. ¿Errata? Cancelar detiene el envío y puedes seguir escribiendo.'),
('it','help_themen_5','Prima dell’invio l’applicazione chiede ancora una volta — ogni volta, non secondo una regola che nessuno vede. Ti dice quante persone possono leggere il messaggio e mostra esattamente l’inizio che arriva come notifica sui loro telefoni: i primi 120 caratteri con il tuo nome davanti. È la parte che i più leggeranno, e l’unica che altrimenti non vedi mai prima. Errore di battitura? Annulla interrompe l’invio e puoi continuare a scrivere.')
ON DUPLICATE KEY UPDATE value = value;
