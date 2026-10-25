-- Entwurf im Browser merken (#381).
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','draft_found','Something you typed here was never saved.'),
('en','draft_restore','Bring it back'),
('en','draft_drop','Discard'),
('en','help_draft','Whatever you type into a large text field, the browser remembers on the side — notes, lyrics, the rider, a contract body. If the page is lost before you save, the next time you open it there is a line with two buttons: bring it back, or discard it. Nothing is filled in by itself — an old draft would otherwise overwrite what somebody else has changed in the meantime. The draft stays on your device, goes away by itself after a week, and disappears as soon as you have saved.'),
('nl','draft_found','Hier stond nog iets dat je getypt en niet opgeslagen had.'),
('nl','draft_restore','Terughalen'),
('nl','draft_drop','Weggooien'),
('nl','help_draft','Wat je in een groot tekstveld typt, onthoudt de browser terloops — notities, songteksten, de rider, een contracttekst. Gaat de pagina verloren voordat je hebt opgeslagen, dan staat er de volgende keer een regel met twee knoppen: terughalen of weggooien. Er wordt niets vanzelf ingevuld — een oud concept zou anders overschrijven wat iemand anders intussen heeft gewijzigd. Het concept blijft op je eigen apparaat, verdwijnt na een week vanzelf en gaat weg zodra je hebt opgeslagen.'),
('fr','draft_found','Il restait ici quelque chose que vous aviez tapé sans l’enregistrer.'),
('fr','draft_restore','Récupérer'),
('fr','draft_drop','Jeter'),
('fr','help_draft','Ce que vous tapez dans un grand champ de texte, le navigateur le retient au passage — notes, paroles, le rider, un texte de contrat. Si la page se perd avant que vous ayez enregistré, la fois suivante une ligne apparaît avec deux boutons : récupérer ou jeter. Rien n’est rempli tout seul — un vieux brouillon écraserait sinon ce que quelqu’un d’autre a modifié entre-temps. Le brouillon reste sur votre appareil, disparaît de lui-même au bout d’une semaine et s’efface dès que vous avez enregistré.'),
('es','draft_found','Aquí quedaba algo que escribiste y no guardaste.'),
('es','draft_restore','Recuperar'),
('es','draft_drop','Descartar'),
('es','help_draft','Lo que escribes en un campo de texto grande, el navegador lo recuerda de paso — notas, letras, el rider, el texto de un contrato. Si la página se pierde antes de guardar, la próxima vez aparece una línea con dos botones: recuperar o descartar. No se rellena nada solo — un borrador viejo sobrescribiría si no lo que otra persona haya cambiado entretanto. El borrador se queda en tu dispositivo, desaparece solo al cabo de una semana y se borra en cuanto has guardado.'),
('it','draft_found','Qui era rimasto qualcosa che avevi scritto e non salvato.'),
('it','draft_restore','Recuperare'),
('it','draft_drop','Scartare'),
('it','help_draft','Quello che scrivi in un campo di testo grande, il browser se lo ricorda di passaggio — note, testi, il rider, il testo di un contratto. Se la pagina va persa prima che tu abbia salvato, la volta dopo compare una riga con due pulsanti: recuperare o scartare. Nulla viene inserito da solo — una bozza vecchia sovrascriverebbe altrimenti ciò che nel frattempo ha cambiato qualcun altro. La bozza resta sul tuo dispositivo, sparisce da sola dopo una settimana e se ne va appena hai salvato.'),
('en','help_draft_title','Text you started'),
('nl','help_draft_title','Begonnen tekst'),
('fr','help_draft_title','Texte commencé'),
('es','help_draft_title','Texto empezado'),
('it','help_draft_title','Testo iniziato')
ON DUPLICATE KEY UPDATE value = value;
