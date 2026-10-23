-- Lieder direkt am Termin, für Proben (#390).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_songs','Songs'),
('en','ev_songs_hint','What this rehearsal is working on. Pick several.'),
('nl','ev_songs','Nummers'),
('nl','ev_songs_hint','Waaraan tijdens deze repetitie wordt gewerkt. Meerdere mogelijk.'),
('fr','ev_songs','Morceaux'),
('fr','ev_songs_hint','Ce sur quoi porte cette répétition. Choix multiple.'),
('es','ev_songs','Canciones'),
('es','ev_songs_hint','En qué se trabaja en este ensayo. Selección múltiple.'),
('it','ev_songs','Brani'),
('it','ev_songs_hint','Su cosa si lavora in questa prova. Scelta multipla.')
ON DUPLICATE KEY UPDATE value = value;

-- Der Hilfeabsatz dazu (#390).
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_termine_6','A rehearsal has a "Songs" field instead of the setlist: pick what you are working on. A gig has a programme with order, breaks and encores — that is what the setlist is for. A rehearsal has no running order, it has a focus, and making a setlist for that would fill the list of programmes with notes nobody ever puts up. The songs then sit on the event card, and the microphone next to them opens the teleprompter, which pages through exactly those songs. Take the event offline and they come along.'),
('nl','help_termine_6','Bij een repetitie staat in plaats van de setlist een veld „Nummers": daar kies je waaraan gewerkt wordt. Een optreden heeft een programma met volgorde, pauzes en toegiften — daarvoor is de setlist. Een repetitie heeft geen verloop maar een focus, en daarvoor een setlist aanmaken zou de lijst met programma''s vullen met briefjes die niemand ophangt. De nummers staan daarna op de afspraakkaart, en de microfoon ernaast opent de teleprompter, die juist door die nummers bladert. Neem je de afspraak offline mee, dan gaan ze mee.'),
('fr','help_termine_6','Une répétition propose un champ « Morceaux » à la place de la setlist : vous y choisissez ce sur quoi vous travaillez. Un concert a un programme avec ordre, pauses et rappels — c''est à cela que sert la setlist. Une répétition n''a pas de déroulé mais un objet, et créer une setlist pour cela remplirait la liste des programmes de feuilles que personne n''affiche. Les morceaux figurent ensuite sur la fiche, et le micro à côté ouvre le prompteur, qui fait défiler précisément ces morceaux. Emportez la date hors ligne et ils suivent.'),
('es','help_termine_6','En un ensayo aparece un campo «Canciones» en lugar de la lista: ahí elegís en qué se trabaja. Un concierto tiene un programa con orden, pausas y bises — para eso está la lista. Un ensayo no tiene desarrollo sino un foco, y crear una lista para eso llenaría el listado de programas con papeles que nadie cuelga. Las canciones quedan luego en la ficha, y el micrófono al lado abre el teleprónter, que pasa justo por esas canciones. Si os lleváis la cita sin conexión, van con ella.'),
('it','help_termine_6','In una prova compare un campo «Brani» al posto della scaletta: lì scegliete su cosa si lavora. Un concerto ha un programma con ordine, pause e bis — a questo serve la scaletta. Una prova non ha uno svolgimento ma un obiettivo, e creare una scaletta per quello riempirebbe l''elenco dei programmi di fogli che nessuno appende. I brani restano poi sulla scheda, e il microfono accanto apre il gobbo, che scorre proprio quei brani. Se portate la data offline, vengono con voi.')
ON DUPLICATE KEY UPDATE value = value;
