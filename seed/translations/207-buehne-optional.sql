-- Der Haken „Ich stehe auf der Bühne“ bedeutet mehr als den Bühnenplan (#420).
--
-- Der alte Hinweis fing mit „Nur für den Bühnenplan“ an, und das stimmt nicht
-- mehr. Weil der frueheste Seed gewinnt, muss der alte Wortlaut einmal weg.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'prof_on_stage_hint'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'prof_on_stage_420') = 0;
INSERT INTO settings (`key`, value) VALUES ('prof_on_stage_420', '1')
  ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_on_stage_hint','Whoever comes along without playing — sound, driving, management — unticks this. Two things follow: the stage template stops placing them, and at a rehearsal, a recording or a photo session the band does not wait for their answer. They are still invited and can say yes at any time — they just hold nothing up. At a gig and at a meeting their answer counts like everybody else''s.'),
('nl','prof_on_stage_hint','Wie met de band meegaat zonder te spelen — techniek, rijdienst, management — vinkt dit uit. Twee dingen hängen eraan: de podiumsjabloon zet hem niet meer neer, en bij een repetitie, opname of fotoshoot wacht de band niet op zijn antwoord. Uitgenodigd is hij wel en hij kan altijd ja zeggen — hij houdt alleen niets tegen. Bij een optreden en bij een bespreking telt zijn antwoord als elk ander.'),
('fr','prof_on_stage_hint','Qui accompagne le groupe sans jouer — technique, conduite, management — décoche ceci. Deux choses en découlent : le modèle de scène ne le place plus, et lors d’une répétition, d’un enregistrement ou d’une séance photo le groupe n’attend pas sa réponse. Il reste invité et peut accepter à tout moment — il ne bloque simplement rien. Pour un concert et une réunion, sa réponse compte comme toutes les autres.'),
('es','prof_on_stage_hint','Quien acompaña a la banda sin tocar — técnica, conducción, management — desmarca esto. De ello se siguen dos cosas: la plantilla del escenario deja de colocarlo, y en un ensayo, una grabación o una sesión de fotos la banda no espera su respuesta. Sigue invitado y puede decir que sí en cualquier momento — simplemente no detiene nada. En un bolo y en una reunión su respuesta cuenta como cualquier otra.'),
('it','prof_on_stage_hint','Chi accompagna la band senza suonare — tecnica, trasporti, management — toglie questa spunta. Ne seguono due cose: il modello di palco non lo mette più, e a una prova, una registrazione o un servizio fotografico la band non aspetta la sua risposta. Resta invitato e può dire di sì in qualsiasi momento — semplicemente non blocca nulla. A un concerto e a una riunione la sua risposta conta come ogni altra.')
ON DUPLICATE KEY UPDATE value = value;
