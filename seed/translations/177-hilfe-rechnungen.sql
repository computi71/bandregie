-- Name und Hilfe für den Rechnungsbereich (#369).
--
-- Beides fehlte seit der Einführung der Rechnungen. Die Hilfeseite gibt
-- t('inav_…') und t('help_…') ungeprüft aus, also stand auf jeder laufenden
-- Anlage ein Abschnitt mit der Überschrift „inav_rechnungen" und dem Text
-- „help_rechnungen".
SET NAMES utf8mb4;

INSERT INTO translations (lang, tkey, value) VALUES
('en','inav_rechnungen','Invoices'),
('en','help_rechnungen','What you charge the promoter. The figures come from the contract — promoter, date and fee are already there, and copying them by hand is exactly where two amounts start to differ.'),
('en','help_rechnungen_2','Numbers run without gaps and are issued when the invoice is created. That is not cosmetic: a gap in the sequence is something you have to explain to an auditor.'),
('en','help_rechnungen_3','Plenty of gigs are settled in cash on the night. That is what the "paid in cash" stamp is for — the invoice moves to "paid" and is only the receipt.'),
('en','help_rechnungen_4','Whether VAT is added depends on your band and is held on the individual invoice, not in the settings: leaving the small-business scheme next year must not change what has already gone out.'),

('nl','inav_rechnungen','Facturen'),
('nl','help_rechnungen','Wat jullie de organisator in rekening brengen. De bedragen komen uit het contract — organisator, datum en gage staan daar al, en overtypen is precies de plek waar twee bedragen uit elkaar gaan lopen.'),
('nl','help_rechnungen_2','De nummers lopen zonder gaten door en worden bij het aanmaken toegekend. Dat is geen franje: een gat in de reeks moet je aan een controleur kunnen uitleggen.'),
('nl','help_rechnungen_3','Veel optredens worden op de avond zelf contant afgerekend. Daarvoor is de stempel „contant betaald" — de factuur gaat daarmee op „betaald" en is alleen nog het bewijs.'),
('nl','help_rechnungen_4','Of er btw bij komt hangt van jullie band af en staat op de factuur zelf, niet in de instellingen: wie volgend jaar de kleineondernemersregeling verlaat, mag daarmee niet veranderen wat al verstuurd is.'),

('fr','inav_rechnungen','Factures'),
('fr','help_rechnungen','Ce que vous facturez à l''organisateur. Les montants viennent du contrat — organisateur, date et cachet y figurent déjà, et les recopier est exactement l''endroit où deux chiffres commencent à diverger.'),
('fr','help_rechnungen_2','Les numéros se suivent sans trou et sont attribués à la création. Ce n''est pas de la coquetterie : un trou dans la série, il faut pouvoir l''expliquer à un contrôleur.'),
('fr','help_rechnungen_3','Beaucoup de concerts se règlent en espèces le soir même. C''est à cela que sert le tampon « payée en espèces » : la facture passe à « payée » et n''est plus qu''un reçu.'),
('fr','help_rechnungen_4','L''ajout de la TVA dépend de votre groupe et tient à la facture elle-même, pas aux réglages : sortir du régime de la franchise l''année prochaine ne doit pas modifier ce qui est déjà parti.'),

('es','inav_rechnungen','Facturas'),
('es','help_rechnungen','Lo que le cobráis al organizador. Las cifras vienen del contrato — organizador, fecha y caché ya están ahí, y copiarlas a mano es justo donde dos importes empiezan a diferir.'),
('es','help_rechnungen_2','Los números corren sin huecos y se asignan al crear la factura. No es cosmética: un hueco en la serie hay que saber explicárselo a un inspector.'),
('es','help_rechnungen_3','Muchos conciertos se liquidan en efectivo la misma noche. Para eso está el sello «pagada en efectivo»: la factura pasa a «pagada» y ya solo es el recibo.'),
('es','help_rechnungen_4','Si se añade IVA depende de vuestro grupo y queda en la factura concreta, no en los ajustes: salir el año que viene del régimen de pequeña empresa no debe cambiar lo que ya se envió.'),

('it','inav_rechnungen','Fatture'),
('it','help_rechnungen','Quello che fatturate all''organizzatore. Gli importi vengono dal contratto — organizzatore, data e compenso sono già lì, e ricopiarli è esattamente il punto in cui due cifre iniziano a divergere.'),
('it','help_rechnungen_2','I numeri scorrono senza buchi e vengono assegnati alla creazione. Non è un vezzo: un buco nella serie bisogna saperlo spiegare a un verificatore.'),
('it','help_rechnungen_3','Molti concerti si saldano in contanti la sera stessa. A questo serve il timbro «pagata in contanti»: la fattura passa a «pagata» ed è solo la ricevuta.'),
('it','help_rechnungen_4','Se si aggiunge l''IVA dipende dalla vostra band e resta sulla singola fattura, non nelle impostazioni: uscire l''anno prossimo dal regime agevolato non deve cambiare ciò che è già partito.')
ON DUPLICATE KEY UPDATE value = value;
