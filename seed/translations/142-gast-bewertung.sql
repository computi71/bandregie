-- Bewertung der Gäste je Einsatz (#294, Schritt 3)
INSERT INTO translations (lang, tkey, value) VALUES
('en','guest_rate','Rate'),('en','guest_rating','Rating'),('en','guest_rating_hint','How was working together? Visible to the band only.'),
('en','guest_rating_none','not rated yet'),('en','guest_rating_comment_ph','a line about it (optional)'),('en','fl_guest_rated','Rating saved.'),
('nl','guest_rate','Beoordelen'),('nl','guest_rating','Beoordeling'),('nl','guest_rating_hint','Hoe was de samenwerking? Alleen zichtbaar voor de band.'),
('nl','guest_rating_none','nog niet beoordeeld'),('nl','guest_rating_comment_ph','een regel erbij (optioneel)'),('nl','fl_guest_rated','Beoordeling opgeslagen.'),
('fr','guest_rate','Noter'),('fr','guest_rating','Note'),('fr','guest_rating_hint','Comment s''est passée la collaboration ? Visible du groupe seulement.'),
('fr','guest_rating_none','pas encore noté'),('fr','guest_rating_comment_ph','une ligne à ce sujet (facultatif)'),('fr','fl_guest_rated','Note enregistrée.'),
('es','guest_rate','Valorar'),('es','guest_rating','Valoración'),('es','guest_rating_hint','¿Cómo fue la colaboración? Solo visible para la banda.'),
('es','guest_rating_none','aún sin valorar'),('es','guest_rating_comment_ph','una línea al respecto (opcional)'),('es','fl_guest_rated','Valoración guardada.'),
('it','guest_rate','Valuta'),('it','guest_rating','Valutazione'),('it','guest_rating_hint','Com''è andata la collaborazione? Visibile solo alla band.'),
('it','guest_rating_none','non ancora valutato'),('it','guest_rating_comment_ph','una riga a riguardo (facoltativa)'),('it','fl_guest_rated','Valutazione salvata.')
ON DUPLICATE KEY UPDATE value = value;
