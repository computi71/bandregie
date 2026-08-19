-- Teleprompter für die ganze Setliste (#254)
INSERT INTO translations (lang, tkey, value) VALUES
('en','sl_prompter','Teleprompter'),
('en','sl_prompter_hint','Starts at the first song with lyrics, the whole setlist behind it. At the end of a song the next one comes up by itself — a tap on the text starts it.'),
('nl','sl_prompter','Teleprompter'),
('nl','sl_prompter_hint','Begint bij het eerste nummer met tekst, met de hele setlijst erachter. Aan het eind van een nummer komt het volgende er zelf bovenaan — een tik op de tekst start het.'),
('fr','sl_prompter','Prompteur'),
('fr','sl_prompter_hint','Commence au premier morceau qui a des paroles, avec toute la setlist derriere. A la fin d''un morceau, le suivant arrive de lui-meme — une touche sur le texte le lance.'),
('es','sl_prompter','Teleprompter'),
('es','sl_prompter_hint','Empieza con la primera cancion que tiene letra, con toda la lista detras. Al acabar una cancion aparece la siguiente por si sola: un toque en el texto la pone en marcha.'),
('it','sl_prompter','Teleprompter'),
('it','sl_prompter_hint','Parte dal primo pezzo che ha un testo, con tutta la scaletta dietro. Alla fine di un pezzo arriva il successivo da solo: un tocco sul testo lo avvia.')
ON DUPLICATE KEY UPDATE value = value;
