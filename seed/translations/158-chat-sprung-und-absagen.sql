-- Sprung an die erste ungelesene Stelle (#318), Absagen am Ort einklappen (#319)
INSERT INTO translations (lang, tkey, value) VALUES
('en','topic_new_from_here','New from here'),
('en','venues_events_cancelled','Cancelled events'),
('nl','topic_new_from_here','Vanaf hier nieuw'),
('nl','venues_events_cancelled','Afgezegde afspraken'),
('fr','topic_new_from_here','Nouveau à partir d''ici'),
('fr','venues_events_cancelled','Dates annulées'),
('es','topic_new_from_here','Nuevo a partir de aquí'),
('es','venues_events_cancelled','Eventos cancelados'),
('it','topic_new_from_here','Nuovo da qui'),
('it','venues_events_cancelled','Date annullate')
ON DUPLICATE KEY UPDATE value = value;

-- Die Hilfe dazu, als eigene Absätze: die schon ausgelieferten bleiben stehen.
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_themen_4','On the overview every topic with something new gets a line of its own. The way in does not drop you at the top but at the first place you have not read — a mark there says "New from here". In a long thread you do not have to hunt for it.'),
('en','help_orte_4','Cancelled dates do not sit between the ones you played; they are folded away below, with their number beside them. You can open them at any time — nothing is gone.'),
('nl','help_themen_4','Op het overzicht krijgt elk onderwerp met iets nieuws een eigen regel. De weg naar binnen brengt je niet naar het begin maar naar de eerste plek die je nog niet gelezen hebt — daar staat een markering "Vanaf hier nieuw". In een lang gesprek hoef je er dus niet naar te zoeken.'),
('nl','help_orte_4','Afgezegde afspraken staan niet tussen de gespeelde, maar ingeklapt eronder, met hun aantal ernaast. Uitklappen kan altijd; er is niets weg.'),
('fr','help_themen_4','Sur l''aperçu, chaque sujet avec du nouveau a sa propre ligne. Le lien ne vous dépose pas au début mais au premier endroit que vous n''avez pas lu — une marque « Nouveau à partir d''ici » s''y trouve. Dans un long fil, inutile de la chercher.'),
('fr','help_orte_4','Les dates annulées ne figurent pas parmi celles que vous avez jouées : elles sont repliées en dessous, avec leur nombre à côté. On peut les déplier à tout moment ; rien n''a disparu.'),
('es','help_themen_4','En el resumen cada tema con algo nuevo tiene su propia línea. El enlace no te deja al principio sino en el primer punto que aún no has leído — allí hay una marca «Nuevo a partir de aquí». En un hilo largo no tienes que buscarla.'),
('es','help_orte_4','Los eventos cancelados no están entre los que tocasteis, sino plegados debajo, con su número al lado. Se pueden desplegar en cualquier momento; no ha desaparecido nada.'),
('it','help_themen_4','Nel riepilogo ogni argomento con qualcosa di nuovo ha una riga propria. Il collegamento non ti porta all''inizio ma al primo punto che non hai ancora letto — lì c''è un segno «Nuovo da qui». In una discussione lunga non devi cercarlo.'),
('it','help_orte_4','Le date annullate non stanno in mezzo a quelle suonate, ma ripiegate sotto, con il loro numero accanto. Si aprono quando vuoi; non è sparito nulla.')
ON DUPLICATE KEY UPDATE value = value;
