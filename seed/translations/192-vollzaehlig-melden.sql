-- Eine Meldung, wenn alle auf einen Termin geantwortet haben (#382).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','push_topic_vollzaehlig','When everyone has answered on an event of mine'),
('en','push_ev_complete','Everyone has answered: %1'),
('en','push_ev_complete_body','%1 in, %2 maybe, %3 out'),
('nl','push_topic_vollzaehlig','Als iedereen op een afspraak van mij heeft geantwoord'),
('nl','push_ev_complete','Iedereen heeft geantwoord: %1'),
('nl','push_ev_complete_body','%1 toegezegd, %2 misschien, %3 afgezegd'),
('fr','push_topic_vollzaehlig','Quand tout le monde a répondu à une de mes dates'),
('fr','push_ev_complete','Tout le monde a répondu : %1'),
('fr','push_ev_complete_body','%1 oui, %2 peut-être, %3 non'),
('es','push_topic_vollzaehlig','Cuando todos han respondido a una cita mía'),
('es','push_ev_complete','Todos han respondido: %1'),
('es','push_ev_complete_body','%1 sí, %2 quizá, %3 no'),
('it','push_topic_vollzaehlig','Quando tutti hanno risposto a un mio appuntamento'),
('it','push_ev_complete','Hanno risposto tutti: %1'),
('it','push_ev_complete_body','%1 sì, %2 forse, %3 no'),
('en','help_termine_7','Whoever enters an event is usually waiting to find out whether the band can play it. Once everybody has answered they get one notification — one only, with the result in it: how many said yes, maybe and no, and with names for the people who said no. It comes either way, not only on a clean sweep; otherwise the event that falls through would be the one that never produces a message. Changing your mind afterwards sets off no second notification — the round is still complete. Only when somebody new joins and everybody has answered again does it report once more. You can switch it off in your profile under “When everyone has answered on an event of mine”. Asked are all the people who should answer the event — substitutes only if they were asked for it.'),
('nl','help_termine_7','Wie een afspraak invoert wacht meestal af of de band kan. Zodra iedereen heeft geantwoord krijgt hij één melding — één maar, met het resultaat erin: hoeveel er ja, misschien en nee hebben gezegd, en bij afzeggingen met namen. Ze komt hoe dan ook, niet alleen bij louter toezeggingen; anders zou juist de afspraak die afketst de enige zonder bericht zijn. Wie zich daarna bedenkt, zet geen tweede melding in gang — de ronde blijft immers compleet. Pas als er iemand bij komt en iedereen opnieuw heeft geantwoord, meldt ze zich weer. Uitzetten kan in je profiel onder „Als iedereen op een afspraak van mij heeft geantwoord”. Gevraagd worden allen die de afspraak moeten beantwoorden — invallers alleen als ze ervoor zijn aangevraagd.'),
('fr','help_termine_7','Qui saisit une date attend généralement de savoir si le groupe peut. Dès que tout le monde a répondu, il reçoit une notification — une seule, avec le résultat dedans : combien ont dit oui, peut-être et non, et avec les noms pour les refus. Elle arrive dans tous les cas, pas seulement quand tout le monde accepte ; sinon la date qui tombe à l’eau serait la seule sans message. Changer d’avis ensuite ne déclenche pas de deuxième notification — la ronde reste complète. Ce n’est que lorsque quelqu’un s’ajoute et que tous ont de nouveau répondu qu’elle se manifeste à nouveau. On peut la désactiver dans son profil sous « Quand tout le monde a répondu à une de mes dates ». Sont consultés tous ceux qui doivent répondre à la date — les remplaçants seulement s’ils ont été sollicités.'),
('es','help_termine_7','Quien introduce una cita suele esperar a saber si la banda puede. En cuanto todos han respondido recibe un aviso — uno solo, con el resultado dentro: cuántos han dicho sí, quizá y no, y con nombres en las negativas. Llega en cualquier caso, no solo cuando todos aceptan; si no, justo la cita que se cae sería la única sin mensaje. Cambiar de idea después no dispara un segundo aviso — la ronda sigue completa. Solo cuando se suma alguien y todos vuelven a responder se manifiesta de nuevo. Se puede desactivar en el perfil bajo «Cuando todos han respondido a una cita mía». Se pregunta a todos los que deben responder la cita — a los sustitutos solo si se les ha solicitado.'),
('it','help_termine_7','Chi inserisce un appuntamento di solito aspetta di sapere se la band può. Appena tutti hanno risposto riceve una notifica — una sola, con il risultato dentro: quanti hanno detto sì, forse e no, e con i nomi per i rifiuti. Arriva in ogni caso, non solo quando sono tutti d’accordo; altrimenti proprio l’appuntamento che salta sarebbe l’unico senza messaggio. Cambiare idea dopo non fa scattare una seconda notifica — il giro resta completo. Solo quando si aggiunge qualcuno e tutti hanno risposto di nuovo si rifa viva. Si può disattivare nel profilo sotto «Quando tutti hanno risposto a un mio appuntamento». Vengono interpellati tutti quelli che devono rispondere all’appuntamento — i sostituti solo se sono stati richiesti.')
ON DUPLICATE KEY UPDATE value = value;
