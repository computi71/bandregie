-- "Blocked" has two causes, and the second one is invisible: with the browser's
-- master switch off there is no prompt, no bell, and no per-site choice either.
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','prof_push_denied','The browser refuses notifications. That cannot be changed from here — and it can be either of two things.'),
('en','prof_push_denied_site','For this site: click the lock or info icon in the address bar, set notifications to "Allow", reload the page.'),
('en','prof_push_denied_all','For every site: if no question ever appeared and there is no bell icon either, the browser''s master switch is off. In Edge under edge://settings/content/notifications, in Chrome under chrome://settings/content/notifications — "Ask before sending" has to be on. While it is off every request is refused silently, and the per-site permission is not even offered.'),
('en','prof_push_denied_os','If it still will not work: in the Windows settings under "System → Notifications" the browser itself must be allowed to show them.'),
('fr','prof_push_denied','Le navigateur refuse les notifications. Cela ne se change pas d''ici — et il peut y avoir deux raisons.'),
('fr','prof_push_denied_site','Pour ce site : cliquez sur l''icône de cadenas ou d''information dans la barre d''adresse, réglez les notifications sur « Autoriser », rechargez la page.'),
('fr','prof_push_denied_all','Pour tous les sites : si aucune question n''est jamais apparue et qu''il n''y a pas non plus de cloche, l''interrupteur principal du navigateur est éteint. Dans Edge sous edge://settings/content/notifications, dans Chrome sous chrome://settings/content/notifications — « Demander avant d''envoyer » doit être activé. Tant qu''il est éteint, chaque demande est refusée en silence et l''autorisation par site n''est même pas proposée.'),
('fr','prof_push_denied_os','Si cela ne suffit pas : dans les paramètres Windows, sous « Système → Notifications », le navigateur lui-même doit avoir le droit de les afficher.'),
('es','prof_push_denied','El navegador rechaza los avisos. Eso no se cambia desde aquí — y puede deberse a dos cosas.'),
('es','prof_push_denied_site','Para este sitio: haz clic en el icono de candado o de información en la barra de direcciones, pon los avisos en «Permitir» y recarga la página.'),
('es','prof_push_denied_all','Para todos los sitios: si nunca apareció una pregunta y tampoco hay una campanita, el interruptor principal del navegador está apagado. En Edge en edge://settings/content/notifications, en Chrome en chrome://settings/content/notifications — «Preguntar antes de enviar» tiene que estar activado. Mientras esté apagado, toda petición se rechaza en silencio y el permiso por sitio ni siquiera se ofrece.'),
('es','prof_push_denied_os','Si aun así no funciona: en la configuración de Windows, en «Sistema → Notificaciones», el propio navegador debe tener permiso para mostrarlas.'),
('nl','prof_push_denied','De browser weigert meldingen. Dat is hiervandaan niet te wijzigen — en het kan aan twee dingen liggen.'),
('nl','prof_push_denied_site','Voor deze site: klik op het slot- of infopictogram in de adresbalk, zet meldingen op „Toestaan" en laad de pagina opnieuw.'),
('nl','prof_push_denied_all','Voor alle sites: is er nooit een vraag verschenen en is er ook geen belletje, dan staat de hoofdschakelaar van de browser uit. In Edge onder edge://settings/content/notifications, in Chrome onder chrome://settings/content/notifications — „Vragen voor het verzenden" moet aan staan. Zolang die uit staat wordt elk verzoek stil geweigerd en wordt de toestemming per site niet eens aangeboden.'),
('nl','prof_push_denied_os','Helpt dat niet: in de Windows-instellingen onder „Systeem → Meldingen" moet de browser zelf ze mogen tonen.'),
('it','prof_push_denied','Il browser rifiuta le notifiche. Da qui non si può cambiare — e le cause possono essere due.'),
('it','prof_push_denied_site','Per questo sito: clicca sull''icona del lucchetto o delle informazioni nella barra degli indirizzi, imposta le notifiche su «Consenti» e ricarica la pagina.'),
('it','prof_push_denied_all','Per tutti i siti: se non è mai comparsa una domanda e non c''è nemmeno una campanella, l''interruttore principale del browser è spento. In Edge su edge://settings/content/notifications, in Chrome su chrome://settings/content/notifications — «Chiedi prima di inviare» deve essere attivo. Finché è spento ogni richiesta viene rifiutata in silenzio e il permesso per singolo sito non viene nemmeno proposto.'),
('it','prof_push_denied_os','Se ancora non funziona: nelle impostazioni di Windows, in «Sistema → Notifiche», il browser stesso deve poterle mostrare.')
ON DUPLICATE KEY UPDATE value = value;
