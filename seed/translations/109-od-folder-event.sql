-- Verknuepften Ordner einem Termin zuordnen (#21)
INSERT INTO translations (lang, tkey, value) VALUES
('en','od_folder_event','Event for this folder'),
('en','od_folder_event_none','– none –'),
('en','od_folder_event_hint','Pictures from this folder belong to that event — including the ones that arrive later. Nothing changes about the files at Microsoft.'),
('en','od_folder_event_suggest','Read out of the folder name: %1'),
('en','od_folder_event_set','Folder assigned to the event. %1 picture(s) without an event took it over.'),
('en','od_folder_event_cleared','Assignment removed. The pictures already assigned stay where they are.'),

('nl','od_folder_event','Afspraak van deze map'),
('nl','od_folder_event_none','– geen –'),
('nl','od_folder_event_hint','Foto''s uit deze map horen bij die afspraak — ook die er later bij komen. Aan de bestanden bij Microsoft verandert niets.'),
('nl','od_folder_event_suggest','Uit de mapnaam opgemaakt: %1'),
('nl','od_folder_event_set','Map aan de afspraak gekoppeld. %1 foto(s) zonder afspraak hebben die overgenomen.'),
('nl','od_folder_event_cleared','Koppeling opgeheven. De al gekoppelde foto''s blijven waar ze zijn.'),

('fr','od_folder_event','Date de ce dossier'),
('fr','od_folder_event_none','– aucune –'),
('fr','od_folder_event_hint','Les images de ce dossier appartiennent a cette date — y compris celles qui arriveront plus tard. Rien ne change aux fichiers chez Microsoft.'),
('fr','od_folder_event_suggest','Deduit du nom du dossier : %1'),
('fr','od_folder_event_set','Dossier rattache a la date. %1 image(s) sans date l''ont reprise.'),
('fr','od_folder_event_cleared','Rattachement supprime. Les images deja rattachees restent ou elles sont.'),

('es','od_folder_event','Fecha de esta carpeta'),
('es','od_folder_event_none','– ninguna –'),
('es','od_folder_event_hint','Las imagenes de esta carpeta pertenecen a esa fecha, tambien las que lleguen despues. No cambia nada en los archivos de Microsoft.'),
('es','od_folder_event_suggest','Deducido del nombre de la carpeta: %1'),
('es','od_folder_event_set','Carpeta asignada a la fecha. %1 imagen(es) sin fecha la han tomado.'),
('es','od_folder_event_cleared','Asignacion deshecha. Las imagenes ya asignadas se quedan donde estan.'),

('it','od_folder_event','Data di questa cartella'),
('it','od_folder_event_none','– nessuna –'),
('it','od_folder_event_hint','Le immagini di questa cartella appartengono a quella data, comprese quelle che arriveranno poi. Nulla cambia nei file su Microsoft.'),
('it','od_folder_event_suggest','Dedotto dal nome della cartella: %1'),
('it','od_folder_event_set','Cartella collegata alla data. %1 immagine/i senza data l''hanno presa.'),
('it','od_folder_event_cleared','Collegamento sciolto. Le immagini gia collegate restano dove sono.')
ON DUPLICATE KEY UPDATE value = value;
