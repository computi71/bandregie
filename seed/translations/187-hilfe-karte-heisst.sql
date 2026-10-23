-- Die Übersichtskarte beim Namen nennen (#387).
--
-- help_termine_5 zitierte eine Überschrift, die es so nicht gibt: Die Karte
-- heißt „Open tasks“, nicht „Open work“, und in vier weiteren Sprachen stand
-- ebenfalls ein Name, den dort niemand liest. Seit #387 trägt sie außerdem
-- einen zweiten Namen, wenn jemand nur die Termine sehen darf.
--
-- Der früheste Seed gewinnt (ON DUPLICATE KEY UPDATE value = value), also muss
-- der alte Satz einmal weg, sonst bliebe der falsche Name stehen.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'help_termine_5'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'help_termine_5_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('help_termine_5_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','help_termine_5','You can answer straight from the overview: every event you have not spoken about yet is listed there with the three buttons beside it — and after them, who else has already said yes, no or maybe. So you see where the band stands without opening the event. The card is called "Open tasks" — or "Answers still missing", if you cannot see the tasks at all. Once you have answered, your answer stays visible for a moment and the row then folds away, because it is no longer open. To change your mind afterwards, go to the event itself, where the three buttons are always there.'),
('nl','help_termine_5','Toezeggen of afzeggen kan ook vanuit het overzicht: daar staat elke afspraak waarover je je nog niet hebt uitgelaten, met de drie knoppen ernaast — en daarachter wie van de anderen al ja, nee of misschien heeft gekozen. Zo zie je waar de band staat zonder de afspraak open te klappen. De kaart heet „Open taken" — en „Openstaande reacties", als je de taken helemaal niet ziet. Heb je geantwoord, dan blijft je antwoord even staan en klapt de regel daarna dicht, want hij is niet meer open. Van gedachten veranderen doe je daarna bij de afspraak zelf, daar staan de drie knoppen altijd.'),
('fr','help_termine_5','Vous pouvez répondre directement depuis l''aperçu : chaque date sur laquelle vous ne vous êtes pas encore prononcé y figure avec les trois boutons à côté — et derrière eux, qui parmi les autres a déjà dit oui, non ou peut-être. Vous voyez donc où en est le groupe sans ouvrir la date. La carte s''appelle « Tâches ouvertes » — et « Réponses en attente », si vous ne voyez pas les tâches du tout. Une fois votre réponse donnée, elle reste visible un instant puis la ligne se replie, car elle n''est plus en attente. Pour changer d''avis ensuite, allez à la date elle-même : les trois boutons y sont toujours.'),
('es','help_termine_5','También puedes responder desde el resumen: allí aparece cada cita sobre la que aún no te has pronunciado, con los tres botones al lado — y detrás, quién de los demás ya ha dicho sí, no o quizá. Así ves dónde está la banda sin abrir la cita. La tarjeta se llama «Tareas abiertas» — y «Respuestas pendientes», si no ves las tareas en absoluto. Una vez respondido, tu respuesta se queda visible un momento y la fila se pliega después, porque ya no está pendiente. Para cambiar de idea, ve luego a la cita misma: allí los tres botones están siempre.'),
('it','help_termine_5','Puoi rispondere anche dal riepilogo: lì compare ogni data su cui non ti sei ancora espresso, con i tre pulsanti accanto — e dietro, chi degli altri ha già detto sì, no o forse. Così vedi a che punto è la band senza aprire la data. La scheda si chiama «Compiti aperti» — e «Risposte mancanti», se i compiti non li vedi affatto. Dopo aver risposto, la tua risposta resta visibile un momento e la riga si richiude, perché non è più aperta. Per cambiare idea vai poi alla data stessa: lì i tre pulsanti ci sono sempre.')
ON DUPLICATE KEY UPDATE value = value;
