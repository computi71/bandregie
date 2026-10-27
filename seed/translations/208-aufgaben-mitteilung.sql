-- Mitteilung für zugewiesene Aufgaben (#421).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_topic_aufgaben','When I am made responsible for a task'),
('en','push_task_new','A task for you'),
('nl','push_topic_aufgaben','Wanneer ik verantwoordelijk word voor een taak'),
('nl','push_task_new','Een taak voor jou'),
('fr','push_topic_aufgaben','Quand on me confie une tâche'),
('fr','push_task_new','Une tâche pour toi'),
('es','push_topic_aufgaben','Cuando me hacen responsable de una tarea'),
('es','push_task_new','Una tarea para ti'),
('it','push_topic_aufgaben','Quando divento responsabile di un compito'),
('it','push_task_new','Un compito per te')
ON DUPLICATE KEY UPDATE value = value;
