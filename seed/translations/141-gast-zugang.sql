-- Gastzugang: Rider, Setliste und Lieder hinter dem Link (#294, Schritt 2).
-- Der Dank nach der Zusage versprach „bald" — jetzt steht es da; die alte Fassung weicht.
DELETE FROM translations WHERE tkey = 'gast_thanks_yes' AND lang IN ('en','nl','fr','es','it');
INSERT INTO translations (lang, tkey, value) VALUES
('en','gast_thanks_yes','Thanks for confirming! From now on this link is your access for the event — schedule, rider and setlist are right below.'),
('en','gast_for_evening','For the evening'),('en','gast_rider','Stage rider with channel list and stage plan'),('en','gast_setlist','Setlist to print'),
('en','gast_songs','The songs'),('en','gast_no_setlist','The setlist is not fixed yet.'),('en','gast_back','Back to the event'),('en','gast_no_lyrics','No lyrics stored for this song.'),
('nl','gast_thanks_yes','Bedankt voor je toezegging! Vanaf nu is deze link je toegang voor de datum — verloop, rider en setlist staan hieronder.'),
('nl','gast_for_evening','Voor de avond'),('nl','gast_rider','Stagerider met kanaallijst en podiumplan'),('nl','gast_setlist','Setlist om te printen'),
('nl','gast_songs','De nummers'),('nl','gast_no_setlist','De setlist staat nog niet vast.'),('nl','gast_back','Terug naar de datum'),('nl','gast_no_lyrics','Voor dit nummer is geen tekst opgeslagen.'),
('fr','gast_thanks_yes','Merci pour ta confirmation ! Ce lien est désormais ton accès pour la date — déroulé, rider et setlist sont juste en dessous.'),
('fr','gast_for_evening','Pour la soirée'),('fr','gast_rider','Rider technique avec patch et plan de scène'),('fr','gast_setlist','Setlist à imprimer'),
('fr','gast_songs','Les morceaux'),('fr','gast_no_setlist','La setlist n''est pas encore fixée.'),('fr','gast_back','Retour à la date'),('fr','gast_no_lyrics','Pas de paroles enregistrées pour ce morceau.'),
('es','gast_thanks_yes','¡Gracias por confirmar! Desde ahora este enlace es tu acceso para la fecha: horario, rider y lista de canciones están aquí abajo.'),
('es','gast_for_evening','Para la noche'),('es','gast_rider','Rider técnico con lista de canales y plano de escenario'),('es','gast_setlist','Lista de canciones para imprimir'),
('es','gast_songs','Las canciones'),('es','gast_no_setlist','La lista de canciones aún no está fijada.'),('es','gast_back','Volver a la fecha'),('es','gast_no_lyrics','No hay letra guardada para esta canción.'),
('it','gast_thanks_yes','Grazie per la conferma! Da ora questo link è il tuo accesso per la data: orari, rider e setlist sono qui sotto.'),
('it','gast_for_evening','Per la serata'),('it','gast_rider','Rider tecnico con lista canali e piano palco'),('it','gast_setlist','Setlist da stampare'),
('it','gast_songs','I brani'),('it','gast_no_setlist','La setlist non è ancora fissata.'),('it','gast_back','Torna alla data'),('it','gast_no_lyrics','Nessun testo salvato per questo brano.')
ON DUPLICATE KEY UPDATE value = value;
