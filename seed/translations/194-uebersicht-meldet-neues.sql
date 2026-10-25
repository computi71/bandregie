-- Die Übersicht meldet, wenn sich etwas getan hat (#378).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','refresh_new','Something has happened since.'),
('en','refresh_now','Reload'),
('nl','refresh_new','Er is sindsdien iets gebeurd.'),
('nl','refresh_now','Opnieuw laden'),
('fr','refresh_new','Quelque chose s’est passé depuis.'),
('fr','refresh_now','Recharger'),
('es','refresh_new','Desde entonces ha pasado algo.'),
('es','refresh_now','Recargar'),
('it','refresh_new','Nel frattempo è successo qualcosa.'),
('it','refresh_now','Ricarica'),
('en','help_intro3','A page left open for a while does not stay silent: when something happens, a bar appears at the top with “Reload”. Nothing reloads by itself — a half-typed comment and the button you are aiming at should not be pulled away. It asks every two minutes, and only while the window is actually in front of you.'),
('nl','help_intro3','Een pagina die langer openligt blijft niet stil: gebeurt er iets, dan verschijnt bovenaan een balk met „Opnieuw laden”. Vanzelf wordt er niets ververst — een half getypte reactie en de knop waar je op mikt horen niet weggetrokken te worden. Er wordt elke twee minuten gevraagd, en alleen zolang het venster echt voor je ligt.'),
('fr','help_intro3','Une page restée ouverte un moment ne reste pas muette : s’il se passe quelque chose, une barre apparaît en haut avec « Recharger ». Rien ne se recharge tout seul — un commentaire à moitié tapé et le bouton que vous visez ne doivent pas vous être retirés. La question est posée toutes les deux minutes, et seulement tant que la fenêtre est vraiment devant vous.'),
('es','help_intro3','Una página que lleva un rato abierta no se queda muda: si pasa algo, arriba aparece una barra con «Recargar». No se recarga nada solo — un comentario a medio escribir y el botón al que estás apuntando no deben quitarse de en medio. Se pregunta cada dos minutos, y solo mientras la ventana esté realmente delante de ti.'),
('it','help_intro3','Una pagina rimasta aperta a lungo non resta muta: se succede qualcosa, in alto compare una barra con «Ricarica». Non si ricarica nulla da solo — un commento scritto a metà e il pulsante che stai mirando non devono esserti tolti. Si chiede ogni due minuti, e solo finché la finestra è davvero davanti a te.')
ON DUPLICATE KEY UPDATE value = value;
