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
('it','topic_send_none','Inviare? Al momento nessuno tranne te può leggere questo messaggio.')
ON DUPLICATE KEY UPDATE value = value;
