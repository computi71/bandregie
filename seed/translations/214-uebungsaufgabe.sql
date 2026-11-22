-- Ein Lied in Vorbereitung bekommt eine Übungsaufgabe für die Band, die sich
-- von selbst schließt, wenn das Lied die Vorbereitung verlässt (#439).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','song_practice_task','Practise “%1”'),
('nl','song_practice_task','„%1” oefenen'),
('fr','song_practice_task','Travailler « %1 »'),
('es','song_practice_task','Ensayar «%1»'),
('it','song_practice_task','Provare «%1»'),
('en','help_songs_4','When a song moves to “In preparation”, the band gets a task to practise it — all admins and members, not substitutes. Everyone ticks it off once they can play the song, and the song shows under “Still to do” who is still practising. When the song leaves preparation, usually for “Active repertoire”, the task closes by itself. Songs that were already in preparation before this was introduced get none; to get one, move the song away once and back.'),
('nl','help_songs_4','Gaat een nummer naar „In voorbereiding”, dan krijgt de band de taak het te oefenen — alle admins en leden, geen invallers. Iedereen vinkt hem af zodra hij het nummer kan, en bij het nummer staat onder „Nog te doen” wie nog oefent. Verlaat het nummer de voorbereiding, meestal richting „Actief repertoire”, dan sluit de taak vanzelf. Nummers die al voor de invoering in voorbereiding waren krijgen er geen; wil je er een, zet het nummer dan een keer weg en weer terug.'),
('fr','help_songs_4','Quand un morceau passe à « En préparation », le groupe reçoit la tâche de le travailler — tous les admins et membres, pas les remplaçants. Chacun la coche dès qu’il sait jouer le morceau, et le morceau indique sous « Reste à faire » qui travaille encore. Quand le morceau quitte la préparation, en général pour « Répertoire actif », la tâche se ferme d’elle-même. Les morceaux déjà en préparation avant ce changement n’en reçoivent pas ; pour en avoir une, change une fois le statut du morceau puis remets-le.'),
('es','help_songs_4','Cuando una canción pasa a «En preparación», la banda recibe la tarea de ensayarla — todos los admins y miembros, no sustitutos. Cada uno la marca en cuanto sabe tocar la canción, y en la canción aparece bajo «Pendiente» quién sigue ensayando. Cuando la canción sale de la preparación, normalmente hacia «Repertorio activo», la tarea se cierra sola. Las canciones que ya estaban en preparación antes de este cambio no reciben ninguna; para tenerla, cambia una vez el estado de la canción y vuelve a ponerlo.'),
('it','help_songs_4','Quando un brano passa a «In preparazione», la band riceve il compito di provarlo — tutti gli admin e i membri, non i sostituti. Ognuno lo spunta appena sa suonare il brano, e sul brano compare sotto «Ancora da fare» chi sta ancora provando. Quando il brano esce dalla preparazione, di solito verso «Repertorio attivo», il compito si chiude da solo. I brani già in preparazione prima di questa modifica non ne ricevono; per averne uno, cambia una volta lo stato del brano e poi rimettilo.')
ON DUPLICATE KEY UPDATE value = value;
