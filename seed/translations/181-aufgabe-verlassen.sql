-- Sich selbst aus einer Aufgabe nehmen (#379). „Nicht meine" ist kurz, weil
-- der Knopf neben dem Löschen steht und beide Platz brauchen.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','task_leave','Not mine'),
('en','task_leave_confirm','Take yourself off this task? A tick you set goes with it.'),
('nl','task_leave','Niet van mij'),
('nl','task_leave_confirm','Jezelf van deze taak halen? Een vinkje dat je zette gaat mee.'),
('fr','task_leave','Pas la mienne'),
('fr','task_leave_confirm','Vous retirer de cette tâche ? La coche que vous avez mise part avec.'),
('es','task_leave','No es mía'),
('es','task_leave_confirm','¿Quitarte de esta tarea? La marca que pusiste se va con ella.'),
('it','task_leave','Non è mia'),
('it','task_leave_confirm','Toglierti da questo compito? La spunta che hai messo se ne va con te.')
ON DUPLICATE KEY UPDATE value = value;

-- Der Hilfeabsatz dazu. Ein neuer Schlüssel statt einer Änderung an _3: So
-- bleibt der alte Text samt Übersetzungen stehen (siehe hilfe.php).
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_aufgaben_5','If you are on a task that is none of your business, "Not mine" takes you off it — no write permission needed, because it touches only your own row. A tick you set goes with it. The number beside it moves up: if two remain and both have ticked, the task is done. The other way round, a task already done does not reopen just because somebody left. If the last one leaves, the task is one nobody is responsible for again — and then anyone may tick it off.'),
('nl','help_aufgaben_5','Sta je op een taak die jou niets aangaat, dan haal je jezelf er met „Niet van mij" af — daar is geen wijzigrecht voor nodig, want het raakt alleen je eigen regel. Een vinkje dat je zette gaat mee. Het getal ernaast schuift op: blijven er twee over die allebei afgevinkt hebben, dan is de taak klaar. Andersom gaat een al afgeronde taak niet weer open omdat iemand vertrok. Vertrekt de laatste, dan is de taak er weer een waarvoor niemand verantwoordelijk is — en dan mag iedereen weer afvinken.'),
('fr','help_aufgaben_5','Si vous figurez sur une tâche qui ne vous concerne pas, « Pas la mienne » vous en retire — sans droit de modification, car cela ne touche que votre propre ligne. La coche que vous aviez mise part avec. Le nombre à côté se resserre : s''il reste deux personnes ayant toutes deux coché, la tâche est faite. À l''inverse, une tâche déjà faite ne se rouvre pas parce que quelqu''un est parti. Si le dernier part, la tâche redevient une tâche sans responsable — et chacun peut alors la cocher.'),
('es','help_aufgaben_5','Si estás en una tarea que no te corresponde, con «No es mía» te quitas tú mismo — no hace falta permiso de escritura, porque solo toca tu propia fila. La marca que pusiste se va contigo. El número de al lado se ajusta: si quedan dos y ambos han marcado, la tarea está hecha. Al revés, una tarea ya hecha no vuelve a abrirse porque alguien se haya ido. Si se va el último, la tarea vuelve a ser una sin responsable — y entonces cualquiera puede marcarla.'),
('it','help_aufgaben_5','Se sei su un compito che non ti riguarda, con «Non è mia» ti togli da solo — non serve il permesso di modifica, perché tocca solo la tua riga. La spunta che avevi messo se ne va con te. Il numero accanto si stringe: se restano in due e hanno spuntato entrambi, il compito è fatto. Al contrario, un compito già fatto non si riapre solo perché qualcuno se n''è andato. Se se ne va l''ultimo, il compito torna a essere senza responsabile — e allora chiunque può spuntarlo.')
ON DUPLICATE KEY UPDATE value = value;
