-- Neu und geändert markieren, bis es gesehen wurde (#321)
INSERT INTO translations (lang, tkey, value) VALUES
('en','mark_new','New'),
('en','mark_changed','Changed'),
('nl','mark_new','Nieuw'),
('nl','mark_changed','Gewijzigd'),
('fr','mark_new','Nouveau'),
('fr','mark_changed','Modifié'),
('es','mark_new','Nuevo'),
('es','mark_changed','Modificado'),
('it','mark_new','Nuovo'),
('it','mark_changed','Modificato')
ON DUPLICATE KEY UPDATE value = value;

-- Die Hilfe dazu, und wohin eine Mitteilung führt (#321, #322)
INSERT INTO translations (lang, tkey, value) VALUES
('en','help_termine_4','Whatever is new or has changed carries a mark: "New", or "Changed" with the name and the day. It goes away as soon as you unfold the entry — not when you merely skim the list. Your own changes never mark anything, and somebody who joins later does not find the whole band history unseen. What gets marked is what concerns the band: date, times, place, fee, status, setlist, notes. A corrected invoice number makes no mark. The same goes for setlists, songs, quotes, contracts and new files.'),
('en','help_push_trouble_ziel','A notification leads to where it came from: to the event it is about, or in the chat to the first place you have not read. If the card is folded, it opens by itself when you land on it.'),
('nl','help_termine_4','Wat nieuw is of veranderd, draagt een merkje: "Nieuw", of "Gewijzigd" met de naam en de dag. Het verdwijnt zodra je het item openklapt — niet al wanneer je de lijst doorkijkt. Eigen wijzigingen geven nooit een merkje, en wie later binnenkomt, vindt niet de hele bandgeschiedenis als ongezien. Gemarkeerd wordt wat de band aangaat: datum, tijden, plaats, gage, status, setlist, notities. Een gecorrigeerd factuurnummer geeft geen merkje. Hetzelfde geldt voor setlists, nummers, offertes, contracten en nieuwe bestanden.'),
('nl','help_push_trouble_ziel','Een melding leidt naar de plek waar ze ontstaan is: naar de afspraak waar het om gaat, of in de chat naar de eerste plek die je nog niet gelezen hebt. Is de kaart ingeklapt, dan gaat ze vanzelf open.'),
('fr','help_termine_4','Ce qui est nouveau ou a changé porte une marque : « Nouveau », ou « Modifié » avec le nom et le jour. Elle disparaît dès que vous dépliez l''entrée — pas au simple survol de la liste. Vos propres modifications ne marquent jamais rien, et qui arrive plus tard ne trouve pas toute l''histoire du groupe comme non vue. Est marqué ce qui concerne le groupe : date, horaires, lieu, cachet, statut, setlist, notes. Un numéro de facture corrigé ne fait pas de marque. Idem pour les setlists, les morceaux, les devis, les contrats et les nouveaux fichiers.'),
('fr','help_push_trouble_ziel','Une notification mène là où elle est née : à la date concernée, ou dans le chat au premier endroit que vous n''avez pas lu. Si la carte est repliée, elle s''ouvre d''elle-même à l''arrivée.'),
('es','help_termine_4','Lo que es nuevo o ha cambiado lleva una marca: «Nuevo», o «Modificado» con el nombre y el día. Desaparece en cuanto despliegas la entrada — no con solo ojear la lista. Tus propios cambios nunca marcan nada, y quien llega más tarde no encuentra toda la historia del grupo como no vista. Se marca lo que importa al grupo: fecha, horas, lugar, caché, estado, repertorio, notas. Un número de factura corregido no deja marca. Lo mismo vale para repertorios, canciones, presupuestos, contratos y archivos nuevos.'),
('es','help_push_trouble_ziel','Un aviso lleva al lugar donde nació: al evento del que se trata, o en el chat al primer punto que no has leído. Si la tarjeta está plegada, se abre sola al llegar.'),
('it','help_termine_4','Ciò che è nuovo o è cambiato porta un segno: «Nuovo», oppure «Modificato» con il nome e il giorno. Sparisce appena apri la voce — non già scorrendo l''elenco. Le tue modifiche non segnano mai nulla, e chi arriva dopo non si trova davanti tutta la storia della band come non vista. Si segna ciò che riguarda la band: data, orari, luogo, cachet, stato, scaletta, note. Un numero di fattura corretto non lascia segno. Lo stesso vale per scalette, brani, preventivi, contratti e file nuovi.'),
('it','help_push_trouble_ziel','Una notifica porta dove è nata: alla data di cui si tratta, o in chat al primo punto che non hai ancora letto. Se la scheda è chiusa, si apre da sola quando ci arrivi.')
ON DUPLICATE KEY UPDATE value = value;
