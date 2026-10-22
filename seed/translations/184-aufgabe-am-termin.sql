-- Aufgabe direkt am Termin anlegen (#390).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_task_add','Task for this event'),
('en','ev_task_songs','Songs for it'),
('nl','ev_task_add','Taak bij deze afspraak'),
('nl','ev_task_songs','Nummers erbij'),
('fr','ev_task_add','Tâche pour cette date'),
('fr','ev_task_songs','Morceaux concernés'),
('es','ev_task_add','Tarea para esta cita'),
('es','ev_task_songs','Canciones para ello'),
('it','ev_task_add','Compito per questa data'),
('it','ev_task_songs','Brani relativi')
ON DUPLICATE KEY UPDATE value = value;

-- Der Hilfeabsatz dazu (#390).
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_aufgaben_6','A task can also be created straight from an event: "Task for this event" sits on every event card, and the event is linked already. Songs can be picked at the same time — handy for a rehearsal where you take on two numbers. The due date is pre-filled with the day of the event. After saving you are back at the event, not in the task list.'),
('nl','help_aufgaben_6','Een taak kun je ook rechtstreeks bij een afspraak aanmaken: „Taak bij deze afspraak" staat op elke afspraakkaart, en de afspraak is dan al gekoppeld. Nummers kies je er meteen bij — handig voor een repetitie waarbij jullie twee stukken oppakken. De vervaldatum staat voorgevuld op de dag van de afspraak. Na het opslaan sta je weer bij de afspraak, niet in de takenlijst.'),
('fr','help_aufgaben_6','Une tâche peut aussi se créer directement depuis une date : « Tâche pour cette date » figure sur chaque fiche, et la date est déjà liée. Les morceaux se choisissent dans la foulée — pratique pour une répétition où vous vous attaquez à deux titres. L''échéance est préremplie avec le jour de la date. Après l''enregistrement, vous revenez à la date et non à la liste des tâches.'),
('es','help_aufgaben_6','Una tarea también se puede crear directamente desde una cita: «Tarea para esta cita» aparece en cada ficha, y la cita ya queda vinculada. Las canciones se eligen de paso — práctico para un ensayo en el que os ponéis con dos temas. La fecha límite viene puesta en el día de la cita. Tras guardar volvéis a la cita, no a la lista de tareas.'),
('it','help_aufgaben_6','Un compito si può creare anche direttamente da una data: «Compito per questa data» sta su ogni scheda, e la data è già collegata. I brani si scelgono nello stesso momento — comodo per una prova in cui vi mettete su due pezzi. La scadenza è precompilata con il giorno della data. Dopo il salvataggio tornate alla data, non all''elenco dei compiti.')
ON DUPLICATE KEY UPDATE value = value;
