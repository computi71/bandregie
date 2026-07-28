-- Income tax as it reaches the individual musician.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','fin_own_title','Your own entries'),
('en','fin_own_hint','What you booked privately is visible to you alone — added up here, because it counts for your own return and not for the band''s.'),
('fr','fin_own_title','Vos écritures privées'),
('fr','fin_own_hint','Ce que vous avez inscrit à titre privé n''est visible que par vous — additionné ici, parce que cela compte pour votre déclaration et non pour celle du groupe.'),
('es','fin_own_title','Tus propios apuntes'),
('es','fin_own_hint','Lo que has anotado en privado solo lo ves tú; aquí sumado, porque cuenta para tu declaración y no para la del grupo.'),
('nl','fin_own_title','Je eigen boekingen'),
('nl','fin_own_hint','Wat je privé hebt geboekt zie alleen jij — hier opgeteld, omdat het voor jouw eigen aangifte telt en niet voor die van de band.'),
('it','fin_own_title','Le tue registrazioni'),
('it','fin_own_hint','Quello che hai registrato in privato lo vedi solo tu: sommato qui, perché conta per la tua dichiarazione e non per quella della band.')
ON DUPLICATE KEY UPDATE value = value;
