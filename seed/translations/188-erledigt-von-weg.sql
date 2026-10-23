-- „Erledigt von" ist überflüssig geworden (#376).
--
-- Jeder Zuständige trägt seinen Haken jetzt selbst, also sagt eine zweite
-- Liste der Fertigen nichts mehr. Der Schlüssel ist aus den Texten und aus
-- Seed 169 verschwunden; in bestehenden Datenbanken bliebe er sonst als Zeile
-- stehen, die niemand mehr liest.
DELETE FROM translations WHERE tkey = 'task_done_by';
