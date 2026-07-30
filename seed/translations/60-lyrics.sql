-- Lyrics are their own field: notes are for the band, the lyrics are what
-- somebody reads while singing.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','song_lyrics','Lyrics'),
('en','song_lyrics_ph','[Verse]\nFirst line\nSecond line\n\n[Chorus]\n…'),
('en','song_lyrics_hint','Put section names in square brackets on a line of their own: [Verse], [Chorus], [Bridge], [Solo]. That is enough for them to be highlighted later.'),
('en','song_read','Lyrics and sheet music'),
('en','song_no_lyrics','No lyrics have been entered for this song.'),
('en','song_edit_link','Edit'),

('fr','song_lyrics','Paroles'),
('fr','song_lyrics_ph','[Couplet]\nPremière ligne\nDeuxième ligne\n\n[Refrain]\n…'),
('fr','song_lyrics_hint','Mets les noms de section entre crochets sur une ligne à part : [Couplet], [Refrain], [Pont], [Solo]. Cela suffit pour qu''ils soient mis en évidence plus tard.'),
('fr','song_read','Paroles et partitions'),
('fr','song_no_lyrics','Aucune parole n''est enregistrée pour ce morceau.'),
('fr','song_edit_link','Modifier'),

('es','song_lyrics','Letra'),
('es','song_lyrics_ph','[Estrofa]\nPrimera línea\nSegunda línea\n\n[Estribillo]\n…'),
('es','song_lyrics_hint','Pon los nombres de las secciones entre corchetes en una línea propia: [Estrofa], [Estribillo], [Puente], [Solo]. Con eso basta para resaltarlas más adelante.'),
('es','song_read','Letra y partituras'),
('es','song_no_lyrics','No hay letra registrada para esta canción.'),
('es','song_edit_link','Editar'),

('nl','song_lyrics','Songtekst'),
('nl','song_lyrics_ph','[Couplet]\nEerste regel\nTweede regel\n\n[Refrein]\n…'),
('nl','song_lyrics_hint','Zet de namen van de delen tussen rechte haken op een eigen regel: [Couplet], [Refrein], [Bridge], [Solo]. Dat is genoeg om ze later te kunnen markeren.'),
('nl','song_read','Tekst en bladmuziek'),
('nl','song_no_lyrics','Voor dit nummer is geen tekst ingevoerd.'),
('nl','song_edit_link','Bewerken'),

('it','song_lyrics','Testo'),
('it','song_lyrics_ph','[Strofa]\nPrima riga\nSeconda riga\n\n[Ritornello]\n…'),
('it','song_lyrics_hint','Metti i nomi delle parti tra parentesi quadre su una riga a sé: [Strofa], [Ritornello], [Bridge], [Solo]. Basta questo perché possano essere evidenziate più tardi.'),
('it','song_read','Testo e spartiti'),
('it','song_no_lyrics','Per questo brano non è stato inserito alcun testo.'),
('it','song_edit_link','Modifica')
ON DUPLICATE KEY UPDATE value = value;
