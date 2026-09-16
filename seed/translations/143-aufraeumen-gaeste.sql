-- Aufräumen nach den Gästen (#294): der Text „Du hast abgesagt" hatte nie eine
-- Stelle — nach der Absage ist der Link ungültig, die Seite dazu ist die 404.
DELETE FROM translations WHERE tkey = 'gast_declined';
