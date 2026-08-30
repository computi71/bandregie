-- Postfach statt Post, und der Versand als eigener Bereich (#270)
-- Der DELETE ist gewacht: Er trifft nur die mitgelieferten alten Werte. Wer die
-- Zeile selbst uebersetzt hat, behaelt seine Fassung.
DELETE FROM translations WHERE tkey = 'inav_post'
  AND value IN ('Mail', 'Post', 'Courrier', 'Correo', 'Posta');
INSERT INTO translations (lang, tkey, value) VALUES
('en','inav_post','Mailbox'),
('nl','inav_post','Postvak'),
('fr','inav_post','Boîte mail'),
('es','inav_post','Buzón'),
('it','inav_post','Casella di posta'),
('en','inav_mailversand','Sending mail'),
('nl','inav_mailversand','Mail versturen'),
('fr','inav_mailversand','Envoi de courrier'),
('es','inav_mailversand','Envío de correo'),
('it','inav_mailversand','Invio di posta')
ON DUPLICATE KEY UPDATE value = value;
