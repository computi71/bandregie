-- Erinnerung nach einer Absage ohne Vorschlag (#403).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_topic_erinnerung','Reminder when I declined and proposed nothing'),
('en','push_remind_alt','Would you suggest another date?'),
('en','push_remind_alt_body','You could not make “%1” on %2 — and so far nobody has put in an alternative.'),
('nl','push_topic_erinnerung','Herinnering als ik heb afgezegd en niets heb voorgesteld'),
('nl','push_remind_alt','Wil je een andere datum voorstellen?'),
('nl','push_remind_alt_body','Je kon niet bij „%1” op %2 — en tot nu toe heeft niemand een alternatief ingevoerd.'),
('fr','push_topic_erinnerung','Rappel si j’ai décliné sans rien proposer'),
('fr','push_remind_alt','Voulez-vous proposer une autre date ?'),
('fr','push_remind_alt_body','Vous ne pouviez pas pour « %1 » le %2 — et jusqu’ici personne n’a proposé d’alternative.'),
('es','push_topic_erinnerung','Recordatorio si he rechazado y no he propuesto nada'),
('es','push_remind_alt','¿Quieres proponer otra fecha?'),
('es','push_remind_alt_body','No podías en «%1» el %2 — y hasta ahora nadie ha puesto una alternativa.'),
('it','push_topic_erinnerung','Promemoria se ho rifiutato senza proporre nulla'),
('it','push_remind_alt','Vuoi proporre un’altra data?'),
('it','push_remind_alt_body','Non potevi per «%1» il %2 — e finora nessuno ha inserito un’alternativa.'),
('en','help_termine_10','If you declined a rehearsal, recording session, photo shoot or meeting and nobody has put in an alternative since, the app comes back a day later — this time as a notification on your phone, because the window from back then is long dismissed. Tapping it opens the prepared form again. It reminds exactly once, never twice, and only while the event is still ahead. You can switch it off in your profile under “Reminder when I declined and proposed nothing”.'),
('nl','help_termine_10','Heb je een repetitie, opname, fotoshoot of overleg afgezegd en heeft niemand daarna een alternatief ingevoerd, dan meldt de app zich een dag later nog een keer — nu als melding op je telefoon, want het venster van toen heb je allang weggetikt. Een tik erop opent weer het voorbereide formulier. Er wordt precies één keer herinnerd, nooit twee keer, en alleen zolang de afspraak nog voor de boeg ligt. Uitzetten kan in je profiel onder „Herinnering als ik heb afgezegd en niets heb voorgesteld”.'),
('fr','help_termine_10','Si vous avez décliné une répétition, une session d’enregistrement, un shooting ou une réunion et que personne n’a proposé d’alternative depuis, l’application revient un jour plus tard — cette fois en notification sur votre téléphone, car la fenêtre d’alors a été fermée depuis longtemps. Un appui rouvre le formulaire préparé. Le rappel vient exactement une fois, jamais deux, et seulement tant que la date est encore devant. On peut le désactiver dans son profil sous « Rappel si j’ai décliné sans rien proposer ».'),
('es','help_termine_10','Si has rechazado un ensayo, una grabación, una sesión de fotos o una reunión y desde entonces nadie ha puesto una alternativa, la aplicación vuelve un día después — esta vez como aviso en el móvil, porque la ventana de entonces hace tiempo que la cerraste. Al tocarlo se abre otra vez el formulario preparado. Recuerda exactamente una vez, nunca dos, y solo mientras la cita siga por delante. Puedes desactivarlo en tu perfil bajo «Recordatorio si he rechazado y no he propuesto nada».'),
('it','help_termine_10','Se hai rifiutato una prova, una registrazione, un servizio fotografico o una riunione e da allora nessuno ha inserito un’alternativa, l’applicazione si rifa viva un giorno dopo — stavolta come notifica sul telefono, perché la finestra di allora l’hai chiusa da un pezzo. Un tocco riapre il modulo già pronto. Ricorda esattamente una volta, mai due, e solo finché l’appuntamento è ancora davanti. Puoi disattivarlo nel profilo sotto «Promemoria se ho rifiutato senza proporre nulla».')
ON DUPLICATE KEY UPDATE value = value;
