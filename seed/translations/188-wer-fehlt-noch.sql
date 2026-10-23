-- Jeder Zuständige mit seinem Stand (#376).
--
-- „Erledigt von" ist überflüssig geworden: Jeder Name trägt seinen Haken jetzt
-- selbst, also sagt eine zweite Liste der Fertigen nichts mehr. Der Schlüssel
-- ist aus den Texten und aus Seed 169 verschwunden; in bestehenden Datenbanken
-- bliebe er sonst als Zeile stehen, die niemand mehr liest.
--
-- help_aufgaben_2 beschrieb die alte Darstellung — „2 von 3 erledigt samt den
-- Namen der beiden". Weil der früheste Seed gewinnt (ON DUPLICATE KEY UPDATE
-- value = value), muss der alte Satz einmal weg; deshalb steht der neue hier
-- und nicht in 169, wo er bei Bestandsinstallationen nie ankäme.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'task_done_by';

DELETE FROM translations WHERE tkey = 'help_aufgaben_2'
  AND (SELECT COUNT(*) FROM settings WHERE `key` = 'help_aufgaben_2_v2') = 0;
INSERT INTO settings (`key`, value) VALUES ('help_aufgaben_2_v2', '1')
  ON DUPLICATE KEY UPDATE value = value;

INSERT INTO translations (lang, tkey, value) VALUES
('en','help_aufgaben_2','A task can belong to several people. Next to it stands how many of them have to do it: "Everyone assigned", or a number — "one of three" when it only needs doing once. The tick is personal: everyone ticks for themselves. The list names every person assigned with their state — ✔ for those who are done, ☐ for those still being waited on — and "2 of 3 done" beside them. So you see who to ask without subtracting names in your head. Once the number is reached, the task counts as done for everyone — including the third person, who need not clear away what two have already cleared. It then also drops out of the number on the app icon. Anyone who ticked by mistake taps the tick again; then it is open once more.'),
('nl','help_aufgaben_2','Een taak kan van meerdere mensen zijn. Daarnaast staat hoeveel van hen hem moeten doen: "Alle toegewezenen", of een getal — "een van drie" als het maar één keer gedaan hoeft te worden. Het vinkje is persoonlijk: iedereen vinkt voor zichzelf af. In de lijst staat elke toegewezene met zijn stand — ✔ bij wie klaar is, ☐ bij wie nog wordt afgewacht — en daarnaast "2 van 3 gedaan". Zo zie je wie je nog moet vragen, zonder namen in je hoofd af te trekken. Is het getal bereikt, dan geldt de taak voor iedereen als gedaan — ook voor de derde, die niet meer hoeft op te ruimen wat twee al opgeruimd hebben. Hij verdwijnt dan ook uit het getal op het app-symbool. Wie per ongeluk heeft afgevinkt, tikt nog een keer op het vinkje; dan is hij weer open.'),
('fr','help_aufgaben_2','Une tâche peut appartenir à plusieurs personnes. À côté figure combien d''entre elles doivent la faire : « Toutes les personnes assignées », ou un nombre — « une sur trois » quand il suffit de le faire une fois. La coche est personnelle : chacun coche pour soi. La liste nomme chaque personne assignée avec son état — ✔ pour celles qui ont fini, ☐ pour celles que l''on attend encore — et « 2 sur 3 faites » à côté. Vous voyez donc qui relancer sans soustraire les noms de tête. Une fois le nombre atteint, la tâche vaut comme faite pour tout le monde — y compris pour la troisième personne, qui n''a plus à ranger ce que deux ont déjà rangé. Elle disparaît alors aussi du nombre sur l''icône. Qui a coché par erreur appuie encore une fois sur la coche ; la tâche est alors de nouveau ouverte.'),
('es','help_aufgaben_2','Una tarea puede pertenecer a varias personas. Al lado está cuántas de ellas tienen que hacerla: "Todos los asignados", o un número — "uno de tres" cuando solo hay que hacerlo una vez. La marca es personal: cada uno marca por sí mismo. En la lista aparece cada asignado con su estado — ✔ los que han terminado, ☐ aquellos a los que aún se espera — y al lado "2 de 3 hechas". Así ves a quién preguntar sin restar nombres de cabeza. Alcanzado el número, la tarea cuenta como hecha para todos — también para el tercero, que ya no tiene que recoger lo que dos han recogido. Desaparece entonces también del número en el icono. Quien haya marcado por error toca otra vez la marca; entonces vuelve a estar abierta.'),
('it','help_aufgaben_2','Un compito può appartenere a più persone. Accanto sta quante di loro devono farlo: "Tutti gli assegnati", oppure un numero — "uno su tre" quando basta farlo una volta. La spunta è personale: ognuno spunta per sé. Nell''elenco compare ogni assegnato con il suo stato — ✔ per chi ha finito, ☐ per chi si aspetta ancora — e accanto "2 di 3 fatti". Così vedi chi devi ancora chiedere senza sottrarre i nomi a mente. Raggiunto il numero, il compito vale come fatto per tutti — anche per il terzo, che non deve più sistemare ciò che due hanno già sistemato. Sparisce allora anche dal numero sull''icona. Chi ha spuntato per errore tocca di nuovo la spunta; allora è di nuovo aperto.')
ON DUPLICATE KEY UPDATE value = value;
