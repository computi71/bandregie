-- Was der Link eines Gasts zeigt, und was er selbst beantworten kann
-- (#449, #450, #451, #452).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','guest_shows','What he sees'),
('nl','guest_shows','Wat hij ziet'),
('fr','guest_shows','Ce qu''il voit'),
('es','guest_shows','Lo que ve'),
('it','guest_shows','Che cosa vede'),
('en','guest_shows_hint','What his link shows. Date, place and meeting time he always sees — that is what you are inviting him for.'),
('nl','guest_shows_hint','Wat zijn link laat zien. Datum, plaats en verzameltijd ziet hij altijd — daarvoor nodig je hem uit.'),
('fr','guest_shows_hint','Ce que son lien affiche. La date, le lieu et l''heure de rendez-vous, il les voit toujours — c''est pour cela que vous l''invitez.'),
('es','guest_shows_hint','Lo que muestra su enlace. La fecha, el lugar y la hora de encuentro los ve siempre: para eso lo invitáis.'),
('it','guest_shows_hint','Che cosa mostra il suo link. Data, luogo e orario di ritrovo li vede sempre: è per questo che lo invitate.'),
('en','guest_show_rider','Stage rider'),
('nl','guest_show_rider','Stagerider'),
('fr','guest_show_rider','Fiche technique'),
('es','guest_show_rider','Rider técnico'),
('it','guest_show_rider','Scheda tecnica'),
('en','guest_show_setlist','Setlist and lyrics'),
('nl','guest_show_setlist','Setlist en songteksten'),
('fr','guest_show_setlist','Setlist et paroles'),
('es','guest_show_setlist','Repertorio y letras'),
('it','guest_show_setlist','Scaletta e testi'),
('en','guest_show_songs','Song list to tick'),
('nl','guest_show_songs','Liedlijst om aan te kruisen'),
('fr','guest_show_songs','Liste des morceaux à cocher'),
('es','guest_show_songs','Lista de canciones para marcar'),
('it','guest_show_songs','Elenco dei brani da spuntare'),
('en','fl_guest_shows_saved','Saved — his link now shows what is ticked.'),
('nl','fl_guest_shows_saved','Opgeslagen — zijn link toont vanaf nu wat is aangevinkt.'),
('fr','fl_guest_shows_saved','Enregistré — son lien affiche désormais ce qui est coché.'),
('es','fl_guest_shows_saved','Guardado: su enlace muestra a partir de ahora lo marcado.'),
('it','fl_guest_shows_saved','Salvato: il suo link mostra d''ora in poi ciò che è spuntato.')
ON DUPLICATE KEY UPDATE value = value;
