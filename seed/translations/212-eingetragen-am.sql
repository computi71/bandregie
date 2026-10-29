-- Wer eingetragen hat, steht immer da — mit dem Tag (#427).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','ev_created_on','on %1'),
('nl','ev_created_on','op %1'),
('fr','ev_created_on','le %1'),
('es','ev_created_on','el %1'),
('it','ev_created_on','il %1'),
('en','help_termine_14','Every event card says who entered it and when. That is a note about an act, not a second statement about who takes care of it — which is why it stands there even when the same person is responsible. With enquiries from outside in particular, “since when do we know about this?” is a different question from “who is looking after it?”. Events from the imported past carry no such note: the application does not know who entered them back then.'),
('nl','help_termine_14','Op elke afspraakkaart staat wie hem heeft ingevoerd en wanneer. Dat is een aantekening over een handeling en geen tweede mededeling over wie zich erom bekommert — daarom staat hij er ook als dezelfde persoon verantwoordelijk is. Juist bij aanvragen van buiten is „sinds wanneer weten we ervan?” een andere vraag dan „wie regelt het?”. Bij afspraken uit het overgenomen verleden ontbreekt de aantekening: wie ze destijds heeft ingevoerd, weet de toepassing niet.'),
('fr','help_termine_14','Sur chaque fiche de date figure qui l’a saisie et quand. C’est une mention d’un acte et non une seconde indication sur qui s’en occupe — elle apparaît donc aussi lorsque la même personne est responsable. Pour les demandes venues de l’extérieur surtout, « depuis quand le savons-nous ? » est une autre question que « qui s’en charge ? ». Les dates reprises du passé n’ont pas cette mention : l’application ignore qui les avait saisies.'),
('es','help_termine_14','En cada ficha de cita consta quién la introdujo y cuándo. Es la constancia de un acto y no una segunda indicación de quién se ocupa — por eso aparece también cuando la misma persona es responsable. Sobre todo en las solicitudes de fuera, «¿desde cuándo lo sabemos?» es otra pregunta que «¿quién se encarga?». Las citas del pasado importado no llevan esa constancia: la aplicación no sabe quién las introdujo entonces.'),
('it','help_termine_14','Su ogni scheda di appuntamento c’è scritto chi lo ha inserito e quando. È l’annotazione di un atto e non una seconda indicazione su chi se ne occupa — per questo compare anche quando la stessa persona è responsabile. Soprattutto per le richieste dall’esterno, « da quando lo sappiamo? » è una domanda diversa da « chi se ne occupa? ». Gli appuntamenti della storia importata non hanno questa annotazione: l’applicazione non sa chi li avesse inseriti allora.')
ON DUPLICATE KEY UPDATE value = value;
