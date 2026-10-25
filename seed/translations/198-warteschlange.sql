-- Mitteilungen aus der Warteschlange, gesammelt (#383).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_att_many','%1 new replies to %2'),
('nl','push_att_many','%1 nieuwe antwoorden op %2'),
('fr','push_att_many','%1 nouvelles réponses pour %2'),
('es','push_att_many','%1 respuestas nuevas a %2'),
('it','push_att_many','%1 nuove risposte per %2'),
('en','sys_pushqueue','Sending notifications'),
('en','sys_pushqueue_stuck','%1 notifications have been stuck for over 15 minutes'),
('en','sys_pushqueue_hint','Notifications are queued and picked up by a run every minute. If the queue stands still, that run is missing. Add: * * * * * php /path/to/app/bin/push-senden.php — until then the app accepts notifications but sends none.'),
('nl','sys_pushqueue','Versturen van meldingen'),
('nl','sys_pushqueue_stuck','%1 meldingen staan al meer dan 15 minuten vast'),
('nl','sys_pushqueue_hint','Meldingen komen in een wachtrij die elke minuut wordt opgehaald. Staat de rij stil, dan ontbreekt die taak. Toe te voegen: * * * * * php /pad/naar/app/bin/push-senden.php — tot dan neemt de app meldingen aan maar verstuurt er geen.'),
('fr','sys_pushqueue','Envoi des notifications'),
('fr','sys_pushqueue_stuck','%1 notifications sont bloquées depuis plus de 15 minutes'),
('fr','sys_pushqueue_hint','Les notifications sont mises en file et relevées par une tâche chaque minute. Si la file ne bouge pas, cette tâche manque. À ajouter : * * * * * php /chemin/vers/app/bin/push-senden.php — d’ici là l’application accepte les notifications mais n’en envoie aucune.'),
('es','sys_pushqueue','Envío de avisos'),
('es','sys_pushqueue_stuck','%1 avisos llevan más de 15 minutos atascados'),
('es','sys_pushqueue_hint','Los avisos se ponen en cola y los recoge una tarea cada minuto. Si la cola no avanza, falta esa tarea. Hay que añadir: * * * * * php /ruta/a/la/app/bin/push-senden.php — hasta entonces la aplicación acepta avisos pero no envía ninguno.'),
('it','sys_pushqueue','Invio delle notifiche'),
('it','sys_pushqueue_stuck','%1 notifiche sono ferme da oltre 15 minuti'),
('it','sys_pushqueue_hint','Le notifiche vanno in coda e vengono prelevate da un’esecuzione ogni minuto. Se la coda resta ferma, manca quell’esecuzione. Da aggiungere: * * * * * php /percorso/app/bin/push-senden.php — fino ad allora l’applicazione accetta notifiche ma non ne invia.')
ON DUPLICATE KEY UPDATE value = value;
